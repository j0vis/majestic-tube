A dedicated row under the header for the sites you send traffic to.

**Added**

- A new **Friends & Ad Links** menu location, printed as its own row directly under the
  header, set apart from the widget area so it never reads as site navigation
- Every link in that row is force-tagged `rel="sponsored nofollow noopener noreferrer"`,
  plus `target="_blank"` when you want it, so exchanged or paid links cannot leak
  follow equity
- Five row styles &mdash; plain, stars, rockets, glam and neon &mdash; each with its own
  color treatment and per-link glyphs
- `Customize &rarr; Advertising &rarr; Friends & Ad Links`, with switches for the row
  itself, its label, opening in a new tab, the style, the icons, and the shine sweep

**Kept**

- The row prints nothing until you assign a menu to it, so an existing site looks
  exactly the same until you use it
- Only this menu is affected. Navigation menus, post links and footer links keep the
  attributes you set on them
- Nothing is written to the database, and no existing setting moves

**Note**

A strip of small gray links under the masthead reads as leftover clutter, which is why
the row ships with five looks instead of one. Pick `plain` if you want it as quiet as
possible, or `neon` if you want it to earn its place. Every animation respects
`prefers-reduced-motion`, so it disappears for visitors who have asked their system for
less motion.

---

`majestic-tube.zip` &middot; 632,651 bytes
`SHA-256 7aa6fb9e5515990f56ea8a85280d984f672846f461a57edba6da7569fbcaf9d3`
