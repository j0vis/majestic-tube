# Majestic Tube

Everything you need to install, configure, populate, and manage a Majestic Tube video website. Follow the quick start first, then use the detailed sections as reference.

- **Video-first** - Multi-quality player, thumbnails, trailers, ratings, and related videos
- **Member friendly** - Front-end login, registration, profiles, and video submissions
- **Easy to customize** - Settings, branding, menus, and content areas use standard WordPress screens
- **Responsive** - Desktop and mobile layouts, including right-to-left support

**Contents**

- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [Dashboard guide](#dashboard)
- [Adding videos](#videos)
- [Thumbnail rotation](#thumbnails)
- [Actors and categories](#taxonomy)
- [Player settings](#player)
- [Members and submissions](#membership)
- [Customizer settings](#customizer)
- [Menus, widgets, and content](#appearance)
- [Reports and moderation](#reports)
- [Legal pages](#legal)
- [Routine maintenance](#maintenance)
- [What's new in 2.2.0](#whats-new-in-220)
- [Troubleshooting](#troubleshooting)
- [FAQ](#faq)
- [Credits, license & support](#credits)

**Release**

## What’s New in 2.2.0

This release reorganizes the Customizer, replaces the spam check with Cloudflare Turnstile, and makes imported videos display correctly.

### A Customizer you can navigate

The settings used to sit in nine flat sections with a catch-all bucket that mixed homepage settings with video-page settings. They are now grouped into panels, and every setting is filed under the page it actually affects. The two panels you will visit most are kept apart: **Homepage & Listings** and **Video Page**. The full map is in [Customizer Settings](#customizer-settings).

Nothing is lost in the move, and no setting is reset: the values stay exactly where they were stored. Only the labels and the layout around them changed.

### Cloudflare Turnstile replaces reCAPTCHA

Spam protection on sign-up and video submission is now handled by Cloudflare Turnstile, and reCAPTCHA has been removed. Turnstile is free at any traffic level and asks less of a visitor. Setup takes a few minutes and is covered in [Members and Video Submissions](#members-and-video-submissions).

### Imported videos display correctly

Two problems showed up on videos brought in by an importer, and both are fixed:

- **Ratings appeared as 0%.** Imported posts store their rating as a pair of values, which the theme was not reading, so every imported video showed no rating. The theme now reads them, and the first visitor vote builds on the imported score instead of replacing it with 100%.
- **Hover previews showed one broken image.** Extra preview images are stored as a single comma-separated value, while the theme read them as one URL per stored value. A comma is a legal URL character, so the whole string survived as one unusable address. Both storage shapes are now handled, including removing a single preview from the editor.

The 18 USC 2257 page is also now repaired automatically if it goes missing from the footer menu, so a deleted or renamed legal link comes back on its own.

> **Upgrading from 2.1.7**
>
> Replace the theme folder. Your settings carry over. If you were using reCAPTCHA, create a Turnstile widget and paste its two keys into `Appearance → Customize → Site Features → Accounts & Spam Protection` — the old reCAPTCHA keys are no longer used.

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
- Starter pages for **18 USC 2257**, **DMCA**, and **Privacy Policy**.
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
- [ ] Set the site title, tagline, logo, and main colour.
- [ ] Choose the homepage video sort and the number of videos displayed.
- [ ] Create video categories, tags, and actors.
- [ ] Add images to important categories and actors.
- [ ] Publish the first video and verify its player on desktop and mobile.
- [ ] Review the Main Menu and footer links.
- [ ] Configure member registration if you plan to accept submissions.
- [ ] Review and customize the legal pages for your actual operation.
- [ ] Test search, video reports, password reset, and email delivery.

**Know where everything is**

## Dashboard Guide

Majestic Tube uses familiar WordPress menus. The most important areas are listed below.

| WordPress area | What to do there |
| --- | --- |
| `Videos` | Add, edit, publish, search, and review videos. Video Categories, Video Tags, and Reported Videos are available here. |
| `Actors` | Create actor names and assign a portrait to each actor. |
| `Media` | Upload images and other media used by videos and site content. |
| `Pages` | Edit the automatically created submission, profile, directory, and legal pages. |
| `Appearance → Customize` | Control listings, the player, branding, sharing, submission rules, SEO fields, custom code, and mobile presentation. |
| `Appearance → Menus` | Manage the Main Menu and Footer Legal Menu. |
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
> Do not add both a direct video URL and a conflicting embed or shortcode. If more than one source is present, keep only the method you intend visitors to use.

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
| Advertising under the video player | Special content for this video only | Leave blank to use the general Below-player content area. |

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

### Browse categories, tags, and actors by letter

The Categories, Tags, and Actors pages all carry an alphabet bar above the listing. **All** shows the complete directory; each letter shows only the terms starting with it, along with how many there are. Only letters that actually have terms appear, so the bar never offers a letter that leads to an empty page.

Terms are filed under the first letter of their first word, so *AnnaBelle* appears under A. Names beginning with a digit are grouped under that digit, and a name beginning with punctuation is left out of the bar but still appears under **All**.

The letter is a query argument, so it survives pagination: `/tags/?letter=A` can be bookmarked and shared, and moving to page 2 keeps the filter. Choosing a letter resets to page 1.

Terms with no videos attached are left off the directory pages, and out of the alphabet bar's counts, so the two always agree. This is a change from the original theme, which listed them. It matters after a bulk import, which can leave a large number of unused terms behind; they made every directory page longer and linked to archives with nothing in them.

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

Theater mode dims the page around the player rather than hiding it, and it deliberately does *not* lock scrolling: the description and comments below the video stay reachable, because a visitor who came to read the page as well as watch the video has been trapped rather than helped. The choice is remembered in that visitor’s browser, like the colour scheme in 2.1.6, and nothing is stored on your server.

The player controls keep the same dark chrome in both colour schemes, on purpose. A control that changed colour with the site would flicker the moment someone switched skins mid-video, and the player is a black box either way.

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

### Member menu

When membership is enabled, the Main Menu includes:

- **My Account**
- **Submit a Video**
- **My Channel**
- **My Profile**
- **Logout**

Administrators can hide the Submit a Video, My Profile, and My Channel links in the Customizer.

### Edit a member profile

1. Sign in to the website.
2. Open `My Account → My Profile`.
3. Update the display name, names, email, website, or biography.
4. To change the password, enter the new password twice.
5. Choose **Save profile**.

### Submit a video from the front end

1. Sign in as a member.
2. Open `My Account → Submit a Video`.
3. Complete the title, description, playback source, thumbnail, duration, category, tags, and actors as needed.
4. For duration, enter hours, minutes, and seconds using the three boxes.
5. Complete the verification when spam protection is enabled.
6. Choose **Submit video**.

New submissions are saved for moderation and do not appear publicly until an administrator publishes them.

### Configure required submission fields

Go to `Appearance → Customize → Site Features → Video Submission`. You can make the following fields required or optional:

- [ ] Video title
- [ ] Description
- [ ] Video URL
- [ ] Embed code
- [ ] Thumbnail URL
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
12. Open your site in a private browser window, choose **Sign up**, and complete the challenge to confirm it works. Sign-up and video submission are the two protected forms.

> **Tip**
>
> **Before going live, use Cloudflare’s test keys.** Cloudflare publishes a pair that always passes and a pair that always fails, so you can prove the wiring without creating real challenges. Paste `1x00000000000000000000AA` as the site key and `1x0000000000000000000000000000000AA` as the secret key, and the widget will succeed every time. When you are ready, swap in the keys from your own widget.

> **Important**
>
> Keep the secret key private. Never paste it into a public page, post, widget, or any field marked as custom code — the theme sends it to Cloudflare from the server and it must never reach the browser.

#### How the theme uses Turnstile

Turnstile’s script is loaded only while spam protection is switched on and both keys are filled in, so a site with the feature off makes no request to Cloudflare at all.

- The widget’s token is sent to Cloudflare’s `siteverify` endpoint from the server, along with the visitor’s IP address. A token that does not verify stops the sign-up or the submission.
- Tokens are good once. If a visitor fails for an unrelated reason — a username already taken, say — the theme clears the challenge and hands them a fresh one instead of leaving them with a form that can never succeed.
- The sign-up form sits in a modal, so the widget is mounted only once that panel is actually open. A challenge mounted into a hidden panel measures itself as zero and never recovers.

#### Turn protection off

Set **Enable spam protection** to **Off**. The widget disappears from both forms and Cloudflare’s script stops loading; your Turnstile keys stay saved, so switching back on needs no work.

> **Important**
>
> Keep the secret key private. Never paste it into a public page, post, or widget.

**Theme preferences**

## Customizer Settings

Open `Appearance → Customize`. The settings are grouped into panels, and each panel holds one or more sections. Every setting is filed under the page it affects, so the homepage and the listing archives sit in one place and the single video page sits in another.

| Panel | Section | What you can control |
| --- | --- | --- |
| Homepage & Listings | Homepage | How the homepage sorts its videos, how many show per page and per row, and the homepage title and where it sits. |
| Homepage & Listings | Homepage on Mobile | Videos per page and per row on phones and tablets, and hiding the homepage widget areas on mobile. |
| Homepage & Listings | Category, Tag & Actor Archives | Videos per page on those archives, and whether the category and tag description goes above or below the list. |
| Video Page | Video Page Layout | The video sidebar, comments, breadcrumbs, the description block, categories, tags, actors, the tracking button, the view, duration and rating displays, the Report video button, and related videos. |
| Video Page | Player | Autoplay, the player engine, the quality selector, view counting, and the optional keyboard shortcuts, speed, resume and theater controls. |
| Video Page | Sharing | The share buttons printed under the player. |
| Site Design | Colours & Typography | Light or dark skin, the header toggle, the accent colour, the custom background class, and the site font. |
| Site Design | Logo | An image or text logo, its font, size, dimensions and spacing, a copy in the footer, and the favicon. |
| Site Design | Player Watermark | A logo overlaid on the player, with its size, colour treatment and corner. |
| Site Design | Thumbnails | Thumbnail aspect ratio, image fit, image quality, and the hover rotation on video cards. |
| Site Features | Header, Footer & Search | The search bar, the number of footer columns, the copyright bar and its text, and the admin bar. |
| Site Features | Accounts & Spam Protection | Member login and registration, and the Cloudflare Turnstile spam check with its two keys. |
| Site Features | Video Submission | The submission form, the links that lead to it, and which fields are required. |
| Advertising | Advertising | In-feed advertising, popunder and interstitial code, and the consent gate. Every other page area is managed from `Appearance → Widgets`. |
| SEO & Analytics | SEO & Social | The Facebook app ID, the X/Twitter handle, the playable card URL, verification tags, and SEO footer text. |
| SEO & Analytics | Custom Code | Analytics code in the page head, extra scripts before the closing body tag, and scripts for mobile visitors only. |

#### Homepage sort options

- **Latest** shows recently published videos first.
- **Most viewed** prioritizes view count.
- **Longest** prioritizes duration.
- **Popular** prioritizes the video rating percentage.
- **Random** changes the order on each visit.

### Video Page → Player

- Enable or disable autoplay.
- Choose the enhanced player or the browser’s native player.
- Enable or disable the quality selector.
- Count a view only after playback starts, so a visit is only counted as a view once the video has actually played for three seconds.

### Site Design

- Enable a custom background.
- Choose the main accent colour.
- Set the site's default colour scheme: Light, Dark, or Follow system.
- Optionally show a light/dark toggle in the header so visitors can switch.
- Pick the site font: the bundled Inter, or the visitor's own system font.
- Use an uploaded logo or a text logo with an icon.
- Adjust logo font, size, dimensions, and spacing.
- Optionally show the logo in the footer.
- Optionally place a logo watermark over the video player.
- Set a favicon for browser tabs and bookmarks.

#### Typography

The theme ships one font of its own: **Inter**, self-hosted in two subset files that weigh about 130 KB together. A page therefore makes no request to a third-party font host — no extra DNS lookup, no referrer leak, no cookie-consent question — and every visitor sees the same letterforms instead of Segoe UI on Windows, San Francisco on macOS and Roboto on Android. The variable file covers the whole regular-to-bold range in one download, and the Latin Extended subset is only fetched once a page really renders a character from it.

If the font file cannot be loaded — an offline reader, a blocked asset — the stack falls back to the operating system's own interface font, so the page is still readable. The **Site font** setting under *Appearance → Customize* switches the whole site to that operating-system font on purpose.

The text logo has its own choice: **Inter** (the default), **System UI**, **System serif** (Georgia, Times New Roman, Noto Serif), and **System monospace** (Menlo, Consolas, DejaVu Sans Mono). The three system voices are installed fonts rather than downloaded ones, so each looks slightly different per operating system — that is expected, not a glitch.

#### Colour scheme and dark mode

Go to `Appearance → Customize → Site Design → Colours & Typography`. Two settings control the skin.

- **Colour scheme** chooses what the site uses by default.
- **Show the light/dark toggle in the header** adds a small round button to the header so visitors can switch for themselves. It is off by default.

**Light** is the classic Majestic Tube surface. **Dark** is a near-black companion built for evening viewing: the same layout, the same accent, retuned borders and text colours so nothing turns into unreadable grey-on-black. **Follow system** renders whatever the visitor's own operating system asks for and needs no script and no stored value — the browser decides, and the page is correct before any of the theme's JavaScript runs.

The accent colour, background, and every other control in the `Site Design` panel apply to both skins, so a dark site is the same site rather than a separate design.

> **Note**
>
> A visitor's choice is stored in their own browser and is never sent to the server. No cookie is set, nothing is written to the database, and a visitor who has never chosen anything simply gets the site default. Clearing site data, or opening a private window, returns them to the default — which is usually the right behaviour.

With the toggle on, the button cycles **Light** and **Dark**. If the site default is **Follow system**, it cycles all three, so a visitor can leave it back on automatic. The button's label, tooltip, and screen-reader text all name the scheme it will switch to, so it stays usable without relying on the glyph.

A short script in the page head applies the stored choice before the first paint, so a dark-mode visitor never sees a flash of the light skin on the way in. On a site set to Light with the toggle switched off, the theme prints nothing for any of this. Where a browser refuses local storage — private browsing, a strict cookie policy, a partitioned frame — the script steps aside quietly and the site default stands, so the page is still readable.

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

> **Note**
>
> Connecting a consent plugin is a one-line filter in a small snippet or a site-specific plugin. Let the consent plugin return `true` once advertising is allowed, and the theme will print its placements as normal. The filter is `majestic_tube_ads_allowed` and it receives the placement name as its second argument: in-feed, popunder, header, footer, under-player, video-sidebar, or player. It also works the other way round for a single placement, which is useful if one area should always be shown.

### SEO & Analytics → SEO & Social

- Add an optional Facebook app ID.
- Add an X/Twitter site handle.
- Add an optional Twitter player URL, which upgrades the video preview card to the playable player card.
- Paste search engine verification tags.
- Add optional SEO footer text.

Majestic Tube also supplies social preview information and video structured data on individual video pages. If a major SEO plugin is active, the theme normally avoids duplicating its social output.

#### Playable cards on X and Twitter

By default a shared video link produces the large image card. To get the playable card, fill in `Twitter player URL base` with the HTTPS address of a small, bare page of your own that embeds a video and reads the `?post=` query argument. The theme appends the video ID for you, so a base of `https://example.com/player/` points that page at `https://example.com/player/?post=123` for video 123, and the card becomes playable on the timeline.

Leave the field empty to keep the large image card. The page you point at must be served over HTTPS, must return nothing but the player, and should stay under a few hundred kilobytes: X loads it in a card of roughly 435 pixels wide, so a full site template with a header, sidebar, and footer would look wrong inside the card.

### SEO & Analytics → Custom Code

Administrators can add analytics code to the page header and extra scripts near the end of the page. Paste complete snippets exactly as supplied by the service. Incorrect code can affect the entire site.

### Homepage & Listings → Homepage on Mobile

- Choose the number of videos per mobile page and per row.
- Hide homepage widget areas on mobile if needed.

**Navigation and optional content**

## Menus, Widgets, and Content Areas

### Main Menu

1. Go to `Appearance → Menus`.
2. Select the Main Menu or create it if necessary.
3. Add pages, custom links, categories, or actor archives.
4. Arrange the menu using drag and drop.
5. Choose **Save Menu**.

Keep legal pages in the footer rather than crowding the primary navigation.

### Video list widget

1. Open `Appearance → Widgets`.
2. Find **Majestic Tube Videos**.
3. Drag it into a widget area.
4. Choose a title, sort order, and number of videos.
5. Choose **Save**.

Available sorting choices are Latest, Most viewed, and Random.

### Content Block widget

The **Content Block** widget is a general place for administrator-provided shortcodes and approved content markup. It can be targeted to all devices, desktop only, or mobile only.

| Content area | Where it appears |
| --- | --- |
| Header content | Below the site header. |
| Player content | Over the desktop video player where supported. |
| Below-player content | Below the individual video player unless the video has its own content. |
| Video sidebar | Beside an individual video when the sidebar setting is enabled and the area contains content. |
| Footer content | At the top of the site footer. |

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

### Review reported videos

1. Go to `Videos → Reported Videos`.
2. Review the video, report count, most recent reason, optional message, and date.
3. Open the video to inspect it and decide whether to edit, unpublish, or remove it.
4. After resolving a report, use **Clear reports** on the reported video row when appropriate.

Editors may also see a reported-video summary on the WordPress dashboard.

**Policy and compliance**

## Legal and Policy Pages

Activation creates starter pages for:

- 18 USC 2257
- DMCA
- Privacy Policy

### Review before publishing

1. Open `Pages`.
2. Open each legal page.
3. Replace every placeholder with accurate operator, contact, address, and policy information.
4. Review the page against your actual content, hosting, analytics, account, age-verification, and recordkeeping practices.
5. Ask qualified legal counsel to review the completed policies.

> **Important**
>
> The included pages are starting templates, not legal advice. They do not by themselves establish compliance with every law that may apply to your website.

### Contact email

Built-in legal pages use the site’s WordPress administrative email. Keep that address current under `Settings → General`.

### Footer legal menu

The legal pages are normally kept separate from the Main Menu. Review the footer menu after editing page titles or slugs. If you intentionally use a different footer menu, make sure the legal links are still present.

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

Every visible string uses the `majestic-tube` text domain, and the theme loads that domain from the `languages` folder automatically. To add or update a translation, place the compiled `.mo` file for your locale in that folder; WordPress then serves it based on the site language set under `Settings → General`. Regenerate the `.pot` template from the theme source whenever the wording changes, so translators work from a current file. See the `languages/README.md` file shipped with the theme for the exact commands.

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
- Open `Appearance → Widgets → Video sidebar`.
- Add at least one widget and save.
- Remember that the sidebar belongs to individual video pages, not the homepage or normal archives.

#### Social previews are missing or duplicated

Review your active SEO plugins. Majestic Tube normally steps aside when a supported SEO plugin already provides social metadata. If the preview looks wrong, update the image, clear the social platform’s cache, and re-save the video.

**Common questions**

## Frequently Asked Questions

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

Yes. Add a **Content Block** widget to **Below-player content**. A video-specific value entered in the editor takes priority for that individual video.

### Can I target content to desktop or mobile?

Yes. Each Content Block widget can display on all devices, desktop only, or mobile only.

### Why is my in-feed ad not showing?

Check, in this order: `Enable in-feed advertising` is On, the `In-feed ad code` field holds the complete snippet, and the grid is long enough for the frequency to trigger, since a block is only inserted after a full run of videos. Also confirm that `Only load advertising after consent` is Off, or that your consent plugin allows the `in-feed` placement.

### My popunder opens on every page or not at all

The theme prints that snippet once per page from the end of the page, which is where such a script expects to be. Frequent opening is usually the network's own frequency or click limit rather than a theme setting. No popunder at all points to an empty field, the enable switch being Off, or a consent plugin that is withholding the `popunder` placement.

### How do I make my ads wait for a cookie banner?

Turn on `Only load advertising after consent`, then let your consent plugin return `true` through the `majestic_tube_ads_allowed` filter once the visitor has accepted. Until then, every placement prints nothing. Remember to clear any page cache afterwards, since cached HTML may still contain the earlier ad markup.

### My shared link shows an image card, not a playable one

Fill in `Twitter player URL base` with the HTTPS address of your own bare player page, and make sure that page really does embed a video when given `?post=`. Social networks cache their previews for a long time, so expect an existing post to keep its old card for a while.

### Should I count a view when someone only opens the page?

That is the default, and it matches the original theme. Turn on `Count a view only after playback starts` to record the view after three seconds of playback instead, which gives a much truer number. The switch affects new views only, so existing totals stay as they are.

### How often are view and rating numbers updated?

They update as visitors use the site. A browser, server, or caching configuration can delay the visible refresh; refreshing the video page normally shows the latest stored values.

### Why are there no keyboard shortcuts or speed control?

They are off until you turn them on, under `Appearance → Customize → Video Page → Player`. That is deliberate: a theme update should not start intercepting a visitor’s keystrokes or add buttons to a page they already got used to. Turn on the ones you want, one at a time or together.

### Do the resume position and theater mode store anything about my visitors?

No. The playback position, the chosen speed, and whether theater mode is on are all kept in the visitor’s own browser, exactly like the colour scheme choice in 2.1.6. Nothing is sent to your server, no cookie is set, and nothing is written to the database or any report. Clearing site data, or opening a private window, simply returns a visitor to your defaults.

### Why is a password or report action unavailable?

Sign in when required, disable conflicting caching or security restrictions, and confirm JavaScript is enabled. A visitor may also be prevented from voting or reporting the same item again within the applicable time window.

### Can I have a dark site?

Yes. Set `Colour scheme` to **Dark** under `Appearance → Customize → Site Design → Colours & Typography`. Set it to **Follow system** instead and each visitor gets the skin their own device already asks for, which is usually the friendliest default for a video site people watch at night.

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
| Change theme settings | Appearance → Customize |
| Manage navigation | Appearance → Menus |
| Manage content areas | Appearance → Widgets |
| Edit the homepage | Settings → Reading |
| Control registration | Settings → General |
| Edit a member profile | My Account → My Profile |
| Submit a video | My Account → Submit a Video |
| Edit legal pages | Pages |

Majestic Tube user guide · WordPress video theme · [prof@xxxpm.com](mailto:prof@xxxpm.com)
