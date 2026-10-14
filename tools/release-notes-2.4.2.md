Icons on everything, drawn by the theme itself.

**Added**

- A site-wide icon set of 43 shapes, shipped in the theme's own stylesheet. No icon
  font, no plugin, no request &mdash; each one is a masked SVG that paints in whatever
  color surrounds it, so a single shape works in the light skin, the dark skin, on
  accent-colored buttons and on muted metadata
- Icons on the search button, the mobile menu toggle, the sort tabs, the video card and
  video page metadata, the like, dislike, share and report buttons, the category, actor
  and tag labels, the video tags list, pagination and the back-to-top button
- Icons on the card grid: an eye on the view count, a clock on the length and a star on
  the rating, so a badge says what it is without a legend. The sidebar widget and the
  category directories are marked the same way, and a card with no picture shows a
  frame above its caption instead of an empty rectangle that looked like a thumbnail
  still loading
- Icons on the player controls: sliders on the quality selector, a dial on the speed
  control, the expand mark on theater mode, and a play and a close on the resume pill
- A tick beside the current choice in the quality and speed menus. The tick is reserved
  on every row, so the options stay in a column rather than the one you just picked
  shifting sideways
- The quality menu now marks the source that is playing when it opens, which it did not
  do before
- `Customize &rarr; Site Features &rarr; Header, Footer &amp; Search &rarr; Show icons
  throughout the site`, on by default

**Changed**

- The theater button draws its mark from the shared set instead of a bordered rectangle
  drawn out of its own `::before`
- The resume pill's dismiss button uses the same close mark as the header menu, and
  keeps the old character only if you turn icons off
- The logo mark is drawn from the shared set rather than carrying its own copy of each
  path

**Kept**

- Turning the setting off returns every surface to words alone, which is the right
  choice for a site that has been styling the old `fa fa-*` classes through a child
  theme
- No icon font is requested, nothing is written to the database, and no existing
  setting moves. An icon name the theme does not ship returns nothing rather than an
  empty box, so a typo degrades to no icon instead of a gap
- A child theme can print one directly with `majestic_tube_get_icon( 'star' )`
- The skin toggle in the header still draws its three Unicode marks. It is the one
  control this release leaves alone, because sun, moon and monitor are not shapes the
  set has yet

**Fixed**

- A defect in the icon generator had silently removed 260 lines from the stylesheet,
  including the skin toggle and the base typography and header rules. The file still
  parsed and the braces still balanced, so nothing reported it

**Note**

A column of bare numbers in a grid makes everyone work out what each one counts, and a
toolbar of text-only buttons makes people read each one twice. Icons are the cheapest
fix for both, but only if they are the same shapes everywhere &mdash; which is why the
whole set is generated from one list and checked against the templates that use it,
rather than pasted in place by hand.

---

`majestic-tube.zip` &middot; 645,199 bytes
`SHA-256 9b7c8325ab1a9305283c81abd52b785568efa3147f80455c60db2a25446635fa`