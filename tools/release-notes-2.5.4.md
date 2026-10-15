A term whose name begins with a number is filed the same way on every PHP the theme supports, and
each responsive step states its own grid instead of leaving the layout to whichever rule comes
last.

**Fixed**

- A **term whose name begins with a number no longer lands in a different place depending on the
  site's PHP.** The Actors and Categories directories bucket every term under its first character,
  and a name like `7 Up` files under a digit - a key PHP keeps as the number 7 rather than as text.
  That map was sorted with PHP's own key comparison, and its answer changed between the versions
  the theme supports: 0-9 came after A-Z on PHP 7.4 through 8.1 and before it on 8.2 and later, so
  the same directory could read two ways on two servers. The keys are compared as strings now,
  which is the order the bar's own markup is written in.
- The **responsive steps state their own grid.** The tablet, phone and small-phone steps restated
  the column count and the gutter but left `display` to the stylesheet's opening rule, so what a
  step produced depended on which block came last. Each step declares the grid, the columns and the
  gutter together now. Nothing on the page changes; what changes is that a later edit can no longer
  take the layout away from the step it was written for.

**Upgrading**

Replace the theme folder. Nothing is reset and nothing is deleted.

---

`majestic-tube.zip` &middot; 738,974 bytes
`SHA-256 36700ad12e8c1ce74960f324ac0e64bf4cf0c6d887bfd74e948fb4541a897850`
