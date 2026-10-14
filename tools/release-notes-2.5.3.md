A random listing is drawn from a cache instead of sorting the whole catalog, a thumbnail is
resized once per upload instead of twice, and the theme ships the template a translation starts
from.

**Changed**

- A **random listing no longer reads and sorts your whole catalog.** The random listing, the
  **Video list** widget set to Random, and the picture a category or actor card borrows from a
  video all asked MySQL for `ORDER BY RAND()`, which reads every matching row and sorts it on
  every request - the most expensive query the theme made, on the pages that repeat it most.
  Each of them now draws from a pool of video IDs kept for the archive it belongs to. The pool
  is rebuilt when you publish, edit or delete a video, and the three surfaces share it, so a
  busy page costs one query for the sample rather than one sort of the archive.
- An **upload is resized once.** Every thumbnail size was registered twice: the original
  theme's `wpst_thumb_*` names sat beside the theme's own at identical dimensions, so WordPress
  wrote seven metadata entries for five files and encoded three of them a second time. The
  older names are answered from the attachment's own metadata now, which is where WordPress
  looks a size up and which can only point at a file that was really generated. The names still
  resolve for everything that asks for one, including the **Main thumbnail quality** setting and
  WP-Script plugins, and nothing in your media library has to be re-uploaded or regenerated.
- The **theme ships a translation template.** `languages/majestic-tube.pot` lists every string
  the theme asks to have translated, in the format a translation tool reads, with the contexts
  and plural forms the harder strings carry. Copy it to your locale, translate the copy,
  compile it and place the `.mo` beside it; three steps, described in that folder's own notes.

**Fixed**

- A **part of the video sitemap is built once and kept** until a video changes. It ran a
  `COUNT(*)` whose answer it never read, and rendered the whole chunk again on every request; a
  site with a persistent object cache now answers a crawl from memory. A site without one sees
  no change at all.
- A **share row with no address to share is not printed.** Each network's link carries the
  video's own permalink, so a video whose permalink cannot be built - trashed, still a draft,
  or a post type that never publishes - produced a row of buttons that shared whichever page
  the reader happened to be on.
- A **related-videos list no longer breaks on a taxonomy that answers with an error.** The term
  is left out of the query and the rest of the list prints.
- The **term image picker's strings** travel as one inline value rather than a localized script
  object, which drops a script WordPress loaded on every admin page for the sake of one
  screen.

**Upgrading**

Replace the theme folder. Nothing is reset and nothing is deleted.

**Note**

A random listing is a sample, not a shuffle of the catalog. The pool holds 500 IDs by default
and is taken at a random offset into the archive, so a page is drawn from part of the catalog
rather than from all of it, and the same video can appear twice across two pages. A site that
wants a wider sample raises it with the `majestic_tube_random_pool_size` filter, at the cost of
a larger query whenever the pool is rebuilt.

---

`majestic-tube.zip` &middot; 737,741 bytes
`SHA-256 f39ff1d40f33cf866627aed68292dcd17e29cb9a526e9352bb69576058443b69`
