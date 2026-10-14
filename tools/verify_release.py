#!/usr/bin/env python3
"""Verify a built Majestic Tube zip before anything publishes it.

The zip is the one artifact nobody tests. The smoke suite runs against the
working tree, the build writes a file from an export of that same tree, and
until now the next time those bytes were read was by whoever installed them.
This closes that gap, and it runs twice: build-release.py runs it on the file it
has just written, and publish-release.py runs it on the file it is about to
upload, so a stale zip, a wrong version, a syntax error, a missing file or a
failing suite stops the release instead of shipping.

The tests come from the commit the artifact claims to be, because the suite is
versioned with the theme: a zip built from an old commit is held to the tests
that shipped with it, not to tests written afterwards that ask for code it never
had. When that commit is too old to carry them, the working tree's suite runs
instead and the run says so.

What it checks, in order:

  1. the archive opens, every entry passes its own checksum, and nothing in it
     is a symbolic link;
  2. one top-level directory, named for the theme, holding no development-only
     file: no tests, no tools, no .git, no .env, no source map, no archive;
  3. the files WordPress needs to install and identify the theme are there;
  4. the version in style.css and the version in functions.php agree with each
     other and with the version being released;
  5. every PHP file in the archive parses (php -l);
  6. the contents match, file for file and byte for byte, the commit or the
     exported tree the zip claims to be, so a zip built before the last commit
     is caught here rather than by a user;
  7. the smoke suite passes against the extracted archive rather than against
     the working tree, and it is the suite the artifact's own commit carries
     when that commit is known;
  8. the release notes describe this exact file: its size and its SHA-256.

Usage:
    python3 tools/verify_release.py <zip> [version] [--tree DIR] [--commit SHA]
                                   [--notes FILE] [--require-notes]
"""

import hashlib
import io
import os
import re
import shutil
import stat
import subprocess
import sys
import tarfile
import tempfile
import zipfile

# The single top-level directory WordPress expects, and the asset name GitHub
# serves the zip under. The notes are checked against that name.
PREFIX = "majestic-tube"
ASSET_NAME = "majestic-tube.zip"

# Names that must never appear in a distributable. A release is public: anything
# here is either the development harness, a repository artifact, a secret, or a
# map that only a browser's developer tools read.
#
# This is the one list. The suite, the toolchain and the notes are tracked in
# git now, so an export of a commit contains them, and both ends of the release
# read the rule from here: build-release.py drops these on the way into the zip,
# and this module rejects a zip that has one of them inside.
DEV_ONLY = {
    ".git",
    ".gitignore",
    ".gitattributes",
    ".gitmodules",
    ".freebuff",
    ".kilo",
    ".idea",
    ".vscode",
    ".env",
    ".env.local",
    ".env.production",
    "node_modules",
    "tests",
    "tools",
    "__pycache__",
    "FEATURES.md",
    "knowledge.md",
    "Thumbs.db",
    ".DS_Store",
}
DEV_ONLY_SUFFIXES = (
    ".map",
    ".zip",
    ".log",
    ".sql",
    ".pem",
    ".key",
    ".bak",
    ".orig",
    ".pyc",
)

# What a theme cannot be installed or recognized without.
REQUIRED = (
    "style.css",
    "index.php",
    "functions.php",
    "theme.json",
    "screenshot.png",
    "rtl.css",
    "LICENSE",
    "README.md",
    "docs.html",
    "404.php",
    "header.php",
    "footer.php",
    "single.php",
    "archive.php",
    "page.php",
    "search.php",
    "comments.php",
    "template-tags.php",
)

RELEASE_NAME = "Majestic Tube - Version {version}"
NOTES_SIZE = re.compile(r"`majestic-tube\.zip`\s*&middot;\s*([\d,]+)\s+bytes")
NOTES_DIGEST = re.compile(r"SHA-256\s+([0-9a-f]{64})", re.IGNORECASE)


def digest(path: str) -> str:
    """SHA-256 of a file on disk."""
    sha = hashlib.sha256()
    with open(path, "rb") as handle:
        for block in iter(lambda: handle.read(1 << 20), b""):
            sha.update(block)
    return sha.hexdigest()


class Report:
    """Prints the checks and remembers whether any of them failed."""

    def __init__(self) -> None:
        self.passed = 0
        self.failed = 0

    def section(self, title: str) -> None:
        print(f"\n{title}")

    def check(self, label: str, ok: bool, detail: str = "") -> bool:
        if ok:
            self.passed += 1
            print(f"  ok   {label}")
        else:
            self.failed += 1
            print(f"  FAIL {label}" + (f"  ({detail})" if detail else ""))
        return bool(ok)


def is_dev_only(relative: str) -> bool:
    """Whether a path inside the archive has no business being distributed."""
    parts = relative.split("/")

    for part in parts:
        if part in DEV_ONLY:
            return True

    return parts[-1].endswith(DEV_ONLY_SUFFIXES)


def tree_digests(directory: str) -> dict:
    """Every distributable file under a directory, mapped to its digest.

    Development-only paths are left out on both sides of a comparison: the
    commit carries tests/ and tools/, the archive must not, and neither is a
    difference worth reporting when the other checks already say what may ship.
    """
    found: dict = {}

    for root, dirs, files in os.walk(directory):
        dirs[:] = sorted(d for d in dirs if d != ".git")

        for name in sorted(files):
            absolute = os.path.join(root, name)
            relative = os.path.relpath(absolute, directory).replace(os.sep, "/")

            if is_dev_only(relative):
                continue

            found[relative] = digest(absolute)

    return found


def compare_trees(expected: dict, actual: dict, report: Report, label: str) -> None:
    """Report how two trees differ, by name and by content."""
    missing = sorted(set(expected) - set(actual))
    extra = sorted(set(actual) - set(expected))
    differing = sorted(k for k in set(expected) & set(actual) if expected[k] != actual[k])

    report.check(
        f"{label}: nothing missing",
        not missing,
        ", ".join(missing[:6]) or "none",
    )
    report.check(
        f"{label}: nothing extra",
        not extra,
        ", ".join(extra[:6]) or "none",
    )
    report.check(
        f"{label}: no file differs",
        not differing,
        ", ".join(differing[:6]) or "none",
    )


def extract_git_tree(root: str, commit: str, destination: str) -> str:
    """Unpack `git archive` of a commit, the way the build's export was made."""
    archive = subprocess.run(
        ["git", "-C", root, "archive", "--format=tar", commit],
        capture_output=True,
    )

    if archive.returncode != 0:
        raise RuntimeError(
            f"git archive {commit} failed: {archive.stderr.decode(errors='replace').strip()}"
        )

    with tarfile.open(fileobj=io.BytesIO(archive.stdout)) as tar:
        tar.extractall(destination)

    return destination


def suite_source(root: str, commit_tree: str, commit: str) -> tuple:
    """Which copy of the smoke suite holds this artifact to account.

    The suite ships with the theme, so the commit an artifact was built from
    owns the tests that describe it. A commit older than that arrangement has
    none of its own, and then the working tree's suite is what there is.
    """
    if commit_tree:
        tracked = os.path.join(commit_tree, "tests")

        if os.path.isdir(tracked):
            return tracked, f"the artifact's own commit {commit[:12]}"

        return (
            os.path.join(root, "tests"),
            f"the working tree - commit {commit[:12]} carries none of its own",
        )

    return os.path.join(root, "tests"), "the working tree"


def run_suite(theme_dir: str, tests_dir: str, php: str, report: Report) -> None:
    """Run the smoke suite against the extracted archive, not the working tree."""
    destination = os.path.join(theme_dir, "tests")

    if os.path.isdir(destination):
        shutil.rmtree(destination)

    shutil.copytree(tests_dir, destination)

    files = sorted(name for name in os.listdir(destination) if name.endswith(".php"))
    passed = 0
    failed = 0
    broken: list = []

    for name in files:
        try:
            result = subprocess.run(
                [php, os.path.join("tests", name)],
                cwd=theme_dir,
                capture_output=True,
                text=True,
                timeout=300,
            )
        except subprocess.TimeoutExpired:
            broken.append(f"{name} (timed out)")
            continue

        counts = re.findall(r"(\d+) passed, (\d+) failed", result.stdout)

        if counts:
            passed += int(counts[-1][0])
            failed += int(counts[-1][1])

        if result.returncode != 0:
            broken.append(f"{name} (exit {result.returncode})")
            tail = (result.stdout + result.stderr).strip().splitlines()[-3:]
            for line in tail:
                print(f"         {line}")

    report.check("the suite runs at all", bool(files), "no test file found")
    report.check(
        "every suite passes against the extracted archive",
        not broken,
        ", ".join(broken),
    )
    report.check("no check inside a suite failed", 0 == failed, f"{failed} failed")

    print(f"         {len(files)} files, {passed} checks, {failed} failures")


def compare_notes(notes_path: str, size: int, sha: str, report: Report) -> None:
    """Hold the notes to the numbers of the file they describe."""
    try:
        with open(notes_path, encoding="utf-8") as handle:
            notes = handle.read()
    except OSError as error:
        report.check("the release notes can be read", False, str(error))
        return

    stated_size = NOTES_SIZE.search(notes)
    stated_digest = NOTES_DIGEST.search(notes)

    report.check("the notes state the asset size", stated_size is not None)
    report.check("and its SHA-256", stated_digest is not None)

    if stated_size:
        report.check(
            "the stated size is this file's",
            stated_size.group(1).replace(",", "") == str(size),
            f"notes say {stated_size.group(1)}, file is {size:,}",
        )

    if stated_digest:
        report.check(
            "and the stated digest is this file's",
            stated_digest.group(1).lower() == sha,
            f"notes say {stated_digest.group(1)[:16]}..., file is {sha[:16]}...",
        )

    if not stated_size or not stated_digest or stated_digest.group(1).lower() != sha:
        print("\n  the lines the notes should end with:")
        print(f"    `{ASSET_NAME}` &middot; {size:,} bytes")
        print(f"    `SHA-256 {sha}`")


def verify(
    zip_path: str,
    version: str = "",
    tree: str = "",
    commit: str = "",
    notes_path: str = "",
    require_notes: bool = False,
    root: str = "",
) -> bool:
    """Check one built zip. Returns True only when every check passes."""
    report = Report()
    root = root or os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

    print(f"Verifying {zip_path}")

    if not os.path.isfile(zip_path):
        report.check("the file exists", False, zip_path)
        return False

    size = os.path.getsize(zip_path)
    sha = digest(zip_path)

    print(f"  {size:,} bytes  sha256:{sha}")

    report.section("The archive")

    try:
        archive = zipfile.ZipFile(zip_path)
    except (zipfile.BadZipFile, OSError) as error:
        report.check("it opens as a zip", False, str(error))
        return False

    infos = [info for info in archive.infolist() if not info.is_dir()]
    names = [info.filename for info in infos]
    corrupt = archive.testzip()

    report.check("it opens as a zip", True)
    report.check("it holds files at all", bool(names), "no entries")
    report.check("every entry passes its own checksum", corrupt is None, f"corrupt: {corrupt}")
    report.check(
        "nothing in it is a symbolic link",
        not any(stat.S_ISLNK(info.external_attr >> 16) for info in infos),
    )

    relative = [name[len(PREFIX) + 1 :] for name in names if name.startswith(PREFIX + "/")]

    report.section("What is inside")

    report.check(
        f"one top-level directory, {PREFIX}/",
        len(relative) == len(names),
        f"{len(names) - len(relative)} entry/entries outside it",
    )

    offenders = sorted(name for name in relative if is_dev_only(name))

    report.check(
        "no development-only file is distributed",
        not offenders,
        ", ".join(offenders[:6]),
    )

    present = set(relative)
    missing = sorted(name for name in REQUIRED if name not in present)

    report.check(
        "every file a theme is installed and recognized by is present",
        not missing,
        ", ".join(missing),
    )

    report.section("The version")

    style = archive.read(f"{PREFIX}/style.css").decode("utf-8-sig", errors="replace")
    functions = archive.read(f"{PREFIX}/functions.php").decode("utf-8-sig", errors="replace")

    style_version = re.search(r"^Version:\s*(\S+)\s*$", style, re.MULTILINE)
    constant = re.search(r"MAJESTIC_TUBE_VERSION'\s*,\s*'([^']+)'", functions)

    reported = style_version.group(1) if style_version else ""
    declared = constant.group(1) if constant else ""

    report.check("style.css declares a version", bool(reported))
    report.check("functions.php declares one too", bool(declared))
    report.check(
        "and the two agree",
        bool(reported) and reported == declared,
        f"style.css: {reported or 'none'}, functions.php: {declared or 'none'}",
    )

    if version:
        report.check(
            f"and both are the version being released ({version})",
            reported == version and declared == version,
            f"style.css: {reported or 'none'}, functions.php: {declared or 'none'}",
        )

    workdir = tempfile.mkdtemp(prefix="majestic-tube-verify-")

    try:
        theme_dir = os.path.join(workdir, PREFIX)
        commit_tree = ""
        archive.extractall(workdir)

        report.section("PHP syntax")

        php = shutil.which("php")
        php_files = sorted(
            os.path.join(root_dir, name)
            for root_dir, _, files in os.walk(theme_dir)
            for name in files
            if name.endswith(".php")
        )

        if not php:
            report.check("php is available to parse them with", False, "php not found on PATH")
        else:
            banner = subprocess.run([php, "-v"], capture_output=True, text=True)
            print(f"         {banner.stdout.splitlines()[0].strip() if banner.stdout else php}")

            bad: list = []

            for path in php_files:
                result = subprocess.run([php, "-l", path], capture_output=True, text=True)

                if result.returncode != 0:
                    bad.append(os.path.relpath(path, theme_dir))
                    print(f"         {(result.stdout + result.stderr).strip().splitlines()[0]}")

            report.check(
                f"all {len(php_files)} PHP files parse",
                not bad,
                ", ".join(bad[:6]),
            )

        if commit:
            report.section(f"The contents against commit {commit[:12]}")

            try:
                commit_tree = extract_git_tree(root, commit, os.path.join(workdir, "commit"))
                compare_trees(tree_digests(commit_tree), tree_digests(theme_dir), report, "the commit")
            except RuntimeError as error:
                commit_tree = ""
                report.check("the commit can be read", False, str(error))

        if tree:
            report.section(f"The contents against {tree}")

            if os.path.isdir(tree):
                compare_trees(tree_digests(tree), tree_digests(theme_dir), report, "the tree")
            else:
                report.check("the exported tree exists", False, tree)

        report.section("The smoke suite")

        tests_dir, origin = suite_source(root, commit_tree, commit)

        print(f"         suite: {origin}")

        if not os.path.isdir(tests_dir):
            report.check(
                "the smoke suite is present to run",
                False,
                f"{tests_dir} not found, so the artifact was not tested",
            )
        elif not php:
            report.check("php is available to run it with", False, "php not found on PATH")
        else:
            run_suite(theme_dir, tests_dir, php, report)

        if notes_path or require_notes:
            report.section("The release notes")

            if notes_path:
                compare_notes(notes_path, size, sha, report)
            else:
                report.check(
                    "the release notes are given",
                    False,
                    "this release has to describe the file it publishes",
                )
    finally:
        shutil.rmtree(workdir, ignore_errors=True)

    print("\n" + "-" * 46)
    print(f"{report.passed} checks passed, {report.failed} failed")

    if report.failed:
        print(f"ARTIFACT NOT VERIFIED - do not publish {zip_path}")
        return False

    print(f"artifact verified: {size:,} bytes, sha256 {sha}")
    return True


def parse_args(argv: list) -> tuple:
    """Positional zip and version, then the optional inputs the callers pass."""
    positional: list = []
    options = {"tree": "", "commit": "", "notes": ""}
    require_notes = False
    index = 0

    if any(argument in ("-h", "--help") for argument in argv):
        print(__doc__)
        raise SystemExit(0)

    while index < len(argv):
        argument = argv[index]

        if argument in ("--tree", "--commit", "--notes"):
            index += 1

            if index >= len(argv):
                raise SystemExit(f"{argument} needs a value")

            options[argument.lstrip("-")] = argv[index]
        elif argument == "--require-notes":
            require_notes = True
        elif argument.startswith("-"):
            raise SystemExit(f"unknown option: {argument}")
        else:
            positional.append(argument)

        index += 1

    if not positional or len(positional) > 2:
        print(__doc__)
        raise SystemExit(2)

    return positional, options, require_notes


def main() -> int:
    positional, options, require_notes = parse_args(sys.argv[1:])

    zip_path = positional[0]
    version = positional[1] if len(positional) > 1 else ""

    ok = verify(
        zip_path,
        version=version,
        tree=options["tree"],
        commit=options["commit"],
        notes_path=options["notes"],
        require_notes=require_notes,
    )

    return 0 if ok else 1


if __name__ == "__main__":
    raise SystemExit(main())
