The browser-tab icon an earlier release left behind is handed to WordPress, and the dashboard
says so when it cannot be.

**Fixed**

- A site that had set the theme's own **Browser tab icon** keeps its icon after updating. The
  stored address is moved into WordPress's **Site Icon** (`Appearance &rarr; Customize &rarr;
  Site Identity &rarr; Site Icon`) on the first admin page load after the update, because that
  is where the browser-tab icon lives now. An image the media library already holds is reused
  by reference; one hosted somewhere else is fetched once through WordPress's own importer and
  kept in the library; a stored value is never deleted, so an older release that downgrades
  still reads it
- A **Site Icon** you had already set is never overwritten. The icon you chose is not the
  theme's to replace, so the retired setting stands down rather than competing with it
- When the stored address cannot be moved &mdash; an address that no longer answers, or a
  `.ico` the media library refuses to keep as an image &mdash; the dashboard names the address
  and links the screen where the icon belongs, instead of the tab icon quietly disappearing.
  The warning is dismissible, and comes back only if the address changes
- The move runs on an admin request from a user who could set a Site Icon by hand, and never
  while a visitor is loading a page: it writes an option and it may fetch a file, and neither
  belongs on the front end. A value that could not be moved is not fetched again on every page
  load either

**Upgrading**

Replace the theme folder. Nothing is reset and nothing is deleted. If you had set the theme's
own favicon, version 2.5.0 asked you to set it once more as the **Site Icon** by hand; 2.5.1
does it for you on the next admin page load. If the image could not be fetched, the dashboard
tells you which address it was, so you can upload that file once under `Appearance &rarr;
Customize &rarr; Site Identity &rarr; Site Icon`.

**Note**

Retiring the theme's own favicon field in 2.5.0 was the right call and an incomplete one: it
removed a second answer to the browser-tab icon without answering for the sites that had used
it. A theme update is not a place to take something away silently, so the value is handed over
rather than dropped, and the one case that cannot be finished automatically is reported where
an administrator will see it. Nothing about the move is destructive: the stored setting stays
in the database, the media library only ever gains an image, and a Site Icon you set yourself
is left alone.

---

`majestic-tube.zip` &middot; 700,088 bytes
`SHA-256 0e75996d99f906717d6f6fcdb3979df21d6eca53c28ff02cccd91e299d90caea`
