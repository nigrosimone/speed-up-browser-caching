# Speed Up - Browser Caching

[![CI](https://github.com/nigrosimone/speed-up-browser-caching/actions/workflows/ci.yml/badge.svg)](https://github.com/nigrosimone/speed-up-browser-caching/actions/workflows/ci.yml)
[![WordPress plugin](https://img.shields.io/wordpress/plugin/v/speed-up-browser-caching.svg)](https://wordpress.org/plugins/speed-up-browser-caching/)
[![Active installs](https://img.shields.io/wordpress/plugin/installs/speed-up-browser-caching.svg)](https://wordpress.org/plugins/speed-up-browser-caching/)
[![Downloads](https://img.shields.io/wordpress/plugin/dt/speed-up-browser-caching.svg)](https://wordpress.org/plugins/speed-up-browser-caching/)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

A WordPress plugin that enables browser caching on Apache web servers, so returning
visitors download your static files once instead of on every page view.

**No configuration.** Install it, activate it, done.

📦 [Get it on WordPress.org](https://wordpress.org/plugins/speed-up-browser-caching/)

## What it does

Browsers can keep a local copy of files that rarely change — images, stylesheets, scripts,
fonts — instead of re-downloading them on every page view. They only do this when the
server tells them to, and most WordPress hosts don't send those instructions by default.

This plugin adds the missing Apache directives to your `.htaccess`:

| Directive | Apache module | Purpose |
| --- | --- | --- |
| `ExpiresActive` / `ExpiresByType` | `mod_expires` | How long each file type stays fresh — a year for CSS, JS, images, icons and fonts |
| `AddOutputFilterByType` | `mod_deflate` | Compresses text responses before sending them |
| `AddType` / `AddCharset` | `mod_mime` | Declares correct MIME types and UTF-8, so the rules above match the right files |
| `FileETag None` + `Header unset ETag` | `mod_headers` | Drops ETags, so browsers trust the expiry above instead of re-checking every file |

The result is fewer requests and less bandwidth on repeat visits, which also feeds into
PageSpeed and Core Web Vitals scores.

The directives are based on
[Apache Server Configs](https://github.com/h5bp/server-configs-apache) by the HTML5
Boilerplate project.

## How it works

On **activation** the plugin backs up your `.htaccess` to
`speed-up-backup-<timestamp>.htaccess` in the WordPress root, then prepends its own block:

```apache
# BEGIN SpeedUp_BrowserCaching
...directives from htaccess.txt...
# END SpeedUp_BrowserCaching

# BEGIN WordPress
...your existing rules, untouched...
# END WordPress
```

On **deactivation** it removes everything between its own markers and leaves the rest of
the file exactly as it was. Your existing rules are never rewritten, only surrounded.

## Requirements

- **Apache**, with `mod_expires`, `mod_headers`, `mod_deflate` and `mod_mime` available.
  Each block is wrapped in `<IfModule>`, so a missing module is skipped rather than
  causing a 500. On nginx or IIS the plugin has no effect: caching is configured in the
  server config there, not in `.htaccess`.
- A **writable `.htaccess`** in the WordPress root. If it isn't writable the plugin does
  nothing, rather than breaking the site.
- WordPress 3.5 or newer.

## Installation

From your dashboard: **Plugins → Add New**, search for *Speed Up - Browser Caching*,
then install and activate.

Manually: upload the `speed-up-browser-caching` folder to `/wp-content/plugins/` and
activate it from the **Plugins** menu.

## Development

The plugin itself is a single PHP file — everything else in this repository is tooling.

```bash
composer install        # also activates the git pre-commit hook

composer test           # PHPUnit
composer phpcs          # WordPress Coding Standards
composer phpcbf         # auto-fix coding style
composer lint           # php -l across every shipped file
composer compat         # PHP 7.0+ compatibility
composer check-version  # plugin header, Stable tag and changelog agree
```

Every check above also runs in CI on each pull request, across PHP 7.2 → 8.4, and all of
them block a merge. The one exception is WordPress Plugin Check: it needs Docker and npm,
so an infrastructure hiccup there must not fail a release.

A pre-commit hook runs the same checks against staged files only, in about a second.
`composer install` installs it for you. It's a convenience, not a guarantee — the
guarantee is CI, which can't be bypassed. Skip it once with `git commit --no-verify`.

The CI workflows and the helper commands are not defined here: they come from
[`nigrosimone/wp-plugin-ci`](https://github.com/nigrosimone/wp-plugin-ci), shared by every
`speed-up-*` plugin so there's one copy to maintain instead of eight. This repository only
declares which version of PHP to support and which findings are accepted.

### Releases

**GitHub is the source of truth. The WordPress.org SVN repository is a publishing target,
written only by CI — never edit it by hand.**

To publish a new version: **Actions → Prepare release → Run workflow**, filling in the
version, `Tested up to` and the changelog entries. The workflow opens a pull request with
the version bump; merging it runs every check again, tags the release, publishes to
WordPress.org and creates a GitHub Release. Nothing is published before that merge, so
closing the pull request cancels the release.

A weekly job compares the published SVN trunk against `main` and opens an issue if they
diverge — so a change made directly on SVN, for instance a fix from the WordPress.org
review team, can't go unnoticed.

### Accepted findings

The plugin edits `.htaccess` with native PHP file functions rather than `WP_Filesystem`.
That's the original 2016 implementation and replacing it is a refactor in its own right,
so those specific findings are declared as accepted in [`phpcs.xml.dist`](phpcs.xml.dist)
and under `ignore-codes` in [`ci.yml`](.github/workflows/ci.yml).

Keeping that list explicit is what keeps the checks useful: they stay green, so going red
means something *new* showed up. Entries come off the list as they get fixed.

## Repository layout

```text
speed-up-browser-caching.php   the plugin
htaccess.txt                   directives injected into the user's .htaccess
readme.txt                     WordPress.org readme — the source of the changelog
index.html                     empty file, prevents directory listing
.wordpress-org/                banner and icon for the WordPress.org page
.distignore                    what never ships to WordPress.org
tests/                         PHPUnit tests
.github/workflows/             thin callers into nigrosimone/wp-plugin-ci
```

`.githooks/` is not in the repository: `composer install` generates it from the shared
package, so the hook can't drift between plugins.

## Contributing

Bug reports and pull requests are welcome. Please make sure `composer test` and
`composer phpcs` pass — the pre-commit hook checks both for you.

## License

[GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html) — © Simone Nigro
