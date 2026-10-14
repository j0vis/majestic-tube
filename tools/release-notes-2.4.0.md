Video pages now describe themselves to Google Video search.

**Added**

- A schema.org `VideoObject` block on every video page, built from the runtime, the file
  or embed the player is actually using, and the actors taxonomy
- The `og:video` tags, plus `twitter:player` where an embeddable player exists
- A Google video sitemap at `/video-sitemap.xml`, advertised in `robots.txt` and split
  across numbered files so a large catalog stays inside the per-file limits
- A **Video SEO** column on `Videos &rarr; All Videos`, showing whether a video has what a
  sitemap entry needs
- `Customize &rarr; SEO &amp; Analytics &rarr; Video Structured Data`, with independent
  switches for the in-page markup and for the sitemap

**Kept**

- Titles, meta descriptions and canonicals are unchanged and still belong to your SEO plugin
- `og:image` is filled in only when the SEO plugin has not already set one
- Nothing is written to the database, and no existing setting moves

**Note**

A video played from another domain's player is marked up correctly, but Google cannot
count it as part of your site's own video library, so it will not appear in Google Video
results. Serving the file yourself, or embedding a player from your own domain, is the
only way to qualify. `wp majestic-tube audit` reports how much of a catalog is affected;
the **Video SEO** column shows the same thing in the dashboard.

---

`majestic-tube.zip` &middot; 625,553 bytes
`SHA-256 c6040af473b864d711b88f59c101a252eb6d6ad6ae83d7d0153094da9f76f56e`
