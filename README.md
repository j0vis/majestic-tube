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
- [Troubleshooting](#troubleshooting)
- [FAQ](#faq)
- [Credits, license & support](#credits)

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

### Recommended video delivery

- Use properly encoded MP4 files for the broadest browser support.
- Keep all resolution files accessible over HTTPS.
- Use clear, correctly sized thumbnails.
- Enter the real duration in seconds.
- Check the player after switching themes, updating WordPress, or changing a caching plugin.

**Accounts and community features**

## Members and Video Submissions

### Enable or disable membership

1. Go to `Appearance → Customize → Majestic Tube Options`.
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
5. Complete the verification when reCAPTCHA is enabled.
6. Choose **Submit video**.

New submissions are saved for moderation and do not appear publicly until an administrator publishes them.

### Configure required submission fields

Go to `Appearance → Customize → Majestic Tube - Video Submission`. You can make the following fields required or optional:

- [ ] Video title
- [ ] Description
- [ ] Video URL
- [ ] Embed code
- [ ] Thumbnail URL
- [ ] Tags
- [ ] Actors
- [ ] Duration

### Set up reCAPTCHA

1. Create a reCAPTCHA v2 site in the service’s administration area.
2. Copy the site key and secret key.
3. Open `Appearance → Customize → Majestic Tube Options`.
4. Enable reCAPTCHA and enter both keys.
5. Publish the changes.
6. Test registration and video submission in a private browser window.

> **Important**
>
> Keep the secret key private. Never paste it into a public page, post, or widget.

**Theme preferences**

## Customizer Settings

Open `Appearance → Customize`. The Majestic Tube sections appear in the Customizer sidebar.

### Majestic Tube Options

This is the main section for listings, cards, video details, membership, footer behavior, and reports.

| Setting group | What you can control |
| --- | --- |
| Homepage and listing | Homepage sort, videos per page, videos per row, category columns, and desktop/mobile counts. |
| Video cards | Thumbnail aspect ratio, image fit, thumbnail quality, hover rotation, views, durations, and rating display. |
| Single video | Related videos, comments, breadcrumbs, search bar, description block, categories, tags, actors, and video sidebar. |
| Homepage presentation | Homepage title, description position, search bar, and whether long descriptions are shortened. |
| Tracking button | Show or hide the button, choose its icon, text, and destination URL. |
| Directories | Categories and actors per page, plus description position for category and tag archives. |
| Membership | Enable member login and registration, reCAPTCHA, account links, and the admin bar. |
| Footer | Enable the copyright bar, edit copyright text, and choose one to four columns. |
| Reporting | Enable the front-end Report video button. |

#### Homepage sort options

- **Latest** shows recently published videos first.
- **Most viewed** prioritizes view count.
- **Longest** prioritizes duration.
- **Popular** prioritizes the video rating percentage.
- **Random** changes the order on each visit.

### Majestic Tube - Video Player

- Enable or disable autoplay.
- Choose the enhanced player or the browser’s native player.
- Enable or disable the quality selector.

### Majestic Tube - Logo & Colours

- Enable a custom background.
- Choose the main accent colour.
- Use an uploaded logo or a text logo with an icon.
- Adjust logo font, size, dimensions, and spacing.
- Optionally show the logo in the footer.
- Optionally place a logo watermark over the video player.
- Set a favicon for browser tabs and bookmarks.

#### Typography

The theme ships no font files and makes no font request: the whole site is drawn in the font your operating system already uses for interfaces — Segoe UI on Windows, San Francisco on macOS and iOS, Cantarell or Ubuntu on Linux. That means the page is readable the instant it appears, and it looks native to each visitor's device instead of forcing one look on everyone.

The text logo is the one place you can pick a different voice, and the choices are the fonts your device already has: **System UI** (the same font as the rest of the site, the default), **System serif** (Georgia, Times New Roman, Noto Serif), and **System monospace** (Menlo, Consolas, DejaVu Sans Mono). Because these are installed fonts rather than downloaded ones, each choice looks slightly different per operating system — that is expected, not a glitch.

### Majestic Tube - Sharing & Social

Enable sharing as a whole, then enable or disable individual networks such as Facebook, X/Twitter, LinkedIn, Reddit, Tumblr, email, and the other available options. Remove unused buttons to keep the video page uncluttered.

### Majestic Tube - Video Submission

Enable or disable the front-end submission form, its navigation links, and each required field. The duration and title are required by default.

### Majestic Tube - Content areas

This section directs you to `Appearance → Widgets`, where header, player, below-player, video-sidebar, and footer content is managed.

### Majestic Tube - SEO & Social

- Add an optional Facebook app ID.
- Add an X/Twitter site handle.
- Paste search engine verification tags.
- Add optional SEO footer text.

Majestic Tube also supplies social preview information and video structured data on individual video pages. If a major SEO plugin is active, the theme normally avoids duplicating its social output.

### Majestic Tube - Custom Code

Administrators can add analytics code to the page header and extra scripts near the end of the page. Paste complete snippets exactly as supplied by the service. Incorrect code can affect the entire site.

### Majestic Tube - Mobile

- Choose the number of videos per mobile page and per row.
- Hide homepage widget areas on mobile if needed.
- Add optional scripts for mobile visitors only.

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

1. Open `Appearance → Customize → Majestic Tube Options`.
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
- Do not share secret API keys, reCAPTCHA secrets, or tracking credentials.

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
- If reCAPTCHA is enabled, confirm both keys are valid and the challenge appears.

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

### How often are view and rating numbers updated?

They update as visitors use the site. A browser, server, or caching configuration can delay the visible refresh; refreshing the video page normally shows the latest stored values.

### Why is a password or report action unavailable?

Sign in when required, disable conflicting caching or security restrictions, and confirm JavaScript is enabled. A visitor may also be prevented from voting or reporting the same item again within the applicable time window.

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
