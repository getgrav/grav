# v2.0.0-rc.3
## 05/11/2026

1. [](#bugfix)
    * Fixed `bin/gpm` commands silently exiting with no error on a fresh Grav 2.0 + Admin install before any user accounts had been created ([#4079](https://github.com/getgrav/grav/issues/4079)).

# v2.0.0-rc.2
## 05/08/2026

1. [](#improved)
    * Hardened the Twig `read_file()` function with strict allow-lists for streams, file extensions, and a configurable max file size, all tunable under `security.read_file.*` and surfaced in the admin under Configuration → Security ([grav-premium-issues#573](https://github.com/getgrav/grav-premium-issues/issues/573)).
    * All hardcoded English in core blueprints now uses `PLUGIN_ADMIN.*` translation keys, so admin labels and help text translate correctly even without admin classic installed.
1. [](#bugfix)
    * [security] Closed a Twig sandbox hole that let editor-role users dump plugin secrets like SMTP passwords, API tokens, and OAuth keys via `config.toArray()` (GHSA-j274-39qw-32c9).
    * Restored `addLoader()` on the Twig loader so plugins that extend Twig with their own loaders (including Admin) no longer crash on Grav 2.
    * Fixed a typo in the Page Permissions section header on per-page security tabs.

# v2.0.0-rc.1
## 05/04/2026

1. [](#new)
    * New `system.pages.order_digits` setting (default `2`) lets sites that use 3- or 4-digit folder prefixes (e.g. `005.about`) set the width once and have all admin and API page operations honor it.
1. [](#bugfix)
    * Editing and saving a page no longer rewrites its folder prefix to a different width, which previously turned `005.about` into `05.about` and produced a duplicate page under flex pages ([grav-plugin-admin#2492](https://github.com/getgrav/grav-plugin-admin/issues/2492)).

# v2.0.0-beta.4
## 04/29/2026

1. [](#bugfix)
    * [security] Extended default `uploads_dangerous_extensions` to include `md`, `yaml`, `yml`, `json`, `twig`, and `ini`, blocking page-content extensions from being uploaded via permissive form `accept` policies (GHSA-w4rc-p66m-x6qq).

# v2.0.0-beta.3
## 04/28/2026

1. [](#improved)
    * Retired the legacy `security.twig_filter.*` regex pre-filter for editor-authored Twig; the Twig sandbox introduced in beta.2 is now the sole SSTI protection, toggleable via `security.twig_sandbox.enabled`, with no upgrade action needed.

# v2.0.0-beta.2
## 04/25/2026

1. [](#new)
    * **NEW** Twig content sandbox so editor-authored page content renders inside an allowlist-based Twig sandbox, blocking SSTI attacks while leaving theme templates unaffected.
    * **NEW** Dedicated `logs/security.log` that records every blocked Twig expression with the page route and a hint pointing at the setting to change.
    * **NEW** "Twig Sandbox" section under Admin → Configuration → Security with toggles and editable allowlists for tags, filters, functions, methods, and properties.
1. [](#improved)
    * Smarter dangerous-Twig filter that no longer flags safe expressions like `{{ page.header.user.mail }}` for containing a substring that matches a dangerous function name.
    * Sandbox violations now soft-fail so the rest of the page still renders, with visitors seeing a small placeholder and admins seeing a pointer to the log entry.
    * The sandbox can be disabled from the admin UI or YAML if a site genuinely needs the unrestricted behaviour.
1. [](#bugfix)
    * [security] Fixed unauthenticated path traversal in `FormFlash` so the `__form-flash-id` parameter can no longer be used to create arbitrary directories on disk (GHSA-hmcx-ch82-3fv2).
    * [security] Moved the HMAC key for CSRF nonces and admin rate-limit hashing out of Config and into `user/config/security-private.php` so it can no longer leak through sandboxed Twig; existing sessions and nonces survive the upgrade (GHSA-3f29-pqwf-v4j4).
    * [security] Hardened the new-user uniqueness check so a low-privileged user with `admin.users.create` can no longer disrupt a super-admin account by reusing its username on the add-user form (GHSA-rr73-568v-28f8).
    * [security] Added HMAC integrity to file-based cache entries so tampered or attacker-planted cache files are treated as misses and removed instead of being deserialized; existing caches rebuild transparently on first read (GHSA-gwfr-jfjf-92vv).
    * [security] Closed a five-part advisory covering tampered job queues, forged session flash payloads, shell-injection in `bin/gpm install` git arguments, and additional Twig callables added to the dangerous-Twig blocklist (GHSA-vj3m-2g9h-vm4p).
    * [security] Tightened the XSS detector's `on*` event-handler regex so attributes without quotes or whitespace around `=` (e.g. `<img onerror=alert(1)>`) are no longer missed (GHSA-9695-8fr9-hw5q, GHSA-c2q3-p4jr-c55f, GHSA-w8cg-7jcj-4vv2).
    * [security] Added `svg`, `math`, `option`, and `select` to default `security.xss_dangerous_tags` to block XML-namespace inline scripting and the select-context escape used against admin form templates (GHSA-w8cg-7jcj-4vv2, GHSA-c2q3-p4jr-c55f).
    * [security] Markdown images can no longer inject event-handler attributes via `?attribute=onload,…` query strings; attribute names now pass a strict identifier check and denylist (GHSA-r7fx-8g49-7hhr).
    * [security] Hardened SVG dimension reading against XXE and billion-laughs attacks by stripping DOCTYPE/ENTITY declarations before parse and parsing with `LIBXML_NONET` (GHSA-3446-6mgw-f79p).
    * [security] `Installer::unZip` now refuses Zip Slip archive entries (paths containing `..`, absolute paths, Windows drive letters, or NUL bytes) before extraction (GHSA-w48r-jppp-rcfw).

# v2.0.0-beta.1
## 04/16/2026

1. [](#new)
    * Rebranded 1.8-beta as 2.0-beta.
    * **NEW** Quark2 theme for Grav 2.0.
    * **NEW** Migrate Grav plugin required to upgrade from 1.x to 2.0.
    * **NEW** API plugin now required to support Admin 2.0.
    * **NEW** Admin 2.0 is the new default admin for Grav 2.0.
    * Switched from Markdown Notices to GitHub Markdown Alerts.

# v1.8.0-beta.30
## 04/15/2026

1. [](#new)
    * Added family-aware GPM upgrade gate: blocks cross-major.minor auto-upgrades (e.g. 1.8 → 2.0) and points users at the `migrate-grav` plugin
    * Added `next_major` hint surfaced from the remote GPM feed so admin/CLI can display upcoming major-version availability
    * Added compatibility blueprint support for major-version upgrade gating
    * Added `media://` stream for a site-level media directory
    * Added fast static asset serving for plugin-bundled SPA apps
    * Added `onFlexDirectoryConfigBeforeSave` event
    * Added `cache-cleanup` CLI command
    * Added `-v` verbose flag to `yamllinter` command
1. [](#improved)
    * Moved media config blueprint and translations from admin plugin to core
    * `yamllinter` now uses Grav's built-in YAML parser for more detailed errors
    * More readable date/time output in `LogViewerCommand` (#4007, #4009)
    * Postflight cleanup removes stale `upgrade.php` and `needs_fixing.txt` from existing 1.8 beta installs
    * Updated vendor libs
    * Removed `SafeUpgradeService`, `RecoveryManager`, `system/recovery.php`, and the standalone `upgrade.php` fallback script — replaced by the `migrate-grav` plugin for major-version migrations
    * Removed recovery-mode config options from `system.yaml`
1. [](#bugfix)
    * Fix for undefined array key path triggered through URL-encoded characters (#4012)
    * Fix for default language loading when using session store
    * Fix for `schedule` flag being ignored in backup profiles
    * Fixes for modern scheduler

# v1.8.0-beta.29
## 12/27/2025

1. [](#improved)
    * Avoid mail in twig content trigger security error
    * Don’t do internal grav-based gzip, rely on webserver
    * Updated vendor libs
1. [](#bugfix)
    * Fix for grav not picking up config + page changes
    * Fix for unusual format SVGs
    * Fix for nested config changes
    * Fix for user editing causing `hashed_password` to be removed
    * Fix of setEscaper move in Twig 3.9+
    * Fix for broken symlinks

# v1.8.0-beta.28
## 12/08/2025

1. [](#new)
    * Added `updates.recovery_mode` config option to enable/disable recovery mode
    * Added admin blueprint toggle for recovery mode setting
1. [](#improved)
    * Redesigned recovery mode screen with clearer messaging and modern UI
    * Added collapsible stack trace details to recovery mode screen
    * Added "Clear Recovery Mode" button that works without token authentication
    * Added "Disable Recovery Mode" option to disable via config from recovery screen
    * Added stack trace capture for exceptions in recovery context
    * Added PHP version validation from package's `defines.php` during safe upgrade
    * Added proxy methods to `Twig3CompatibilityLoader` for backwards compatibility with plugins that call loader methods directly (addPath, prependPath, getPaths, etc.)
1. [](#bugfix)
    * Fixed recovery mode image path for Grav installations in subdirectories
    * Fixed backup restriction preventing backups on systems with Grav installed under `/var/www` - Fixes [#4002](https://github.com/getgrav/grav/issues/4002)
    * Fixed XSS false positives for legitimate HTML tags containing 'on' (caption, button, section) - Fixes [grav-plugin-admin#2472](https://github.com/getgrav/grav-plugin-admin/issues/2472)

# v1.8.0-beta.27
## 11/30/2025

1. [](#improved)
    * Hardened Twig sandbox with expanded blacklist blocking 150+ dangerous functions and attack patterns
    * Added static regex caching in Security class for improved performance
    * Added path traversal protection to backup root configuration
    * Added validation for language codes to prevent regex injection DoS
1. [](#bugfix)
    * Fixed path traversal vulnerability in username during account creation
    * Fixed username uniqueness bypass allowing duplicate accounts
    * Fixed arbitrary file read via `read_file()` Twig function
    * Fixed DoS via malformed cron expressions in scheduler
    * Fixed password hash exposure to frontend via JSON serialization
    * Fixed email disclosure in user edit page title
    * Fixed XSS via `isindex` tag bypass (CVE-2023-31506)
    * Fixed issue with FlexObjects caching [flex-objects#187](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/187)

# v1.8.0-beta.26
## 11/29/2025

1. [](#improved)
    * Improvements for JS minification and now pulls any broken JS out of pipeline
    * Disallow xref/xhref in SVGs
    * Upgraded to recently released Symfony 7.4
1. [](#bugfix)
   * fix range requests for partial content in Utils::downloads() - Fixes [#3990](https://github.com/getgrav/grav-plugin-admin/issues/3990)

# v1.8.0-beta.25
## 11/22/2025

1. [](#bugfix)
   * Fixed Twig version

# v1.8.0-beta.24
## 11/20/2025

1. [](#improved)
    * More Twig3 compatibility fixes and tests
    * Changed snapshot creationg to use copy instead of move for improved reliability
    * Lazy load page optimization
    * Regex caching optimization
    * Gated Debugger `addEvent()` optimization
    * Various SafeUpgrade performance optimizations
    * Improved Twig Deferred block implementation
1. [](#bugfix)
    * Fix various Twig3 deprecated notices
    * Fixed slow purge snapshot functionality and test

# v1.8.0-beta.23
## 11/14/2025

1. [](#improved)
    * Refactored safe-upgrade from scratch with simplified 'install' step

# v1.8.0-beta.22
## 11/06/2025

1. [](#bugfix)
    * Removed over zealous safety checks
    * Removed .gitattributes which was causing some unintended issues

# v1.8.0-beta.21
## 11/05/2025

1. [](#improved)
    * Exclude dev files from exports
1. [](#bugfix)
    * Ignore .github and .phan folders during self-upgrade
    * Fixed path check in self-upgrade

# v1.8.0-beta.20
## 11/05/2025

1. [](#bugfix)
    * Fixed an issue where non-upgradable root-level folders were snapshotted

# v1.8.0-beta.19
## 11/05/2025

1. [](#new)
    * Added new `bin/gpm preflight` command
    * Added `--safe` and `--legacy` overrides for `bin/gpm self-upgrade` command
1. [](#improved)
    * Improved JS assets pipeline handling to support different loading strategies
    * Cache fallbacks for unsupported Cache drivers
    * More safe-upgrade fixes around safe guarding `/user/` and maintaining permissions better
1. [](#bugfix)
   * Fixed a regex issue that corrupted safe-upgrade output

# v1.8.0-beta.18
## 10/31/2025

1. [](#improved)
    * Replaced legacy Doctrine cache dependency with Symfony-backed provider while keeping compatibility layer
    * More safe-upgrade improvements
    
# v1.8.0-beta.17
## 10/23/2025

1. [](#improved)
    * Reworked `Monolog3` ship for better compatibility
    * Latest vendor libraries
    * Don't crash if `getManifest()` is not available
    
# v1.8.0-beta.16
## 10/20/2025

1. [](#improved)
    * Set `bin/*` binaries to `+x` permission when upgrading via CLI
    * Improved Twig3 compatibility fixes

# v1.8.0-beta.15
## 10/19/2025

1. [](#improved)
    * Safe handling of disabled plugins
    * Move `recover.flag` into `user://data`

# v1.8.0-beta.14
## 10/18/2025

1. [](#improved)
    * Implemented more robust snapshot management via the `bin/restore` command

# v1.8.0-beta.13
## 10/17/2025

1. [](#improved)
    * Refactored safe-upgrade check to use copy-based snapshot/install/restore system

# v1.8.0-beta.12
## 10/17/2025

1. [](#bugfix)
    * new low-level routing for safe-upgrade check

# v1.8.0-beta.11
## 10/16/2025

1. [](#bugfix)
    * Sync 1.7 changes to 1.8 branch

# v1.8.0-beta.10
## 10/16/2025

1. [](#bugfix)
    * Fixed an issue with **safe upgrade** losing dot files

# v1.8.0-beta.9
## 10/16/2025

1. [](#new)
    * Added new **core safe upgrade** installer with staging, validation, and rollback support

# v1.8.0-beta.8
## 10/14/2025

1. [](#improved)
    * Upgraded to latest Symfony 7 (might cause issues with some plugins)
    * `wordCount` twig filter (merged from 1.7 branch)
    * More PHP 8.4 compatibility fixes
    * Update all vendor libraries to latest
1. [](#bugfix)
    * Fixed some CLI level bugs
    * Fixed a Twig Sandbox bybpass issue

# v1.8.0-beta.7
## 09/22/2025

1. [](#bugfix)
    * Changed `private` to `public` for YamlUpdater::get() and YamUpdater::set() methods
    * Fixed a session cookie issue that manifested when logging-in to client side

# v1.8.0-beta.6
## 09/22/2025

1. [](#bugfix)
    * Fixed a missing YamlUpdater::exists() method
   
# v1.8.0-beta.5
## 09/22/2025

1. [](#new)
    * Deferred Extension support in Forked version of Twig 3    
    * Added separate `strict_mode.twig2_compat` and `strict_mode.twig3_compat` toggles to manage auto-escape behaviour and automatic Twig 3 compatible template rewrites
1. [](#bugfix)
    * Fix for cache blowing up when upgrading from 1.7 to 1.8 via CLI

# v1.8.0-beta.4
## 01/27/2025

1. [](#bugfix)
    * Fixed a PHP compatibility issue with `AbstractLazyCollection`
1. [](#improved)
    * Global PHP 8.2 code optimizations
    * More PHP 8.4 compatibility fixes
    * Twig 2.x forked to getgrav/twig 2.x for PHP 8.4 compatibility
    * Switch to cache@v4 + limit PHP version for Github actions
    * Trigger testing Github action for Grav 1.8
    * Merge latest Grav 1.7 fixes into Grav 1.8

# v1.8.0-beta.3
## 11/21/2024

1. [](#improved)
    * Updated composer libraries to latest versions for compatibility fixes

# v1.8.0-beta.2
## 10/28/2024

1. [](#new)
    * Use `dev-master` branch of Clockwork to support Monolog2 / Monolog3
    * `AVIF` image support via updates to `getgrav/Image` library
    * Upgraded to **Doctrine Collection 2.2**
1. [](#improved) 
    * Updated composer libraries
    * Updated composer.php binary to `v2.8.1`
    * Fixes for PHP 8.4 - Implicitly nullable parameter declarations deprecated
    * Added back Missing `RocketTheme\Toolbox\Event\EventSubscriberInterface` for Gantry5
1. [](#bugfix)
    * Various fixes to use `$log->debug()`, `$log->info()`, `$log->warning()` and `$log->error()` For Monolog2 support
   
# v1.8.0-beta.1
## 10/23/2024

1. [](#new)
    * Set minimum requirements to **PHP 8.3**
    * Updated to **Twig 2.14**
    * Updated to **Symfony 6.4**
    * Updated to **Monolog 2.3**
    * Updated to **RocketTheme/Toolbox 2.0**
    * Updated to **Composer/Semver 3.2**
    * Use **Symfony Cache** instead of unmaintained **Doctrine Cache**
    * Removed unsupported **APC**, **WinCache**, **XCache** and **Memcache**, use apcu or memcached instead
    * Removed `system.umask_fix` setting for security reasons
    * Support phpstan level 6 in Framework classes

