#!/usr/bin/env python3
"""Create a GitHub release and attach the built theme zip.

Reads the token Git Credential Manager already stores for github.com, so no
secret is ever passed on the command line or written to disk here. Mirrors the
shape of the existing releases: the asset is named majestic-tube.zip regardless
of the version, and the notes carry the size and SHA-256 of what was uploaded.

The asset is sent as a raw body with a plain Content-Type, not as multipart.
Every multipart attempt against uploads.github.com from here stored the whole
envelope instead of the file part - 184 to 286 bytes of boundary text wrapped
around the payload - which produced a zip that downloaded and then failed to
open. Raw stores the bytes exactly. The upload is confirmed by fetching the
public download and comparing digests, because the size the API echoes back has
already proved it can be wrong; an asset that does not match is deleted rather
than left behind as a broken download.

Nothing is uploaded until the artifact has been verified, because a published
release is permanent. The release name and the tag are held to the version
inside the zip, and the zip itself is checked by tools/verify_release.py against
the commit being released: its layout, its version, every PHP file in it, its
contents file for file, the smoke suite that commit carries, run with the
archive extracted, and the size and SHA-256 the notes state. A file that fails
any of it stops the release before the first API call.

Usage:
    python3 tools/publish-release.py <tag> <name> <notes-file> <zip> <target>
"""

import hashlib
import json
import os
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request

# The verifier sits beside this script, and Python puts that directory first
# when this file is run as `python tools/publish-release.py`.
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

import verify_release  # noqa: E402

API = "https://api.github.com"
UPLOADS = "https://uploads.github.com"
REPO = "j0vis/majestic-tube"
ASSET_NAME = "majestic-tube.zip"


def token() -> str:
    """Pull the stored credential without echoing it."""
    request = subprocess.run(
        ["git", "credential", "fill"],
        input="protocol=https\nhost=github.com\n\n",
        capture_output=True,
        text=True,
        check=True,
    )
    for line in request.stdout.splitlines():
        if line.startswith("password="):
            return line.split("=", 1)[1].strip()
    raise SystemExit("no stored credential for github.com")


def call(method: str, url: str, tok: str, payload: dict | None = None) -> dict:
    data = json.dumps(payload).encode() if payload is not None else None
    req = urllib.request.Request(url, data=data, method=method)
    req.add_header("Authorization", f"Bearer {tok}")
    req.add_header("Accept", "application/vnd.github+json")
    req.add_header("X-GitHub-Api-Version", "2022-11-28")
    if data:
        req.add_header("Content-Type", "application/json")
    with urllib.request.urlopen(req) as response:
        return json.load(response)


def sha256_bytes(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def sha256(path: str) -> str:
    digest = hashlib.sha256()
    with open(path, "rb") as handle:
        for block in iter(lambda: handle.read(1 << 20), b""):
            digest.update(block)
    return digest.hexdigest()


def attach(release_id: int, zip_path: str, tok: str) -> dict:
    """Upload the zip and confirm the public download is the same bytes."""
    with open(zip_path, "rb") as handle:
        payload = handle.read()

    local = sha256_bytes(payload)

    req = urllib.request.Request(
        f"{UPLOADS}/repos/{REPO}/releases/{release_id}/assets"
        f"?name={urllib.parse.quote(ASSET_NAME)}",
        data=payload,
        method="POST",
    )
    req.add_header("Authorization", f"Bearer {tok}")
    req.add_header("Content-Type", "application/zip")

    try:
        with urllib.request.urlopen(req) as response:
            asset = json.load(response)
    except urllib.error.HTTPError as error:
        raise SystemExit(
            f"asset upload failed: HTTP {error.code}\n"
            f"{error.read().decode(errors='replace')}"
        )

    print(f"asset    {asset['name']}  id {asset['id']}")
    print(f"url      {asset['browser_download_url']}")
    print(f"local    {len(payload):,} bytes  sha256:{local}")

    with urllib.request.urlopen(asset["browser_download_url"]) as response:
        fetched = response.read()

    remote = sha256_bytes(fetched)
    print(f"download {len(fetched):,} bytes  sha256:{remote}")

    if fetched != payload:
        # A corrupt download is worse than no download.
        delete_req = urllib.request.Request(
            f"{API}/repos/{REPO}/releases/assets/{asset['id']}", method="DELETE"
        )
        delete_req.add_header("Authorization", f"Bearer {tok}")
        delete_req.add_header("Accept", "application/vnd.github+json")
        try:
            urllib.request.urlopen(delete_req)
        except urllib.error.HTTPError:
            pass
        raise SystemExit(
            "MISMATCH: the downloaded asset is not the file on disk. Asset deleted."
        )

    print("verified: the public download is the file on disk")
    return asset


def main() -> int:
    if len(sys.argv) != 6:
        print(__doc__)
        return 2

    tag, name, notes_path, zip_path, target = sys.argv[1:6]

    for path in (notes_path, zip_path):
        if not os.path.isfile(path):
            raise SystemExit(f"missing: {path}")

    if not tag.startswith("v") or len(tag) < 2:
        raise SystemExit(f"tag is not v<version>: {tag}")

    version = tag[1:]
    expected_name = f"Majestic Tube - Version {version}"

    if name != expected_name:
        raise SystemExit(f"release name should be {expected_name!r}, got {name!r}")

    if not verify_release.verify(
        zip_path,
        version=version,
        commit=target,
        notes_path=notes_path,
        require_notes=True,
    ):
        raise SystemExit(f"release not created: {zip_path} did not pass verification")

    tok = token()

    # Refuse to create a second release for a tag that already has one.
    try:
        existing = call("GET", f"{API}/repos/{REPO}/releases/tags/{tag}", tok)
        raise SystemExit(f"release already exists for {tag}: {existing['html_url']}")
    except urllib.error.HTTPError as error:
        if error.code != 404:
            raise

    with open(notes_path, encoding="utf-8") as handle:
        notes = handle.read()

    release = call(
        "POST",
        f"{API}/repos/{REPO}/releases",
        tok,
        {
            "tag_name": tag,
            "name": name,
            "body": notes,
            "target_commitish": target,
            "draft": False,
            "prerelease": False,
        },
    )
    print(f"created  {release['html_url']}")
    print(f"id       {release['id']}")

    attach(release["id"], zip_path, tok)
    print(f"sha256   {sha256(zip_path)}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())