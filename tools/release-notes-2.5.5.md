The related-videos list under a video follows the grid the rest of the site is set to, a comment
from a visitor who is not signed in is checked before it is accepted, and the settings panel
stops offering controls for the features that are switched off.

**Changed**

- The **related-videos list under a video follows your grid settings.** The list carried its own
  column count - the one written for tablets - from 783px up, whatever **Videos per row** said,
  so a site set to five across showed five cards on the homepage and three much larger ones
  under every video. The override is gone, and the list is drawn by the same rules as the
  homepage grid: five across on a five-across site, at the card size the rest of the site uses.
  On a video page the list sits beside the video sidebar, so it has a narrower column than the
  homepage and its cards are proportionally smaller - the count is the same, and the cards come
  from the same markup. Turn the single-page sidebar off and the two grids match to the pixel.
- **The comment form is guarded.** With Turnstile switched on, a visitor who is not signed in is
  asked to prove they are human before their comment is accepted, and the token is checked
  before the comment is written. Until now the form reserved the room for a challenge and never
  placed one, so a site running Turnstile collected comment spam through the one form the check
  did not cover. The widget prints inside the form, above the button, where the reserved room
  already was; no form moves, and nothing about the layout changes.
- **A control that does nothing is not shown.** Thirty-nine controls now wait for the switch they
  belong to: the watermark's image, width, color treatment and corner appear with the watermark;
  the eleven submission controls, and the link to the form, with video submission; the two member
  links with member accounts; the two Turnstile keys with the spam check; the three share services
  with sharing; the two homepage SEO fields with the theme's own SEO; the ad frequency and the
  consent gate with the placements they belong to; and the four logo fields that only the text
  logo uses stand down while an image logo is in charge. Turning a feature off shortens the
  section rather than leaving inert controls in it,
  and every hidden control comes back exactly where it was. What the panel shows changed; what
  your site does did not - a value that was already saved is kept and used either way.
- **Every section and every control says what it is for.** Each of the panel's twenty sections
  now opens with a line describing what its settings do, and which other section the adjacent
  ones live under, so a switch found in the wrong place is easier to place; thirteen of them had
  no line at all before. Beside that, twenty-nine controls that carried a label alone now explain
  what they do and what the values around them mean - the thumbnail size a card loads, the space
  a logo leaves, what the watermark's width is measured against.
- The **accent color's description reaches the screen**. A color control was built from two of
  the four values it was handed - the label and the section - so the description, and the order
  it was given, were dropped on the way. It is built from all four now, which is why the color
  section reads like the rest of the panel.

**Fixed**

- Every control that belongs to a feature **is hidden while that feature is off**. The switches
  that only apply to a switched-off feature were still offered, so a site with no image logo
  could set the image address, an empty watermark could be sized and colored, and a site with
  submission closed could mark fields as required.
- The **in-feed ad frequency** field accepts a number between 3 and 100 and says what it counts.
  A value below 3 still behaves like 3; the field no longer invites a number that cannot mean
  anything.
- The **watermark's maximum width** is a number field, like the logo's two, so it takes a value
  from a keyboard and a spinner rather than any text at all. At `0`, or left empty, the theme's
  own 200px is used - which is what the field did already, and what the panel now says.

**Upgrading**

Replace the theme folder. Nothing is reset and nothing is deleted. If you run Turnstile, your
comment form starts asking visitors who are not signed in to prove they are human, and a
comment posted without a token is refused. A signed-in commenter is never challenged: the
account is the check. Neither is an import, a plugin or a trackback, because none of them posts
through that form.

**Note**

The comment form was the one place the theme reserved a Turnstile widget and never mounted one.
The reserved room was deliberate - it keeps a form the same height whether the check is on or
off - but the reasoning stopped at the sign-up, password-reset and submission forms, and the
comment form kept the space without the check. It now does what the room was reserved for.

---

`majestic-tube.zip` &middot; 747,059 bytes
`SHA-256 1bc38c61041c66e4ce06b3cba44494322cc1ccf91360ed53d6fb4ca13208885b`
