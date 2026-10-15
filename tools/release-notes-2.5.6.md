The close box on the player overlay sits in the top right corner, where the controls an ad draws
for itself cannot reach it.

**Changed**

- The **close box on the player overlay is in the top right corner.** It was pinned to the start
  edge of the slot - the left on a page that reads left to right - and that is the corner an ad
  network draws its own controls in. An Exoclick creative puts its info link there, so the button
  sat on top of it: the visitor could not open the ad's own information, and the control read as
  part of the creative. The button is pinned to the right edge now, and its plate follows it, so
  the hairline and the rounded corner still face into the creative instead of hanging off the
  player's clipped corner. The slot keeps its full 300x250 and the button still sits over the
  creative rather than taking a row from it, so nothing about the overlay's size or position
  changes.

**Upgrading**

Replace the theme folder. Nothing is reset and nothing is deleted.

**Note**

The offset is a physical `right` rather than a logical inline-end, and that is deliberate: the
creative is a third-party ad frame whose own controls sit at its own left however the page reads,
so a logical offset would have walked this button back on top of them on a right-to-left site.

---

`majestic-tube.zip` &middot; 747,858 bytes
`SHA-256 13c99d0258986617552c13e95a026d81523ff60f81086e212b3f0ae99eacfad6`
