# Translations

Every user-facing string in Majestic Tube passes the `majestic-tube` text
domain, and the domain is bound to this folder by `load_theme_textdomain()`, so
a compiled translation placed here is loaded automatically on every request.

`majestic-tube.pot` is the template: the list of strings the theme asks to have
translated, in the format translation tools read. It is generated from the
theme's own source and regenerated when the strings change, so it always
describes the version you have installed.

## Translating the theme

1. Copy the template to the locale you want, for example
   `de_DE.majestic-tube.po`, and translate the `msgstr` lines in the copy. Any
   gettext editor reads it; so does a text editor.
2. Compile the copy to `de_DE.majestic-tube.mo`. That is the file WordPress
   actually loads. An editor that saves a `.po` usually writes the `.mo` beside
   it, and the gettext compiler does it from a command line if you have one.
3. Put both files in this folder.

The locale comes from the site's language setting under Settings → General, so
there is nothing else to configure. A file here is loaded before a language pack
for the same locale and wins for every string it translates, so a pack fills in
the gaps rather than replacing your work.

## About the template

- It is generated from the theme's source, never edited by hand. A hand-edit is
  a string that no longer exists in the theme, and the next regeneration drops
  it; change the string in the theme and the template follows.
- It carries no creation date and no source line numbers on purpose. Both change
  on edits that leave every string alone, and a template that churns on every
  edit is one nobody can review.
- A string with a context (`_x`, `_nx`) or a plural form (`_n`, `_nx`) is listed
  with it, so a translation can tell two uses of one word apart and can carry the
  language's own plural rules.
- The theme ships no compiled translation of its own. Any `.mo` here is yours or
  a language pack's.
