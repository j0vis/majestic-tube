# Contributing

The repository root **is** the theme directory: the folder you clone is the one a
site installs, so every tracked file is a step away from being served to
visitors. A few rules follow from that, and most of them are enforced by the
suite rather than by review.

## A commit message ends with its own last line

Do not add a trailer to a commit message: no `Generated with Codebuff` line, no
`Co-Authored-By: Codebuff <noreply@codebuff.com>` sign-off, and nothing else
naming the agent or tool that wrote the commit. The same goes for a pull request
body, a release note and any other text written into this repository. When
tooling appends one, the tooling is what changes, not the message.

- Commits made before this rule keep theirs. History is not rewritten to tidy
  it, so `git log` still carries seventeen of them.
- An edit to an existing message for any other reason strips the trailer.
- Nothing checks this yet. A trailer that arrives is removed by hand, and a tool
  that keeps appending one is not used again.

## What a change has to survive

- **Nothing development-only ships.** No suite, harness, local note, workflow or
  source map belongs in the theme zip. `tools/verify_release.py` holds that list
  and both ends of a release read it, so a new development-only file is added
  there rather than excluded a second time somewhere else.
- **The two version numbers move together.** `style.css` and
  `MAJESTIC_TUBE_VERSION` in `functions.php` are compared against each other and
  against the version being released.
- **The guides agree with the release.** The same release is described in both
  `README.md` and `docs.html`, newest first, and a new level-two heading in the
  README needs an entry in its contents list.
- **Run the suites a change touches**, and the whole suite before calling it
  done:

  ```sh
  for f in tests/*-smoke.php; do php "$f" || echo "FAILED: $f"; done
  ```

- **The theme writes American English** (`color`, `behavior`, `labeled`), which
  is what WordPress itself uses.
- **A site owner never reads about the harness.** The README, the HTML guide and
  the doc comments in the theme describe what a user can do: no path under
  `tests/` or `tools/`, no maintainer-only command and no local development URL.

## The local notes are not versioned

A checkout used for maintenance may also hold `AGENTS.md` and `local-notes.md`.
Neither is tracked. `AGENTS.md` is untracked rather than ignored so the tools
that read it can see it; `local-notes.md` holds the local test site's
credentials, and git ignores it for that reason. Neither file is part of the
theme, and the release verifier refuses either one inside an archive.
