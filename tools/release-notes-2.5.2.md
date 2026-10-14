A locked video stays locked, a broken term no longer takes the page with it, and a title
cannot break the data the theme prints for Google.

**Fixed**

- A **password-protected video** stayed behind its password only in name. The single-video
  template printed the player - and, for a self-hosted file, the direct file addresses -
  above the content, so a protected video handed its file to anyone who followed the link,
  password or no password. The template now stops before it reads any of that and shows
  WordPress's own password form instead, which is what the comments on the same video
  already did
- A video's **title can no longer break the page's structured data**. The VideoObject block
  printed for Google is JSON inside a `<script>` element, and a title carrying `</script>`
  ended that element: everything after the title was then read as page markup by every
  visitor. The block is encoded so no title, description or tag can end it, and the address
  of an embedded player is checked to be http or https before it is used
- A **category, tag or actor whose link cannot be built no longer stops the page.** The list
  under a video asks WordPress for each term's link, and that answer can be an error rather
  than a URL. The error was printed as if it were the address, which ends the whole request -
  one broken term, on one video, for every reader. Each term is checked now: one with no link
  is left out and the rest of the list prints
- The **counters only answer for videos a visitor can see.** The front-end endpoints behind
  views, likes, dislikes and reports took any post ID they were handed, including a draft, a
  pending submission and a private video - content the theme never shows on the front end
- The **password reset form is guarded** the way the sign-up form is: when the Turnstile keys
  are filled in, a reset request is checked before it is answered. The reply no longer says
  whether an account exists, and a lookup that finds no account counts toward the throttle
  like any other attempt
- The **rotation thumbnails box works.** Its **Add** and **Remove** buttons did nothing at
  all: the box handed out the endpoint, the nonce and the post ID, and no script read them.
  Both buttons do what they say now, and the list is written the moment a button is pressed
  rather than on publish
- The **date under a video title is a date again**, instead of the markup around it printed
  as text
- The **term image picker** writes its preview safely - an image address containing a quote
  could close the attribute it was written into and turn the rest of the address into markup -
  and copes with an image that has no thumbnail size, instead of leaving the picker open on a
  selection that never reached the field
- An **RSS item's featured image no longer errors** when the item is built without a post in
  the loop
- A saved **color scheme survives the update** if it was chosen before the site's default was
  recorded beside it, instead of being reset to the default on the next page load

**Upgrading**

Replace the theme folder. Nothing is reset and nothing is deleted.

**Note**

A post title is content the site owns. The theme prints it as written - the same thing
WordPress and its own themes do - so a title cannot carry markup in through the submission
form, and a title written by an importer is safest coming from an importer that sanitizes what
it writes.

---

`majestic-tube.zip` &middot; 717,300 bytes
`SHA-256 411d811c10a64bb77f305c131baa20e240f6f329c57a8c2e06c9b2d38b59d7d3`
