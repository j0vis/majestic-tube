A site that describes itself, and pages that carry no advertising.

**Added**

- **Basic SEO** (`SEO & Analytics &rarr; Basic SEO`), on by default: the theme's own meta
  description, a canonical on archive pages (including page 2, which canonicalises to itself
  rather than to page 1), and the Open Graph and Twitter cards a shared link needs, with the
  image taken from the video's own picture, then a term image, then your logo, then the Site
  Icon
- **Homepage search title** and **Homepage meta description**, so the front page can say
  something different in a result from what a visitor reads on it. It filters WordPress's own
  title parts; the theme never prints a second title tag
- The module switches itself off, completely, the moment Yoast, Rank Math, All in One SEO,
  SEOPress, The SEO Framework or Slim SEO is active, so a site that already runs one keeps
  exactly the search appearance it had
- **Adult content rating tag** and **RTA label**, two optional content-rating tags off by
  default. They are independent of the switch above and of the plugin step-aside, because no
  SEO plugin emits them. Neither keeps a page out of the index &mdash; it is `noindex` that
  does that
- A required **terms checkbox** on the login, registration and video submission forms, on by
  default. It names and links the documents your site actually publishes &mdash; a Terms of Use
  page, the Privacy Policy, and a Cookie Policy page if you have one &mdash; and the handler
  asks again on the server, so a form submitted without it is refused with *Please accept the
  terms to continue.* A site that publishes neither a terms page nor a privacy policy prints no
  checkbox and enforces nothing, because nobody can accept a document that does not exist.
  Switch: `Site Features &rarr; Accounts & Spam Protection &rarr; Ask visitors to accept the terms`
- An optional **cookie notice** along the bottom of the page, off by default, with your own
  wording. It links the same policy pages, keeps its dismissal in the visitor's own browser,
  records nothing on your server, and does not hold advertising back &mdash; the advertising
  consent switch still needs a consent solution to report the visitor's choice
- **Extra thumbnails** and a **preview video** on the submission form. A member can send one
  thumbnail URL, a list of them &mdash; one per line, rotated as the pointer rests on a card
  &mdash; or a preview video to play there instead. The first URL of a list becomes the video's
  main thumbnail, and two new switches make the list or the preview a required field
- **Accent color** derives the tones one value cannot serve: the fill keeps your color and
  picks white or the theme's near-black ink for the text on it by measuring contrast, links,
  text and icons are deepened for the light skin until they clear 5:1 and brightened for the
  dark skin until they clear 7:1, a hover steps further away from its surface than the resting
  state, and the player takes a bright version because its chrome stays black over video.
  Nothing derived is stored: change the accent and every tone moves with it
- `majestic_tube_register_icons()` for child themes, so a shape can be added or a shipped one
  replaced without editing the generated stylesheet. The generated CSS is printed after
  `main.css`, which is what makes an override win

**Changed**

- A legal page &mdash; the 2257 document, the DMCA page, the privacy policy, and any page whose
  slug or title reads as one of those &mdash; now carries none of the **Code and ads** areas and
  no footer widget columns, and no advertising from any placement either. The rule is asked in
  `majestic_tube_content_block()`, the one printer every placement ends up in, so the popunder,
  which had been opening over a policy page while every area on that page printed nothing, is
  covered without any placement having to remember to ask
- The submission page is treated the same way while the form is on it, and the login /
  registration panel and the cookie notice never contain a widget area
- The **18 U.S.C. § 2257** page is renamed in place, keeping its ID, its `18-usc-2257` address,
  its content, its author and its comments; only the title and the theme's own footer link
  label change. A page you renamed yourself keeps the name you gave it
- WordPress's own draft **Privacy Policy** page is adopted and published rather than duplicated,
  because the footer legal menu links it
- The bolded **Template notice** earlier versions ended each legal page with is removed on
  update. The wording you wrote around it is left exactly as it is
- The friends and ad links row prints on the **index page only** &mdash; not beside a player,
  over somebody else's listing, or next to a policy or a form
- The theme's own favicon setting is retired: the browser-tab icon is WordPress's **Site Icon**,
  under `Customize &rarr; Site Identity`, which is one place to keep rather than two
- Every form reserves the room the Turnstile widget needs whether protection is off, on or
  still loading, so switching it on never moves a submit button and the widget arriving never
  shifts the field beside it
- The first card image of a listing, or the player poster on a video page, is loaded eagerly,
  marked high priority and preloaded from the head, because that image is the one the browser
  scores as the largest contentful paint; every later image stays lazy
- Core's emoji detection script and stylesheet are no longer loaded on the front end. The admin
  screens keep them, and `majestic_tube_trim_emoji_assets` brings them back
- The footer legal row is centered on the same axis as the copyright line and wraps instead of
  scrolling sideways
- American spelling throughout the theme, its documentation and its comments

**Fixed**

- The moderation screen took the whole page down with it when its ten-minute cache named a
  video that had since been deleted or trashed
- A counter row written with no value &mdash; an interrupted import, a hand-edited row &mdash;
  raised `Undefined array key 0` on PHP 8, on a request that only meant to display a number
- The like endpoint reported a missing `post_id` twice, and the second check could never run
  because the first one had already answered
- Two exception messages escaped a post id that is an integer, which is how an escape function
  ends up inside a message rather than around it

**Upgrading**

Replace the theme folder; no setting is reset and nothing is deleted. A site that runs an SEO
plugin keeps that plugin's search appearance, because Basic SEO stands down the moment one is
detected. A site that runs none gets a description, an archive canonical and social cards it
did not have before, and can turn them off in the same section. The terms checkbox is on by
default and appears as soon as your site publishes a terms page or a privacy policy. If you had
set the theme's own favicon, set it once more as the WordPress **Site Icon**.

**Note**

An advertisement beside a policy a visitor is asked to accept is not a placement problem, it is
a contradiction: the page is asking for agreement and selling something at the same time. That
is why the rule is not a list of placements to exclude but one question &mdash; does this page
ask the visitor to accept something? &mdash; asked where the markup is made, by the code that
prints every placement and every widget area. A placement added later is covered by the same
answer, and a page added later that asks a question is covered too.

---

`majestic-tube.zip` &middot; 695,553 bytes
`SHA-256 2dead7c0f61f98fe0fa2b094be15d009240a8da5437dd453d814843cc520b8d8`
