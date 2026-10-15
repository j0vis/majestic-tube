## Caveman mode

| caveman
think: ≤5 words
respond: ≤10 words
no reasoning out loud
just answer
|

## Git workflow

- Every time a file changes, `git add -A && git commit -m "chore: update"` and `git push` are run automatically — the agent should not manual-push; the CI/workflow handles it.
- If a commit message requires a trailer, strip any `Generated with...` or `Co-Authored-By:` lines before committing.

Working notes for coding agents in this repository. Human-facing documentation

Working notes for coding agents in this repository. Human-facing documentation
lives in [README.md](README.md) and [docs.html](docs.html); this file is for the
agent that has to change the code.

## What this is

A WordPress theme for a video site, built on the WP-Script Majestic Tube data
model. The repository root **is** the theme directory — `style.css` sits here —
so a file added at the root is one step away from being installed on a site.

|               |                                                                        |
| ------------- | ---------------------------------------------------------------------- |
| WordPress     | 6.5 or newer (`Requires at least` in `style.css`)                      |
| PHP           | 7.4 or newer                                                           |
| Text domain   | `majestic-tube`                                                        |
| Shipped version | `Major.Minor.Patch` in `style.css` **and** `MAJESTIC_TUBE_VERSION` in `functions.php` — the two are compared, so they move together |

There is no build step, no `node_modules` and no Composer package: every file in
the theme is the file that ships, and the folder has to stay installable as it
stands.

## Layout

| Path | What lives there |
| ---- | ---------------- |
| `*.php` at the root | The template hierarchy — `index.php`, `single.php`, `page.php`, `archive.php`, `search.php`, `404.php`, `comments.php`, `header.php`, `footer.php` — plus the `template-*.php` page templates and `taxonomy-actors.php`. |
| `inc/` | All behavior, one concern per module, loaded by `functions.php`. |
| `template-parts/` | The pieces templates repeat: the video card, the excerpt, the logo watermark, "nothing found". |
| `assets/` | `css/`, `js/` (two scripts: `main.js`, `term-images.js`), `fonts/` (Inter) and `vendor/videojs`. |
| `theme.json`, `rtl.css` | Block-editor settings and the right-to-left glue. |
| `tests/`, `tools/` | Development only — see *Never put development files in the theme*. |

## How it is put together

`functions.php` is a loader: it defines the version and directory constants and
requires every `inc/` module in order. Each module guards itself with
`defined( 'ABSPATH' ) || exit;`, opens with a docblock saying what it is for and
why it exists, and registers its own hooks — so a behavior is found by reading
the module named after it rather than by searching the tree.

Worth knowing before changing anything:

- **The compatibility layer is deliberate.** `inc/compat.php` exposes the
  original theme's `wpst_*` function names and `inc/ajax.php` answers the
  original AJAX action names, because WP-Script tooling, importers and child
  themes call them. Renaming one of those is a breaking change.

  The pieces a drop-in Majestic Tube/WP-Script swap must keep, and how solidly
  each is backed, are listed below. Items marked "theme's own code" are known to
  be kept because this file says so; the WP-Script docs page(s) named for those
  items describe the same concept but do not enumerate the exact function name,
  action string, meta key or selector — see the agent that ran before this note
  was written for the current source list.

  | Layer | What's kept | Backed by |
  | ----- | ---------- | -------- |
  | `wpst_*` helpers | `wpst_get_type_from_video_url`, `wpst_get_video_duration`, `wpst_get_post_views`, `wpst_get_human_number`, `wpst_iso8601_duration`, `wpst_get_multithumbs`, `wpst_get_video_preview`, `wpst_cats_tags`, `wpst_get_post_data`, `wpst_get_post_like_rate`, `wpst_has_already_voted`, `wpst_get_post_like_link`, `wpst_get_filter_title`, `wpst_selected_filter`, `wpst_get_nopaging_url`, `wpst_page_navi`, `wpst_page_number`, `wpst_body_classes`, `wpst_pingback_header`, `wpst_get_async_post_data`, `wpst_set_post_views`, `wpst_post_like`, `wpst_breadcrumbs`, `wpst_posted_on`, `wpst_rss_post_thumbnail` — every one guarded with `function_exists()` so a plugin declaring its own version wins. | All of them in [inc/compat.php](inc/compat.php); the four added for original template calls (`wpst_breadcrumbs`, `wpst_posted_on`, `wpst_get_async_post_data`, `wpst_set_post_views`, `wpst_post_like`) are called out there as the ones "called by the original theme's own templates but had no shim." |
  | AJAX actions | `post-views`, `post-like`, `get-post-data`, and the login/register/password handlers under `wp_ajax_` / `wp_ajax_nopriv_` in [inc/ajax.php](inc/ajax.php) and [inc/ajax-login-register.php](inc/ajax-login-register.php). | [inc/ajax.php](inc/ajax.php) keeps the original action names and response keys on purpose — `post-like` keeps `alreadyrate`, `percentage`, `button`, `nbrates`, `likes`, `dislikes`, `progressbar` — and [inc/ajax.php#L144](inc/ajax.php#L144), [inc/ajax.php#L232](inc/ajax.php#L232) state the mirror explicitly. |
  | Like-link selector | `.post-like a` with `data-post_id` / `data-post_like`. | `wpst_get_post_like_link()` in [inc/compat.php:299-306](inc/compat.php#L299-L306) keeps that markup on purpose; its docblock says "Front-end scripts bind to `.post-like a` and read data-post_id / data-post_like, so the markup keeps the original shape." |
  | Rotation thumbnails | `data-thumbs` attribute fed by `wpst_get_multithumbs()`, stored in the original unindexed `thumbs` meta key, including importer-style comma-joined rows. | `wpst_get_multithumbs()` at [inc/compat.php#L99-L142](inc/compat.php#L99-L142); consumer at [majestic_tube_get_multithumbs()](inc/multithumbs.php#L132-L168) reads attached images first, then `thumbs` meta written by WP-Script importers, and upgrades `http://` to `https://` on SSL; parser at [majestic_tube_parse_thumbs_meta()](inc/multithumbs.php#L43-L65) normalizes both storage shapes; admin handler at [inc/multithumbs.php#L173-L241](inc/multithumbs.php#L173-L241) handles removal from a comma-joined row. |
  | Hover preview / Clean Tube Player hook | `<video class="wpst-trailer">` / `<img class="wpst-trailer preview-thumb">` from `wpst_get_video_preview()`. | `wpst_get_video_preview()` in [inc/compat.php#L144-L184](inc/compat.php#L144-L184); its comment says the `wpst-trailer` class is "what Clean Tube Player looks for when it decides which `<video>` tags it may replace." |
  | Term images | actors and categories stored as attachment IDs in the original keys `actors-image-id` and `category-image-id`. | `majestic_tube_term_image_taxonomies()` in [inc/term-images.php:10-L15](inc/term-images.php#L10-L15); directory cards at [template-parts/content-video-card.php](template-parts/content-video-card.php) and the term-card helper read those keys. |
  | Ratings meta | `likes_count`, `dislikes_count`, `rate` on the video post. | Voted-on handler at [inc/ajax.php#L197-L229](inc/ajax.php#L197-L229) writes those keys; the `rate` refresh in [inc/ajax.php#L242](inc/ajax.php#L242) uses the live ratio rather than the stored meta, and the comment records why. |
  | Views meta | `post_views_count` is the original key the `wpst_get_post_views` helper wraps; this theme's counter is the single source of truth for it. | `wpst_get_post_views()` at [inc/compat.php#L51-L57](inc/compat.php#L51-L57); the counter lives in `inc/analytics.php`. |
  | Options surface | the old flat `wpst-options` keys are migrated into the Customizer and stay readable; content-placement keys are retained for migration but not exposed as Customizer fields. | [inc/theme-options.php](inc/theme-options.php) header; `show-videos-homepage`, `actor-card-description`, `category-card-description` survive as existing keys read by [majestic_tube_term_card_line()](inc/term-images.php#L158-L160). |

  Items **not** confirmed from any WP-Script docs source (and therefore supported only by this theme's own code and the companion audit): the enumerated `wpst_*` list as a named API, the login/register AJAX action strings and payloads, any exact player selector beyond `.post-like a`, and whether `wpst_get_async_post_data` / `wpst_set_post_views` / `wpst_post_like` must remain callable from a plugin. If a specific caller breaks after a swap, trace that caller through these files before assuming the surface changed.
- **The counters have one home.** `inc/analytics.php` reads and writes views,
  likes, dislikes and reports for every surface — cards, the single page, the
  structured data, the AJAX endpoints, the moderation screens. Read a counter
  through it rather than calling `get_post_meta()` in a template.
- **The data model is the original one.** Video sources live in the resolution
  meta keys plus `video_url`, rotation thumbnails in `thumbs`, ratings in
  `likes_count`, `dislikes_count` and `rate`. `inc/video-meta.php` and
  `inc/template-tags.php` own the reading and writing of those keys.
- **Search appearance has two halves.** `inc/basic-seo.php` switches itself off
  the moment a recognized SEO plugin is active and describes the site itself
  meanwhile; `inc/video-seo.php` stays additive and only prints the
  `VideoObject` a general-purpose plugin cannot build.
- **Assets are versioned by content.** `majestic_tube_asset_version()` appends
  the theme version and the file's modification time, so an edited stylesheet is
  re-fetched without a version bump.

### The modules

| Module | Concern |
| ------ | ------- |
| `activation.php` | What a fresh activation creates — pages, menus, legal pages — and the migrations that move settings written by earlier releases. |
| `theme-options.php` | The Customizer: every setting, its section, and the migration off the old flat `wpst-options` array. |
| `theme-support.php`, `site-icon.php` | Core feature support and image sizes; the handover of the retired browser-tab icon to WordPress's Site Icon. |
| `post-types.php`, `video-meta.php` | Post types and taxonomies; the video meta box and its keys. |
| `template-tags.php`, `template-filters.php`, `breadcrumbs.php`, `pagination.php` | The helpers templates call, the filters that adjust core output, and the two navigation pieces. |
| `analytics.php`, `ajax.php`, `reports.php` | Counters, the front-end AJAX endpoints (`post-views`, `post-like`, `get-post-data`) and video reporting with its moderation screens. |
| `ajax-login-register.php`, `consent.php` | Membership: the login/register/password-reset panel and its handlers, the Turnstile spam check, the terms checkbox and the cookie notice. |
| `basic-seo.php`, `video-seo.php` | The theme's own description, canonical and social cards; the video structured data. |
| `assets.php`, `icons.php` | Enqueuing and asset versions; the icon set and the CSS masks built from it. |
| `widgets.php`, `widget-videos.php`, `ads.php`, `share.php`, `custom-code.php` | Widget areas and their widgets, ad placements, share buttons, and the head/footer code fields. |
| `multithumbs.php`, `term-images.php`, `compat.php` | Thumbnail rotation, term images for actors and categories, and the `wpst_*` compatibility layer. |

## Local test site

The credentials for it — the URL, the username and the password — live in
`local-notes.md` at the repository root, and that file is ignored by git. They
were moved out of this one for that reason: an ignored path cannot be swept into
a commit or a release archive by accident, which `git add -A` and a hand-built
zip would both otherwise do, and a password belongs somewhere no tidy-up of the
staged changes can reach it.

This file stays untracked rather than ignored for the opposite reason — an
ignored path is hidden from the agent file tools that are meant to read it — so
the two notes live in two files, and the one holding a password is the one git
ignores. `DEV_ONLY` below is what keeps development-only files out of the theme
regardless.

## Never put development files in the theme

Nothing under `tests/` may reach the theme files: not the theme folder a site
installs, and not the release zip. The same goes for `tools/` and for local
notes such as this file. `DEV_ONLY` in
[tools/verify_release.py](tools/verify_release.py) is what enforces it: the
verifier rejects an archive holding one of those paths and the build skips them,
so a new development-only file belongs in that set. `tests/` and `tools/` are
also in `.gitignore`, which keeps them out of `git archive` — the export the
release tree is built from — in the first place.

## No development references in the documentation

Nothing development-only belongs in documentation a site owner can read: not in
[README.md](README.md) or [docs.html](docs.html), the guides that ship with the
theme, and not in the doc comments of the theme files themselves. That means no
`tests/` or `tools/` path, no name for a script only a maintainer runs, no
mention of this file, no local development URL such as the local test site, and
no developer-only command. A guide that points at the harness sends an installer
looking for files that were never in the download, and a comment that names a
generator reads as an invitation to hand-edit a file that is meant to be
generated. Describe the rule a file follows rather than the tool that checks it;
the tooling stays in `tools/`, where the people who can run it will find it.

## Tests

Each `tests/*-smoke.php` is a standalone script that stubs WordPress, prints
`<n> passed, <n> failed` and exits non-zero on failure. There is no runner or
config to register a new file with — the glob *is* the suite:

```sh
for f in tests/*-smoke.php; do php "$f" || echo "FAILED: $f"; done
```

Run the suites a change touches, and run the whole suite before calling a change
done. Favour tests that render the shipping file over tests that match its
source: `tests/single-password-smoke.php` includes `single.php` and asserts the
markup it prints. `tests/fixtures/` holds the WordPress `wp-admin` includes a
test needs when a stub alone will not do — the media importer and the image
handling the Site Icon and retired-settings suites exercise.

## Agent behavior and build tools

- Caveman mode. When this file is present, a coding agent should answer plainly
  and directly unless the task calls for a step-by-step plan or a conversation.
  Short answers are the default; only expand when a sequence of actions, a
  review of intermediate results, or a back-and-forth with the user is actually
  needed.

- Archives use 7-Zip. Zips are built with 7z from
  `C:\Program Files\7-Zip\7z.exe`. The command lives there in this
  environment, so any scripted or hand-run archive step uses that path rather
  than assuming `zip` or `7z` is on the system `PATH`.

## Releasing

- The notes for a release are written to `tools/release-notes-<version>.md`, and
  the same release is described under "What's New in `<version>`" in both
  [README.md](README.md) and [docs.html](docs.html). `tests/release-notes-smoke.php`
  fails when the version, the contents anchor, the list of releases or their
  order disagree, so a version bump is never just a number.
- `python3 tools/build-release.py <version> <exported-tree> <output.zip>` writes
  the archive and verifies it immediately; `python3 tools/publish-release.py`
  verifies it again, then attaches it to a GitHub release. A file that fails any
  check is deleted rather than left on disk to be published later.
- The `majestic-tube-*.zip` files in the root are earlier release archives kept
  for comparison. They are ignored by git and are not part of the theme.
- Most of `tools/` is one-off maintenance tooling — preview builders, icon-set
  generators, upload probes. Read a script before running it: several of them
  write files into the theme.
- **Release definition**: a "release" means building a new archive with the next
  version tag (e.g. `v2.5.4`). Tag the commit with `git tag v<new-version>` and
  `git push --tags`. The CI workflow then builds and publishes the release automatically.

## Conventions worth keeping

- Code style is WordPress's, with tabs for indentation, a docblock on every
  function, and a `// phpcs:ignore ... -- reason` where a rule is deliberately
  broken. No linter is configured, so those comments are for the reader.
- Escape at print time (`esc_html`, `esc_attr`, `esc_url`), run `wp_unslash()`
  over input, and check a capability and a nonce before anything changes state.
- The theme writes **American English** (`color`, `behavior`, `labeled`),
  matching WordPress itself. `tests/design-smoke.php` walks every shipped text
  file and fails on a British spelling of one of those words — including in this
  file, which it treats as shipped text.
- A new `##` heading in [README.md](README.md) needs an entry in its contents
  list: `tests/readme-contents-smoke.php` recomputes the anchors GitHub builds
  from heading text and fails on a section nothing links to.
- Templates stay thin. A template prints markup and calls helpers; the logic
  belongs in the `inc/` module that owns the concern.
- **A commit message carries no trailer.** The message ends with its own last
  line: no `Generated with Codebuff` line, no `Co-Authored-By: Codebuff
  <noreply@codebuff.com>` sign-off, and the same for a pull request body, a
  release note or any other text written into the repository. Tooling that
  appends one is not to be used, and an edit to an existing message strips it.
  Commits made before this rule keep theirs, because history is not rewritten
  to tidy it: seventeen commits in `main` carry it today.
- The tracked copy of that rule is [`.github/CONTRIBUTING.md`](.github/CONTRIBUTING.md),
  which is what a fresh clone receives and what GitHub shows a contributor.
  This file is untracked, so a rule a contributor has to know belongs there as
  well: an edit to one is an edit to the other.
