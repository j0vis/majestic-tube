# SEO tests

## Available in this checkout

```bash
php tests/seo-regression.php
```

This dependency-free suite loads the real theme modules with small WordPress/DB doubles. It checks template safety, output hooks, query arguments, pagination canonicals, ownership, permissions, previews, history and failed/changed index generations. It does **not** validate real MySQL queries or WordPress request handling.

## Real WordPress integration

The integration cases in `tests/seo-wordpress.php` require the official WordPress PHPUnit test library, PHPUnit and a **disposable test database**. They exercise actual taxonomy queries, numeric duration bounds, term head metadata, noindex sitemap exclusion, table installation and collection sitemap registration.

Use an existing WordPress test harness whose bootstrap loads `includes/bootstrap.php` from the WordPress test library, then target this file:

```bash
phpunit --bootstrap /path/to/isolated-wp-tests-bootstrap.php tests/seo-wordpress.php
```

Do not point the test harness at a live or production database. This checkout does not bundle PHPUnit, WordPress core, database credentials or a database server. The integration suite must be run in that environment before release.

The tests are development files; exclude `tests/` from theme distribution archives.
