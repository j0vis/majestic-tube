# Translations

Every user-facing string in Majestic Tube is wrapped in the `majestic-tube`
text domain. The domain is bound to this folder by `load_theme_textdomain()` in
`inc/theme-support.php`, so a compiled translation placed here is loaded
automatically on every request.

## Adding a translation

1. Generate the template from the theme source, for example with WP-CLI:

   ```
   wp i18n make-pot . majestic-tube.pot --domain=majestic-tube
   ```

2. Copy the template to the locale you want, for example
   `de_DE.majestic-tube.po`, translate it, and compile it:

   ```
   wp i18n make-json . --no-purge          # only needed for block scripts
   msgfmt de_DE.majestic-tube.po -o de_DE.majestic-tube.mo
   ```

3. Drop the resulting `.mo` next to the `.po` in this folder.

## Notes

- The `.pot` template itself is not committed; regenerate it when the strings
  change so translators always work from a current file.
- The locale is chosen by WordPress from the site's language setting, so no
  further configuration is needed per language.
