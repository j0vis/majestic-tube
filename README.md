# Majestic Tube

Everything you need to install, configure, populate, and manage a Majestic Tube video website. Follow the quick start first, then use the detailed sections as reference.

- **Video-first** - Multi-quality player, thumbnails, trailers, ratings, and related videos
- **Member friendly** - Front-end login, registration, profiles, and video submissions
- **Easy to customize** - Settings, branding, menus, and content areas use standard WordPress screens
- **Responsive** - Desktop and mobile layouts, including right-to-left support

**Contents**

- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start-checklist)
- [Dashboard guide](#dashboard-guide)
- [Adding videos](#adding-and-managing-videos)
- [Thumbnail rotation](#thumbnail-rotation-and-trailers)
- [Actors and categories](#actors-categories-and-tags)
- [Player settings](#player-and-media-behavior)
- [Members and submissions](#members-and-video-submissions)
- [Icons](#icons)
- [Search appearance](#search-appearance)
- [Video structured data](#video-structured-data)
- [What a page loads](#what-a-page-loads)
- [Customizer settings](#customizer-settings)
- [Menus, widgets, and content](#menus-widgets-and-content-areas)
- [Reports and moderation](#video-reports-and-moderation)
- [Legal pages](#legal-and-policy-pages)
- [Routine maintenance](#routine-maintenance)
- [What's new in 2.5.4](#whats-new-in-254)
- [Troubleshooting](#troubleshooting)
- [FAQ](#frequently-asked-questions)
- [Credits, license & support](#credits-license--support)
- [Quick reference](#quick-reference)

**Release**

## What’s New in 2.5.4

The guide last introduced 2.2.0. Everything published since is below, newest first: what changed, and what an update asks of you. In short, the theme draws its own icons, describes its videos to Google Video, hands search appearance to an SEO plugin, prints a row for the sites you link to, keeps advertising off the pages that ask a visitor to accept something, hands a retired browser-tab icon over to WordPress, keeps a locked video locked, stops one unlinkable tag from taking a page down with it, draws a random listing from a cache, ships the template a translation starts from, and files a term that begins with a number the same way on every PHP.

### 2.5.4 — the alphabet bar files a digit the same way on every PHP

**A term whose name starts with a number no longer lands in a different place depending on your server.** The Actors and Categories directories bucket every term under its first character, and `7 Up` files under `7` — a key PHP keeps as the number 7 rather than as text. Sorting that map used PHP's own key comparison, and its answer changed between versions: the digit bucket came after A-Z on PHP 7.4 through 8.1 and before it on 8.2 and later, so the same directory could be ordered two ways on two servers. The keys are compared as strings now — the order the bar's own markup is written in — so the directory reads the same on every PHP the theme supports, and on the one your host moves you to. See [Actors, categories and tags](#actors-categories-and-tags).

**Each responsive step states its own grid.** The tablet, phone and small-phone steps set the column count and the gutter but left the grid itself to the rule at the top of the stylesheet, so what a step produced depended on which block happened to come last. Each of them declares the grid, the columns and the gutter together now. Nothing on the page changes; what changes is that a later edit cannot take the layout away from the step it was written for.

### 2.5.3 — a random listing stops re-sorting the catalog

**A random listing is drawn from a cache instead of sorting your catalog.** The random listing, the [Video list widget](#video-list-widget) set to Random, and the picture a category or actor card borrows from a video all asked MySQL for `ORDER BY RAND()`, which reads every matching row and sorts it on every request — the most expensive query the theme made, on the pages that repeat it most. Each of them now draws from a pool of video IDs kept for the archive it belongs to, shared by all three and rebuilt when you publish, edit or delete a video.

**An upload is resized once.** Every thumbnail size was registered twice — the original theme’s `wpst_thumb_*` names sat beside the theme’s own at identical dimensions — so WordPress wrote seven metadata entries for five files and encoded three of them a second time. The older names are answered from the attachment’s own metadata now, which is where WordPress looks a size up and which can only point at a file that was really generated, so everything that asks for one by name still finds it: the **Main thumbnail quality** setting, WP-Script plugins, and a child theme. Nothing in your media library has to be re-uploaded or regenerated. See [Thumbnail rotation and trailers](#thumbnail-rotation-and-trailers).

**The theme ships a translation template.** `languages/majestic-tube.pot` lists every string the theme asks to have translated, in the format a translation tool reads, with the contexts and plural forms the harder strings carry. Copy it to your locale, translate the copy, compile it and place the `.mo` beside it — three steps, described in that folder’s own notes. It is generated from the theme’s own source rather than edited by hand, so it keeps describing the version you have installed.

**Also fixed:**

- A **part of the video sitemap is built once and kept** until a video changes. It ran a `COUNT(*)` whose answer it never read, and rendered the whole chunk again on every request, so a crawl of a large catalog repeated that work page after page. A site with a persistent object cache answers a crawl from memory now; a site without one sees no change at all.
- A **share row with no address to share is not printed.** Each network’s link carries the video’s own permalink, so a video whose permalink cannot be built — trashed, still a draft, or a post type that never publishes — produced a row of buttons that shared whichever page the reader happened to be on.
- A **related-videos list no longer breaks on a taxonomy that answers with an error.** The term is left out of the query and the rest of the list prints.
- The **term image picker’s strings** travel as one inline value rather than a localized script object, which drops a script WordPress loaded on every admin page for the sake of one screen.

**One thing worth knowing about random listings:** a random listing is a sample, not a shuffle of the catalog. The pool holds 500 IDs by default and is taken at a random offset into the archive, so a page is drawn from part of the catalog rather than from all of it, and the same video can appear on two pages running. The `majestic_tube_random_pool_size` filter raises it for a site that wants a wider sample, at the cost of a larger query whenever the pool is rebuilt.

### 2.5.2 — a locked video stays locked, and one broken term no longer takes the page

This release is about the content a video site did not write itself: a visitor's submission, an imported feed, a title copied from somewhere else. Several of the fixes below only show up on a page nobody looks at twice, which is what made them worth finding.

- A **password-protected video** was protected in name only. The template printed the player — and, for a self-hosted file, the direct file addresses — above the content, so a locked video handed its file to anyone who followed the link. It now shows WordPress's own password form and reads none of the player data first. See [Player and media behavior](#player-and-media-behavior).
- A video's **title can no longer break the page's structured data**. The VideoObject block is JSON inside a `<script>` element, and a title carrying `</script>` ended that element, so everything after it was read as page markup by every visitor. The block is encoded so no title, description or tag can end it, and an embedded player's address is checked to be http or https before it is used. See [Video structured data](#video-structured-data).
- A **category, tag or actor whose link cannot be built no longer stops the page.** The list under a video asks WordPress for each term's link, and that answer can be an error rather than an address — which was printed as if it were the address, ending the whole request for every reader of that video. A term with no link is left out now, and the rest of the list prints.
- The **counters only answer for videos a visitor can see.** The endpoints behind views, likes, dislikes and reports took any post ID they were handed, including a draft, a pending submission and a private video. See [Video reports and moderation](#video-reports-and-moderation).
- The **password reset form is guarded** the way the sign-up form is: with the Turnstile keys filled in, a reset request is checked before it is answered. The reply no longer says whether an account exists, and a lookup that finds nothing counts toward the throttle.
- The **rotation thumbnails box works.** Its **Add** and **Remove** buttons did nothing at all — the box handed out the endpoint, the nonce and the post ID, and no script read them. Both buttons do what they say now, and the list is written when a button is pressed rather than on publish. See [Thumbnail rotation and trailers](#thumbnail-rotation-and-trailers).
- The **date under a video title is a date again**, instead of the markup around it printed as text.
- The **term image picker** writes its preview safely — an image address with a quote in it could close the attribute it was written into — and copes with an image that has no thumbnail size. See [Actors, categories and tags](#actors-categories-and-tags).
- An **RSS item's featured image no longer errors** when the item is built without a post in the loop, and a saved **color scheme survives the update** if it was chosen before the site's default was recorded beside it.

### 2.5.1 — the browser-tab icon is handed over

If you had set the theme’s own **Browser tab icon** before it was retired, it is moved into WordPress’s **Site Icon** (`Appearance → Customize → Site Identity → Site Icon`) on the first admin page load after the update, because that is where the browser-tab icon lives now. An image the media library already holds is reused by reference; one hosted somewhere else is fetched once through WordPress’s own importer and kept in the library. Nothing is deleted — the stored setting stays in the database, so an older release that downgrades still reads it.

- A **Site Icon** you set yourself is never overwritten. The icon you chose is not the theme’s to replace, so the retired setting stands down rather than competing with it.
- When the address cannot be moved — it no longer answers, or it points at a file the media library refuses to keep as an image — the dashboard names the address and links the screen where the icon belongs, instead of the tab icon quietly disappearing. The warning is dismissible, and comes back only if the address changes.
- The move runs on an admin request from a user who could set a Site Icon by hand, and never while a visitor is loading a page: it writes an option and it may fetch a file, and neither belongs on the front end.

### 2.5.0 — a site that describes itself, and no advertising where a question is asked

**The theme describes the site again, unless you run an SEO plugin.** `SEO & Analytics → Basic SEO` is on by default and prints a meta description, a canonical on archive pages — including page 2, which canonicalises to itself rather than to page 1 — and the Open Graph and Twitter cards a shared link needs, with the image taken from the video’s own picture, then a term image, then your logo, then the Site Icon. It switches itself off completely the moment Yoast, Rank Math, All in One SEO, SEOPress, The SEO Framework or Slim SEO is active, so a site that already runs one keeps exactly the search appearance it had. The **Adult content rating tag** and the **RTA label** are off by default and keep working with a plugin, because no SEO plugin emits them. See [Search appearance](#search-appearance).

**A page that asks a visitor to accept something carries no advertising.** A legal page — the 2257 document, the DMCA page, the privacy policy, and any page whose slug or title reads as one of those — prints none of the **Code and ads** areas, no footer widget columns, and no advertising from any placement, the popunder included. The submission page is treated the same way while the form is on it, and the login and registration panel and the cookie notice never contain a widget area.

**Forms, consent, and submissions.** The login, registration and submission forms carry a required **terms checkbox**, on by default, naming and linking the documents your site actually publishes; the handler asks again on the server, so a form submitted without it is refused. A **cookie notice** along the bottom of the page is off by default, uses your own wording, keeps its dismissal in the visitor’s browser and records nothing on your server. The submission form gained **extra thumbnails** — a list of URLs, one per line, rotated as the pointer rests on a card — and an optional **preview video**, with switches that make either one a required field.

**Color, icons, and the first image.** The **Accent color** now derives the tones one value cannot serve: the fill keeps your color and picks white or the theme’s near-black ink for the text on it by measuring contrast, links, text and icons are deepened for the light skin until they clear 5:1 and brightened for the dark skin until they clear 7:1, and the player takes a bright version because its chrome stays black over video. Nothing derived is stored. A child theme can add or replace a shape — see [Icons](#icons). The first card image of a listing, or the player poster on a video page, is loaded eagerly, marked high priority and preloaded from the head, because that image is the one the browser scores as the largest contentful paint; every later image stays lazy. Core’s emoji script and stylesheet no longer load on the front end, and the footer legal row is centered on the same axis as the copyright line and wraps instead of scrolling sideways.

**Fixed.** The moderation screen no longer takes the page down with it when its ten-minute cache names a video that has since been deleted or trashed. A counter row written with no value — an interrupted import, a hand-edited row — no longer raises `Undefined array key 0` on PHP 8. The like endpoint stops asking for a missing `post_id` twice, and two exception messages escaped an integer post id instead of the message around it.

### 2.4.2 — icons drawn by the theme

The theme now ships its own icon set of 43 shapes in its stylesheet: no icon font, no plugin, no extra request. Each one is a masked SVG that paints in whatever color surrounds it, so a single shape works in the light skin, the dark skin, on accent-colored buttons and on muted metadata. They appear on the search button, the mobile menu toggle, the sort tabs, the video card and video page metadata, the like, dislike, share and report buttons, the category, actor and tag labels, the tag list, pagination and the back-to-top button.

A thumbnail badge is no longer a bare number: an eye marks the view count, a clock the length and a star the rating, in the sidebar widget and the category directories too, and a card with no picture shows a frame above its caption rather than an empty rectangle that looked like a thumbnail still loading. The player controls carry the same shapes — sliders on the quality selector, a dial on the speed control, the expand mark on theater mode, and a play and a close on the resume pill — and the quality and speed menus tick the current choice with the tick reserved on every row, so the options stay in a column instead of the one you just picked shifting sideways.

Icons are on by default. `Customize → Site Features → Header, Footer & Search → Show icons throughout the site` turns them off everywhere at once and returns every surface to words alone, which is the right choice for a site that has been styling the old `fa fa-*` classes through a child theme. Nothing is written to the database and no setting moves, an icon name the theme does not ship returns nothing rather than an empty box, and the full set is documented under [Icons](#icons).

The release also repaired a defect worth naming: the icon generator had silently removed 260 lines from the stylesheet, including the skin toggle and the base typography and header rules. The file still parsed and the braces still balanced, so nothing reported it.

### 2.4.1 — a row for the sites you link to

A new **Friends & Ad Links** menu location prints as its own row directly under the header, set apart from the widget area so it never reads as site navigation. Every link in it is force-tagged `rel="sponsored nofollow noopener noreferrer"`, plus `target="_blank"` if you want it, so exchanged or paid links cannot leak follow equity — and only that menu is affected, because navigation menus, post links and footer links keep the attributes you set on them. Five row styles are built in (plain, stars, rockets, glam and neon), and `Customize → Advertising → Friends & Ad Links` holds the switches. The row prints nothing until you assign a menu to it, so an existing site looks exactly the same until it uses it.

### 2.4.0 — videos described to Google Video

Every video page now carries a schema.org `VideoObject` block built from the runtime, the file or embed the player is actually using, and the actors taxonomy, plus the `og:video` tags and `twitter:player` where an embeddable player exists. A video sitemap is served at `/video-sitemap.xml`, advertised in `robots.txt` and split across numbered files so a large catalog stays inside the per-file limits; submit it in Search Console as its own sitemap, because the XML sitemap index does not cover it. `Videos → All Videos` gained a **Video SEO** column that shows `OK`, `OK (embed)`, or the fields a video is missing, and a video missing a duration, a thumbnail or a source is skipped rather than submitted half-finished. The switches are described under [Video Structured Data](#video-structured-data); both default to on, and neither touches the title, description or canonical your SEO plugin prints.

One limit is worth knowing before you count on it: a video played from another domain’s player can still rank as a web page, but Google cannot count it as part of your site’s own video library, so it will not appear in Google Video results. Serving the file yourself, or embedding a player from your own domain, is the only way to qualify. `wp majestic-tube audit` reports how much of a catalog is affected.

### 2.3.0 — search appearance is your SEO plugin’s job

This release took the theme out of search appearance, on the reasoning that two sources of truth for one page title is worse than one. It removed the dashboard **SEO** workspace and its five tabs, the theme-printed titles, meta descriptions, robots directives and canonicals, the Open Graph and Twitter cards with the Facebook app ID and the player card, the JSON-LD `VideoObject` and the `BreadcrumbList` graph, the per-term SEO title and description fields with their list column and bulk editor, and the programmatic collections with their `/browse/` crawl spine and the theme’s XML sitemap provider.

`Customize → SEO & Analytics` still holds **Homepage SEO** (heading position, visible introduction), **Archive Page SEO** (category and actor card text, description placement) and **Custom Code** (analytics code, extra scripts, mobile scripts). Share links and the visible breadcrumb trail are unchanged.

**Updating from the 2.2 line**, install an SEO plugin for titles, descriptions, social cards and structured data. Nothing those screens stored is read any more, although it stays in the database. The theme came back to two of those jobs on its own terms, because no general-purpose plugin can do them: [Video structured data](#video-structured-data) in 2.4.0, and the theme’s own description of the site in 2.5.0.

### 2.2.1 – 2.2.26 — the 2.2 patch line

Twenty-six patch releases followed 2.2.0. Grouped by what they touch:

- **The alphabet bar and the term directories.** Every letter A–Z and 0–9 is printed on every directory whether or not anything sits behind it — a letter with terms is a link, a letter without is dimmed and inert, digits sort last — because a directory that lists its terms with no visible sort control is the worst outcome, and an empty lookup produced exactly that. Choosing a letter lists every term under it on one page, the letter map is read straight from the term tables so a `hide_empty` combination cannot defeat it, and the tags page lists every tag rather than twenty of them.
- **Term cards and archives.** Category and actor cards take a phrase from the Customizer with `{description}`, `{count}` and `{videos}` tokens, the term description fills a band that used to leave the card looking unfinished, and the video count no longer borrows the thumbnail-badge class. Category, tag and author archives render the same grid as every other listing, which they did not before.
- **Widget areas and labels.** Every content area now says “code and ads”, the one area that takes any widget says “Footer widgets”, and three Customizer labels that described something other than the control they belonged to were corrected. Labels only — no saved value moved.
- **The player.** A 300x250 overlay sits centered over the player with a close button that can be seen on a black video, the homepage description can sit above or below the title, and a site-wide choice decides which of a post’s two video fields the player prefers, with the social and schema tags following the same choice. The membership menu links honor their show/hide switches, and `categories-per-row` emits its own column count instead of inheriting the video listing’s.
- **Members and submissions.** The profile editor answers honestly when WordPress refuses a password or email change, first name, last name and bio can be cleared once they hold a value, the eight-character password floor is enforced on the server rather than in a `minlength` attribute, and password reset goes through WordPress’s own flow again so a membership plugin’s filters and mail customizations apply. A full-page cache no longer freezes the front-end nonces: a stale one answers 403, and the script fetches a live nonce and replays the request once. Subscriber submissions keep their tags and actors, and unsubscribing removes the navigation menu items it used to leave behind.
- **Privacy.** Vote, view and report de-duplication no longer keeps raw IP addresses in post meta. They are keyed fingerprints that expire, a daily sweep clears rows nothing writes to again, and the 24-hour windows are unchanged.
- **The legal pages.** The 2257 starter page no longer asserts that its operator holds the primary producer’s record-keeping duty, which most installs of this theme are not in a position to hold: it describes the secondary producer role and says outright that it does not decide which obligations apply. The site name is expanded from `Settings → General` where it is shown rather than stored as a bracketed field, so it reaches pages older installs already have. A deleted legal page can be recreated from the welcome screen, duplicate copies are shown there so the extra one can be trashed, and all five are linked on the main menu.
- **The listings.** Popular ranks by likes once the site has a vote and by views until then, instead of sorting on a rate value only a vote writes. The most-used tags print as a strip under the sort bar, scrolling on its own so it works without JavaScript, with the arrow buttons revealed only once the tags are measured as overflowing.
- **Search appearance, briefly the theme’s own.** 2.2.20 to 2.2.26 built a search-appearance workspace of the theme’s own: facet pages generated from the catalog behind an index gate, per-term SEO titles and descriptions, switchable generated pages with editable wording, and a `/browse/` crawl spine with their sitemaps. 2.3.0 removed all of it, so what those releases added is history rather than a feature: on 2.5.1 that job belongs to your SEO plugin, and none of those settings is read.
- **Fixed along the way.** The sitemap provider implemented method names WordPress does not call, so every sitemap request died with a fatal error; the tag bar’s sort order was an `orderby` array `get_terms()` cannot take, which was a `TypeError` on every page that printed the bar; a cleared page-template assignment left **Submit a Video** rendering a title with no form; the header search bar sat hard against the logo with the space it was meant to fill left empty; the logo badge was dropped, a logo icon that duplicated the plain play triangle was retired in favor of Film, and Film and Video were given shapes of their own instead of sharing one character; and the stats refresh stopped answering 0% for an imported post whose rating a vote had not written.

### 2.2.0 — the release this guide opened with

It regrouped the Customizer, replaced the spam check with Cloudflare Turnstile, and made imported videos display correctly.

#### A Customizer you can navigate

The settings used to sit in nine flat sections with a catch-all bucket that mixed homepage settings with video-page settings. They are now grouped into panels, and every setting is filed under the page it actually affects. The two panels you will visit most are kept apart: **Homepage & Listings** and **Video Page**. The full map is in [Customizer Settings](#customizer-settings).

Nothing is lost in the move, and no setting is reset: the values stay exactly where they were stored. Only the labels and the layout around them changed.

#### Cloudflare Turnstile replaces reCAPTCHA

Spam protection on sign-up and video submission is now handled by Cloudflare Turnstile, and reCAPTCHA has been removed. Turnstile is free at any traffic level and asks less of a visitor. Setup takes a few minutes and is covered in [Members and Video Submissions](#members-and-video-submissions).

#### Imported videos display correctly

Two problems showed up on videos brought in by an importer, and both are fixed:

- **Ratings appeared as 0%.** Imported posts store their rating as a pair of values, which the theme was not reading, so every imported video showed no rating. The theme now reads them, and the first visitor vote builds on the imported score instead of replacing it with 100%.
- **Hover previews showed one broken image.** Extra preview images are stored as a single comma-separated value, while the theme read them as one URL per stored value. A comma is a legal URL character, so the whole string survived as one unusable address. Both storage shapes are now handled, including removing a single preview from the editor.

The **18 U.S.C. § 2257** page is also repaired automatically if it goes missing from the footer menu, so a deleted or renamed legal link comes back on its own. The page is named the way the statute is written, and an update renames the page and its footer link in place — same page, same address, same content. Later releases go further and let you recreate any deleted legal page from the welcome screen.

> **Upgrading from an earlier release**
>
> Replace the theme folder. Your settings carry over, and nothing is deleted: a value written by an earlier release stays in the database even where nothing reads it any more.
>
> - **Search appearance** has been an SEO plugin’s job since 2.3.0. Install one, or — if you run none — leave **Basic SEO** on, which is its default. Per-term SEO titles and descriptions, the generated collections and the `/browse/` pages from the 2.2.x line are not read any more.
> - **A browser-tab icon you set in the theme** is moved into WordPress’s **Site Icon** for you on the first admin page load after the update. If the address cannot be fetched, the dashboard names it so you can upload that file once.
> - **If you are updating from 2.1.7 or earlier**, remember the spam check is now Cloudflare Turnstile: create a widget and paste its two keys into `Appearance → Customize → Site Features → Accounts & Spam Protection`. The old reCAPTCHA keys are no longer used.

**Before you begin**

## Requirements

Majestic Tube runs inside a normal WordPress website. Use a current, supported hosting environment and keep WordPress, PHP, and browser software up to date.

| Requirement | Recommended value | Notes |
| --- | --- | --- |
| WordPress | 6.5 or newer | An up-to-date WordPress installation is strongly recommended. |
| PHP | 7.4 or newer | Your host should support current secure PHP releases. |
| Browser | Current Chrome, Edge, Firefox, or Safari | JavaScript must remain enabled for playback and interactive features. |
| HTTPS | Enabled | Use HTTPS on a live site, especially for member login and password reset emails. |
| Media | Direct MP4/WebM files or a supported embed | Optional trailer previews accept common video or image formats. |

**Get the theme online**

## Installation

### Install from the WordPress dashboard

1. Sign in to your WordPress administration area.
2. Go to `Appearance → Themes → Add New → Upload Theme`.
3. Select the Majestic Tube theme archive and choose **Install Now**.
4. Choose **Activate**.
5. Follow the welcome screen shown after activation.

### What activation creates

Activation sets up the basic site structure. Existing content is not overwritten.

- A main navigation menu, assigned to the theme’s main menu location.
- Pages for **Submit a Video**, **Profile**, **Actors**, **Categories**, and **Tags**.
- Starter pages for **18 U.S.C. § 2257**, **DMCA**, and **Privacy Policy**.
- A separate footer menu containing the built-in legal links when an appropriate menu location is available.

> **Note**
>
> If you are updating an already active theme, the setup routine is safe to run again. It will not intentionally create duplicate pages or duplicate footer links.

### After installation

1. Visit `Settings → Permalinks` and choose **Save Changes** once. This refreshes page and archive links after activation.
2. Open `Appearance → Menus` and review the Main Menu.
3. Open `Settings → Reading` if you want the latest videos on the homepage.
4. Open `Appearance → Customize` and review the Majestic Tube sections.

**Recommended first setup**

## Quick Start Checklist

Complete these tasks before announcing the website.

- [ ] Activate Majestic Tube and visit the welcome screen.
- [ ] Set the site title, tagline, logo, and main color.
- [ ] Choose the homepage video sort and the number of videos displayed.
- [ ] Create video categories, tags, and actors.
- [ ] Add images to important categories and actors.
- [ ] Publish the first video and verify its player on desktop and mobile.
- [ ] Review the Main Menu and footer links.
- [ ] Configure member registration if you plan to accept submissions.
- [ ] Review and customize the legal pages for your actual operation.
- [ ] Test search, video reports, password reset, and email delivery.
- [ ] Decide how search appearance is handled: `SEO & Analytics → Basic SEO` is on by default, or install an SEO plugin and the theme steps aside for it.

**Know where everything is**

## Dashboard Guide

Majestic Tube uses familiar WordPress menus. The most important areas are listed below.

| WordPress area | What to do there |
| --- | --- |
| `Videos` | Add, edit, publish, search, and review videos. Video Categories, Video Tags, and Reported Videos are available here. |
| `Actors` | Create actor names and assign a portrait to each actor. |
| `Media` | Upload images and other media used by videos and site content. |
| `Pages` | Edit the automatically created submission, profile, directory, and legal pages. |
| `Appearance → Customize` | Control listings, the player, branding, sharing, submission rules, custom code, and mobile presentation. |
| `Appearance → Menus` | Manage the Main Menu, Footer Legal Menu, and the Friends & Ad Links row. |
| `Appearance → Widgets` | Place video lists and optional content blocks in theme areas. |
| `Settings → Reading` | Choose what appears on the homepage. |
| `Settings → Discussion` | Control comments and discussion settings for videos. |
| `Users` | Manage members, authors, administrators, and user permissions. |

**Create the core content**

## Adding and Managing Videos

### Add a video

1. Go to `Videos → Add Video`.
2. Enter a clear title.
3. Write the description in the main content editor.
4. Select one or more video categories.
5. Add relevant tags.
6. Select one or more actors when applicable.
7. Scroll to **Video information** and provide the playback source, duration, thumbnail, and optional details.
8. In **Thumbnails (rotation)**, add extra thumbnail URLs if you want a hover sequence.
9. Choose **Publish**, or save as Pending for review.

### Video source options

Use one of the following playback methods:

- **Self-hosted video:** enter the main video URL and any available resolution URLs.
- **Embedded video:** paste a supported iframe or embed code into **Video embed code**.
- **Shortcode-based video:** paste the shortcode provided by your video service into **Video shortcode**.

> **Important**
>
> Do not add both a direct video URL and a conflicting embed or shortcode. If more than one source is present, keep only the method you intend visitors to use, or choose which one wins site-wide.

### Choosing between a video URL and an embed code

`Appearance → Customize → Video Page → Video Page Layout → Video source for the player` decides which of the two fields the player uses, on every video page at once:

| Choice | What plays |
| --- | --- |
| Automatic | The Video URL when the post has one, otherwise the embed code. This is the default. |
| Video URL | The Video URL is preferred. Posts without one fall back to their embed code. |
| Video embed code | The embed code is preferred. Posts without one fall back to their Video URL. |

The setting only changes which field is preferred. Both are still saved on the post, so you can switch at any time without touching a single video, and a video that is missing the field you picked still plays from the other one rather than showing an empty player. The same choice decides which of the two the theme describes in its `VideoObject` markup, so what a search engine and a social network are told about the video matches what a visitor actually watches. See [Video Structured Data](#video-structured-data).

### Video information fields

| Field | Purpose | Recommended use |
| --- | --- | --- |
| Video URL | Default self-hosted video source | Use a direct, publicly reachable media URL. |
| Video URL 240p–4K | Alternative quality sources | Add only the resolutions you actually provide. |
| Video embed code | Third-party player | Paste the complete iframe or supported embed markup. |
| Video shortcode | Shortcode-driven player | Paste the exact shortcode supplied by your video service. |
| Duration (seconds) | Time shown on cards and video pages | Enter total seconds, such as 545 for 9:05. |
| Views | Existing view count | Usually left at zero for new videos. |
| Likes and Dislikes | Existing rating totals | Normally begin at zero. |
| Video trailer URL | Preview shown when a visitor hovers over a card | Use an MP4/WebM trailer or supported image preview. |
| Main thumbnail | Default card image | Use a clear 16:9 image when possible. |
| Tracking URL | Destination for the tracking/download button | Enter the complete destination URL. |
| HD video | Shows an HD badge on cards | Turn on only when the video should be presented as HD. |
| Advertising under the video player | Special content for this video only | Leave blank to use the general Below player: code and ads area. |

### Recommended publishing workflow

1. Save new member submissions as **Pending**.
2. Open each pending video and verify the title, description, source, thumbnail, duration, category, tags, and actors.
3. Correct any missing or unsafe content.
4. Choose **Publish** when the video is ready for public display.

### What visitors can do on a video page

- Play the video and switch between available qualities.
- View the current view count, duration, rating, likes, and dislikes.
- Like or dislike the video when voting is available.
- Share the video using the enabled sharing options.
- Report a broken, incorrect, spam, or misleading video.
- Browse related videos based on shared actors.

**Make cards more engaging**

## Thumbnail Rotation and Trailers

### Add rotating thumbnails

1. Edit the video in `Videos`.
2. Find **Thumbnails (rotation)**.
3. Paste an image URL into the thumbnail field.
4. Choose **Add thumbnail**.
5. Repeat for each additional image.
6. Use **Remove** beside any thumbnail you want to delete.
7. Save or update the video.

When enabled, visitors see the thumbnail sequence when hovering over a video card. The first available image is used when no featured image is set.

### Choose the main thumbnail

Use the **Main thumbnail** field for the default card image. A featured image attached to the video can also be used. Keep all important images at the same aspect ratio to avoid cropping.

### Add a trailer preview

1. Edit the video.
2. Enter the trailer address in **Video trailer URL**.
3. Save the video.
4. Test the card on desktop and mobile.

Video trailers play briefly on hover. Supported image previews appear as an overlay. A trailer takes priority over thumbnail rotation for that card.

Neither effect is wired up when the page loads. A card starts listening for hover only once it has scrolled close to the screen, and lets go again when it scrolls away, so a long category page with sixty cards does not sit on sixty dormant timers and listeners. Cards far below the fold are not prepared at all until they are nearly in view, which is also why a page of trailers no longer costs anything on the reader's data until they actually scroll to it.

> **Note**
>
> This needs no setting and cannot be switched off. A browser without `IntersectionObserver` — which in practice means no current browser — falls back to preparing every card on load, exactly as the theme behaved before.

**Organize the library**

## Actors, Categories, and Tags

### Create an actor

1. Go to `Actors`.
2. Choose **Add Actor**.
3. Enter the actor name.
4. Select an image from the Media Library.
5. Choose **Add Actor**.

To change an image later, open the actor, choose **Edit Actor**, select a replacement image, and save.

### Create a video category

1. Go to `Videos → Video Categories`.
2. Choose **Add New Category**.
3. Enter the name, optional description, and parent category if needed.
4. Select a category image.
5. Save the category.

### Use tags

Add short, descriptive tags in the Video editor. The Tags page template presents all site tags as a cloud with video counts.

### The popular tags bar

The twenty most-used tags appear in a scrolling bar directly under the sort bar — Latest, Most viewed, Longest, Popular, Random — on the homepage and on actor pages. Each chip shows the tag and how many videos carry it, and opens that tag's archive. On a tag archive, the tag being read is highlighted.

The bar scrolls by touch, by trackpad, and through the two arrow buttons at either end. The arrows only appear when the tags genuinely overflow the row, and each one goes inactive at the end it leads to. Without JavaScript the row still scrolls and the arrows are simply absent, so nobody is shown a control that does nothing.

Turn it off, or change how many tags it shows, under `Customize → Homepage & Listings → Homepage`:

- **Show the popular tags bar** switches the whole row on or off.
- **Number of popular tags** sets the size of the bar, from 5 to 50. The default is 20.

A site with no tagged videos shows no bar at all, rather than an empty row under the sort bar.

### Write the line on a category or actor card

Every card on the Categories and Actors pages carries a line of text between the name and the video count. By default it is that term's own description, and nothing shows for a term you never wrote one for.

You can replace that with a phrase of your own under `Customize → SEO & Analytics → Archive Page SEO`, using **Category card text** and **Actor card text**. The default reads `Free "{description}" videos`, and the same idea is available separately for actors.

### Choose how many categories sit in a row

**Categories per row**, under `Customize → Homepage & Listings → Category, Tag & Actor Archives`, sets how many category cards line up on the Categories page. The number is the desktop figure; the theme steps it down on its own as the screen narrows, never showing fewer than two cards on a phone or three on a tablet, so a wide desktop value never squeezes the cards into unreadable slivers.

| Token | Becomes |
| --- | --- |
| `{description}` | The term's own description, or its name when no description has been written. |
| `{name}` | The category or actor name. |
| `{count}` | The number of videos, formatted for the site language. |
| `{videos}` | The word *video* or *videos*, chosen to match the count. |

So `{count} {videos} starring {name}` gives "42 videos starring Amber Waves", and on a term with a single video it gives "1 video starring…". An unrecognized token is left in place rather than silently deleted, so a typo is visible on the page instead of quietly producing a half-written sentence. Clear the box to go back to each term's own description.

The line is clamped to two lines, and the card drops the blank title line that a grid card reserves, so filling this in does not make the cards any taller.

### Browse categories, tags, and actors by letter

The Categories, Tags, and Actors pages all carry an alphabet bar above the listing. **All** shows the complete directory; each letter shows only the terms starting with it, along with how many there are. Only letters that actually have terms appear, so the bar never offers a letter that leads to an empty page.

Terms are filed under the first letter of their first word, so *AnnaBelle* appears under A. Names beginning with a digit are grouped under that digit, and a name beginning with punctuation is left out of the bar but still appears under **All**.

The letter is a query argument, so it survives pagination: `/tags/?letter=A` can be bookmarked and shared, and moving to page 2 keeps the filter. Choosing a letter resets to page 1.

Terms with no videos attached are left off the directory pages, and out of the alphabet bar's counts, so the two normally agree. The one exception is deliberate: if a directory somehow produced no letters at all, the bar still prints rather than silently disappearing, so an empty directory is never mistaken for a broken theme. This is a change from the original theme, which listed them. It matters after a bulk import, which can leave a large number of unused terms behind; they made every directory page longer and linked to archives with nothing in them.

To go back to listing them, add this to a small plugin:

`add_filter( 'majestic_tube_term_directory_hide_empty', '__return_false' );`

Unused actors and tags are still reachable directly, and still appear in wp-admin under `Actors` and `Videos → Video Tags`, where you can delete them in bulk. Filtering by **Empty** finds them all at once.

### Card images when a term has none

Categories and actors can each have an image of their own, set from the term's edit screen. When one has not been set, the card borrows a thumbnail from one of its own videos rather than showing an empty placeholder:

- An **actor** card uses the thumbnail of that actor's most recent video, since a person is best represented by their latest work.
- A **category** card uses the thumbnail of a randomly chosen video from that category, so each category card looks different instead of every card in the grid showing the same picture.

Only videos that actually have a featured image are considered, so a borrowed card is never blank. The choice is remembered for a while and then remade, which keeps a card from changing its picture on every page load while still giving the grid variety over time. Setting an image on the term always wins, and an image that was deleted from the media library falls through to a borrowed one rather than showing a broken picture.

To go back to empty placeholders, add this to a small plugin:

`add_filter( 'majestic_tube_term_fallback_image_url', '__return_empty_string' );`

To make actor cards random too, or categories use the most recent video, use the mode filter:

`add_filter( 'majestic_tube_term_fallback_mode', function ( $mode, $taxonomy ) { return 'actors' === $taxonomy ? 'random' : $mode; }, 10, 2 );`

### Directory pages

The automatically created Actors, Categories, and Tags pages use portrait cards, images, and video counts. You can edit their introductory block editor content without changing the directory itself.

**Playback preferences**

## Player and Media Behavior

### Multiple quality sources

Add direct video URLs for 240p, 360p, 480p, 720p, 1080p, and 4K. When more than one source is available, visitors can use the quality control in the player.

### Player options

| Option | What it does |
| --- | --- |
| Autoplay video player | Starts playback automatically in a muted state, as required by modern browsers. |
| Use the native HTML5 player | Uses the browser’s built-in controls instead of the enhanced Majestic Tube player. |
| Enable the video quality selector | Shows available quality choices for direct video sources. |
| Count a view only after playback starts | Records a view once the video has played for three seconds rather than the moment the page loads, so refreshes and accidental clicks stop inflating the counter. Off by default, and it applies to new views only, so enabling it never rewrites history. |
| Keyboard shortcuts, playback speed, resume, and theater mode | Four optional player extras, all off by default and described in detail below. |

### Playback speed, keyboard shortcuts, resume, and theater mode

Four optional extras, all under `Appearance → Customize → Video Page → Player` and all **off by default**, so updating the theme changes nothing a visitor can see until you switch one on. Each is independent: you can offer the speed control without the keyboard shortcuts, or theater mode on its own.

| Option | What it does |
| --- | --- |
| Keyboard shortcuts on the video page | Adds the keys people already expect from a video site. Shortcuts never fire while a visitor is typing in a form field, and never take a keystroke away from a button or a link. |
| Playback speed control | Adds a speed button to the player, from 0.5× to 2×. The chosen speed is remembered in that visitor’s own browser and applied to every video they open. |
| Offer to resume where the visitor stopped | After someone has watched a little of a video, a bar offers to continue from where they left off on their next visit. |
| Theater mode | Widens the player across the page and dims everything around it, for watching without distractions. Also on the **T** key, and the choice is remembered. |

#### Keyboard shortcuts

| Key | Action |
| --- | --- |
| `Space` or `K` | Play and pause |
| `Left` / `Right` | Back or forward 5 seconds |
| `J` / `L` | Back or forward 10 seconds |
| `0` to `9` | Jump to that point in the video, so `5` is the halfway mark |
| `T` | Theater mode, which works whether or not the rest of the shortcuts are on |

> **Note**
>
> Shortcuts step aside completely while a visitor is typing in a search box, the comment form, or anywhere else that takes text, and they ignore `Ctrl` , `Cmd` , and `Alt` combinations so browser shortcuts keep working. A key pressed on a focused button still activates that button, because that is what someone pressing space on a focused button means.

Where the site runs the enhanced player, the arrow keys are left to it while it has focus, so one press never seeks twice. Where the site uses the native HTML5 player, the theme handles them itself and the set behaves identically either way.

#### Resume where you left off

The position is kept in the visitor’s own browser and is never sent to your server: no cookie, no database row, nothing added to any report. A few deliberate rules keep the offer from becoming an irritation.

- Under **30 seconds** in, there is no offer. Someone that early has barely started, and the bar would cover the very controls they are reaching for.
- Within the last **15 seconds** of the video there is no offer either, because that is the end, not somewhere to return to.
- **Finishing** a video clears the stored position, so it never offers to resume into the last frame.
- **Dismissing** the bar clears it too, so a visitor who chose to start again is not asked on their next visit.

Positions are stored per video, so watching three videos and coming back to each of them behaves the way you would expect.

#### Theater mode

Theater mode dims the page around the player rather than hiding it, and it deliberately does *not* lock scrolling: the description and comments below the video stay reachable, because a visitor who came to read the page as well as watch the video has been trapped rather than helped. The choice is remembered in that visitor’s browser, like the color scheme in 2.1.6, and nothing is stored on your server.

The player controls keep the same dark chrome in both color schemes, on purpose. A control that changed color with the site would flicker the moment someone switched skins mid-video, and the player is a black box either way.

> **Note**
>
> **For developers:** each of the four has a filter — `majestic_tube_player_hotkeys_enabled` , `majestic_tube_player_speed_enabled` , `majestic_tube_player_resume_enabled` , and `majestic_tube_player_theater_enabled` — plus `majestic_tube_player_speeds` to change the speed list. All are documented in the theme’s `inc/theme-options.php` .

### Recommended video delivery

- Use properly encoded MP4 files for the broadest browser support.
- Keep all resolution files accessible over HTTPS.
- Use clear, correctly sized thumbnails.
- Enter the real duration in seconds.
- Check the player after switching themes, updating WordPress, or changing a caching plugin.

**Accounts and community features**

## Members and Video Submissions

### Enable or disable membership

1. Go to `Appearance → Customize → Site Features → Accounts & Spam Protection`.
2. Find `Enable membership (login/register)`.
3. Choose **On** or **Off**.
4. Choose **Publish**.

When enabled, visitors can log in, register, and request a password reset. Logged-in members receive the My Account menu.

### Allow or prevent registration

WordPress controls whether registration is available. Go to `Settings → General` and review **Membership** and **New User Default Role**. For a moderated video site, use a cautious default role and review new accounts.

The login, registration, and submission forms also carry a required terms checkbox by default, which links your Terms of Use and Privacy Policy pages; see **Accepting the terms, and the cookie notice** under Legal and Policy Pages for the switch that prints it.

### Member menu

When membership is enabled, a signed-in member gets a **My Account** item in the Main Menu that opens a dropdown holding:

- **Submit a Video**
- **My Channel**
- **My Profile**
- **Logout**

These are account links, so they live under My Account rather than beside Home and the directory pages — the theme's own menu setup does not add Submit a Video or Profile as top-level items. A signed-out visitor sees a **Login** item in their place. Administrators can hide the Submit a Video, My Profile, and My Channel links in the Customizer.

### Edit a member profile

1. Sign in to the website.
2. Open `My Account → My Profile`.
3. Update the display name, names, email, website, or biography.
4. To change the password, enter the new password twice.
5. Choose **Save profile**.

### Submit a video from the front end

1. Sign in as a member.
2. Open `My Account → Submit a Video`.
3. Complete the title, description, playback source, duration, category, tags, and actors as needed.
4. Paste one thumbnail URL, or several — one URL per line — to rotate through while the pointer rests on the card.
5. Paste a preview video URL (mp4, webm, gif, or webp) when you would rather the card play that on hover than rotate thumbnails.
6. For duration, enter hours, minutes, and seconds using the three boxes.
7. Complete the verification when spam protection is enabled.
8. Choose **Submit video**.

New submissions are saved for moderation and do not appear publicly until an administrator publishes them.

A submission only needs the parts it has: one thumbnail URL, a list of them, or a preview video. When a list of thumbnails is submitted and the single Thumbnail URL box is left empty, the first URL in the list becomes the video's main thumbnail, which is what the card paints before the pointer reaches it. A preview video outranks the rotation thumbnails: a card with one plays its preview on hover instead of rotating through them.

### Configure required submission fields

Go to `Appearance → Customize → Site Features → Video Submission`. You can make the following fields required or optional:

- [ ] Video title
- [ ] Description
- [ ] Video URL
- [ ] Embed code
- [ ] Thumbnail URL
- [ ] Extra thumbnails
- [ ] Preview video
- [ ] Tags
- [ ] Actors
- [ ] Duration

### Set up Cloudflare Turnstile

Turnstile is Cloudflare’s free, privacy-friendly spam check. It asks less of a visitor than the puzzles it replaces, it is free at any traffic level, and it runs without sending visitors to a third-party page. It is the only spam-protection service this theme supports, and it is off until you switch it on.

1. Sign in to the Cloudflare dashboard and open `Turnstile` in the left-hand menu. An account is enough — the site does not have to be using Cloudflare as its host or DNS, and the free plan is all that is required.
2. Choose **Add widget**.
3. Name the widget after your site, for example *example.com sign-up*. The name is only for your own reference.
4. Set **Widget Mode** to **Managed**. Managed is the best default for a public sign-up form: Cloudflare decides when to issue an interactive challenge and stays quiet for most visitors who pass. **Non-interactive** never interrupts anyone, and **Invisible** is not used by this theme.
5. Add every hostname the widget will appear on. The form is protected on each host you list, so include `example.com` and, if your staging or development site is public, its hostname too. A hostname you leave out will be refused.
6. Choose **Create**.
7. Cloudflare shows a **Site Key** and a **Secret Key**. Copy both — the dialog will not show them again.
8. Open `Appearance → Customize → Site Features → Accounts & Spam Protection`.
9. Turn on **Enable spam protection**.
10. Paste the site key into **Turnstile site key** and the secret key into **Turnstile secret key**. Both are required: while either one is blank the theme leaves the forms alone rather than blocking every visitor.
11. Choose **Publish**.
12. Open your site in a private browser window, choose **Sign up**, and complete the challenge to confirm it works. Sign-up, password reset and video submission are the three protected forms.

> **Tip**
>
> **Before going live, use Cloudflare’s test keys.** Cloudflare publishes a pair that always passes and a pair that always fails, so you can prove the wiring without creating real challenges. Paste `1x00000000000000000000AA` as the site key and `1x0000000000000000000000000000000AA` as the secret key, and the widget will succeed every time. When you are ready, swap in the keys from your own widget.

> **Important**
>
> Keep the secret key private. Never paste it into a public page, post, widget, or any field marked as custom code — the theme sends it to Cloudflare from the server and it must never reach the browser.

#### How the theme uses Turnstile

Turnstile’s script is loaded only while spam protection is switched on and both keys are filled in, so a site with the feature off makes no request to Cloudflare at all.

- The widget’s token is sent to Cloudflare’s `siteverify` endpoint from the server, along with the visitor’s IP address. A token that does not verify stops the sign-up, the password reset or the submission.
- Tokens are good once. If a visitor fails for an unrelated reason — a username already taken, say — the theme clears the challenge and hands them a fresh one instead of leaving them with a form that can never succeed.
- The sign-up form sits in a modal, so the widget is mounted only once that panel is actually open. A challenge mounted into a hidden panel measures itself as zero and never recovers.
- Every form keeps the room the widget needs, whether protection is off, on, or still loading. The widget is 65px tall and arrives after the page has rendered, so the space is reserved up front: switching protection on never moves a submit button, and the widget landing never shifts the field beside it. The sign-up, login, password reset, comment and submission forms all reserve the same room; only sign-up, password reset and submission are guarded.

#### Turn protection off

Set **Enable spam protection** to **Off**. The widget disappears from every guarded form, the room it sat in stays reserved, and Cloudflare’s script stops loading; your Turnstile keys stay saved, so switching back on needs no work.

> **Important**
>
> Keep the secret key private. Never paste it into a public page, post, or widget.

**Theme preferences**

## Icons

The theme draws its own icons and ships them in the stylesheet. There is no icon font to load, nothing to keep up to date, and no plugin required.

Each icon is an SVG **mask**, so it paints in whatever color surrounds it. One shape works in the light skin, the dark skin, on the accent-colored buttons and on the muted metadata row, without a second copy of it for each.

They appear on the search button, the mobile menu toggle, the sort tabs, the video card and video page metadata, the like, dislike, share and report buttons, the category, actor and tag labels, the video tags list, pagination, and the back-to-top button.

### On the cards and in the player

A thumbnail badge used to be a bare number, so a grid read as a column of unexplained figures until someone worked out which was which. Each badge now carries the mark that says what it is — an eye for the view count, a clock for the length, a star for the rating — and the sidebar widget and the category directories are marked the same way. A card with no picture shows a frame above its "No image" caption rather than an empty rectangle that looked like a thumbnail still loading.

The player controls are the same idea applied to the chrome: the quality selector carries sliders, the speed control a dial, the theater toggle the expand mark the header menu already uses, and the resume pill a play and a close. Inside the quality and speed menus the current choice is ticked, and the tick is reserved on every row so the options stay in a column instead of the one you just picked shifting sideways.

These controls are built by `assets/js/main.js` rather than printed by a template, so they take their icons through their own allowlist and read the same **Show icons** switch through the localised options. With the switch off they fall back to text alone, and the dismiss button returns to a character in place of the close mark.

To turn icons off site-wide, set **Customize → Site Features → Header, Footer & Search → Show icons throughout the site** to **Off**. Everything falls back to the words alone, which is the right choice for a site that has been styling the old `fa fa-*` classes through a child theme.

A child theme can add one of its own:

```php
echo majestic_tube_get_icon( 'star' );               // the shape
echo majestic_tube_get_icon( 'heart', 'my-class' ); // with an extra class
```

An icon name the theme does not ship returns an empty string rather than an empty box, so a typo degrades to no icon instead of a gap. The full list is `majestic_tube_icon_names()`.

### Adding your own icons from a child theme

The shapes in `assets/css/main.css` are generated and replaced wholesale when the set is rebuilt, so a child theme cannot add to them by appending CSS — the next rebuild would take it away. Register the shape instead, and the theme generates the mask and the paint to match everything else:

```php
add_action( 'after_setup_theme', function () {
	majestic_tube_register_icons( array(
		// A name => SVG path data, on the same 24x24 grid as the theme's own.
		'brand-mark' => 'M12 2 2 22h20L12 2zm0 5 5 11H7l5-11z',

		// Or with your own viewBox, for a shape that is not square.
		'wordmark' => array(
			'path'    => 'M2 8h4v8H2V8zm6 0h4v8H8V8zm6 0h4v8h-4V8z',
			'viewbox' => '0 0 24 20',
		),
	) );
} );
```

Then print it like any other icon. It is sized, spaced and colored by the same rules, so it sits correctly beside a label without a line of CSS:

```php
echo majestic_tube_get_icon( 'brand-mark' );
echo majestic_tube_icon( 'wordmark', 'my-class' );
```

Registering a name the theme already ships **replaces that shape** everywhere it is used, including the legacy `fa fa-*` classes that point at the same masks. That is the supported way to restyle a mark you do not like:

```php
majestic_tube_register_icons( array( 'star' => 'M12 2 22 22H2L12 2z' ) );  // a solid star
```

**What is accepted.** A name must be lowercase, start with a letter, and contain only letters, digits and hyphens — the same convention as the theme's own names. The value is SVG path data, optionally with a `viewbox`. Nothing else registers, and anything rejected is skipped rather than fatal, so one bad shape does not stop a child theme from loading. A path is held to the characters path data is actually made of, because it ends up inside a CSS `url()`; a name or path that needs escaping into something else is refused.

The generated CSS is printed after `main.css`, which is what makes an override win. `majestic_tube_registered_icons()` reads the registry back if you need to know what is in it, and `majestic_tube_registered_icons_css()` returns the stylesheet on its own.

## Search appearance

Two sources of truth for one page title is worse than one, so the rule is simple: **an SEO plugin wins whenever one is active.** Yoast, Rank Math, All in One SEO, SEOPress, The SEO Framework and Slim SEO are all detected, and the theme stops describing pages the moment it sees one.

When no plugin is active, the theme describes the site itself, so a page is not left with a bare `<title>` and nothing else. That is `SEO & Analytics → Basic SEO`, and it is on by default:

| What the theme prints | Where it comes from |
| --- | --- |
| `<title>` | WordPress itself, from the theme's `title-tag` support. The theme filters the parts on the front page if you write a **Homepage search title**; it never prints a second title tag. |
| `<meta name="description">` | The excerpt, then the body text, then the site tagline. The homepage has its own field. |
| `<link rel="canonical">` | WordPress prints one on a video or page. The theme adds one on archive pages — including page 2, which canonicalises to itself rather than to page 1. Search results and 404s get none. |
| Open Graph and Twitter cards | Title, description, URL, type, site name, locale, and an image: the video's own picture on a video page, a term image on an archive, then your logo, then the Site Icon. |
| `robots` | WordPress, unchanged. |

What the theme has never owned, and still does not, is a title or description for an individual term or post: those are edited where the plugin provides them, or not at all.

Video structured data is the other exception, and it is described under [Video Structured Data](#video-structured-data) below. In short, the theme marks up each video as a `VideoObject` and publishes a video sitemap, because a video page is the one place the theme knows things a general-purpose SEO plugin has no way to read: the runtime, which file or embed the player is using, and who is in it. That module keeps working even while a plugin is running, because no plugin emits it.

### Labeling an adult site

Two optional tags in the same section declare what the site is, and both are off by default:

| Setting | What it prints |
| --- | --- |
| **Adult content rating tag** | `<meta name="rating" content="adult" />`, the value Google documents for explicit content. |
| **RTA label** | `<meta name="RATING" content="RTA-5042-1996-1400-1577-RTA" />`, the label Restricted To Adults prescribes and what parental-control and filtering software looks for. |

Google reads either value as the same explicit-content signal, which is why one of the two is enough for search: it asks SafeSearch to filter the site. Neither is a noindex directive — a page labeled this way can still be indexed, and it is `noindex` that keeps a page out of the index. Both are independent of the Basic SEO switch and of the plugin step-aside, because no SEO plugin emits them.

Nothing you saved in an earlier release is deleted when you upgrade. Settings from earlier releases stay in the database, unread. The one setting that is handed over rather than left behind is the browser-tab icon: an icon set in an earlier release is moved into WordPress's **Site Icon** on update, so the tab icon a site had is the one it keeps. If the image is hosted somewhere the site cannot reach, the dashboard says which address it was and points at the screen where the icon belongs.

## Video Structured Data

A video page can be ranked two different ways: as an ordinary web page, and as a video in Google Video search. The second one needs a `VideoObject` block, and it needs the details only a video theme holds — how long the video runs, whether it plays from your own file or someone else's embed, and who appears in it. That is what `SEO & Analytics → Video Structured Data` turns on.

| Setting | What it does |
| --- | --- |
| **Video structured data** | Prints the `VideoObject` block and the `og:video` tags on every video page, and adds a **Video SEO** column to the Videos list. |
| **Video sitemap** | Serves a video sitemap at `/video-sitemap.xml` and advertises it in `robots.txt`. |
| **Videos per sitemap file** | How many URLs go in one sitemap file. 1,000 by default. |

The two switches are independent, so you can keep the sitemap while turning off the in-page markup, or the other way round. Both default to on, and neither touches the title, description or canonical your SEO plugin prints.

**A `Video SEO` column appears on the Videos list.** It shows `OK` for a video with everything Google needs, `OK (embed)` for one that plays from a third-party player, or `Missing: duration, thumbnail, source` for a video that will be left out of the sitemap. A video missing any of those three is skipped rather than submitted half-finished, because a page with no markup can still rank as a web page, whereas a broken one is a quality signal.

**Submit the video sitemap separately.** The address is `/video-sitemap.xml`, and it is not covered by the XML sitemap index, so add it in Google Search Console as its own sitemap. A large library is split across numbered files under that same address.

**Embedded videos have a limit worth knowing about.** If your videos play from another domain's player, the page can still rank as a normal web page, but Google cannot count the video as part of your site's library, so it will not appear in Google Video results. Serving the file yourself, or embedding a player from your own domain, is the only way to qualify. The theme reports how much of your catalog is affected if you have WP-CLI available:

```
wp majestic-tube audit
```

It lists how many videos are submittable, which fields are missing, and which domains the embeds come from. It needs WP-CLI on the server; the **Video SEO** column shows the same information without it.

## What a page loads

A video site lives or dies on how fast the first screen appears, so the front end is thin and everything in it is conditional:

| Asset | When it is requested |
| --- | --- |
| `assets/css/main.css` | Every page. The theme's own `style.css` is never requested — it carries the theme header and no rules, so a child theme's `style.css` is the only one that is loaded. |
| `assets/css/fonts.css` and one `woff2` | Only while an option still selects the bundled Inter. Choosing the system font makes no font request at all. |
| `assets/js/main.js` | Every page, deferred, no jQuery. |
| `assets/css/player.css` and Video.js | Only on a video page using the enhanced player. |
| Cloudflare Turnstile | Only while spam protection is switched on with both keys filled in. |
| Core's emoji script and stylesheet | Never on the front end — see below. |

Beyond that:

- The **first card image on a listing**, or the **player poster on a video page**, is loaded eagerly and marked high priority, because that image is the one the browser scores as the largest contentful paint. Every later image stays lazy.
- The poster is announced with a `<link rel="preload">` from the head, so it starts downloading while the stylesheet is still arriving.
- WordPress's **speculative loading** is opted into (`wp_speculation_rules_configuration`), so a link is prefetched on hover. Forms and the like/report endpoints are POSTs, which the API never prefetches.
- Thumbnails are served from WordPress's own `srcset`, with a `sizes` attribute built from your per-row settings, so a phone downloads a phone-sized file.

**Emoji assets are not loaded.** WordPress normally prints an emoji stylesheet and a detection script on every page so that `:smile:` can be swapped for an image and so that emoji have a picture fallback on systems whose fonts lack them. Neither is worth two requests per page here: content is titles and categories, and a literal emoji renders from the visitor's own font. The admin screens keep their emoji. A site whose content genuinely depends on the fallback image can bring it back by returning `false` from the `majestic_tube_trim_emoji_assets` filter.

## Customizer Settings

Open `Appearance → Customize`. The settings are grouped into panels, and each panel holds one or more sections. Every setting is filed under the page it affects, so the homepage and the listing archives sit in one place and the single video page sits in another.

| Panel | Section | What you can control |
| --- | --- | --- |
| Homepage & Listings | Homepage | How the homepage sorts its videos, how many show per page and per row, the homepage title, and the popular tags bar under the sort bar. |
| Homepage & Listings | Homepage on Mobile | Videos per page and per row on phones and tablets. |
| Homepage & Listings | Category, Tag & Actor Archives | How many categories sit in a row, how many show per page on the Categories page, and how many actors show per page on the Actors page. |
| Video Page | Video Page Layout | Which of a post's two video fields the player uses (Video URL or embed code), the video sidebar, comments, breadcrumbs, the description block, categories, tags, actors, the outbound video button, the view, duration and rating displays, the Report video button, and related videos. |
| Video Page | Player | Autoplay, the player engine, the quality selector, view counting, and the optional keyboard shortcuts, speed, resume and theater controls. |
| Video Page | Sharing | The share buttons printed under the player. |
| Site Design | Colors & Typography | Light or dark skin, the header toggle, the accent color and the readable link, text and icon tones derived from it, whether to ignore the site background image, and the site font. |
| Site Design | Logo | An image or text logo, its font, size, dimensions and spacing, and a copy in the footer. |
| Site Design | Player Watermark | A logo overlaid on the player, with its size, color treatment and corner. |
| Site Design | Thumbnails | Thumbnail aspect ratio, image fit, image quality, and the hover rotation on video cards. |
| Site Features | Header, Footer & Search | The search bar, whether the theme draws its own icons, the number of footer columns, the copyright bar and its text, the admin bar, and the cookie notice with its wording. |
| Site Features | Accounts & Spam Protection | Member login and registration, the Cloudflare Turnstile spam check with its two keys, and the terms checkbox asked for on the login, registration and submission forms. |
| Site Features | Video Submission | The submission form, the links that lead to it, and which fields are required. |
| Advertising | Advertising | In-feed advertising, popunder and interstitial code, and the consent gate. Every other page area is managed from `Appearance → Widgets`. |
| Advertising | Friends & Ad Links | The row of outbound partner and ad text links printed under the header on the index page. The links themselves are a menu, under `Appearance → Menus`. |
| SEO & Analytics | Basic SEO | The theme's own search appearance — homepage search title and meta description, and the two optional content-rating labels. Switched off automatically while an SEO plugin is active. |
| SEO & Analytics | Homepage SEO | Where the visible homepage heading sits and the introduction printed once beneath it. |
| SEO & Analytics | Archive Page SEO | The line shown on each category and actor card, and whether the category and tag descriptions go above or below the list. |
| SEO & Analytics | Custom Code | Analytics code in the page head, extra scripts before the closing body tag, and scripts for mobile visitors only. |
| SEO & Analytics | Video Structured Data | The `VideoObject` markup on video pages, the video social tags, and the video sitemap. |

#### Homepage sort options

- **Latest** shows recently published videos first.
- **Most viewed** prioritizes view count.
- **Longest** prioritizes duration.
- **Popular** shows the most liked videos. While the site has no likes at all it falls back to view count, so the tab is never empty.
- **Random** changes the order on each visit.

#### Popular tags bar

- **Show the popular tags bar** switches the row of most-used tags on or off. It sits under the sort bar on the homepage and on actor pages.
- **Number of popular tags** sets how many tags the bar shows, from 5 to 50. The default is 20.

The bar is cached, and the cache key carries WordPress's own `last_changed` marker for tags, so a tag added, renamed or removed is picked up on the next request without the theme flushing anything by hand.

### Video Page → Player

- Enable or disable autoplay.
- Choose the enhanced player or the browser’s native player.
- Enable or disable the quality selector.
- Count a view only after playback starts, so a visit is only counted as a view once the video has actually played for three seconds.

### Site Design

- Enable a custom background.
- Choose the main accent color. The theme derives its readable link, text and icon tones from that one value for each skin.
- Set the site's default color scheme: Light, Dark, or Follow system.
- Optionally show a light/dark toggle in the header so visitors can switch.
- Pick the site font: the bundled Inter, or the visitor's own system font.
- Use an uploaded logo or a text logo with an icon.
- Adjust logo font, size, dimensions, and spacing.
- Optionally show the logo in the footer.
- Optionally place a logo watermark over the video player.

The browser-tab icon is not here: it is WordPress's own **Site Icon**, under `Appearance → Customize → Site Identity → Site Icon`. That is the only place it lives, so there is nothing to keep in sync. A browser-tab icon set in an earlier release is moved there for you when you update; if the image cannot be fetched, the dashboard tells you to upload it once instead.

#### Typography

The theme ships one font of its own: **Inter**, self-hosted in two subset files that weigh about 130 KB together. A page therefore makes no request to a third-party font host — no extra DNS lookup, no referrer leak, no cookie-consent question — and every visitor sees the same letterforms instead of Segoe UI on Windows, San Francisco on macOS and Roboto on Android. The variable file covers the whole regular-to-bold range in one download, and the Latin Extended subset is only fetched once a page really renders a character from it.

If the font file cannot be loaded — an offline reader, a blocked asset — the stack falls back to the operating system's own interface font, so the page is still readable. The **Site font** setting under *Appearance → Customize* switches the whole site to that operating-system font on purpose.

The text logo has its own choice: **Inter** (the default), **System UI**, **System serif** (Georgia, Times New Roman, Noto Serif), and **System monospace** (Menlo, Consolas, DejaVu Sans Mono). The three system voices are installed fonts rather than downloaded ones, so each looks slightly different per operating system — that is expected, not a glitch.

#### Color scheme and dark mode

Go to `Appearance → Customize → Site Design → Colors & Typography`. Two settings control the skin.

- **Color scheme** chooses what the site uses by default.
- **Show the light/dark toggle in the header** adds a small round button to the header so visitors can switch for themselves. It is off by default.

**Light** is the classic Majestic Tube surface. **Dark** is a near-black companion built for evening viewing: the same layout, the same accent, and retuned borders and text colors so nothing turns into unreadable gray-on-black — including the accent itself, which is brightened for the dark surface so a link reads as well on near-black as it does on white. **Follow system** renders whatever the visitor's own operating system asks for and needs no script and no stored value — the browser decides, and the page is correct before any of the theme's JavaScript runs.

The accent color, background, and every other control in the `Site Design` panel apply to both skins, so a dark site is the same site rather than a separate design.

#### Accent color

There is one accent to choose, and it is the color of the button fills, the active sort tab, the play badge over a thumbnail, the like and dislike buttons, the sort icons and every link. Everything else about it is derived, because one value cannot do every job well: a color dark enough to carry white button text is too dark to read as a link on a near-black page, and a color bright enough to read there is far too pale under white text.

So the theme works the shades out from the one color you pick and measures each against the surface it will be painted on:

- **The fill** keeps your color exactly, with white text on it where white is readable, and the theme's near-black ink where it is not.
- **Links, text and icons** get a tone deepened for the light skin until it clears 5:1, and brightened for the dark skin until it clears 7:1.
- **Hover states** step further away from their surface than the resting state, so hovering always means more contrast rather than less.
- **The player** takes a bright version of the same color, because its chrome stays black over video in both skins: a progress bar in the fill color would disappear over a bright frame.

Whatever you pick therefore stays consistent across the buttons, the links, the text and the icons in both skins, and stays readable — including a very pale or very dark choice, where the theme moves the shade rather than leaving unreadable text on the page. None of the derived shades are stored anywhere: change the accent and every tone moves with it.

**For developers:** the derived values are printed as CSS custom properties on `:root` by `inc/custom-code.php` — `--mt-accent`, `--mt-accent-fg`, `--mt-accent-hover`, `--mt-accent-text`, `--mt-accent-text-hover` and `--mt-accent-on-dark` — and each role is documented beside the tokens in `assets/css/main.css`. The `majestic_tube_brand_css` filter can add to or replace any declaration in that block.

> **Note**
>
> A visitor's choice is stored in their own browser and is never sent to the server. No cookie is set, nothing is written to the database, and a visitor who has never chosen anything simply gets the site default. Clearing site data, or opening a private window, returns them to the default — which is usually the right behavior.

With the toggle on, the button cycles **Light** and **Dark**. If the site default is **Follow system**, it cycles all three, so a visitor can leave it back on automatic. The button's label, tooltip, and screen-reader text all name the scheme it will switch to, so it stays usable without relying on the glyph.

A short script in the page head applies the stored choice before the first paint, so a dark-mode visitor never sees a flash of the light skin on the way in. The theme prints nothing for any of this while the toggle is switched off, whatever the site is set to: the button is the only place the choice is made or shown, so with no button there is nothing to honor.

A stored choice is recorded against the default it was made under, so changing **Color scheme** takes effect for everyone — including the browser that already had a choice stored. A choice made against the old default is dropped rather than left silently deciding what the page looks like; the visitor picks again in one click. The cycle the button steps through is built from the site's own scheme too, so a site set to **Follow system** keeps its third step after a visitor has used the button rather than losing it.

Where a browser refuses local storage — private browsing, a strict cookie policy, a partitioned frame — the script steps aside quietly and the site default stands, so the page is still readable.

> **Note**
>
> **For developers:** the `majestic_tube_color_scheme` filter changes the default scheme, and `majestic_tube_theme_toggle_enabled` decides whether the toggle is rendered. Both live in `inc/theme-options.php` .

### Video Page → Sharing

Enable sharing as a whole, then enable or disable the individual networks. The theme ships **X/Twitter**, **Reddit**, and **email**. Switch off the ones you do not want so the video page stays uncluttered.

Facebook, LinkedIn and Tumblr all restrict or remove adult content, and Odnoklassniki (ok.ru) is no longer reachable for an anonymous share, so none of the four prints a button here. Their settings are kept for compatibility but the toggles are hidden.

### Site Features → Video Submission

Enable or disable the front-end submission form, its navigation links, and each required field. The duration and title are required by default.

### Advertising

This section directs you to `Appearance → Widgets`, where header, player, below-player, video-sidebar, and footer content is managed. It also carries the advertising placements that the theme controls for you and that need no widget at all.

| Setting | What it does |
| --- | --- |
| Enable in-feed advertising | Inserts a card-sized content block into the video grid, between the video cards, instead of only in the areas you filled with widgets. |
| In-feed ad every N videos | How many video cards pass between two ad blocks. The default is 9 and a value below 3 is treated as 3, so short runs of videos never turn into a wall of ads. |
| In-feed ad code | The ad snippet placed in that block. Accepts a complete script, or several snippets separated by a blank line: one is picked per page and stays the same for the rest of the day, so a caching or SEO plugin never sees a different ad on every visit. |
| Enable popunder / interstitial code | Prints a snippet once per page from the end of the page, which is where a popunder or interstitial script belongs. |
| Popunder / interstitial code | The snippet itself, pasted exactly as your network supplies it. |
| Only load advertising after consent | Holds every advertising placement back until your consent solution reports that the visitor has accepted. |

#### Advertising and cookie consent

Advertising is unchanged by default. Turning on `Only load advertising after consent` makes the theme print nothing at all for any of its placements, in-feed, popunder, header, footer, under-player, video-sidebar, and player, until a cookie banner or consent management platform says the visitor has agreed. The theme never records or asks for consent itself, so the switch stays off until you connect a solution that does.

This switch is about the visitor's choice. Where the page itself is the question the rule is different, and it is always on: no placement of any kind prints on a legal page or beside the submission form, whether or not this switch is set. See [What a legal page does not carry](#what-a-legal-page-does-not-carry).

The theme can also print a cookie notice of its own: **Show a cookie notice** under `Site Features → Header, Footer & Search`. It tells visitors the site uses cookies and links your Terms of Use, Privacy Policy, and Cookie Policy pages. It records nothing on your server and does **not** hold advertising back — the switch above is what does that, and it still needs a consent solution to report the visitor's choice.

> **Note**
>
> Connecting a consent plugin is a one-line filter in a small snippet or a site-specific plugin. Let the consent plugin return `true` once advertising is allowed, and the theme will print its placements as normal. The filter is `majestic_tube_ads_allowed` and it receives the placement name as its second argument: in-feed, popunder, header, footer, under-player, video-sidebar, or player. It also works the other way round for a single placement, which is useful if one area should always be shown.

### Advertising → Friends & Ad Links

A row of outbound text links &mdash; partner sites, ad zones, anything outside your own catalog &mdash; printed under the header on the index page, and nowhere else.

The row appears on the site's index: the main listing, which is the page your front page shows when it shows the video grid. Every other request prints nothing &mdash; a single video page, a category, tag or actor archive, a search result, a legal document, and the submission form &mdash; so a partner link never lands beside the player, over a listing it has nothing to do with, or next to a policy or a form a visitor is being asked to accept. Whether the row prints at all is still the switch below, and it still needs an assigned menu with items in it.

The links are a real WordPress menu, so you add, remove and reorder them the usual way. This section only decides whether the row prints and how it looks.

| Setting | What it does |
| --- | --- |
| **Show the friends and ad links row** | Prints the row, on the index page. On by default, but it stays invisible until a menu is actually assigned. |
| **Row label** | An optional heading printed before the links, such as &ldquo;Friends&rdquo;. Leave it blank and the links print alone. |
| **Open links in a new tab** | Whether a link opens in a new tab. On by default. |
| **Row style** | How the row is decorated. See below. |
| **Show the emoji on each link** | Turns the preset&rsquo;s emoji on or off. |
| **Shine sweep on hover** | A highlight passes across a link as the pointer crosses it. |

#### Row styles

| Style | What you get |
| --- | --- |
| **Plain** | Text only. The quiet default. |
| **Stars and sparkles** | An emoji in front of each link, cycling through stars and sparkles. |
| **Rockets and shooting stars** | The same, with a rocket set. Links lift slightly on hover, as though launching. |
| **Glam** | Fire, crowns and jewels, with the heading in your accent color. |
| **Neon** | No emoji at all &mdash; the text itself takes a glow in your accent color. |

The emoji cycle through the preset&rsquo;s set rather than repeating one glyph, so a long row reads as decorated rather than stamped. The row label gets the first glyph of the same set.

**Two details worth knowing.** If you type your own emoji at the start of a menu label, the theme adds nothing in front of it &mdash; two icons on one link looks like a fault, not a flourish. And the neon style deliberately carries no emoji, because a color glow behind a color emoji reads as a rendering problem rather than as design.

The shine sweep, the icon tilt and the rocket lift are all switched off for visitors whose system asks for reduced motion. None of them carries meaning, so nothing is lost.

**To fill it with links**, open `Appearance → Menus`, create or pick a menu, tick **Friends & Ad Links** in the *Menu Locations* column, and add your items. Nothing appears until both the menu is assigned and the row has at least one item &mdash; an assigned but empty menu prints nothing at all, not an empty bordered strip.

Every link in this row is treated as advertising, and the theme adds `rel="sponsored nofollow noopener noreferrer"` to each one whether or not you set a rel yourself. That is not something the switch controls, and it cannot be turned off: the row exists for outbound and usually paid links, and none of it should pass ranking signal to the destination or read as an endorsement. The filter is scoped to this one menu location, so the Main Menu and Footer Legal Menu keep exactly the rel values you give them.

The row is a `nav` landmark labeled by the row label, or by &ldquo;Friends and partners&rdquo; when the label is blank, so it is announced properly rather than being read as a stray line of text.

### SEO & Analytics → Basic SEO

This is the search appearance the theme supplies by itself, for a site that runs no SEO plugin. All of it switches off the moment Yoast, Rank Math, All in One SEO, SEOPress, The SEO Framework or Slim SEO is detected.

| Setting | What it does |
| --- | --- |
| **Basic SEO** | Whether the theme prints its own description, archive canonical and social cards. On by default. |
| **Homepage search title** | What the browser tab and the search result say for the front page. Blank falls back to the site title. |
| **Homepage meta description** | The text under the front page title in a result, and on a share card. Blank falls back to the site tagline. It is deliberately separate from the visible homepage introduction, which a visitor reads. |
| **Adult content rating tag** | Adds the explicit-content rating tag Google documents. Off by default. |
| **RTA label** | Adds the Restricted To Adults label, verbatim. Off by default. |

The two labels are independent of the switch above, so they keep working if you later install an SEO plugin. Neither is a noindex directive: they ask SafeSearch to filter the site, they do not keep a page out of the index.

### SEO & Analytics → Homepage SEO

These controls set the visible homepage introduction and where it appears. The search title and meta description are under Basic SEO, or in your SEO plugin if you run one.

| Setting | What it does |
| --- | --- |
| **Homepage title position** | Whether the homepage title and its description sit above the video grid or below it. |
| **Homepage description** | A short visible introduction, printed once beneath the homepage heading. |

Above the grid is the default. Below the grid moves the heading and introduction together. Both positions print one heading and one introduction; duplicating a paragraph is not an automatic search benefit.

The visible homepage heading remains under `Customize → Homepage & Listings → Homepage`. It is meaningful page content, distinct from the browser/search title.

### SEO & Analytics → Archive Page SEO

Shared archive-card wording and description placement. A term's own name and description are edited on that term's own screen, beside its image; the archive's browser title and meta description come from Basic SEO, or from your SEO plugin if you run one.

| Setting | What it does |
| --- | --- |
| **Category card text** | The phrase under each category name on the Categories page. Blank falls back to each category’s own description. |
| **Actor card text** | The same for actor cards. Blank falls back to each actor’s own description. |
| **Category description position** | Whether a category’s description sits above its video list or below it. |
| **Tag description position** | The same choice for tag pages. |

The two card-text fields take `{name}`, `{description}`, `{count}` and `{videos}`, and default to `Free "{description}" videos` and `Watch "{description}" videos`.

### SEO & Analytics → Custom Code

Administrators can add analytics code to the page header and extra scripts near the end of the page. Paste complete snippets exactly as supplied by the service. Incorrect code can affect the entire site.

### Homepage & Listings → Homepage on Mobile

- Choose the number of videos per mobile page and per row.

The homepage has no widget area of its own, so there is nothing to hide on mobile. The theme's widget areas are the header strip, the player overlay, the strip below the player, the video sidebar, the footer, and the footer's own widget row.

**Navigation and optional content**

## Menus, Widgets, and Content Areas

### Main Menu

1. Go to `Appearance → Menus`.
2. Select the Main Menu or create it if necessary.
3. Add pages, custom links, categories, or actor archives.
4. Arrange the menu using drag and drop.
5. Choose **Save Menu**.

Keep legal pages in the footer rather than crowding the primary navigation.

### Friends & Ad Links menu

1. Go to `Appearance → Menus`.
2. Create a menu, or pick one you already have.
3. Add your partner sites and ad destinations as custom links.
4. In the **Menu Locations** column on the same screen, tick **Friends & Ad Links**.
5. Choose **Save Menu**.

That menu is then printed as a row of text links under the header on the index page, and nowhere else. Whether the row shows, and its heading, are set under `Appearance → Customize → Advertising → Friends & Ad Links`.

Keep it to outbound destinations. Anything that belongs in your own navigation is a better fit in the Main Menu above, and the theme marks every link in this row `sponsored nofollow` so it never passes ranking signal to the destination.

### Video list widget

1. Open `Appearance → Widgets`.
2. Find **Majestic Tube Videos**.
3. Drag it into a widget area.
4. Choose a title, sort order, and number of videos.
5. Choose **Save**.

Available sorting choices are Latest, Most viewed, and Random.

### Content Block widget

The **Content Block** widget is a general place for administrator-provided shortcodes and approved content markup. It can be targeted to all devices, desktop only, or mobile only.

Every area named **code and ads** accepts this widget and nothing else, so any ad tag, tracking snippet or raw embed goes in one of those. The single area named **Footer widgets** is the opposite: it accepts every standard widget, and that is where a link list, a menu or a small logo belongs. For a row of partner links directly under the header, use the **Friends & Ad Links** menu location instead.

| Area on the Widgets screen | Where it appears |
| --- | --- |
| Header: code and ads | Below the site header. Content Block widgets only. |
| Player overlay: 300x250 code and ads | A single 300x250 ad with a close button, centered over the desktop video player. Content Block widgets only. |
| Below player: code and ads | Below the individual video player unless the video has its own content. Content Block widgets only. |
| Video sidebar: code and ads | Beside an individual video when the sidebar setting is enabled. Content Block widgets only. |
| Footer: code and ads | At the very top of the site footer, above the footer widgets. Content Block widgets only. |
| Footer widgets | The column area at the bottom of the footer. Accepts every standard widget, so menus, link lists, friends links, text and images all go here. |

No area in this table appears on the theme's legal pages. A policy, a takedown procedure or a recordkeeping notice prints none of them, so nothing placed here — not even an ad tag — reaches those pages; see [Legal and Policy Pages](#legal-and-policy-pages). Your widget settings are untouched, so the same areas resume on every other page.

The placements the theme prints itself follow the same rule. The in-feed block, the popunder / interstitial snippet and a snippet set on an individual video are printed by the advertising module rather than by a widget area, and that rule is asked where the module prints — so no ad of any kind reaches a legal page or the submission form, whichever placement it came from. A placement added later is covered by the same check rather than by remembering to add one.

The same treatment covers the surfaces where a visitor is asked to accept something rather than to read something. While the video submission form is on the page, its Code and ads areas and Footer widgets columns are withheld exactly as they are on a legal page, and the login/registration panel and the theme's own cookie notice carry no area at all. See [Accepting the terms, and the cookie notice](#accepting-the-terms-and-the-cookie-notice).

> **Important**
>
> Content Block markup is intended for trusted administrators. Review all third-party embed and tracking snippets before activating them, and comply with the service’s terms and your privacy obligations.

**Visitor feedback**

## Video Reports and Moderation

### Enable reports

1. Open `Appearance → Customize → Video Page → Video Page Layout`.
2. Find `Enable “Report video” button`.
3. Choose **On**.
4. Publish the changes.

### Report reasons

- The video does not play
- Wrong video or thumbnail
- Spam or misleading
- Something else

Visitors may also include a short message. A visitor is asked not to report the same video again for 24 hours.

#### What a visitor's address is used for

Stopping one visitor from voting, viewing or reporting the same video several times needs to recognize a returning visitor, and nothing more. The theme stores a keyed fingerprint of the address — a one-way value derived with your site's own secret salt — next to a timestamp, never the address itself. Entries older than the 24-hour window are deleted, both on the next visit and by a daily housekeeping run, and each video keeps at most 500 of them.

That makes the value pseudonymous rather than anonymous, so it still counts as personal data under GDPR: mention it in your Privacy Policy, which the theme installs as a starter page. The fingerprint only means the same thing while your salts stay the same, so regenerating the salts in `wp-config.php` resets the stored history rather than leaving it readable. Sites behind a CDN or proxy should filter `majestic_tube_client_ip` to the header the proxy overwrites; without that, every visitor shares one address and the de-duplication treats them as a single person.

### Review reported videos

1. Go to `Videos → Reported Videos`.
2. Review the video, report count, most recent reason, optional message, and date.
3. Open the video to inspect it and decide whether to edit, unpublish, or remove it.
4. After resolving a report, use **Clear reports** on the reported video row when appropriate.

Editors may also see a reported-video summary on the WordPress dashboard.

**Policy and compliance**

## Legal and Policy Pages

Activation creates starter pages for:

- 18 U.S.C. § 2257
- DMCA
- Privacy Policy

### Bringing a deleted page back

If one of these pages is deleted, two things break: the footer link to it becomes dead, and the document is no longer published at all. The theme used to repair that only when a release advanced an internal version marker, so a page deleted between releases simply stayed gone.

There is now a control for it. Go to `Appearance → Themes → Majestic Tube` — the welcome screen — and scroll to **Legal pages**. It lists all three with a link to each, marks any that are **Missing**, and offers a button to recreate them.

A few things it deliberately does not do:

- **It only creates what is missing.** A page that exists is never touched, so anything you have written into it stays.
- **It is not automatic.** The theme repairs missing *references* — a menu item, a template assignment — on its own, where a missing target is unambiguous. A page is content, and someone who removed their 2257 page may have done it on purpose. So this waits for you to ask.
- **It re-links the footer menu.** Recreating the page is only half the job, so the repair also runs afterwards to point the footer legal menu back at the pages.
- **It tells you what it did.** If a page could not be created — most often because the database user is not allowed to create pages — the screen says so and names the page, rather than reporting a success that did not happen.

A page still sitting on the original `18-usc-2257` address counts as present, not missing, and a site using WordPress's own custom privacy page counts as having its privacy page.

The 2257 document is named **18 U.S.C. § 2257**, the way the statute is written: that is the title at the top of the policy and the label on the footer link to it. An update renames a page an earlier version created under its former name (`18 USC 2257`), and relabels the footer link that came with it. The rename is deliberately narrow — the page keeps its ID, its `18-usc-2257` address, its content, its author and its comments, and only its title and the theme's own link label change. It also only touches a name the theme itself shipped: a page you have named yourself keeps the name you gave it, and the theme still recognizes it as the same document rather than creating a second one beside it.

WordPress creates its own **Privacy Policy** page as a draft when a site is installed. The theme adopts that page rather than making a second one, and publishes it — on activation and again if the footer repair runs — because the footer legal menu links it, and a link to a draft is a link visitors cannot open. No duplicate is created.

### What a legal page does not carry

A legal page is a document a visitor may be shown, or asked to accept, on its own terms, so the theme keeps three things off it.

**No widget areas.** The 2257 page, the DMCA page, the privacy policy, and any page whose slug or title reads as one of those carry none of the Code and ads areas and no Footer widgets columns. Anything placed in those areas — an ad tag, a tracking script, a promo block — is withheld rather than hidden, so none of it reaches the page. What the theme prints itself is unchanged: the header, the menus, the footer legal links and the copyright line all appear as usual.

**No advertising of any kind.** The placements the theme controls itself — the in-feed block in the video grid, the popunder / interstitial snippet, a snippet set on an individual video, and the legacy ad fields an older version of the theme used — are all printed through one shared function, and that function asks the same question the areas ask. A legal page therefore carries no advertising from any placement, including one a child theme adds through the same helper. On every other page each of these placements answers to its own switch and code, exactly as it did before.

**No template notice.** Earlier versions ended each legal page with a bolded **Template notice** paragraph written for you, the administrator. It sat inside the page body, so a visitor reading the policy was told by the policy itself that it was a template. The pages this version creates do not carry it, and an update to this version removes it — and the horizontal rule that introduced it — from the pages an earlier version created, leaving the wording you wrote around it exactly as it is. A page you have renamed or moved so the theme no longer recognizes it is left alone; if you see the paragraph there, delete it by hand. The warning belongs in this document and on the welcome screen, not on a page your visitors read.

Neither change affects what the pages say: they remain starting points you are responsible for reviewing, not legal advice.

### Accepting the terms, and the cookie notice

Two more surfaces ask a visitor to accept something, and both are treated the way a legal page is: nothing an administrator placed in a Code and ads area appears beside the question, and neither does anything the theme prints itself — the in-feed block, the popunder / interstitial snippet or a per-video snippet. Advertising of any kind is withheld beside the question, not only the widget areas.

**The terms checkbox.** The login form, the registration form, and the front-end video submission form each carry a required checkbox naming the documents being accepted — *I have read and accept the Terms of Use and Privacy Policy.* It links the pages your site actually publishes: a Terms of Use page if you have one (`terms`, `terms-of-use`, `terms-and-conditions`, or `terms-of-service`), your Privacy Policy page, and a Cookie Policy page if you have one. A page still sitting in draft does not count, because a link to a page visitors cannot open is not something anyone can accept.

Turn the checkbox off, or back on, at `Appearance → Customize → Site Features → Accounts & Spam Protection` → **Ask visitors to accept the terms**. It is on by default — the theme asks for acceptance as soon as it gives a visitor something to accept — and it decides both halves of the rule: the checkbox is printed *and* a form submitted without it is refused. A site that publishes neither a terms page nor a privacy policy prints no checkbox and enforces nothing: the theme will not ask a visitor to accept a document that does not exist.

The checkbox is not decoration. The `required` attribute stops a browser from sending an empty form, and the handler asks again on the server, so a login, a registration or a video submission that arrives without the acceptance is refused with *Please accept the terms to continue.* The server check is the one that decides, because an attribute in the markup reaches only forms the browser built. The case you will notice is a page cached before you switched this on: the visitor submits, reads that sentence, reloads, and gets the form with the checkbox on it.

**The cookie notice.** Turn on **Show a cookie notice** at `Appearance → Customize → Site Features → Header, Footer & Search` and the theme prints a dismissible bar along the bottom of the page: your wording, or the sentence it ships, together with links to the same documents the checkbox names. Write your own words in **Cookie notice text** and the links are still printed beneath them.

The dismissal is kept in the visitor's own browser, in local storage, exactly like the color scheme choice. The notice sets no cookie, writes nothing to your database, and sends no choice to your server — so a visitor who clears their site data sees the bar once more, and where a browser refuses storage the bar simply never appears. It is off until you turn it on, because a cookie notice is your site's public copy and a theme update should not add any.

> **Note**
>
> The notice tells; it does not hold anything back, and it is not a consent management platform. To keep advertising off until a visitor has accepted, use `Only load advertising after consent` under Advertising with a solution that reports the choice back — see [Advertising and cookie consent](#advertising-and-cookie-consent).

### The 18 U.S.C. § 2257 page and which role your site is in

The starter 2257 page is written for a site that **distributes material produced by other people** — the posture this theme's video submission and membership features normally put an operator in. It describes the *secondary producer* role under 18 U.S.C. § 2257 and 28 C.F.R. part 75 in general terms, and it does not assert that your site holds any particular obligation.

It also does not decide the question for you, and the page says so. If you produce any of the material on your site yourself — filming, directing, commissioning, or otherwise originating it — the obligations attach to the **primary producer** instead, and the generic secondary-producer wording is not sufficient. Rewrite the page to match your actual position, or get counsel to do it.

Only new installs receive this wording. A site that was activated with an earlier version keeps the page it already has; edit it by hand to match.

### Placeholders that fill themselves

Two of the placeholders on all three pages are substituted for you at render time, so you do not need to edit them:

| Placeholder | Becomes |
| --- | --- |
| `[Site Name]` | The site title from `Settings → General`, escaped for output. |
| `[majestic_tube_default_email_link]` | A mailto link to the site’s WordPress administrative email. |

Because these are expanded when the page is displayed rather than written into it, renaming the site or changing the admin email updates all three legal pages at once — including the pages an earlier version of the theme already created. If the site title is left blank, the `[Site Name]` token stays visible rather than printing an empty name, so an unconfigured site is obvious instead of publishing “This site is operated by .” to the public.

Every other bracketed field — records custodian, postal address, telephone, mailing address, designated agent — is yours to complete.

### Review before publishing

1. Open `Pages`.
2. Open each legal page.
3. Replace every remaining placeholder with accurate operator, contact, address, and policy information.
4. Establish which 2257 role your site is in, and rewrite the page to match.
5. Review the page against your actual content, hosting, analytics, account, age-verification, and recordkeeping practices.
6. Ask qualified legal counsel to review the completed policies.

> **Important**
>
> The included pages are starting templates, not legal advice. They do not by themselves establish compliance with every law that may apply to your website, and the 2257 page specifically does not state which obligations apply to your site.

### Contact email

Built-in legal pages use the site’s WordPress administrative email. Keep that address current under `Settings → General`.

### Footer legal menu

The legal pages are normally kept separate from the Main Menu. Review the footer menu after editing page titles or slugs. If you intentionally use a different footer menu, make sure the legal links are still present.

The row is centered on the page, on the same axis as the copyright line below it, and stays centered whatever the site direction is. A long footer menu still wraps to a second line rather than scrolling sideways; the widget columns above it keep their own left-aligned text.

**Keep the site healthy**

## Routine Maintenance

### Recommended schedule

| Frequency | Tasks |
| --- | --- |
| Weekly | Review new videos, pending submissions, reported videos, comments, and member accounts. |
| Monthly | Check menus, links, thumbnails, player sources, contact email, password resets, and important pages. |
| Quarterly | Review memberships, administrator accounts, privacy practices, legal pages, and unused content blocks. |
| Before a major update | Back up the site, update WordPress and trusted plugins, test a video, login, submission, report, search, and email flow. |

### Recommended backups

Use a reliable WordPress backup solution or your hosting provider’s backup service. Protect both the database and the WordPress files. Confirm that backups can be restored before relying on them.

### Keep the admin area secure

- Use unique administrator passwords and strong member passwords.
- Keep WordPress, PHP, and trusted plugins updated.
- Limit administrator accounts.
- Require HTTPS.
- Review new registrations and submitted content.
- Do not share secret API keys, the Turnstile secret key, or tracking credentials.

### Translate the theme

Every visible string uses the `majestic-tube` text domain, and the theme loads that domain from the `languages` folder automatically. That folder ships with `majestic-tube.pot`, the template: the list of every string the theme asks to have translated, generated from the theme's own source and current for the version you installed. Copy it to your locale (for example `de_DE.majestic-tube.po`), translate the `msgstr` lines in the copy, compile it to `de_DE.majestic-tube.mo`, and place both files in the folder; WordPress then serves the translation based on the site language set under `Settings → General`. A translation in the folder wins over a language pack for the same locale, so a pack fills in whatever your file does not translate. See the `languages/README.md` file shipped with the theme for the full procedure.

**Solve common problems**

## Troubleshooting

#### A video page shows no player

- Confirm the video is published.
- Open the video in the WordPress editor and inspect **Video information**.
- Confirm there is one valid source: direct video URL, embed code, or shortcode.
- Open the direct video URL in a new tab and confirm it plays.
- Check that JavaScript is enabled and no security or caching plugin blocks the player.
- Save the video again after changing the source.

#### The quality selector is missing

- Enable `Enable the video quality selector`.
- Add more than one direct resolution URL.
- Check that the URLs are publicly reachable and use supported formats.
- Refresh the browser cache after changing the player option.

#### Thumbnail rotation is not working

- Enable `Enable thumbnails rotation on hover`.
- Confirm the video has valid image URLs in **Thumbnails (rotation)**.
- Check that the image host allows the browser to load the files.
- Test on desktop; touch devices do not provide a true hover action.

#### A trailer does not preview

Confirm the trailer URL is complete and publicly reachable. MP4 and WebM video trailers are supported for inline preview; GIF and WebP image trailers appear as overlays. A trailer takes priority over rotation thumbnails.

#### Registration is not available

- Confirm membership is enabled in the Customizer.
- Check `Settings → General → Membership`.
- Review any security, spam, or membership plugin restrictions.
- If spam protection is on, confirm both Turnstile keys are filled in and the challenge appears.
- Confirm the site’s hostname is listed in the widget’s allowed hostnames at Cloudflare. A hostname left out is refused, and the check can never pass.

#### Password reset email is not received

- Wait a few minutes and check spam or junk mail.
- Confirm the site can send email.
- Ask the hosting provider to check outbound email delivery.
- Use a legitimate SMTP service if your host’s mail system is unreliable.
- Confirm the site is using HTTPS.

#### Submitted videos do not appear

Front-end submissions are intentionally moderated. Open `Videos`, select the pending video, review it, and choose **Publish**. Also confirm that video submission is enabled and the member is logged in.

#### A directory or menu page returns not found

1. Confirm the page still exists under `Pages`.
2. Confirm the correct Page Template is selected in its Page Attributes panel.
3. Go to `Settings → Permalinks`.
4. Choose **Save Changes**.
5. Review the menu item and make sure it points to the current page.

#### The single-video sidebar is not visible

- Confirm `Show the video sidebar` is On.
- Open `Appearance → Widgets → Video sidebar: code and ads`.
- Add at least one widget and save.
- Remember that the sidebar belongs to individual video pages, not the homepage or normal archives.

## Frequently Asked Questions

### Do I need an SEO plugin for this theme?

No. `SEO & Analytics → Basic SEO` prints a meta description, a canonical on archive pages, and the Open Graph and Twitter cards a shared link needs, and it is on by default. Install a plugin only if you want more than that — per-post keywords, a score, redirects, a noindex switch per page — and the theme will step aside for it automatically rather than printing a competing tag.

What the theme does not do either way is set a title or description for an individual category, actor, tag, studio or series term. Those come from the plugin you install, or stay as the term's own name and description.

The theme also adds video structured data — a `VideoObject` block and a video sitemap — because a video theme is the only place that knows a video's runtime, source and cast. See [Video Structured Data](#video-structured-data). It keeps working while a plugin is running, and it is switched off in one click if your plugin already covers it.

### Can I use this on an existing WordPress site?

Yes. Back up the site first, install the theme, activate it, and preview the main pages before switching menus or publishing new content. Existing posts remain in WordPress, but some existing theme-specific menu and content settings may need to be assigned again.

### Are videos a separate content type?

No. They use WordPress’s normal Posts system, so familiar editing tools, revisions, search, categories, tags, comments, and scheduled publishing continue to work.

### Can a visitor submit a video without an account?

No. Front-end video submission requires the visitor to sign in.

### Does a new submission publish immediately?

No. It is submitted for moderation and remains pending until an administrator reviews and publishes it.

### Can I use a YouTube or Vimeo embed?

Use the provider’s current iframe embed code in **Video embed code**. Keep only one playback method for a video. Availability can also depend on the provider’s embedding permissions.

### Why is the quality selector absent?

The quality selector needs multiple direct video sources. Embed-based players use the controls provided by their external service.

### Why is autoplay silent?

Modern browsers generally block audible autoplay. The theme starts autoplay muted to comply with browser requirements.

### Can I hide or disable the views, duration, or rating?

Yes. Each display option is in the main Majestic Tube Customizer section.

### Can I show content below every video player?

Yes. Add a **Content Block** widget to **Below player: code and ads**. A video-specific value entered in the editor takes priority for that individual video.

### Can I target content to desktop or mobile?

Yes. Each Content Block widget can display on all devices, desktop only, or mobile only.

### Why is my in-feed ad not showing?

Check, in this order: `Enable in-feed advertising` is On, the `In-feed ad code` field holds the complete snippet, and the grid is long enough for the frequency to trigger, since a block is only inserted after a full run of videos. Also confirm that `Only load advertising after consent` is Off, or that your consent plugin allows the `in-feed` placement.

### My popunder opens on every page or not at all

The theme prints that snippet once per page from the end of the page, which is where such a script expects to be. Frequent opening is usually the network's own frequency or click limit rather than a theme setting. No popunder at all points to an empty field, the enable switch being Off, or a consent plugin that is withholding the `popunder` placement.

### How do I make my ads wait for a cookie banner?

Turn on `Only load advertising after consent`, then let your consent plugin return `true` through the `majestic_tube_ads_allowed` filter once the visitor has accepted. Until then, every placement prints nothing. Remember to clear any page cache afterwards, since cached HTML may still contain the earlier ad markup.

### Does the theme's own cookie notice hold ads back?

No. The notice is a sentence, a dismiss control, and links to your policy pages. It records nothing on your server and changes nothing about advertising. Advertising is held back by `Only load advertising after consent`, which needs a consent solution to report the visitor's choice.

### Should I count a view when someone only opens the page?

That is the default, and it matches the original theme. Turn on `Count a view only after playback starts` to record the view after three seconds of playback instead, which gives a much truer number. The switch affects new views only, so existing totals stay as they are.

### How often are view and rating numbers updated?

They update as visitors use the site. A browser, server, or caching configuration can delay the visible refresh; refreshing the video page normally shows the latest stored values.

### Why are there no keyboard shortcuts or speed control?

They are off until you turn them on, under `Appearance → Customize → Video Page → Player`. That is deliberate: a theme update should not start intercepting a visitor’s keystrokes or add buttons to a page they already got used to. Turn on the ones you want, one at a time or together.

### Do the resume position and theater mode store anything about my visitors?

No. The playback position, the chosen speed, and whether theater mode is on are all kept in the visitor’s own browser, exactly like the color scheme choice in 2.1.6. Nothing is sent to your server, no cookie is set, and nothing is written to the database or any report. Clearing site data, or opening a private window, simply returns a visitor to your defaults.

### Why is a password or report action unavailable?

Sign in when required, disable conflicting caching or security restrictions, and confirm JavaScript is enabled. A visitor may also be prevented from voting or reporting the same item again within the applicable time window.

### Can I have a dark site?

Yes. Set `Color scheme` to **Dark** under `Appearance → Customize → Site Design → Colors & Typography`. Set it to **Follow system** instead and each visitor gets the skin their own device already asks for, which is usually the friendliest default for a video site people watch at night.

To let visitors override it, also turn on `Show the light/dark toggle in the header`. Their choice is remembered per browser and never reaches your server, so it costs you nothing in stored data and reports no personal preference.

### Does the dark mode slow the page down?

No. Both skins live in the same stylesheet as a set of CSS custom properties, so switching is a single attribute change on the root element and costs no extra download. If you leave the site on Light and keep the toggle off, the theme prints no scheme script and no button at all.

**Support the project**

## Credits, License & Support

### Free theme license

Majestic Tube is a free theme. You may use, distribute, and modify it for your own or other websites, following the terms of the [GNU General Public License (GPL)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html). The complete license text is included in the theme as the **LICENSE** file.

### Support

Questions, bug reports, and feature requests are welcome at [prof@xxxpm.com](mailto:prof@xxxpm.com).

### Donate

Donations are entirely voluntary. They help keep the theme free and actively maintained, and they are never required to use it.

#### Donate with USDC

0x7a7118b7C0007705dc8e7fF0f02456a919130705

Send on the USDC network. Please double-check the address before sending.

#### Donate with Bitcoin

3K6yA17jLpDZutxxiNAY2a1SCtzXvHixH7

Send on the Bitcoin network. Please double-check the address before sending.

#### Buy the developer a coffee

[buymeacoffee.com/abdallahjcw](https://buymeacoffee.com/abdallahjcw)

A quick way to support ongoing maintenance and new features.

#### Included third-party components

- Video.js player — Apache License 2.0

Its license notice is bundled with the theme in the assets folder. No fonts are bundled: the theme uses the ones already installed on the visitor's device.

**At a glance**

## Quick Reference

| Task | Go to |
| --- | --- |
| Add a video | Videos → Add Video |
| Review submissions | Videos → All Videos → Pending |
| Add an actor | Actors → Add Actor |
| Add a category image | Videos → Video Categories → Edit Category |
| Review visitor reports | Videos → Reported Videos |
| Check a video's SEO data | Videos → All Videos, the **Video SEO** column |
| Submit videos to Google | `example.com/video-sitemap.xml` in Search Console |
| Change theme settings | Appearance → Customize |
| Manage navigation | Appearance → Menus |
| Manage content areas | Appearance → Widgets |
| Edit the homepage | Settings → Reading |
| Control registration | Settings → General |
| Edit a member profile | My Account → My Profile |
| Submit a video | My Account → Submit a Video |
| Edit legal pages | Pages |

Majestic Tube user guide · WordPress video theme · [prof@xxxpm.com](mailto:prof@xxxpm.com)
