#!/usr/bin/env python3
"""Build the distributable Majestic Tube theme zip.

Exports the committed tree, drops development-only files that are tracked in
git but do not belong in an installed theme - the smoke suite, this toolchain,
ignored notes and repository plumbing - and writes a zip whose single top-level
directory is the theme folder, which is the layout WordPress expects when
installing from Appearance -> Themes -> Add New -> Upload.

The build is not finished when the file is written. Every zip it produces is
verified first - its layout, its version, every PHP file in it, its contents
against the tree it was built from, the smoke suite run with the archive
extracted, and the release notes when they already exist - and a file that
fails any of it is removed rather than left on disk to be published later.
tools/verify_release.py holds those checks, and publish-release.py runs the
same ones again on the way out.

Usage:
    python3 tools/build-release.py <version> <exported-tree> <output.zip>
"""

import os
import sys
import zipfile

# The verifier sits beside this script, and Python puts that directory first
# when this file is run as `python tools/build-release.py`.
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

import verify_release  # noqa: E402


def main() -> int:
    if len(sys.argv) != 4:
        print(__doc__)
        return 2

    version, tree, output = sys.argv[1], sys.argv[2], sys.argv[3]
    prefix = "majestic-tube"

    if not os.path.isdir(tree):
        print(f"exported tree not found: {tree}", file=sys.stderr)
        return 1

    names: list[str] = []

    for root, dirs, files in os.walk(tree):
        dirs[:] = sorted(d for d in dirs if d != ".git")

        for name in sorted(files):
            absolute = os.path.join(root, name)
            relative = os.path.relpath(absolute, tree).replace(os.sep, "/")

            # The rule lives in the verifier, which has to reject an archive
            # holding one of these, so the two ends cannot disagree. It is why a
            # tracked tests/ or tools/ never reaches a release.
            if verify_release.is_dev_only(relative):
                continue

            names.append(relative)

    # Sorted for a byte-stable archive: two builds of the same commit produce
    # the same file, which makes it obvious when a "new" release is not.
    names.sort()

    with zipfile.ZipFile(output, "w", zipfile.ZIP_DEFLATED) as archive:
        for relative in names:
            archive.write(os.path.join(tree, relative), f"{prefix}/{relative}")

    size = os.path.getsize(output)
    print(f"majestic-tube-{version}.zip")
    print(f"  entries : {len(names)}")
    print(f"  size    : {size:,} bytes")

    notes = os.path.join(
        os.path.dirname(os.path.abspath(__file__)), f"release-notes-{version}.md"
    )

    verified = verify_release.verify(
        output,
        version=version,
        tree=tree,
        notes_path=notes if os.path.isfile(notes) else "",
    )

    if not verified:
        os.remove(output)
        print(f"\n{output} removed: a file that fails its own checks")
        print("must not be left where a release can pick it up.")
        return 1

    print(f"\n{output} is verified and ready to publish")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
