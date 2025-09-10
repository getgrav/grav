# v1.7.49.5
## 09/10/2025

1. [](#bugfix)
    * Backup not honoring ignored paths [#3952](https://github.com/getgrav/grav/issues/3952)

# v1.7.49.4
## 09/03/2025

1. [](#bugfix)
    * Fixed cron force running jobs severy minute! [#3951](https://github.com/getgrav/grav/issues/3951)

# v1.7.49.3
## 09/02/2025

1. [](#bugfix)
    * Fixed an error in ZipArchive that was causing issues on some systems
    * Fixed namespace change for `Cron\Expression`
    * Removed broken cron install field... use 'instructions' instead
    * Fixed duplicate jobs listing in some CLI commands

# v1.7.49.2
## 08/28/2025

1. [](#bugfix)
    * Fix translation of key for image adapter [#3944](https://github.com/getgrav/grav/pull/3944)

# v1.7.49.1
## 08/25/2025

1. [](#new)
    * Rerelease to include all updated plugins/theme etc.

# v1.7.49
## 08/25/2025

1. [](#new)
    * Revamped Grav Scheduler to support webhook to call call scheduler + concurrent jobs + jobs queue + logging, and other improvements
    * Revamped Grav Cache purge capabilities to only clear obsolete old cache items
    * Added full imagick support in Grav Image library
    * Added support for Validate `match` and `match_any` in forms
1. [](#improved)
    * Handle empty values on require with ignore fields in Forms
    * Use `actions/cache@v4` in github workflows
    * Use `actions/checkout@v4`in github workflows [#3867](https://github.com/getgrav/grav/pull/3867)
    * Update code block in README.md [#3886](https://github.com/getgrav/grav/pull/3886)
    * Updated vendor libs to latest
1. [](#bugfix)
    * Bug in `exif_read_data` [#3878](https://github.com/getgrav/grav/pull/3878)
    * Fix parser error in URI: [#3894](https://github.com/getgrav/grav/issues/3894)


# v1.7.48
## 10/28/2024

1. [](#new)
    * New Trait for fetchPriority attribute on images [#3850](https://github.com/getgrav/grav/pull/3850)
1. [](#improved)
    * Fix for #3164. Adds aliases as possible commands during lookup [#3863](https://github.com/getgrav/grav/pull/3863)
1. [](#bugfix)
    * Fix style conflict with Clockwork and tooltips [#3861](https://github.com/getgrav/grav/pull/3861)

# v1.7.47
## 10/23/2024

1. [](#new)
  * New `Utils::toAscii()` method  
  * Added support for Clockwork Debugger to allow web UI (requires new `clockwork-web` plugin)
1. [](#improved) 
  * Include modular sub-pages in last-modification date computation [#3562](https://github.com/getgrav/grav/pull/3562)
  * Updated vendor libs to latest versions
  * Updated JQuery to `3.7.1` [#3787](https://github.com/getgrav/grav/pull/3827)
  * Updated vendor libraries to latest versions
  * Support for Fediverse Creator meta tag [#3844](https://github.com/getgrav/grav/pull/3844)
1. [](#bugfix)
  * Fixes deprecated for return type in Filesystem with PHP 8.3.6 [#3831](https://github.com/getgrav/grav/issues/3831) 
  * Fix for `exif_imagtetype()` throwing an exception when file doesn't exist
  * Fix JSON output comments check with content type [#3859](https://github.com/getgrav/grav/pull/3859)

# v1.7.46
## 05/15/2024

1. [](#new)
   * Added a new `Utils::toAscii()` method to remove UTF-8 characters from string
1. [](#improved) 
   * Removed unused `symfony/service-contracts` [#3828](https://github.com/getgrav/grav/pull/3828)
   * Upgraded bundled legacy JQuery to `3.7.1` [#3727](https://github.com/getgrav/grav/pull/3827)
   * Include modular pages in header `last-modified:` calculation [#3562](https://github.com/getgrav/grav/pull/3562)
   * Updated vendor libs to latest versions
1. [](#bugfix)
   * Fixed some deprecated issues in Filesystem [#3831](https://github.com/getgrav/grav/issues/3831)

# v1.7.46
## 05/15/2024

1. [](#improved) 
   * Better handling of external protocols in `Utils::url()` such as `mailto:`, `tel:`, etc.
   * Handle `GRAV_ROOT` or `GRAV_WEBROOT` when `/` [#3667](https://github.com/getgrav/grav/pull/3667)
1. [](#bugfix)
   * Fixes for multi-lang taxonomy when reinitializing the languages (e.g. LangSwitcher plugin) 
   * Ensure the full filepath is checked for invalid filename in `MediaUploadTrait::checkFileMetadata()`
   * Fixed a bug in the `on_events` REGEX pattern of `Security::detectXss()` as it was not matching correctly.
   * Fixed an issue where `read_file()` Twig function could be used nefariously in content [#GHSA-f8v5-jmfh-pr69](https://github.com/getgrav/grav/security/advisories/GHSA-f8v5-jmfh-pr69)

# v1.7.45
## 03/18/2024

1. [](#new)
   * Added new Image trait for `decoding` attribute [#3796](https://github.com/getgrav/grav/pull/3796)
1. [](#bugfix)
   * Fixed some multibyte issues in Inflector class [#732](https://github.com/getgrav/grav/issues/732)
   * Fallback to page modified date if Page date provided is invalid and can't be parsed [getgrav/grav-plugin-admin#2394](https://github.com/getgrav/grav-plugin-admin/issues/2394)
   * Fixed a path traversal vulnerability with file uploads [#GHSA-m7hx-hw6h-mqmc](https://github.com/getgrav/grav/security/advisories/GHSA-m7hx-hw6h-mqmc)
   * Fixed a security issue with insecure Twig functions be processed [#GHSA-2m7x-c7px-hp58](https://github.com/getgrav/grav/security/advisories/GHSA-2m7x-c7px-hp58) [#GHSA-r6vw-8v8r-pmp4](https://github.com/getgrav/grav/security/advisories/GHSA-r6vw-8v8r-pmp4) [#GHSA-qfv4-q44r-g7rv](https://github.com/getgrav/grav/security/advisories/GHSA-qfv4-q44r-g7rv) [#GHSA-c9gp-64c4-2rrh](https://github.com/getgrav/grav/security/advisories/GHSA-c9gp-64c4-2rrh)
1. [](#improved) 
   * Updated composer packages
   * Updated `bin/composer.phar` to latest `2.7.2`

# v1.7.44
## 01/05/2024

1. [](#new)
   * Added PHP `8.3` to tests [#3782](https://github.com/getgrav/grav/pull/3782)
   * Added debugger messages when Page routes conflict
   * Added `ISO 8601` date format [#3721](https://github.com/getgrav/grav/pull/37210)
   * Added support for `.vcf` (vCard) in media configuration [#3772](https://github.com/getgrav/grav/pull/3772)
1. [](#improved) 
   * Update jQuery to `v3.6.4` [#3713](https://github.com/getgrav/grav/pull/3713)
   * Updated vendor libraries including Dom-Sanitizer `v1.0.7` that addresses an XSS issue 
   * Updated `bin/composer.phar` to latest `2.6.6`
   * Updated vendor libraries to latest
   * Updated language files
   * Updated copyright year
1. [](#bugfix)
   * Fixed a math rounding issue with number validation when using floating point steps [#3761](https://github.com/getgrav/grav/issues/3761)
   * Fixed an issue with `Inflector::ordinalize()` not working as expected [#3759](https://github.com/getgrav/grav/pull/3759)
   * Fixed various issues with file extension checking with dangerous extensions [#3756(https://github.com/getgrav/grav/pull/3756)]
   * Fix for invalid input to foreach in `UserGroupObject` [#3724](https://github.com/getgrav/grav/pull/3724)
   * Fixed exception: `Property 'jsmodule_pipeline_include_externals' does not exist in object` [#3661](https://github.com/getgrav/grav/pull/3661)
   * Fixed `too few arguments exception` in FlexObjects [#3658](https://github.com/getgrav/grav/pull/3658)

# v1.7.43
## 10/02/2023

1. [](#new)
   * Add the ability to programatically set a page's `modified` timestamp via a `modified:` frontmatter entry
2. [](#improved)
   * Update vendor libraries
   * Include `phar` in the list of `security.uploads_dangerous_extensions`
   * When enabled `system.languages.debug` now dumps **Key -> Value** to debugger [#3752](https://github.com/getgrav/grav/issues/3752)
   * Updated built-in composer to latest `2.6.4` [#3748](https://github.com/getgrav/grav/issues/3748)
   * Added support for `@import` to ensure paths are rewritten correctly in CSS pipeline [#3750](https://github.com/getgrav/grav/pull/3750)

# v1.7.42.3
## 07/18/2023

2. [](#improved)
   * Fixed a typo in `Utils::isDangerousFunction`

# v1.7.42.2
## 07/18/2023

2. [](#improved)
   * In `Utils::isDangerousFunction`, handle double `\\` in `|map` twig filter to mitigate SSTI attack
   * Better handle empty email in `Validatoin::typeEmail()`

# v1.7.42.1
## 06/15/2023

2. [](#improved)
   * Quick fix for `isDangerousFunction` when `$name` was a closure [#3727](https://github.com/getgrav/grav/issues/3727)

# v1.7.42
## 06/14/2023

1. [](#new)
   * Added a new `system.languages.debug` option that adds a `<span class="translate-debug"></span>` around strings translated with `|t`. This can be styled by the theme as needed.
1. [](#improved)
   * More robust SSTI handling in `filter`, `map`, and `reduce` Twig filters and functions
   * Various SSTI improvements `Utils::isDangerousFunction()`
1. [](#bugfix)
   * Fixed Twig `|map()` allowing code execution
   * Fixed Twig `|reduce()` allowing code execution

# v1.7.41.2
## 06/01/2023

1. [](#improved)
   * Added the ability to set a configurable 'key' for the Twig Cache Tag: `{% cache 'my-key' 600 %}`
1. [](#bugfix)
   * Fixed an issue with special characters in slug's would cause redirect loops

# v1.7.41.1
## 05/10/2023

1. [](#bugfix)
   * Fixed certain UTF-8 characters breaking `Truncator` class [#3716](https://github.com/getgrav/grav/issues/3716)

# v1.7.41
## 05/09/2023

1. [](#improved)
   * Removed `FILTER_SANITIZE_STRING` input filter in favor of `htmlspecialchars(strip_tags())` for PHP 8.2+
   * Added `GRAV_SANITIZE_STRING` constant to replace `FILTER_SANITIZE_STRING` for PHP 8.2+
   * Support non-deprecated style dynamic properties in `Parsedown` class via `ParseDownGravTrait` for PHP 8.2+
   * Modified `Truncator` to not use deprecated `mb_convert_encoding()` for PHP 8.2+
   * Fixed passing null into `mb_strpos()` deprecated for PHP 8.2+
   * Updated internal `TwigDeferredExtension` to be PHP 8.2+ compatible
   * Upgraded `getgrav/image` fork to take advantage of various PHP 8.2+ fixes
   * Use `UserGroupObject::groupNames` method in blueprints for PHP 8.2+
   * Comment out `files-upload` deprecated message as this is not going to be removed
   * Added various public `Twig` class variables used by admin to address deprecated messages for PHP 8.2+
   * Added `parse_url` to list of PHP functions supported in Twig Extension
   * Added support for dynamic functions in `Parsedown` to stop deprecation messages in PHP 8.2+
 
# v1.7.40
## 03/22/2023

1. [](#new)
    * Added a new `timestamp: true|false` option for individual assets
1. [](#improved)
    * Removed outdated `xcache` setting [#3615](https://github.com/getgrav/grav/pull/3615)
    * Updated `robots.txt` [#3625](https://github.com/getgrav/grav/pull/3625)
    * Handle the situation when GRAV_ROOT or GRAV_WEBROOT are `/` [#3625](https://github.com/getgrav/grav/pull/3667)
1. [](#bugfix)
    * Fixed `force_ssl` redirect in case of undefined hostname [#3702](https://github.com/getgrav/grav/pull/3702)
    * Fixed an issue with duplicate identical page paths
    * Fixed `BlueprintSchema:flattenData` to properly handle ignored fields
    * Fixed LogViewer regex greediness [#3684](https://github.com/getgrav/grav/pull/3684)
    * Fixed `whoami` command [#3695](https://github.com/getgrav/grav/pull/3695)

# v1.7.39.4
## 02/22/2023

1. [](#bugfix)
    * Reverted a reorganization of `account.yaml` that caused username to be disabled [admin#2344](https://github.com/getgrav/grav-plugin-admin/issues/2344)

# v1.7.39.3
## 02/21/2023

1. [](#bugfix)
    * Fix for overzealous modular page template rendering fix in 1.7.39 causing Feed plugin to break [#3689](https://github.com/getgrav/grav/issues/3689)

# v1.7.39.2
## 02/20/2023

1. [](#bugfix)
    * Fix for invalid session breaking Flex Accounts (when switching from Regular to Flex)

# v1.7.39.1
## 02/20/2023

1. [](#bugfix)
    * Fix for broken image CSS with the latest version of DebugBar

# v1.7.39
## 02/19/2023

1. [](#improved)
    * Vendor library updates to latest versions
1. [](#bugfix)
    * Various PHP 8.2 fixes
    * Fixed an issue with modular pages rendering thew wrong template when dynamically changing the page
    * Fixed an issue with `email` validation that was failing on UTF-8 characters. Following best practices and now only check for `@` and length.
    * Fixed PHPUnit tests to remove deprecation warnings

# v1.7.38
## 01/02/2023

1. [](#new)
    * New `onBeforeSessionStart()` event to be used to store data lost during session regeneration (e.g. login)
1. [](#improved)
   * Vendor library updates to latest versions
   * Updated `bin/composer.phar` to latest `2.4.4` version [#3627](https://github.com/getgrav/grav/issues/3627)
1. [](#bugfix)
   * Don't fail hard if pages recurse with same path
   * Github workflows security hardening [#3624](https://github.com/getgrav/grav/pull/3624)

# v1.7.37.1
## 10/05/2022

1. [](#bugfix)
    * Fixed a bad return type [#3630](https://github.com/getgrav/grav/issues/3630)

# v1.7.37
## 10/05/2022

1. [](#new)
    * Added new `onPageHeaders()` event to allow for header modification as needed
    * Added a `system.pages.dirs` configuration option to allow for configurable paths, and multiple page paths
    * Added new `Pages::getSimplePagesHash` which is useful for caching pages specific data
    * Updated to latest vendor libraries
1. [](#bugfix)
    * An attempt to workaround windows reading locked file issue [getgrav/grav-plugin-admin#2299](https://github.com/getgrav/grav-plugin-admin/issues/2299)
    * Force user index file to be updated to fix email addresses [getgrav/grav-plugin-login#229](https://github.com/getgrav/grav-plugin-login/issues/229)

# v1.7.36
## 09/08/2022

1. [](#new)
    * Added `authorize-*@:` support for Flex blueprints, e.g. `authorize-disabled@: not delete` disables the field if user does not have access to delete object
    * Added support for `flex-ignore@` to hide all the nested fields in the blueprint
1. [](#bugfix)
    * Fixed login with a capitalised email address when using old users [getgrav/grav-plugin-login#229](https://github.com/getgrav/grav-plugin-login/issues/229)

# v1.7.35
## 08/04/2022

1. [](#new)
   * Added support for `multipart/form-data` content type in PUT and PATCH requests
   * Added support for object relationships
   * Added variables `$environment` (string), `$request` (PSR-7 ServerRequestInterface|null) and `$uri` (PSR-7 Uri|null) to be used in `setup.php`
1. [](#improved)
   * Minor vendor updates

# v1.7.34
## 06/14/2022

1. [](#new)
    * Added back Yiddish to Language Codes [#3336](https://github.com/getgrav/grav/pull/3336)
    * Ignore upcoming `media.json` file in media
1. [](#bugfix)
    * Regression: Fixed saving page with a new language causing cache corruption [getgrav/grav-plugin-admin#2282](https://github.com/getgrav/grav-plugin-admin/issues/2282)
    * Fixed a potential fatal error when using watermark in images
    * Fixed `bin/grav install` command with arbitrary destination folder name
    * Fixed Twig `|filter()` allowing code execution
    * Fixed login and user search by email not being case-insensitive when using Flex Users

# v1.7.33
## 04/25/2022

1. [](#improved)
    * When saving yaml and markdown, create also a cached version of the file and recompile it in opcache
2. [](#bugfix)
    * Fixed missing changes in **yaml** & **markdown** files if saved multiple times during the same second because of a caching issue
    * Fixed XSS check not detecting onX events without quotes
    * Fixed default collection ordering in pages admin

# v1.7.32
## 03/28/2022

1. [](#new)
    * Added `|replace_last(search, replace)` filter
    * Added `parseurl` Twig function to expose PHP's `parse_url` function
2. [](#improved)
    * Added multi-language support for page routes in `Utils::url()`
    * Set default maximum length for text fields
      - `password`: 256
      - `email`: 320
      - `text`, `url`, `hidden`, `commalist`: 2048
      - `text` (multiline), `textarea`: 65536
3. [](#bugfix)
   * Fixed issue with `system.cache.gzip: true` resulted in "Fetch Failed" for PHP 8.0.17 and PHP 8.1.4 [PHP issue #8218](https://github.com/php/php-src/issues/8218)
   * Fix for multi-lang issues with Security Report
   * Fixed page search not working with selected language [#3316](https://github.com/getgrav/grav/issues/3316)

# v1.7.31
## 03/14/2022

1. [](#new)
   * Added new local Multiavatar (local generation). **This will be default in Grav 1.8**
   * Added support to get image size for SVG vector images [#3533](https://github.com/getgrav/grav/pull/3533)
   * Added XSS check for uploaded SVG files before they get stored
   * Fixed phpstan issues (All level 2, Framework level 5)
2. [](#improved)
   * Moved Accounts out of Experimental section of System configuration to new "Accounts" tab
3. [](#bugfix)
   * Fixed `'mbstring' extension is not loaded` error, use Polyfill instead [#3504](https://github.com/getgrav/grav/pull/3504)
   * Fixed new `Utils::pathinfo()` and `Utils::basename()` being too strict for legacy use [#3542](https://github.com/getgrav/grav/issues/3542)
   * Fixed non-standard video html atributes generated by `{{ media.html() }}` [#3540](https://github.com/getgrav/grav/issues/3540)
   * Fixed entity sanitization for XSS detection
   * Fixed avatar save location when `account://` stream points to custom directory
   * Fixed bug in `Utils::url()` when path contains part of root

# v1.7.30
## 02/07/2022

1. [](#new)
    * Added twig filter `|field_parent` to get parent field name
2. [](#bugfix)
    * Fixed error while deleting retina image in admin
    * Fixed "Page Authors" field in Security tab, wrongly loading and saving the value [#3525](https://github.com/getgrav/grav/issues/3525)
    * Fixed accounts filter only matches against email address [getgrav/grav-plugin-admin#2224](https://github.com/getgrav/grav-plugin-admin/issues/2224)

# v1.7.29.1
## 01/31/2022

1. [](#bugfix)
    * Fixed `Call to undefined method` error when upgrading from Grav 1.6 [#3523](https://github.com/getgrav/grav/issues/3523)

# v1.7.29
## 01/28/2022

1. [](#new)
    * Added support for registering assets from `HtmlBlock`
    * Added unicode-safe `Utils::basename()` and `Utils::pathinfo()` methods
2. [](#improved)
    * Improved `Filesystem::basename()` and `Filesystem::pathinfo()` to be unicode-safe
    * Made path handling unicode-safe, use new `Utils::basename()` and `Utils::pathinfo()` everywhere
3. [](#bugfix)
    * Fixed error on thumbnail image creation
    * Fixed MimeType for `gzip` (`application/x-gzip`)

# v1.7.28
## 01/24/2022

1. [](#new)
    * Added links and modules support to `HtmlBlock` class
    * Added module support for twig script tag: `{% script module 'theme://js/module.mjs' %}`
    * Added twig tag for links: `{% link icon 'theme://images/favicon.png' priority: 20 with { type: 'image/png' } %}`
    * Added `HtmlBlock` support for `{% style %}`, `{% script %}` and `{% link %}` tags
    * Support for page-level `redirect_default_route` frontmatter header override
3. [](#bugfix)
    * Fixed XSS check not detecting escaped `&#58`

# v1.7.27.1
## 01/12/2022

3. [](#bugfix)
   * Fixed a typo in CSS Asset pipeline that was erroneously joining files with `;`

# v1.7.27
## 01/12/2022

1. [](#new)
   * Support for `YubiKey OTP` 2-Factor authenticator
   * Added support for generic `assets.link()` for external references. No pipeline support
   * Added support for `assets.addJsModule()` with full pipeline support
   * Added `Utils::getExtensionsByMime()` method to get all the registered extensions for the specific mime type
   * Added `Media::getRoute()` and `Media::getRawRoute()` methods to get page route if available
   * Added `Medium::getAlternatives()` to be able to list all the retina sizes
2. [](#improved)
   * Improved `Utils::download()` method to allow overrides on download name, mime and expires header
   * Improved `onPageFallBackUrl` event
   * Reorganized the Asset system configuration blueprint for clarity
3. [](#bugfix)
   * Fixed CLI `--env` and `--lang` options having no effect if they aren't added before all the other options
   * Fixed scaled image medium filename when using non-existing retina file
   * Fixed an issue with JS `imports` and pipelining Assets

# v1.7.26.1
## 01/04/2022

3. [](#bugfix)
   * Fixed `UserObject::getAccess()` after cloning the object

# v1.7.26
## 01/03/2022

1. [](#new)
    * Made `Grav::redirect()` to accept `Route` class
    * Added `translated()` method to `PageTranslateInterface`
    * Added second parameter to `UserObject::isMyself()` method
    * Added `UserObject::$isAuthorizedCallable` to allow `$user->isAuthorized()` customization
    * Use secure session cookies in HTTPS by default (`system.session.secure_https: true`)
    * Added new `Plugin::inheritedConfigOption()` function to access plugin specific functions for page overrides
2. [](#improved)
   * Upgraded vendor libs for PHP 8.1 compatibility
   * Upgraded to **composer v2.1.14** for PHP 8.1 compatibility
   * Added third `$name` parameter to `Blueprint::flattenData()` method, useful for flattening repeating data
   * `ControllerResponseTrait`: Redirect response should be json if the extension is .json
   * When symlinking Grav install, include also tests
   * Updated copyright year to `2022`
3. [](#bugfix)
   * Fixed bad key lookup in `FlexRelatedDirectoryTrait::getCollectionByProperty()`
   * Fixed RequestHandlers `NotFoundException` having empty request
   * Block `.json` files in web server configs
   * Disabled pretty debug info for Flex as it slows down Twig rendering
   * Fixed Twig being very slow when template overrides do not exist
   * Fixed `UserObject::$authorizeCallable` binding to the user object
   * Fixed `FlexIndex::call()` to return null instead of failing to call undefined method
   * Fixed Flex directory configuration creating environment configuration when it should not

# v1.7.25
## 11/16/2021

1. [](#new)
    * Updated phpstan to v1.0
    * Added `FlexObject::getDiff()` to see difference to the saved object
2. [](#improved)
    * Use Symfony `dump` instead of PHP's `vardump` in side the `{{ vardump(x) }}` Twig vardump function
    * Added `route` and `request` to `onPagesInitialized` event
    * Improved page cloning, added method `Page::initialize()`
    * Improved `FlexObject::getChanges()`: return changed lists and arrays as whole instead of just changed keys/values
    * Improved form validation JSON responses to contain list of failed fields with their error messages
    * Improved redirects: send redirect response in JSON if the request was in JSON
3. [](#bugfix)
    * Fixed path traversal vulnerability when using `bin/grav server`
    * Fixed unescaped error messages in JSON error responses
    * Fixed `|t(variable)` twig filter in admin
    * Fixed `FlexObject::getChanges()` always returning empty array
    * Fixed form validation exceptions to use `400 Bad Request` instead of `500 Internal Server Error`

# v1.7.24
## 10/26/2021

1. [](#new)
    * Added support for image watermarks
    * Added support to disable a form, making it readonly
2. [](#improved)
    * Flex `$user->authorize()` now checks user groups before `admin.super`, allowing deny rules to work properly
3. [](#bugfix)
    * Fixed a bug in `PermissionsReader` in PHP 7.3
    * Fixed `session_store_active` language option (#3464)
    * Fixed deprecated warnings on `ArrayAccess` in PHP 8.1
    * Fixed XSS detection with `&colon;`

# v1.7.23
## 09/29/2021

1. [](#new)
    * Added method `Pages::referrerRoute()` to get the referrer route and language
    * Added true unique `Utils::uniqueId()` / `{{ unique_id() }}` utilities  with length, prefix, and suffix support
    * Added `UserObject::isMyself()` method to check if flex user is currently logged in
    * Added support for custom form field options validation with `validate: options: key|ignore`
2. [](#improved)
   * Replaced GPL `SVG-Sanitizer` with MIT licensed `DOM-Sanitizer`
   * `Uri::referrer()` now accepts third parameter, if set to `true`, it returns route without base or language code [#3411](https://github.com/getgrav/grav/issues/3411)
   * Updated vendor libs with latest
   * Updated with latest language strings via Crowdin.com
3. [](#bugfix)
    * Fixed `Folder::move()` throwing an error when target folder is changed by only appending characters to the end [#3445](https://github.com/getgrav/grav/issues/3445)
    * Fixed some phpstan issues (all code back to level 1, Framework level 3)
    * Fixed form reset causing image uploads to fail when using Flex

# v1.7.22
## 09/16/2021

1. [](#new)
    * Register plugin autoloaders into plugin objects
2. [](#improved)
    * Improve Twig 2 compatibility
    * Update to customized version of Twig DeferredExtension (Twig 1/2 compatible)
3. [](#bugfix)
    * Fixed conflicting `$_original` variable in `Flex Pages`

# v1.7.21
## 09/14/2021

1. [](#new)
    * Added `|yaml` filter to convert input to YAML
    * Added `route` and `request` to `onPageNotFound` event
    * Added file upload/remove support for `Flex Forms`
    * Added support for `flex-required@: not exists` and `flex-required@: '!exists'` in blueprints
    * Added `$object->getOriginalData()` to get flex objects data before it was modified with `update()`
    * Throwing exceptions from Twig templates fires `onDisplayErrorPage.[code]` event allowing better error pages
2. [](#improved)
    * Use a simplified text-based `cron` field for scheduler
    * Add timestamp to logging output of scheduler jobs to see when they ran
3. [](#bugfix)
    * Fixed escaping in PageIndex::getLevelListing()
    * Fixed validation of `number` type [#3433](https://github.com/getgrav/grav/issues/3433)
    * Fixed excessive `security.yaml` file creation [#3432](https://github.com/getgrav/grav/issues/3432)
    * Fixed incorrect port :0 with nginx unix socket setup [#3439](https://github.com/getgrav/grav/issues/3439)
    * Fixed `Session::setFlashCookieObject()` to use the same options as the main session cookie

# v1.7.20
## 09/01/2021

2. [](#improved)
    * Added support for `task` and `action` inside JSON request body

# v1.7.19
## 08/31/2021

1. [](#new)
    * Include active form and request in `onPageTask` and `onPageAction` events (defaults to `null`)
    * Added `UserObject::$authorizeCallable` to allow `$user->authorize()` customization
2. [](#improved)
    * Added meta support for `UploadedFile` class
    * Added support for multiple mime-types per file extension [#3422](https://github.com/getgrav/grav/issues/3422)
    * Added `setCurrent()` method to Page Collection [#3398](https://github.com/getgrav/grav/pull/3398)
    * Initialize `$grav['uri']` before session
3. [](#bugfix)
    * Fixed `Warning: Undefined array key "SERVER_SOFTWARE" in index.php` [#3408](https://github.com/getgrav/grav/issues/3408)
    * Fixed error in `loadDirectoryConfig()` if configuration hasn't been saved [#3409](https://github.com/getgrav/grav/issues/3409)
    * Fixed GPM not using non-standard cache path [#3410](https://github.com/getgrav/grav/issues/3410)
    * Fixed broken `environment://` stream when it doesn't have configuration
    * Fixed `Flex Object` missing key field value when using `FolderStorage`
    * Fixed broken Twig try tag when catch has not been defined or is empty
    * Fixed `FlexForm` serialization
    * Fixed form validation for numeric values in PHP 8
    * Fixed `flex-options@` in blueprints duplicating items in array
    * Fixed wrong form issue with flex objects after cache clear
    * Fixed Flex object types not implementing `MediaInterface`
    * Fixed issue with `svgImageFunction()` that was causing broken output

# v1.7.18
## 07/19/2021

1. [](#improved)
    * Added support for loading Flex Directory configuration from main configuration
    * Move SVGs that cannot be sanitized to quarantine folder under `log://quarantine`
    * Added support for CloudFlare-forwarded client IP in the `URI::ip()` method
1. [](#bugfix)
    * Fixed error when using Flex `SimpleStorage` with no entries
    * Fixed page search to include slug field [#3316](https://github.com/getgrav/grav/issues/3316)
    * Fixed Admin becoming unusable when GPM cannot be reached [#3383](https://github.com/getgrav/grav/issues/3383)
    * Fixed `Failed to save entry: Forbidden` when moving a page to a visible page [#3389](https://github.com/getgrav/grav/issues/3389)
    * Better support for Symfony local server on linux [#3400](https://github.com/getgrav/grav/pull/3400)
    * Fixed `open_basedir()` error with some forms

# v1.7.17
## 06/15/2021

1. [](#new)
    * Interface `FlexDirectoryInterface` now extends `FlexAuthorizeInterface`
1. [](#improved)
    * Allow to unset an asset attribute by specifying null (ie, `'defer': null`)
    * Support specifying custom attributes to assets in a collection [Read more](https://learn.getgrav.org/17/themes/asset-manager#collections-with-attributes?target=_blank) [#3358](https://github.com/getgrav/grav/issues/3358)
    * File `frontmatter.yaml` isn't part of media, ignore it
    * Switched default `JQuery` collection to use 3.x rather than 2.x
1. [](#bugfix)
    * Fixed missing styles when CSS/JS Pipeline is used and `asset://` folder is missing
    * Fixed permission check when moving a page [#3382](https://github.com/getgrav/grav/issues/3382)

# v1.7.16
## 06/02/2021

1. [](#new)
    * Added 'addFrame()' method to ImageMedium [#3323](https://github.com/getgrav/grav/pull/3323)
1. [](#improved)
    * Set `cache.clear_images_by_default` to `false` by default
    * Improve error on bad nested form data [#3364](https://github.com/getgrav/grav/issues/3364)
1. [](#bugfix)
    * Improve Plugin and Theme initialization to fix PHP8 bug [#3368](https://github.com/getgrav/grav/issues/3368)
    * Fixed `pathinfo()` twig filter in PHP7
    * Fixed the first visible child page getting ordering number `999999.` [#3365](https://github.com/getgrav/grav/issues/3365)
    * Fixed flex pages search using only folder name [#3316](https://github.com/getgrav/grav/issues/3316)
    * Fixed flex pages using wrong type in `onBlueprintCreated` event [#3157](https://github.com/getgrav/grav/issues/3157)
    * Fixed wrong SRI paths invoked when Grav instance as a sub folder [#3358](https://github.com/getgrav/grav/issues/3358)
    * Fixed SRI trying to calculate remote assets, only ever set integrity for local files. Use the SRI provided by the remote source and manually add it in the `addJs/addCss` call for remote support. [#3358](https://github.com/getgrav/grav/issues/3358)
    * Fix for weird regex issue with latest PHP versions on Intel Macs causing params to not parse properly in URI object

# v1.7.15
## 05/19/2021

1. [](#improved)
    * Allow optional start date in page collections [#3350](https://github.com/getgrav/grav/pull/3350)
    * Added `page` and `output` properties to `onOutputGenerated` and `onOutputRendered` events
1. [](#bugfix)
    * Fixed twig deprecated TwigFilter messages [#3348](https://github.com/getgrav/grav/issues/3348)
    * Fixed fatal error with some markdown links [getgrav/grav-premium-issues#95](https://github.com/getgrav/grav-premium-issues/issues/95)
    * Fixed markdown media operations not working when using `image://` stream [#3333](https://github.com/getgrav/grav/issues/3333) [#3349](https://github.com/getgrav/grav/issues/3349)
    * Fixed copying page without changing the slug [getgrav/grav-plugin-admin#2135](https://github.com/getgrav/grav-plugin-admin/issues/2139)
    * Fixed missing and commonly used methods when using `system.twig.undefined_functions = false` [getgrav/grav-plugin-admin#2138](https://github.com/getgrav/grav-plugin-admin/issues/2138)
    * Fixed uploading images into Flex Object if field destination is not set

# v1.7.14
## 04/29/2021

1. [](#new)
    * Added `MediaUploadTrait::checkFileMetadata()` method
1. [](#improved)
    * Updating a theme should always keep the custom files [getgrav/grav-plugin-admin#2135](https://github.com/getgrav/grav-plugin-admin/issues/2135)
1. [](#bugfix)
    * Fixed broken numeric language codes in Flex Pages [#3332](https://github.com/getgrav/grav/issues/3332)
    * Fixed broken `exif_imagetype()` twig function

# v1.7.13
## 04/23/2021

1. [](#new)
    * Added support for getting translated collection of Flex Pages using `$collection->withTranslated('de')`
1. [](#improved)
    * Moved `gregwar/Image` and `gregwar/Cache` in-house to official `getgrav/Image` and `getgrav/Cache` packagist packages. This will help environments with very strict proxy setups that don't allow VCS setup. [#3289](https://github.com/getgrav/grav/issues/3289)
    * Improved XSS Invalid Protocol detection regex [#3298](https://github.com/getgrav/grav/issues/3298)
    * Added support for user provided folder in Flex `$page->copy()`
1. [](#bugfix)
    * Fixed `The "Grav/Common/Twig/TwigExtension" extension is not enabled` when using markdown twig tag [#3317](https://github.com/getgrav/grav/issues/3317)
    * Fixed text field maxlength validation newline issue [#3324](https://github.com/getgrav/grav/issues/3324)
    * Fixed a bug in Flex Object `refresh()` method

# v1.7.12
## 04/15/2021

1. [](#improved)
    * Improve JSON support for the request
1. [](#bugfix)
    * Fixed absolute path support for Windows [#3297](https://github.com/getgrav/grav/issues/3297)
    * Fixed adding tags in admin after upgrading Grav [#3315](https://github.com/getgrav/grav/issues/3315)

# v1.7.11
## 04/13/2021

1. [](#new)
    * Added configuration options to allow PHP methods to be used in Twig functions (`system.twig.safe_functions`) and filters (`system.twig.safe_filters`)
    * Deprecated using PHP methods in Twig without them being in the safe lists
    * Prevent dangerous PHP methods from being used as Twig functions and filters
    * Restrict filesystem Twig functions to accept only local filesystem and grav streams
1. [](#improved)
    * Better GPM detection of unauthorized installations
1. [](#bugfix)
  * **IMPORTANT** Fixed security vulnerability with Twig allowing dangerous PHP functions by default [GHSA-g8r4-p96j-xfxc](https://github.com/getgrav/grav/security/advisories/GHSA-g8r4-p96j-xfxc)
    * Fixed nxinx appending repeating `?_url=` in some redirects
    * Fixed deleting page with language code not removing the folder if it was the last language [#3305](https://github.com/getgrav/grav/issues/3305)
    * Fixed fatal error when using markdown links with `image://` stream [#3285](https://github.com/getgrav/grav/issues/3285)
    * Fixed `system.languages.session_store_active` not having any effect [#3269](https://github.com/getgrav/grav/issues/3269)
    * Fixed fatal error if `system.pages.types` is not an array [#2984](https://github.com/getgrav/grav/issues/2984)

# v1.7.10
## 04/06/2021

1. [](#new)
    * Added initial support for running Grav library from outside the webroot [#3297](https://github.com/getgrav/grav/issues/3297)
1. [](#improved)
    * Improved password handling when saving a user
1. [](#bugfix)
    * Ignore errors when using `set_time_limit` in `Archiver` and `GPM\Response` classes [#3023](https://github.com/getgrav/grav/issues/3023)
    * Fixed `Folder::move()` deleting the folder if you move folder into itself, created empty file instead
    * Fixed moving `Flex Page` to itself causing the page to be lost [#3227](https://github.com/getgrav/grav/issues/3227)
    * Fixed `PageStorage` from detecting files as pages
    * Fixed `UserIndex` not implementing `UserCollectionInterface`
    * Fixed missing `onAdminAfterDelete` event call in `Flex Pages`
    * Fixed system templates not getting scanned [#3296](https://github.com/getgrav/grav/issues/3296)
    * Fixed incorrect routing if url path looks like a domain name [#2184](https://github.com/getgrav/grav/issues/2184)

# v1.7.9
## 03/19/2021

1. [](#new)
    * Added `Media::hide()` method to hide files from media
    * Added `Utils::getPathFromToken()` method which works also with `Flex Objects`
    * Added `FlexMediaTrait::getMediaField()`, which can be used to access custom media set in the blueprint fields
    * Added `FlexMediaTrait::getFieldSettings()`, which can be used to get media field settings
1. [](#improved)
    * Method `Utils::getPagePathFromToken()` now calls the more generic `Utils::getPathFromToken()`
    * Updated `SECURITY.md` to use security@getgrav.org
1. [](#bugfix)
    * Fixed broken media upload in `Flex` with `@self/path`, `@page` and `@theme` destinations [#3275](https://github.com/getgrav/grav/issues/3275)
    * Fixed media fields excluding newly deleted files before saving the object
    * Fixed method `$pages->find()` should never redirect [#3266](https://github.com/getgrav/grav/pull/3266)
    * Fixed `Page::activeChild()` throwing an error [#3276](https://github.com/getgrav/grav/issues/3276)
    * Fixed `Flex Page` CRUD ACL when creating a new page (needs Flex Objects plugin update) [grav-plugin-flex-objects#115](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/115)
    * Fixed the list of pages not showing up in admin [#3280](https://github.com/getgrav/grav/issues/3280)
    * Fixed text field min/max validation for UTF8 characters [#3281](https://github.com/getgrav/grav/issues/3281)
    * Fixed redirects using wrong redirect code

# v1.7.8
## 03/17/2021

1. [](#new)
    * Added `ControllerResponseTrait::createDownloadResponse()` method
    * Added full blueprint support to theme if you move existing files in `blueprints/` to `blueprints/pages/` folder [#3255](https://github.com/getgrav/grav/issues/3255)
    * Added support for `Theme::getFormFieldTypes()` just like in plugins
1. [](#improved)
    * Optimized `Flex Pages` for speed
    * Optimized saving visible/ordered pages when there are a lot of siblings [#3231](https://github.com/getgrav/grav/issues/3231)
    * Clearing cache now deletes all clockwork files
    * Improved `system.pages.redirect_default_route` and `system.pages.redirect_trailing_slash` configuration options to accept redirect code
1. [](#bugfix)
    * Fixed clockwork error when clearing cache
    * Fixed missing method `translated()` in `Flex Pages`
    * Fixed missing `Flex Pages` in site if multi-language support has been enabled
    * Fixed Grav using blueprints and form fields from disabled plugins
    * Fixed `FlexIndex::sortBy(['key' => 'ASC'])` having no effect
    * Fixed default Flex Pages collection ordering to order by filesystem path
    * Fixed disappearing pages on save if `pages://` stream resolves to multiple folders where the preferred folder doesn't exist
    * Fixed Markdown image attribute `loading` [#3251](https://github.com/getgrav/grav/pull/3251)
    * Fixed `Uri::isValidExtension()` returning false positives
    * Fixed `page.html` returning duplicated content with `system.pages.redirect_default_route` turned on [#3130](https://github.com/getgrav/grav/issues/3130)
    * Fixed site redirect with redirect code failing when redirecting to sub-pages [#3035](https://github.com/getgrav/grav/pull/3035/files)
    * Fixed `Uncaught ValueError: Path cannot be empty` when failing to upload a file [#3265](https://github.com/getgrav/grav/issues/3265)
    * Fixed `Path cannot be empty` when viewing non-existent log file [#3270](https://github.com/getgrav/grav/issues/3270)
    * Fixed `onAdminSave` original page having empty header [#3259](https://github.com/getgrav/grav/issues/3259)

# v1.7.7
## 02/23/2021

1. [](#new)
    * Added `Utils::arrayToQueryParams()` to convert an array into query params
1. [](#improved)
    * Added original image support for all flex objects and media fields
    * Improved `Pagination` class to allow custom pagination query parameter
1. [](#bugfix)
    * Fixed avatar of the user not being saved [grav-plugin-flex-objects#111](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/111)
    * Replaced special space character with regular space in `system/blueprints/user/account_new.yaml`

# v1.7.6
## 02/17/2021

1. [](#new)
    * Added `Medium::attribute()` to pass arbitrary attributes [#3065](https://github.com/getgrav/grav/pull/3065)
    * Added `Plugins::getPlugins()` and `Plugins::getPlugin($name)` to make it easier to access plugin instances [#2277](https://github.com/getgrav/grav/pull/2277)
    * Added `regex_match` and `regex_split` twig functions [#2788](https://github.com/getgrav/grav/pull/2788)
    * Updated all languages from [Crowdin](https://crowdin.com/project/grav-core) - Please update any translations here
1. [](#improved)
    * Added abstract `FlexObject`, `FlexCollection` and `FlexIndex` classes to `\Grav\Common\Flex` namespace (extend those instead of Framework or Generic classes)
    * Updated bundled `composer.phar` binary to latest version `2.0.9`
    * Improved session fixation handling in PHP 7.4+ (cannot fix it in PHP 7.3 due to PHP bug)
    * Added optional password/database attributes for redis in `system.yaml`
    * Added ability to filter enabled or disabled with bin/gpm index [#3187](https://github.com/getgrav/grav/pull/3187)
    * Added `$grav->getVersion()` or `grav.version` in twig to get the current Grav version [#3142](https://github.com/getgrav/grav/issues/3142)
    * Added second parameter to `$blueprint->flattenData()` to include every field, including those which have no data
    * Added support for setting session domain [#2040](https://github.com/getgrav/grav/pull/2040)
    * Better support inheriting languages when using child themes [#3226](https://github.com/getgrav/grav/pull/3226)
    * Added option for `FlexForm` constructor to reset the form
1. [](#bugfix)
    * Fixed issue with `content-security-policy` not being properly supported with `http-equiv` + support single quotes
    * Fixed CLI progressbar in `backup` and `security` commands to use styled output [#3198](https://github.com/getgrav/grav/issues/3198)
    * Fixed page save failing because of uploaded images [#3191](https://github.com/getgrav/grav/issues/3191)
    * Fixed `Flex Pages` using only default language in frontend [#106](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/106)
    * Fixed empty `route()` and `raw_route()` when getting translated pages [#3184](https://github.com/getgrav/grav/pull/3184)
    * Fixed error on `bin/gpm plugin uninstall` [#3207](https://github.com/getgrav/grav/issues/3207)
    * Fixed broken min/max validation for field `type: int`
    * Fixed lowering uppercase characters in usernames when saving from frontend [#2565](https://github.com/getgrav/grav/pull/2565)
    * Fixed save error when editing accounts that have been created with capital letters in their username [#3211](https://github.com/getgrav/grav/issues/3211)
    * Fixed renaming flex objects key when using file storage
    * Fixed wrong values in Admin pages list [#3214](https://github.com/getgrav/grav/issues/3214)
    * Fixed pipelined asset using different hash when extra asset is added to before/after position [#2781](https://github.com/getgrav/grav/issues/2781)
    * Fixed trailing slash redirect to only apply to GET/HEAD requests and use 301 status code [#3127](https://github.com/getgrav/grav/issues/3127)
    * Fixed root page to always contain trailing slash [#3127](https://github.com/getgrav/grav/issues/3127)
    * Fixed `<meta name="flattr:*" content="*">` to use name instead property [#3010](https://github.com/getgrav/grav/pull/3010)
    * Fixed behavior of opposite filters in `Pages::getCollection()` to match Grav 1.6 [#3216](https://github.com/getgrav/grav/pull/3216)
    * Fixed modular content with missing template file ending up using non-modular template [#3218](https://github.com/getgrav/grav/issues/3218)
    * Fixed broken attachment image in Flex Objects Admin when `destination: self@` used [#3225](https://github.com/getgrav/grav/issues/3225)
    * Fixed bug in page content with both markdown and twig enabled [#3223](https://github.com/getgrav/grav/issues/3223)

# v1.7.5
## 02/01/2021

1. [](#bugfix)
    * Revert: Fixed page save failing because of uploaded images [#3191](https://github.com/getgrav/grav/issues/3191) - breaking save

# v1.7.4
## 02/01/2021

1. [](#new)
    * Added `FlexForm::setSubmitMethod()` to customize form submit action
1. [](#improved)
    * Improved GPM error handling
1. [](#bugfix)
    * Fixed `bin/gpm uninstall` script not working because of bad typehint [#3172](https://github.com/getgrav/grav/issues/3172)
    * Fixed `login: visibility_requires_access` not working in pages [#3176](https://github.com/getgrav/grav/issues/3176)
    * Fixed cannot change image format [#3173](https://github.com/getgrav/grav/issues/3173)
    * Fixed saving page in expert mode [#3174](https://github.com/getgrav/grav/issues/3174)
    * Fixed exception in `$flexPage->frontmatter()` method when setting value
    * Fixed `onBlueprintCreated` event being called multiple times in `Flex Pages` [grav-plugin-flex-objects#97](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/97)
    * Fixed wrong ordering in page collections if `intl` extension has been enabled [#3167](https://github.com/getgrav/grav/issues/3167)
    * Fixed page redirect to the first visible child page (needs to be routable and published, too)
    * Fixed untranslated module pages showing up in the menu
    * Fixed page save failing because of uploaded images [#3191](https://github.com/getgrav/grav/issues/3191)
    * Fixed incorrect config lookup for loading in `ImageLoadingTrait` [#3192](https://github.com/getgrav/grav/issues/3192)

# v1.7.3
## 01/21/2021

1. [](#improved)
    * IMPORTANT - Please [checkout the process](https://getgrav.org/blog/grav-170-cli-self-upgrade-bug) to `self-upgrade` from CLI if you are on **Grav 1.7.0 or 1.7.1**
    * Added support for symlinking individual plugins and themes by using `bin/grav install -p myplugin` or `-t mytheme`
    * Added support for symlinking plugins and themes with `hebe.json` file to support custom folder structures
    * Added support for running post-install scripts in `bin/gpm selfupgrade` if Grav was updated manually
1. [](#bugfix)
    * Fixed default GPM Channel back to 'stable' - this was inadvertently left as 'testing' [#3163](https://github.com/getgrav/grav/issues/3163)
    * Fixed broken stream initialization if `environment://` paths aren't streams
    * Fixed Clockwork debugger in sub-folder multi-site setups
    * Fixed `Unsupported option "curl" passed to "Symfony\Component\HttpClient\CurlHttpClient"` in `bin/gpm selfupgrade` [#3165](https://github.com/getgrav/grav/issues/3165)

# v1.7.2
## 01/21/2021

1. [](#improved)
    * This release was pulled due to a bug in the installer, 1.7.3 replaces it.

# v1.7.1
## 01/20/2021

1. [](#bugfix)
    * Fixed fatal error when `site.taxonomies` contains a bad value
    * Sanitize valid Page extensions from `Page::template_format()`
    * Fixed `bin/gpm index` erroring out [#3158](https://github.com/getgrav/grav/issues/3158)
    * Fixed `bin/gpm selfupgrade` failing to report failed Grav update [#3116](https://github.com/getgrav/grav/issues/3116)
    * Fixed `bin/gpm selfupgrade` error on `Call to undefined method` [#3160](https://github.com/getgrav/grav/issues/3160)
    * Flex Pages: Fixed fatal error when trying to move a page to Root (/) [#3161](https://github.com/getgrav/grav/issues/3161)
    * Fixed twig parsing errors in pages where twig is parsed after markdown [#3162](https://github.com/getgrav/grav/issues/3162)
    * Fixed `lighttpd.conf` access-deny rule [#1876](https://github.com/getgrav/grav/issues/1876)
    * Fixed page metadata being double-escaped [#3121](https://github.com/getgrav/grav/issues/3121)

# v1.7.0
## 01/19/2021

1. [](#new)
    * Requires **PHP 7.3.6**
    * Read about this release in the [Grav 1.7 Released](https://getgrav.org/blog/grav-1.7-released) blog post
    * Read the full list of all changes in the [Changelog on GitHub](https://github.com/getgrav/grav/blob/1.7.0/CHANGELOG.md)
    * Please read [Grav 1.7 Upgrade Guide](https://learn.getgrav.org/17/advanced/grav-development/grav-17-upgrade-guide) before upgrading!
    * Added support for overriding configuration by using environment variables
    * Use PHP 7.4 serialization (the old `Serializable` methods are now final and cannot be overridden)
    * Enabled `ETag` setting by default for 304 responses
    * Added `FlexCollection::getDistinctValues()` to get all the assigned values from the field
    * `Flex Pages` method `$page->header()` returns `\Grav\Common\Page\Header` object, old `Page` class still returns `stdClass`
1. [](#improved)
    * Make it possible to use an absolute path when loading a blueprint
    * Make serialize methods final in `ContentBlock`, `AbstractFile`, `FormTrait`, `ObjectCollectionTrait` and `ObjectTrait`
    * Added support for relative paths in `PageObject::getLevelListing()` [#3110](https://github.com/getgrav/grav/issues/3110)
    * Better `--env` and `--lang` support for `bin/grav`, `bin/gpm` and `bin/plugin` console commands
      * **BC BREAK** Shorthand for `--env`: `-e` will not work anymore as it conflicts with some plugins
    * Added support for locking the `start` and `limit` in a Page Collection
1. [](#bugfix)
    * Fixed port issue with `system.custom_base_url`
    * Hide errors with `exif_read_data` in `ImageFile`
    * Fixed unserialize in `MarkdownFormatter` and `Framework\File` classes
    * Fixed pages with session messages should never be cached [#3108](https://github.com/getgrav/grav/issues/3108)
    * Fixed `Filesystem::normalize()` with dot-dot paths
    * Fixed Flex sorting issues [grav-plugin-flex-objects#92](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/92)
    * Fixed Clockwork missing dumped arrays and objects
    * Fixed fatal error in PHP 8 when trying to access root page
    * Fixed Array->String conversion error when `languages:translations: false` [admin#1896](https://github.com/getgrav/grav-plugin-admin/issues/1896)
    * Fixed `Inflector` methods when translation is missing `GRAV.INFLECTOR_*` translations
    * Fixed exception when changing parent of new page [grav-plugin-admin#2018](https://github.com/getgrav/grav-plugin-admin/issues/2018)
    * Fixed ordering issue with moving pages [grav-plugin-admin#2015](https://github.com/getgrav/grav-plugin-admin/issues/2015)
    * Fixed Flex Pages cache not invalidating if saving an old `Page` object [#3152](https://github.com/getgrav/grav/issues/3152)
    * Fixed multiple issues with `system.language.translations: false`
    * Fixed page collections containing dummy items for untranslated default language [#2985](https://github.com/getgrav/grav/issues/2985)
    * Fixed streams in `setup.php` being overridden by `system/streams.yaml` [#2450](https://github.com/getgrav/grav/issues/2450)
    * Fixed `ERR_TOO_MANY_REDIRECTS` with HTTPS = 'On' [#3155](https://github.com/getgrav/grav/issues/3155)
    * Fixed page collection pagination not behaving as it did in Grav 1.6

# v1.7.0-rc.20
## 12/15/2020

1. [](#new)
    * Update phpstan to version 0.12
    * Auto-Escape enabled by default. Manually enable **Twig Compatibility** and disable **Auto-Escape** to use the old setting.
    * Updated unit tests to use codeception 4.1
    * Added support for setting `GRAV_ENVIRONMENT` by using environment variable or a constant
    * Added support for setting `GRAV_SETUP_PATH` by using environment variable (constant already worked)
    * Added support for setting `GRAV_ENVIRONMENTS_PATH` by using environment variable or a constant
    * Added support for setting `GRAV_ENVIRONMENT_PATH` by using environment variable or a constant
1. [](#improved)
    * Improved `bin/grav install` command
1. [](#bugfix)
    * Fixed potential error when upgrading Grav
    * Fixed broken list in `bin/gpm index` [#3092](https://github.com/getgrav/grav/issues/3092)
    * Fixed CLI/GPM command failures returning 0 (success) value [#3017](https://github.com/getgrav/grav/issues/3017)
    * Fixed unimplemented `PageObject::getOriginal()` call [#3098](https://github.com/getgrav/grav/issues/3098)
    * Fixed `Argument 1 passed to Grav\Common\User\DataUser\User::filterUsername() must be of the type string` [#3101](https://github.com/getgrav/grav/issues/3101)
    * Fixed broken check if php exif module is enabled in `ImageFile::fixOrientation()`
    * Fixed `StaticResizeTrait::resize()` bad image height/width attributes if `null` values are passed to the method
    * Fixed twig script/style tag `{% script 'file.js' at 'bottom' %}`, replaces broken `in` operator [#3084](https://github.com/getgrav/grav/issues/3084)
    * Fixed dropped query params when `?` is preceded with `/` [#2964](https://github.com/getgrav/grav/issues/2964)

# v1.7.0-rc.19
## 12/02/2020

1. [](#bugfix)
    * Updated composer libraries with latest Toolbox v1.5.6 that contains critical fixes

# v1.7.0-rc.18
## 12/02/2020

1. [](#new)
    * Set minimum requirements to **PHP 7.3.6**
    * Updated Clockwork to v5.0
    * Added `FlexDirectoryInterface` interface
    * Renamed `PageCollectionInterface::nonModular()` into `PageCollectionInterface::pages()` and deprecated the old method
    * Renamed `PageCollectionInterface::modular()` into `PageCollectionInterface::modules()` and deprecated the old method'
    * Upgraded `bin/composer.phar` to `2.0.2` which is all new and much faster
    * Added search option `same_as` to Flex Objects
    * Added PHP 8 compatible `function_exists()`: `Utils::functionExists()`
    * New sites have `compatibility` features turned off by default, upgrading from older versions will keep the settings on
1. [](#improved)
    * Updated bundled JQuery to latest version `3.5.1`
    * Forward a `sid` to GPM when downloading a premium package via CLI
    * Allow `JsonFormatter` options to be passed as a string
    * Hide Flex Pages frontend configuration (not ready for production use)
    * Improve Flex configuration: gather views together in blueprint
    * Added XSS detection to all forms. See [documentation](https://learn.getgrav.org/17/forms/forms/form-options#xss-checks)
    * Better handling of missing repository index [grav-plugin-admin#1916](https://github.com/getgrav/grav-plugin-admin/issues/1916)
    * Added support for having all sites / environments under `user/env` folder [#3072](https://github.com/getgrav/grav/issues/3072)
    * Added `FlexObject::refresh()` method to make sure object is up to date
1. [](#bugfix)
    * *Menu Visibility Requires Access* Security option setting wrong frontmatter [login#265](https://github.com/getgrav/grav-plugin-login/issues/265)
    * Accessing page with unsupported file extension (jpg, pdf, xsl) will use wrong mime type [#3031](https://github.com/getgrav/grav/issues/3031)
    * Fixed media crashing on a bad image
    * Fixed bug in collections where filter `type: false` did not work
    * Fixed `print_r()` in twig
    * Fixed sorting by groups in `Flex Users`
    * Changing `Flex Page` template causes the other language versions of that page to lose their content [admin#1958](https://github.com/getgrav/grav-plugin-admin/issues/1958)
    * Fixed plugins getting initialized multiple times (by CLI commands for example)
    * Fixed `header.admin.children_display_order` in Flex Pages to work just like with regular pages
    * Fixed `Utils::isFunctionDisabled()` method if there are spaces in `disable_functions` [#3023](https://github.com/getgrav/grav/issues/3023)
    * Fixed potential fatal error when creating flex index using cache [#3062](https://github.com/getgrav/grav/issues/3062)
    * Fixed fatal error in `CompiledFile` if the cached version is broken
    * Fixed updated media missing from media when editing Flex Object after page reload
    * Fixed issue with `config-default@` breaking on set [#1972](https://github.com/getgrav/grav-plugin-admin/issues/1971)
    * Escape titles in Flex pages list [flex-objects#84](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/84)
    * Fixed Purge successful message only working in Scheduler but broken in CLI and Admin [#1935](https://github.com/getgrav/grav-plugin-admin/issues/1935)
    * Fixed `system://` stream is causing issues in Admin, making Media tab to disappear and possibly causing other issues [#3072](https://github.com/getgrav/grav/issues/3072)
    * Fixed CLI self-upgrade from Grav 1.6 [#3079](https://github.com/getgrav/grav/issues/3079)
    * Fixed `bin/grav yamllinter -a` and `-f` not following symlinks [#3080](https://github.com/getgrav/grav/issues/3080)
    * Fixed `|safe_email` filter to return safe and escaped UTF-8 HTML [#3072](https://github.com/getgrav/grav/issues/3072)
    * Fixed exception in CLI GPM and backup commands when `php-zip` is not enabled [#3075](https://github.com/getgrav/grav/issues/3075)
    * Fix for XSS advisory [GHSA-cvmr-6428-87w9](https://github.com/getgrav/grav/security/advisories/GHSA-cvmr-6428-87w9)
    * Fixed Flex and Page ordering to be natural and case insensitive [flex-objects#87](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/87)
    * Fixed plugin/theme priority ordering to be numeric

# v1.7.0-rc.17
## 10/07/2020

1. [](#new)
    * Added a `Uri::getAllHeaders()` compatibility function
1. [](#improved)
    * Fall back through various templates scenarios if they don't exist in theme to avoid unhelpful error.
    * Added default templates for `external.html.twig`, `default.html.twig`, and `modular.html.twig`
    * Improve Media classes
    * _POTENTIAL BREAKING CHANGE:_ Added reload argument to `FlexStorageInterface::getMetaData()`
1. [](#bugfix)
    * Fixed `Security::sanitizeSVG()` creating an empty file if SVG file cannot be parsed
    * Fixed infinite loop in blueprints with `extend@` to a parent stream
    * Added missing `Stream::create()` method
    * Added missing `onBlueprintCreated` event for Flex Pages
    * Fixed `onBlueprintCreated` firing multiple times recursively
    * Fixed media upload failing with custom folders
    * Fixed `unset()` in `ObjectProperty` class
    * Fixed `FlexObject::freeMedia()` method causing media to become null
    * Fixed bug in `Flex Form` making it impossible to set nested values
    * Fixed `Flex User` avatar when using folder storage, also allow multiple images
    * Fixed Referer reference during GPM calls.
    * Fixed fatal error with toggled lists

# v1.7.0-rc.16
## 09/01/2020

1. [](#new)
    * Added a new `svg_image()` twig function to make it easier to 'include' SVG source in Twig
    * Added a helper `Utils::fullPath()` to get the full path to a file be it stream, relative, etc.
1. [](#improved)
    * Added `themes` to cached blueprints and configuration
1. [](#bugfix)
    * Fixed `Flex Pages` issue with `getRoute()` returning path with language prefix for default language if set not to do that
    * Fixed `Flex Pages` bug where reordering pages causes page content to disappear if default language uses wrong extension (`.md` vs `.en.md`)
    * Fixed `Flex Pages` bug where `onAdminSave` passes page as `$event['page']` instead of `$event['object']` [#2995](https://github.com/getgrav/grav/issues/2995)
    * Fixed `Flex Pages` bug where changing a modular page template added duplicate file [admin#1899](https://github.com/getgrav/grav-plugin-admin/issues/1899)
    * Fixed `Flex Pages` bug where renaming slug causes bad ordering range after save [#2997](https://github.com/getgrav/grav/issues/2997)

# v1.7.0-rc.15
## 07/22/2020

1. [](#bugfix)
    * Fixed Flex index file caching [#2962](https://github.com/getgrav/grav/issues/2962)
    * Fixed various issues with Exif data reading and images being incorrectly rotated [#1923](https://github.com/getgrav/grav-plugin-admin/issues/1923)

# v1.7.0-rc.14
## 07/09/2020

1. [](#improved)
    * Added ability to `noprocess` specific items only in Link/Image Excerpts, e.g. `http://foo.com/page?id=foo&target=_blank&noprocess=id` [#2954](https://github.com/getgrav/grav/pull/2954)
1. [](#bugfix)
    * Regression: Default language fix broke `Language::getLanguageURLPrefix()` and `Language::isIncludeDefaultLanguage()` methods when not using multi-language
    * Reverted `Language::getDefault()` and `Language::getLanguage()` to return false again because of plugin compatibility (updated docblocks)
    * Fixed UTF-8 issue in `Excerpts::getExcerptsFromHtml`
    * Fixed some compatibility issues with recent Changes to `Assets` handling
    * Fixed issue with `CSS_IMPORTS_REGEX` breaking with complex URLs [#2958](https://github.com/getgrav/grav/issues/2958)
    * Moved duplicated `CSS_IMPORT_REGEX` to local variable in `AssetUtilsTrait::moveImports()`
    * Fixed page media only accepting images [#2943](https://github.com/getgrav/grav/issues/2943)

# v1.7.0-rc.13
## 07/01/2020

1. [](#new)
    * Added support for uploading and deleting images directly in `Media`
    * Added new `onAfterCacheClear` event
1. [](#improved)
    * Improved `CvsFormatter` to attempt to encode non-scalar variables into JSON before giving up
    * Moved image loading into its own trait to be used by images+static images
    * Adjusted asset types to enable extension of assets in class [#2937](https://github.com/getgrav/grav/pull/2937)
    * Composer update for vendor library updates
    * Updated bundled `composer.phar` to `2.0.0-dev`
1. [](#bugfix)
    * Fixed `MediaUploadTrait::copyUploadedFile()` not adding uploaded media to the collection
    * Fixed regression in saving media to a new Flex Object [admin#1867](https://github.com/getgrav/grav-plugin-admin/issues/1867)
    * Fixed `Trying to get property 'username' of non-object` error in Flex [flex-objects#62](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/62)
    * Fixed retina images not working in Flex [flex-objects#64](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/64)
    * Fixed plugin initialization in CLI
    * Fixed broken logic in `Page::topParent()` when dealing with first-level pages
    * Fixed broken `Flex Page` authorization for groups
    * Fixed missing `onAdminSave` and `onAdminAfterSave` events when using `Flex Pages` and `Flex Users` [flex-objects#58](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/58)
    * Fixed new `User Group` allowing bad group name to be saved [admin#1917](https://github.com/getgrav/grav-plugin-admin/issues/1917)
    * Fixed `Language::getDefault()` returning false and not 'en'
    * Fixed non-text links in `Excerpts::getExcerptFromHtml`
    * Fixed CLI commands not properly intializing Plugins so events can fire

# v1.7.0-rc.12
## 06/08/2020

1. [](#improved)
    * Changed `Folder::hasChildren` to `Folder::countChildren`
    * Added `Content Editor` option to user account blueprint
1. [](#bugfix)
    * Fixed new `Flex Page` not having correct form fields for the page type
    * Fixed new `Flex User` erroring out on save (thanks @mikebi42)
    * Fixed `Flex Object` request cache clear when saving object
    * Fixed blueprint value filtering in lists [#2923](https://github.com/getgrav/grav/issues/2923)
    * Fixed blueprint for `system.pages.hide_empty_folders` [#1925](https://github.com/getgrav/grav/issues/2925)
    * Fixed file field in `Flex Objects` (use `Grav\Common\Flex\Types\GenericObject` instead of `FlexObject`) [flex-objects#37](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/37)
    * Fixed saving nested file fields in `Flex Objects` [flex-objects#34](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/34)
    * JSON Route of homepage with no ‘route’ set is valid [form#425](https://github.com/getgrav/grav-plugin-form/issues/425)

# v1.7.0-rc.11
## 05/14/2020

1. [](#new)
    * Added support for native `loading=lazy` attributes on images.  Can be set in `system.images.defaults` or per md image with `?loading=lazy` [#2910](https://github.com/getgrav/grav/issues/2910)
1. [](#improved)
    * Added `PageCollection::all()` to mimic Pages class
    * Added system configuration support for `HTTP_X_Forwarded` headers (host disabled by default)
    * Updated `PHPUserAgentParser` to 1.0.0
    * Improved docblocks
    * Fixed some phpstan issues
    * Tighten vendor requirements
1. [](#bugfix)
    * Fix for uppercase image extensions
    * Fix for `&` errors in HTML when passed to `Excerpts.php`

# v1.7.0-rc.10
## 04/30/2020

1. [](#new)
    * Changed `Response::get()` used by **GPM/Admin** to use [Symfony HttpClient v4.4](https://symfony.com/doc/current/components/http_client.html) (`composer install --nodev` required for Git installations)
    * Added new `Excerpts::processLinkHtml()` method
1. [](#bugfix)
    * Fixed `Flex Pages` admin with PHP `intl` extension enabled when using custom page order
    * Fixed saving non-numeric-prefix `Flex Page` changing to numeric-prefix [flex-objects#56](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/56)
    * Copying `Flex Page` in admin does nothing [flex-objects#55](https://github.com/trilbymedia/grav-plugin-flex-objects/issues/55)
    * Force GPM progress to be between 0-100%

# v1.7.0-rc.9
## 04/27/2020

1. [](#new)
    * Support for `webp` image format in Page Media [#1168](https://github.com/getgrav/grav/issues/1168)
    * Added `Route::getBase()` method
1. [](#improved)
    * Support symlinks when saving `File`
1. [](#bugfix)
    * Fixed flex objects with integer keys not working [#2863](https://github.com/getgrav/grav/issues/2863)
    * Fixed `Pages::instances()` returning null values when using `Flex Pages` [#2889](https://github.com/getgrav/grav/issues/2889)
    * Fixed Flex Page parent `header.admin.children_display_order` setting being ignored in Admin [#2881](https://github.com/getgrav/grav/issues/2881)
    * Implemented missing Flex `$pageCollection->batch()` and `$pageCollection->order()` methods
    * Fixed user avatar creation for new `Flex Users` when using folder storage
    * Fixed `Trying to access array offset on value of type null` PHP 7.4 error in `Plugin.php`
    * Fixed Gregwar Image library using `.jpeg` for cached images, rather use `.jpg`
    * Fixed `Flex Pages` with `00.home` page not having ordering set
    * Fixed `Flex Pages` not updating empty content on save [#2890](https://github.com/getgrav/grav/issues/2890)
    * Fixed creating new Flex User with file storage
    * Fixed saving new `Flex Object` with custom key
    * Fixed broken `Plugin::config()` method

# v1.7.0-rc.8
## 03/19/2020

1. [](#new)
    * Added `MediaTrait::freeMedia()` method to free media (and memory)
    * Added `Folder::hasChildren()` method to determine if a folder has child folders
1. [](#improved)
    * Save memory when updating large flex indexes
    * Better `Content-Encoding` handling in Apache when content compression is disabled [#2619](https://github.com/getgrav/grav/issues/2619)
1. [](#bugfix)
    * Fixed creating new `Flex User` when folder storage has been selected
    * Fixed some bugs in Flex root page methods
    * Fixed bad default redirect code in `ControllerResponseTrait::createRedirectResponse()`
    * Fixed issue with PHP `HTTP_X_HTTP_METHOD_OVERRIDE` [#2847](https://github.com/getgrav/grav/issues/2847)
    * Fixed numeric usernames not working in `Flex Users`
    * Implemented missing Flex `$page->move()` method

# v1.7.0-rc.7
## 03/05/2020

1. [](#new)
    * Added `Session::regenerateId()` method to properly prevent session fixation issues
    * Added configuration option `system.strict_mode.blueprint_compat` to maintain old `validation: strict` behavior [#1273](https://github.com/getgrav/grav/issues/1273)
1. [](#improved)
    * Improved Flex events
    * Updated CLI commands to use the new methods to initialize Grav
1. [](#bugfix)
    * Fixed Flex Pages having broken `isFirst()`, `isLast()`, `prevSibling()`, `nextSibling()` and `adjacentSibling()`
    * Fixed broken ordering sometimes when saving/moving visible `Flex Page` [#2837](https://github.com/getgrav/grav/issues/2837)
    * Fixed ordering being lost when saving modular `Flex Page`
    * Fixed `validation: strict` not working in blueprints (see `system.strict_mode.blueprint_compat` setting) [#1273](https://github.com/getgrav/grav/issues/1273)
    * Fixed `Blueprint::extend()` and `Blueprint::embed()` not initializing dynamic properties
    * Fixed fatal error on storing flex flash using new object without a key
    * Regression: Fixed unchecking toggleable having no effect in Flex forms
    * Fixed changing page template in Flex Pages [#2828](https://github.com/getgrav/grav/issues/2828)

# v1.7.0-rc.6
## 02/11/2020

1. [](#new)
    * Plugins & Themes: Call `$plugin->autoload()` and `$theme->autoload()` automatically when object gets initialized
    * CLI: Added `$grav->initializeCli()` method
    * Flex Directory: Implemented customizable configuration
    * Flex Storages: Added support for renaming directory entries
1. [](#improved)
    * Vendor updates to latest
1. [](#bugfix)
    * Regression: Fixed fatal error in blueprints [#2811](https://github.com/getgrav/grav/issues/2811)
    * Regression: Fixed bad method call in FlexDirectory::getAuthorizeRule()
    * Regression: Fixed fatal error in admin if the site has custom permissions in `onAdminRegisterPermissions`
    * Regression: Fixed flex user index with folder storage
    * Regression: Fixed fatal error in `bin/plugin` command
    * Fixed `FlexObject::triggerEvent()` not emitting events [#2816](https://github.com/getgrav/grav/issues/2816)
    * Grav 1.7: Fixed saving Flex configuration with ignored values becoming null
    * Grav 1.7: Fixed `bin/plugin` initialization
    * Grav 1.7: Fixed Flex Page cache key not taking account active language

# v1.7.0-rc.5
## 02/03/2020

1. [](#bugfix)
    * Regression: Flex not working in PHP 7.2 or older
    * Fixed creating first user from admin not clearing Flex User directory cache [#2809](https://github.com/getgrav/grav/issues/2809)
    * Fixed Flex Pages allowing root page to be deleted

# v1.7.0-rc.4
## 02/03/2020

1. [](#new)
    * _POTENTIAL BREAKING CHANGE:_ Upgraded Parsedown to 1.7 for Parsedown-Extra 0.8. Plugins that extend Parsedown may need a fix to render as HTML
    * Added `$grav['flex']` to access all registered Flex Directories
    * Added `$grav->dispatchEvent()` method for PSR-14 events
    * Added `FlexRegisterEvent` which triggers when `$grav['flex']` is being accessed the first time
    * Added Flex cache configuration options
    * Added `PluginsLoadedEvent` which triggers after plugins have been loaded but not yet initialized
    * Added `SessionStartEvent` which triggers when session is started
    * Added `PermissionsRegisterEvent` which triggers when `$grav['permissions']` is being accessed the first time
    * Added support for Flex Directory specific configuration
    * Added support for more advanced ACL
    * Added `flatten_array` filter to form field validation
    * Added support for `security@: or: [admin.super, admin.pages]` in blueprints (nested AND/OR mode support)
1. [](#improved)
    * Blueprint validation: Added `validate: value_type: bool|int|float|string|trim` to `array` to filter all the values inside the array
    * Twig `url()` takes now third parameter (`true`) to return URL on non-existing file instead of returning false
1. [](#bugfix)
    * Grav 1.7: Fixed blueprint loading issues [#2782](https://github.com/getgrav/grav/issues/2782)
    * Fixed PHP 7.4 compatibility issue with `Stream`
    * Fixed new `Flex Users` being stored with wrong filename, login issues [#2785](https://github.com/getgrav/grav/issues/2785)
    * Fixed `ignore_empty: true` not removing empty values in blueprint filtering
    * Fixed `{{ false|string }}` twig to return '0' instead of ''
    * Fixed twig `url()` failing if stream has extra slash in it (e.g. `user:///data`)
    * Fixed `Blueprint::filter()` returning null instead of array if there is nothing to return
    * Fixed `Cannot use a scalar value as an array` error in `Utils::arrayUnflattenDotNotation()`, ignore nested structure instead
    * Fixed `Route` instance in multi-site setups
    * Fixed `system.translations: false` breaking `Inflector` methods
    * Fixed filtering ignored (eg. `security@: admin.super`) fields causing `Flex Objects` to lose data on save
    * Grav 1.7: Fixed `Flex Pages` unserialize issues if Flex-Objects Plugin has not been installed
    * Grav 1.7: Require Flex-Objects Plugin to edit Flex Accounts
    * Grav 1.7: Fixed bad result on testing `isPage()` when using Flex Pages

# v1.7.0-rc.3
## 01/02/2020

1. [](#new)
    * Added root page support for `Flex Pages`
1. [](#improved)
    * Twig filter `|yaml_serialize`: added support for `JsonSerializable` objects and other array-like objects
    * Added support for returning Flex Page specific permissions for admin and testing
    * Updated copyright dates to `2020`
    * Various vendor updates
1. [](#bugfix)
    * Grav 1.7: Fixed error on page initialization [#2753](https://github.com/getgrav/grav/issues/2753)
    * Fixed checking ACL for another user (who is not currently logged in) in a Flex Object or Directory
    * Fixed bug in Windows where `Filesystem::dirname()` returns backslashes
    * Fixed Flex object issues in Windows [#2773](https://github.com/getgrav/grav/issues/2773)

# v1.7.0-rc.2
## 12/04/2019

1. [](#new)
    * Updated Symfony Components to 4.4
    * Added support for page specific CRUD permissions (`Flex Pages` only)
    * Added new `-r <job-id>` option for Scheduler CLI command to force-run a job [#2720](https://github.com/getgrav/grav/issues/2720)
    * Added `Utils::isAssoc()` and `Utils::isNegative()` helper methods
    * Changed `UserInterface::authorize()` to return `null` having the same meaning as `false` if access is denied because of no matching rule
    * Changed `FlexAuthorizeInterface::isAuthorized()` to return `null` having the same meaning as `false` if access is denied because of no matching rule
    * Moved all Flex type classes under `Grav\Common\Flex`
    * DEPRECATED `Grav\Common\User\Group` in favor of `$grav['user_groups']`, which contains Flex UserGroup collection
    * DEPRECATED `$page->modular()` in favor of `$page->isModule()` for better readability
    * Fixed phpstan issues in all code up to level 3
1. [](#improved)
    * Improved twig `|array` filter to work with iterators and objects with `toArray()` method
    * Updated Flex `SimpleStorage` code to feature match the other storages
    * Improved user and group ACL to support deny permissions (`Flex Users` only)
    * Improved twig `authorize()` function to work better with nested rule parameters
    * Output the current username that Scheduler is using if crontab not setup
    * Translations: rename MODULAR to MODULE everywhere
    * Optimized `Flex Pages` collection filtering
    * Frontend optimizations for `Flex Pages`
1. [](#bugfix)
    * Regression: Fixed Grav update bug [#2722](https://github.com/getgrav/grav/issues/2722)
    * Fixed fatal error when calling `{{ grav.undefined }}`
    * Grav 1.7: Reverted `$object->getStorageKey()` interface as it was not a good idea, added `getMasterKey()` for pages
    * Grav 1.7: Fixed logged in user being able to delete his own account from admin account manager

# v1.7.0-rc.1
## 11/06/2019

1. [](#new)
    * Added `Flex Pages` to Grav core and removed Flex Objects plugin dependency
    * Added `Utils::simpleTemplate()` method for very simple variable templating
    * Added `array_diff()` twig function
    * Added `template_from_string()` twig function
    * Updated Symfony Components to 4.3
1. [](#improved)
    * Improved `Scheduler` cron command check and more useful CLI information
    * Improved `Flex Users`: obey blueprints and allow Flex to be used in admin only
    * Improved `Flex` to support custom site template paths
    * Changed Twig `{% cache %}` tag to not need unique key, and `lifetime` is now optional
    * Added mime support for file formatters
    * Updated built-in `composer.phar` to latest `1.9.0`
    * Updated vendor libraries
    * Use `Symfony EventDispatcher` directly and not rockettheme/toolbox wrapper
1. [](#bugfix)
    * Fixed exception caused by missing template type based on `Accept:` header [#2705](https://github.com/getgrav/grav/issues/2705)
    * Fixed `Page::untranslatedLanguages()` not being symmetrical to `Page::translatedLanguages()`
    * Fixed `Flex Pages` not calling `onPageProcessed` event when cached
    * Fixed phpstan issues in Framework up to level 7
    * Fixed issue with duplicate configuration settings in Flex Directory
    * Fixed fatal error if there are numeric folders in `Flex Pages`
    * Fixed error on missing `Flex` templates in if `Flex Objects` plugin isn't installed
    * Fixed `PageTranslateTrait::getAllLanguages()` and `getAllLanguages()` to include default language
    * Fixed multi-language saving issues with default language in `Flex Pages`
    * Selfupgrade CLI: Fixed broken selfupgrade assets reference [#2681](https://github.com/getgrav/grav/issues/2681)
    * Grav 1.7: Fixed PHP 7.1 compatibility issues
    * Grav 1.7: Fixed fatal error in multi-site setups
    * Grav 1.7: Fixed `Flex Pages` routing if using translated slugs or `system.hide_in_urls` setting
    * Grav 1.7: Fixed bug where Flex index file couldn't be disabled

# v1.7.0-beta.10
## 10/03/2019

1. [](#improved)
    * Flex: Removed extra exists check when creating object (messes up "non-existing" pages)
    * Support customizable null character replacement in `CSVFormatter::decode()`
1. [](#bugfix)
    * Fixed wrong Grav param separator when using `Route` class
    * Fixed Flex User Avatar not fully backwards compatible with old user
    * Grav 1.7: Fixed prev/next page missing pages if pagination was turned on in page header
    * Grav 1.7: Reverted setting language for every page during initialization
    * Grav 1.7: Fixed numeric language inconsistencies

# v1.7.0-beta.9
## 09/26/2019

1. [](#new)
    * Added a new `{% cache %}` Twig tag eliminating need for `twigcache` extension.
1. [](#improved)
    * Improved blueprint initialization in Flex Objects (fixes content aware fields)
    * Improved Flex FolderStorage class to better hide storage specific logic
    * Exception will output a badly formatted line in `CsvFormatter::decode()`
1. [](#bugfix)
    * Fixed error when activating Flex Accounts in GRAV system configuration (PHP 7.1)
    * Fixed Grav parameter handling in `RouteFactory::createFromString()`

# v1.7.0-beta.8
## 09/19/2019

1. [](#new)
    * Added new `Security::sanitizeSVG()` function
    * Backwards compatibility break: `FlexStorageInterface::getStoragePath()` and `getMediaPath()` can now return null
1. [](#improved)
    * Several FlexObject loading improvements
    * Added `bin/grav page-system-validator [-r|--record] [-c|--check]` to test Flex Pages
    * Improved language support for `Route` class
1. [](#bugfix)
    * Regression: Fixed language fallback
    * Regression: Fixed translations when language code is used for non-language purposes
    * Regression: Allow SVG avatar images for users
    * Fixed error in `Session::getFlashObject()` if Flex Form is being used
    * Fixed broken Twig `dump()`
    * Fixed `Page::modular()` and `Page::modularTwig()` returning `null` for folders and other non-initialized pages
    * Fixed 404 error when you click to non-routable menu item with children: redirect to the first child instead
    * Fixed wrong `Pages::dispatch()` calls (with redirect) when we really meant to call `Pages::find()`
    * Fixed avatars not being displayed with flex users [#2431](https://github.com/getgrav/grav/issues/2431)
    * Fixed initial Flex Object state when creating a new objects in a form

# v1.7.0-beta.7
## 08/30/2019

1. [](#improved)
    * Improved language support
1. [](#bugfix)
    * `FlexForm`: Fixed some compatibility issues with Form plugin

# v1.7.0-beta.6
## 08/29/2019

1. [](#new)
    * Added experimental support for `Flex Pages` (**Flex Objects** plugin required)
1. [](#improved)
    * Improved `bin/grav yamllinter` CLI command by adding an option to find YAML Linting issues from the whole site or custom folder
    * Added support for not instantiating pages, useful to speed up tasks
    * Greatly improved speed of loading Flex collections
1. [](#bugfix)
    * Fixed `$page->summary()` always striping HTML tags if the summary was set by `$page->setSummary()`
    * Fixed `Flex->getObject()` when using Flex Key
    * Grav 1.7: Fixed enabling PHP Debug Bar causes fatal error in Gantry [#2634](https://github.com/getgrav/grav/issues/2634)
    * Grav 1.7: Fixed broken taxonomies [#2633](https://github.com/getgrav/grav/issues/2633)
    * Grav 1.7: Fixed unpublished blog posts being displayed on the front-end [#2650](https://github.com/getgrav/grav/issues/2650)

# v1.7.0-beta.5
## 08/11/2019

1. [](#new)
    * Added a new `bin/grav server` CLI command to easily run Symfony or PHP built-in webservers
    * Added `hasFlexFeature()` method to test if `FlexObject` or `FlexCollection` implements a given feature
    * Added `getFlexFeatures()` method to return all features that `FlexObject` or `FlexCollection` implements
    * DEPRECATED `FlexDirectory::update()` and `FlexDirectory::remove()`
    * Added `FlexStorage::getMetaData()` to get updated object meta information for listed keys
    * Added `Language::getPageExtensions()` to get full list of supported page language extensions
    * Added `$grav->close()` method to properly terminate the request with a response
    * Added `Pages::getCollection()` method
1. [](#improved)
    * Better support for Symfony local server `symfony server:start`
    * Make `Route` objects immutable
    * `FlexDirectory::getObject()` can now be called without any parameters to create a new object
    * Flex objects no longer return temporary key if they do not have one; empty key is returned instead
    * Updated vendor libraries
    * Moved `collection()` and `evaluate()` logic from `Page` class into `Pages` class
1. [](#bugfix)
    * Fixed `Form` not to use deleted flash object until the end of the request fixing issues with reset
    * Fixed `FlexForm` to allow multiple form instances with non-existing objects
    * Fixed `FlexObject` search by using `key`
    * Grav 1.7: Fixed clockwork messages with arrays and objects

# v1.7.0-beta.4
## 07/01/2019

1. [](#new)
    * Updated with Grav 1.6.12 features, improvements & fixes
    * Added new configuration option `system.debugger.censored` to hide potentially sensitive information
    * Added new configuration option `system.languages.include_default_lang_file_extension` to keep default language in `.md` files if set to `false`
    * Added configuration option to set fallback content languages individually for every language
1. [](#improved)
    * Updated Vendor libraries
1. [](#bugfix)
    * Fixed `.md` page to be assigned to the default language and to be listed in translated/untranslated page list
    * Fixed `Language::getFallbackPageExtensions()` to fall back only to default language instead of going through all languages
    * Fixed `Language::getFallbackPageExtensions()` returning wrong file extensions when passing custom page extension

# v1.7.0-beta.3
## 06/24/2019

1. [](#bugfix)
    * Fixed Clockwork on Windows machines
    * Fixed parent field issues on Windows machines
    * Fixed unreliable Clockwork calls in sub-folders

# v1.7.0-beta.2
## 06/21/2019

1. [](#new)
    * Updated with Grav 1.6.11 fixes
1. [](#improved)
    * Updated the Clockwork text

# v1.7.0-beta.1
## 06/14/2019

1. [](#new)
    * Added support for [Clockwork](https://underground.works/clockwork) developer tools (now default debugger)
    * Added support for [Tideways XHProf](https://github.com/tideways/php-xhprof-extension) PHP Extension for profiling method calls
    * Added Twig profiling for Clockwork debugger
    * Added support for Twig 2.11 (compatible with Twig 1.40+)
    * Optimization: Initialize debugbar only after the configuration has been loaded
    * Optimization: Combine some early Grav processors into a single one

# v1.6.31
## 12/14/2020

1. [](#improved)
    * Allow all CSS and JS via `robots.txt` [#2006](https://github.com/getgrav/grav/issues/2006) [#3067](https://github.com/getgrav/grav/issues/3067)
1. [](#bugfix)
    * Fixed `pages` field escaping issues, needs admin update, too [admin#1990](https://github.com/getgrav/grav-plugin-admin/issues/1990)
    * Fix `svg-image` issue with classes applied to all elements [#3068](https://github.com/getgrav/grav/issues/3068)

# v1.6.30
## 12/03/2020

1. [](#bugfix)
    * Rollback `samesite` cookie logic as it causes issues with PHP < 7.3 [#309](https://github.com/getgrav/grav/issues/3089)
    * Fixed issue with `.travis.yml` due to GitHub API deprecated functionality

# v1.6.29
## 12/02/2020

1. [](#new)
    * Added basic support for `user/config/versions.yaml`
1. [](#improved)
    * Updated bundled JQuery to latest version `3.5.1`
    * Forward a `sid` to GPM when downloading a premium package via CLI
    * Better handling of missing repository index [grav-plugin-admin#1916](https://github.com/getgrav/grav-plugin-admin/issues/1916)
    * Set `grav_cli` as referrer when using `Response` from CLI
    * Add option for timeout in `self-upgrade` command [#3013](https://github.com/getgrav/grav/pull/3013)
    * Allow to set SameSite from system.yaml [#3063](https://github.com/getgrav/grav/pull/3063)
    * Update media.yaml with some MS Office mimetypes [#3070](https://github.com/getgrav/grav/pull/3070)
1. [](#bugfix)
    * Fixed hardcoded system folder in blueprints, config and language streams
    * Added `.htaccess` rule to block attempts to use Twig in the request URL
    * Fix compatibility with Symfony 4.2 and up. [#3048](https://github.com/getgrav/grav/pull/3048)
    * Fix failing example custom shceduled job. [#3050](https://github.com/getgrav/grav/pull/3050)
    * Fix for XSS advisory [GHSA-cvmr-6428-87w9](https://github.com/getgrav/grav/security/advisories/GHSA-cvmr-6428-87w9)
    * Fix uploads_dangerous_extensions checking [#3060](https://github.com/getgrav/grav/pull/3060)
    * Remove redundant prefixing of `.` to extension [#3060](https://github.com/getgrav/grav/pull/3060)
    * Check exact extension in checkFilename utility [#3061](https://github.com/getgrav/grav/pull/3061)

# v1.6.28
## 10/07/2020

1. [](#new)
    * Back-ported twig `{% cache %}` tag from Grav 1.7
    * Back-ported `Utils::fullPath()` helper function from Grav 1.7
    * Back-ported `{{ svg_image() }}` Twig function from Grav 1.7
    * Back-ported `Folder::countChildren()` function from Grav 1.7
1. [](#improved)
    * Use new `{{ theme_var() }}` enhanced logic from Grav 1.7
    * Improved `Excerpts` class with fixes and functionality from Grav 1.7
    * Ensure `onBlueprintCreated()` is initialized first
    * Do not cache default `404` error page
    * Composer update of vendor libraries
    * Switched `Caddyfile` to use new Caddy2 syntax + improved usability
1. [](#bugfix)
    * Fixed Referer reference during GPM calls.
    * Fixed fatal error with toggled lists

# v1.6.27
## 09/01/2020

1. [](#improved)
    * Right trim route for safety
    * Use the proper ellipsis for summary [#2939](https://github.com/getgrav/grav/pull/2939)
    * Left pad schedule times with zeros [#2921](https://github.com/getgrav/grav/pull/2921)

# v1.6.26
## 06/08/2020

1. [](#improved)
    * Added new configuration option to control the supported attributes in markdown links [#2882](https://github.com/getgrav/grav/issues/2882)
1. [](#bugfix)
    * Fixed blueprint for `system.pages.hide_empty_folders` [#1925](https://github.com/getgrav/grav/issues/2925)
    * JSON Route of homepage with no ‘route’ set is valid
    * Fix case-insensitive search of location header [form#425](https://github.com/getgrav/grav-plugin-form/issues/425)

# v1.6.25
## 05/14/2020

1. [](#improved)
    * Added system configuration support for `HTTP_X_Forwarded` headers (host disabled by default)
    * Updated `PHPUserAgentParser` to 1.0.0
    * Bump `Go` to version 1.13 in `travis.yaml`

# v1.6.24
## 04/27/2020

1. [](#improved)
    * Added support for `X-Forwarded-Host` [#2891](https://github.com/getgrav/grav/pull/2891)
    * Disable XDebug in Travis builds

# v1.6.23
## 03/19/2020

1. [](#new)
    * Moved `Parsedown` 1.6 and `ParsedownExtra` 0.7 into `Grav\Framework\Parsedown` to allow fixes
    * Added `aliases.php` with references to direct `\Parsedown` and `\ParsedownExtra` references
1. [](#improved)
    * Upgraded `jQuery` to latest 3.4.1 version [#2859](https://github.com/getgrav/grav/issues/2859)
1. [](#bugfix)
    * Fixed PHP 7.4 issue in ParsedownExtra [#2832](https://github.com/getgrav/grav/issues/2832)
    * Fix for [user reported](https://twitter.com/OriginalSicksec) CVE path-based open redirect
    * Fix for `stream_set_option` error with PHP 7.4 via Toolbox#28 [#2850](https://github.com/getgrav/grav/issues/2850)

# v1.6.22
## 03/05/2020

1. [](#new)
    * Added `Pages::reset()` method
1. [](#improved)
    * Updated Negotiation library to address issues [#2513](https://github.com/getgrav/grav/issues/2513)
1. [](#bugfix)
    * Fixed issue with search plugins not being able to switch between page translations
    * Fixed issues with `Pages::baseRoute()` not picking up active language reliably
    * Reverted `validation: strict` fix as it breaks sites, see [#1273](https://github.com/getgrav/grav/issues/1273)

# v1.6.21
## 02/11/2020

1. [](#new)
    * Added `ConsoleCommand::setLanguage()` method to set language to be used from CLI
    * Added `ConsoleCommand::initializeGrav()` method to properly set up Grav instance to be used from CLI
    * Added `ConsoleCommand::initializePlugins()`method to properly set up all plugins to be used from CLI
    * Added `ConsoleCommand::initializeThemes()`method to properly set up current theme to be used from CLI
    * Added `ConsoleCommand::initializePages()` method to properly set up pages to be used from CLI
1. [](#improved)
    * Vendor updates
1. [](#bugfix)
    * Fixed `bin/plugin` CLI calling `$themes->init()` way too early (removed it, use above methods instead)
    * Fixed call to `$grav['page']` crashing CLI
    * Fixed encoding problems when PHP INI setting `default_charset` is not `utf-8` [#2154](https://github.com/getgrav/grav/issues/2154)

# v1.6.20
## 02/03/2020

1. [](#bugfix)
    * Fixed incorrect routing caused by `str_replace()` in `Uri::init()` [#2754](https://github.com/getgrav/grav/issues/2754)
    * Fixed session cookie is being set twice in the HTTP header [#2745](https://github.com/getgrav/grav/issues/2745)
    * Fixed session not restarting if user was invalid (downgrading from Grav 1.7)
    * Fixed filesystem iterator calls with non-existing folders
    * Fixed `checkbox` field not being saved, requires also Form v4.0.2 [#1225](https://github.com/getgrav/grav/issues/1225)
    * Fixed `validation: strict` not working in blueprints [#1273](https://github.com/getgrav/grav/issues/1273)
    * Fixed `Data::filter()` removing empty fields (such as empty list) by default [#2805](https://github.com/getgrav/grav/issues/2805)
    * Fixed fatal error with non-integer page param value [#2803](https://github.com/getgrav/grav/issues/2803)
    * Fixed `Assets::addInlineJs()` parameter type mismatch between v1.5 and v1.6 [#2659](https://github.com/getgrav/grav/issues/2659)
    * Fixed `site.metadata` saving issues [#2615](https://github.com/getgrav/grav/issues/2615)

# v1.6.19
## 12/04/2019

1. [](#new)
    * Catch PHP 7.4 deprecation messages and report them in debugbar instead of throwing fatal error
1. [](#bugfix)
    * Fixed fatal error when calling `{{ grav.undefined }}`
    * Fixed multiple issues when there are no pages in the site
    * PHP 7.4 fix for [#2750](https://github.com/getgrav/grav/issues/2750)

# v1.6.18
## 12/02/2019

1. [](#bugfix)
    * PHP 7.4 fix in `Pages::buildSort()`
    * Updated vendor libraries for PHP 7.4 fixes in Twig and other libraries
    * Fixed fatal error when `$page->id()` is null [#2731](https://github.com/getgrav/grav/pull/2731)
    * Fixed cache conflicts on pages with no set id
    * Fix rewrite rule for for `lighttpd` default config [#721](https://github.com/getgrav/grav/pull/2721)

# v1.6.17
## 11/06/2019

1. [](#new)
    * Added working ETag (304 Not Modified) support based on the final rendered HTML
1. [](#improved)
    * Safer file handling + customizable null char replacement in `CsvFormatter::decode()`
    * Change of Behavior: `Inflector::hyphenize` will now automatically trim dashes at beginning and end of a string.
    * Change in Behavior for `Folder::all()` so no longer fails if trying to copy non-existent dot file [#2581](https://github.com/getgrav/grav/pull/2581)
    * renamed composer `test-plugins` script to `phpstan-plugins` to be more explicit [#2637](https://github.com/getgrav/grav/pull/2637)
1. [](#bugfix)
    * Fixed PHP 7.1 bug in FlexMedia
    * Fix cache image generation when using cropResize [#2639](https://github.com/getgrav/grav/pull/2639)
    * Fix `array_merge()` exception with non-array page header metadata [#2701](https://github.com/getgrav/grav/pull/2701)

# v1.6.16
## 09/19/2019

1. [](#bugfix)
    * Fixed Flex user creation if file storage is being used [#2444](https://github.com/getgrav/grav/issues/2444)
    * Fixed `Badly encoded JSON data` warning when uploading files [#2663](https://github.com/getgrav/grav/issues/2663)

# v1.6.15
## 08/20/2019

1. [](#improved)
    * Improved robots.txt [#2632](https://github.com/getgrav/grav/issues/2632)
1. [](#bugfix)
    * Fixed broken markdown Twig tag [#2635](https://github.com/getgrav/grav/issues/2635)
    * Force Symfony 4.2 in Grav 1.6 to remove a bunch of deprecated messages

# v1.6.14
## 08/18/2019

1. [](#bugfix)
    * Actually include fix for `system\router.php` [#2627](https://github.com/getgrav/grav/issues/2627)

# v1.6.13
## 08/16/2019

1. [](#bugfix)
    * Regression fix for `system\router.php` [#2627](https://github.com/getgrav/grav/issues/2627)

# v1.6.12
## 08/14/2019

1. [](#new)
    * Added support for custom `FormFlash` save locations
    * Added a new `Utils::arrayLower()` method for lowercasing arrays
    * Support new GRAV_BASEDIR environment variable [#2541](https://github.com/getgrav/grav/pull/2541)
    * Allow users to override plugin handler priorities [#2165](https://github.com/getgrav/grav/pull/2165)
1. [](#improved)
    * Use new `Utils::getSupportedPageTypes()` to enforce `html,htm` at the front of the list [#2531](https://github.com/getgrav/grav/issues/2531)
    * Updated vendor libraries
    * Markdown filter is now page-aware so that it works with modular references [admin#1731](https://github.com/getgrav/grav-plugin-admin/issues/1731)
    * Check of `GRAV_USER_INSTANCE` constant is already defined [#2621](https://github.com/getgrav/grav/pull/2621)
1. [](#bugfix)
    * Fixed some potential issues when `$grav['user']` is not set
    * Fixed error when calling `Media::add($name, null)`
    * Fixed `url()` returning wrong path if using stream with grav root path in it, eg: `user-data://shop` when Grav is in `/shop`
    * Fixed `url()` not returning a path to non-existing file (`user-data://shop` => `/user/data/shop`) if it is set to fail gracefully
    * Fixed `url()` returning false on unknown streams, such as `ftp://domain.com`, they should be treated as external URL
    * Fixed Flex User to have permissions to save and delete his own user
    * Fixed new Flex User creation not being possible because of username could not be given
    * Fixed fatal error 'Expiration date must be an integer, a DateInterval or null, "double" given' [#2529](https://github.com/getgrav/grav/issues/2529)
    * Fixed non-existing Flex object having a bad media folder
    * Fixed collections using `page@.self:` should allow modular pages if requested
    * Fixed an error when trying to delete a file from non-existing Flex Object
    * Fixed `FlexObject::exists()` failing sometimes just after the object has been saved
    * Fixed CSV formatter not encoding strings with `"` and `,` properly
    * Fixed var order in `Validation.php` [#2610](https://github.com/getgrav/grav/issues/2610)

# v1.6.11
## 06/21/2019

1. [](#new)
    * Added `FormTrait::getAllFlashes()` method to get all the available form flash objects for the form
    * Added creation and update timestamps to `FormFlash` objects
1. [](#improved)
    * Added `FormFlashInterface`, changed constructor to take `$config` array
1. [](#bugfix)
    * Fixed error in `ImageMedium::url()` if the image cache folder does not exist
    * Fixed empty form flash name after file upload or form state update
    * Fixed a bug in `Route::withParam()` method
    * Fixed issue with `FormFlash` objects when there is no session initialized

# v1.6.10
## 06/14/2019

1. [](#improved)
    * Added **page blueprints** to `YamlLinter` CLI and Admin reports
    * Removed `Gitter` and `Slack` [#2502](https://github.com/getgrav/grav/issues/2502)
    * Optimizations for Plugin/Theme loading
    * Generalized markdown classes so they can be used outside of `Page` scope with a custom `Excerpts` class instance
    * Change minimal port number to 0 (unix socket) [#2452](https://github.com/getgrav/grav/issues/2452)
1. [](#bugfix)
    * Force question to install demo content in theme update [#2493](https://github.com/getgrav/grav/issues/2493)
    * Fixed GPM errors from blueprints not being logged [#2505](https://github.com/getgrav/grav/issues/2505)
    * Don't error when IP is invalid [#2507](https://github.com/getgrav/grav/issues/2507)
    * Fixed regression with `bin/plugin` not listing the plugins available (1c725c0)
    * Fixed bitwise operator in `TwigExtension::exifFunc()` [#2518](https://github.com/getgrav/grav/issues/2518)
    * Fixed issue with lang prefix incorrectly identifying as admin [#2511](https://github.com/getgrav/grav/issues/2511)
    * Fixed issue with `U0ils::pathPrefixedBYLanguageCode()` and trailing slash [#2510](https://github.com/getgrav/grav/issues/2511)
    * Fixed regresssion issue of `Utils::Url()` not returning `false` on failure. Added new optional `fail_gracefully` 3rd attribute to return string that caused failure [#2524](https://github.com/getgrav/grav/issues/2524)

# v1.6.9
## 05/09/2019

1. [](#new)
    * Added `Route::withoutParams()` methods
    * Added `Pages::setCheckMethod()` method to override page configuration in Admin Plugin
    * Added `Cache::clearCache('invalidate')` parameter for just invalidating the cache without deleting any cached files
    * Made `UserCollectionInderface` to extend `Countable` to get the count of existing users
1. [](#improved)
    * Flex admin: added default search options for flex objects
    * Flex collection and object now fall back to the default template if template file doesn't exist
    * Updated Vendor libraries including Twig 1.40.1
    * Updated language files from `https://crowdin.com/project/grav-core`
1. [](#bugfix)
    * Fixed `$grav['route']` from being modified when the route instance gets modified
    * Fixed Assets options array mixed with standalone priority [#2477](https://github.com/getgrav/grav/issues/2477)
    * Fix for `avatar_url` provided by 3rd party providers
    * Fixed non standard `lang` code lengths in `Utils` and `Session` detection
    * Fixed saving a new object in Flex `SimpleStorage`
    * Fixed exception in `Flex::getDirectories()` if the first parameter is set
    * Output correct "Last Updated" in `bin/gpm info` command
    * Checkbox getting interpreted as string, so created new `Validation::filterCheckbox()`
    * Fixed backwards compatibility to `select` field with `selectize.create` set to true [git-sync#141](https://github.com/trilbymedia/grav-plugin-git-sync/issues/141)
    * Fixed `YamlFormatter::decode()` to always return array [#2494](https://github.com/getgrav/grav/pull/2494)
    * Fixed empty `$grav['request']->getAttribute('route')->getExtension()`

# v1.6.8
## 04/23/2019

1. [](#new)
    * Added `FlexCollection::filterBy()` method
1. [](#bugfix)
    * Revert `Use Null Coalesce Operator` [#2466](https://github.com/getgrav/grav/pull/2466)
    * Fixed `FormTrait::render()` not providing config variable
    * Updated `bin/grav clean` to clear `cache/compiled` and `user/config/security.yaml`

# v1.6.7
## 04/22/2019

1. [](#new)
    * Added a new `bin/grav yamllinter` CLI command to find YAML Linting issues [#2468](https://github.com/getgrav/grav/issues/2468#issuecomment-485151681)
1. [](#improved)
    * Improve `FormTrait` backwards compatibility with existing forms
    * Added a new `Utils::getSubnet()` function for IPv4/IPv6 parsing [#2465](https://github.com/getgrav/grav/pull/2465)
1. [](#bugfix)
    * Remove disabled fields from the form schema
    * Fix issue when excluding `inlineJs` and `inlineCss` from Assets pipeline [#2468](https://github.com/getgrav/grav/issues/2468)
    * Fix for manually set position on external URLs [#2470](https://github.com/getgrav/grav/issues/2470)

# v1.6.6
## 04/17/2019

1. [](#new)
    * `FormInterface` now implements `RenderInterface`
    * Added new `FormInterface::getTask()` method which reads the task from `form.task` in the blueprint
1. [](#improved)
    * Updated vendor libraries to latest
1. [](#bugfix)
    * Rollback `redirect_default_route` logic as it has issues with multi-lang [#2459](https://github.com/getgrav/grav/issues/2459)
    * Fix potential issue with `|contains` Twig filter on PHP 7.3
    * Fixed bug in text field filtering: return empty string if value isn't a string or number [#2460](https://github.com/getgrav/grav/issues/2460)
    * Force Asset `priority` to be an integer and not throw error if invalid string passed [#2461](https://github.com/getgrav/grav/issues/2461)
    * Fixed bug in text field filtering: return empty string if value isn't a string or number
    * Fixed `FlexForm` missing getter methods for defining form variables

# v1.6.5
## 04/15/2019

1. [](#bugfix)
    * Backwards compatiblity with old `Uri::__toString()` output

# v1.6.4
## 04/15/2019

1. [](#bugfix)
    * Improved `redirect_default_route` logic as well as `Uri::toArray()` to take into account `root_path` and `extension`
    * Rework logic to pull out excluded files from pipeline more reliably [#2445](https://github.com/getgrav/grav/issues/2445)
    * Better logic in `Utils::normalizePath` to handle externals properly [#2216](https://github.com/getgrav/grav/issues/2216)
    * Fixed to force all `Page::taxonomy` to be treated as strings [#2446](https://github.com/getgrav/grav/issues/2446)
    * Fixed issue with `Grav['user']` not being available [form#332](https://github.com/getgrav/grav-plugin-form/issues/332)
    * Updated rounding logic for `Utils::parseSize()` [#2394](https://github.com/getgrav/grav/issues/2394)
    * Fixed Flex simple storage not being properly initialized if used with caching

# v1.6.3
## 04/12/2019

1. [](#new)
    * Added `Blueprint::addDynamicHandler()` method to allow custom dynamic handlers, for example `custom-options@: getCustomOptions`
1. [](#bugfix)
    * Missed a `CacheCommand` reference in `bin/grav` [#2442](https://github.com/getgrav/grav/issues/2442)
    * Fixed issue with `Utils::normalizePath` messing with external URLs [#2216](https://github.com/getgrav/grav/issues/2216)
    * Fix for `vUndefined` versions when upgrading

# v1.6.2
## 04/11/2019

1. [](#bugfix)
    * Revert renaming of `ClearCacheCommand` to ensure CLI GPM upgrades go smoothly

# v1.6.1
## 04/11/2019

1. [](#improved)
    * Improved CSS for the bottom filter bar of DebugBar
1. [](#bugfix)
    * Fixed issue with `@import` not being added to top of pipelined css [#2440](https://github.com/getgrav/grav/issues/2440)

# v1.6.0
## 04/11/2019

1. [](#new)
    * Set minimum requirements to [PHP 7.1.3](https://getgrav.org/blog/raising-php-requirements-2018)
    * New `Scheduler` functionality for periodic jobs
    * New `Backup` functionality with multiple backup profiles and scheduler integration
    * Refactored `Assets Manager` to be more powerful and flexible
    * Updated Doctrine Collections to 1.6
    * Updated Doctrine Cache to 1.8
    * Updated Symfony Components to 4.2
    * Added new Cache purge functionality old cache manually via CLI/Admin as well as scheduler integration
    * Added new `{% throw 404 'Not Found' %}` twig tag (with custom code/message)
    * Added `Grav\Framework\File` classes for handling YAML, Markdown, JSON, INI and PHP serialized files
    * Added `Grav\Framework\Collection\AbstractIndexCollection` class
    * Added `Grav\Framework\Object\ObjectIndex` class
    * Added `Grav\Framework\Flex` classes
    * Added support for hiding form fields in blueprints by using dynamic property like `security@: admin.foobar`, `scope@: object` or `scope-ignore@: object` to any field
    * New experimental **FlexObjects** powered `Users` for increased performance and capability (**disabled** by default)
    * Added PSR-7 and PSR-15 classes
    * Added `Grav\Framework\DI\Container` class
    * Added `Grav\Framework\RequestHandler\RequestHandler` class
    * Added `Page::httpResponseCode()` and `Page::httpHeaders()` methods
    * Added `Grav\Framework\Form\Interfaces\FormInterface`
    * Added `Grav\Framework\Form\Interfaces\FormFactoryInterface`
    * Added `Grav\Framework\Form\FormTrait`
    * Added `Page::forms()` method to get normalized list of all form headers defined in the page
    * Added `onPageAction`, `onPageTask`, `onPageAction.{$action}` and `onPageTask.{$task}` events
    * Added `Blueprint::processForm()` method to filter form inputs
    * Move `processMarkdown()` method from `TwigExtension` to more general `Utils` class
    * Added support to include extra files into `Media` (such as uploaded files)
    * Added form preview support for `FlexObject`, including a way to render newly uploaded files before saving them
    * Added `FlexObject::getChanges()` to determine what fields change during an update
    * Added `arrayDiffMultidimensional`, `arrayIsAssociative`, `arrayCombine` Util functions
    * New `$grav['users']` service to allow custom user classes implementing `UserInterface`
    * Added `LogViewer` helper class and CLI command: `bin/grav logviewer`
    * Added `select()` and `unselect()` methods to `CollectionInterface` and its base classes
    * Added `orderBy()` and `limit()` methods to `ObjectCollectionInterface` and its base classes
    * Added `user-data://` which is a writable stream (`user://data` is not and should be avoided)
    * Added support for `/action:{$action}` (like task but used without nonce when only receiving data)
    * Added `onAction.{$action}` event
    * Added `Grav\Framework\Form\FormFlash` class to contain AJAX uploaded files in more reliable way
    * Added `Grav\Framework\Form\FormFlashFile` class which implements `UploadedFileInterface` from PSR-7
    * Added `Grav\Framework\Filesystem\Filesystem` class with methods to manipulate stream URLs
    * Added new `$grav['filesystem']` service using an instance of the new `Filesystem` object
    * Added `{% render object layout: 'default' with { variable: true } %}` for Flex objects and collections
    * Added `$grav->setup()` to simplify CLI and custom access points
    * Added `CsvFormatter` and `CsvFile` classes
    * Added new system config option to `pages.hide_empty_folders` if a folder has no valid `.md` file available. Default behavior is `false` for compatibility.
    * Added new system config option for `languages.pages_fallback_only` forcing only 'fallback' to find page content through supported languages, default behavior is to display any language found if active language is missing
    * Added `Utils::arrayFlattenDotNotation()` and `Utils::arrayUnflattenDotNotation()` helper methods
1. [](#improved)
    * Add the page to onMarkdownInitialized event [#2412](https://github.com/getgrav/grav/issues/2412)
    * Doctrine filecache is now namespaced with prefix to support purging
    * Register all page types into `blueprint://pages` stream
    * Removed `apc` and `xcache` support, made `apc` alias of `apcu`
    * Support admin and regular translations via the `|t` twig filter and `t()` twig function
    * Improved Grav Core installer/updater to run installer script
    * Updated vendor libraries including Symfony `4.2.3`
    * Renamed old `User` class to `Grav\Common\User\DataUser\User` with multiple improvements and small fixes
    * `User` class now acts as a compatibility layer to older versions of Grav
    * Deprecated `new User()`, `User::load()`, `User::find()` and `User::delete()` in favor of `$grav['users']` service
    * `Media` constructor has now support to not to initialize the media objects
    * Cleanly handle session corruption due to changing Flex object types
    * Added `FlexObjectInterface::getDefaultValue()` and `FormInterface::getDefaultValue()`
    * Added new `onPageContent()` event for every call to `Page::content()`
    * Added phpstan: PHP Static Analysis Tool [#2393](https://github.com/getgrav/grav/pull/2393)
    * Added `composer test-plugins` to test plugin issues with the current version of Grav
    * Added `Flex::getObjects()` and `Flex::getMixedCollection()` methods for co-mingled collections
    * Added support to use single Flex key parameter in `Flex::getObject()` method
    * Added `FlexObjectInterface::search()` and `FlexCollectionInterface::search()` methods
    * Override `system.media.upload_limit` with PHP's `post_max_size` or `upload_max_filesize`
    * Class `Grav\Common\Page\Medium\AbstractMedia` now use array traits instead of extending `Grav\Common\Getters`
    * Implemented `Grav\Framework\Psr7` classes as `Nyholm/psr7` decorators
    * Added a new `cache-clear` scheduled job to go along with `cache-purge`
    * Renamed `Grav\Framework\File\Formatter\FormatterInterface` to `Grav\Framework\File\Interfaces\FileFormatterInterface`
    * Improved `File::save()` to use a temporary file if file isn't locked
    * Improved `|t` filter to better support admin `|tu` style filter if in admin
    * Update all classes to rely on `PageInterface` instead of `Page` class
    * Better error checking in `bin/plugin` for existence and enabled
    * Removed `media.upload_limit` references
    * Twig `nicenumber`: do not use 0 + string casting hack
    * Converted Twig tags to use namespaced Twig classes
    * Site shows error on page rather than hard-crash when page has invalid frontmatter [#2343](https://github.com/getgrav/grav/issues/2343)
    * Added `languages.default_lang` option to override the default lang (usually first supported language)
    * Added `Content-Type: application/json` body support for PSR-7 `ServerRequest`
    * Remove PHP time limit in `ZipArchive`
    * DebugBar: Resolve twig templates in deprecated backtraces in order to help locating Twig issues
    * Added `$grav['cache']->getSimpleCache()` method for getting PSR-16 compatible cache
    * MediaTrait: Use PSR-16 cache
    * Improved `Utils::normalizePath()` to support non-protocol URLs
    * Added ability to reset `Page::metadata` to allow rebuilding from automatically generated values
    * Added back missing `page.types` field in system content configuration [admin#1612](https://github.com/getgrav/grav-plugin-admin/issues/1612)
    * Console commands: add method for invalidating cache
    * Updated languages
    * Improved `$page->forms()` call, added `$page->addForms()`
    * Updated languages from crowdin
    * Fixed `ImageMedium` constructor warning when file does not exist
    * Improved `Grav\Common\User` class; added `$user->update()` method
    * Added trim support for text input fields `validate: trim: true`
    * Improved `Grav\Framework\File\Formatter` classes to have abstract parent class and some useful methods
    * Support negotiated content types set via the Request `Accept:` header
    * Support negotiated language types set via the Request `Accept-Language:` header
    * Cleaned up and sorted the Service `idMap`
    * Updated `Grav` container object to implement PSR-11 `ContainerInterface`
    * Updated Grav `Processor` classes to implement PSR-15 `MiddlewareInterface`
    * Make `Data` class to extend `JsonSerializable`
    * Modified debugger icon to use retina space-dude version
    * Added missing `Video::preload()` method
    * Set session name based on `security.salt` rather than `GRAV_ROOT` [#2242](https://github.com/getgrav/grav/issues/2242)
    * Added option to configure list of `xss_invalid_protocols` in `Security` config [#2250](https://github.com/getgrav/grav/issues/2250)
    * Smarter `security.salt` checking now we use `security.yaml` for other options
    * Added apcu autoloader optimization
    * Additional helper methods in `Language`, `Languages`, and `LanguageCodes` classes
    * Call `onFatalException` event also on internal PHP errors
    * Built-in PHP Webserver: log requests before handling them
    * Added support for syslog and syslog facility logging (default: 'file')
    * Improved usability of `System` configuration blueprint with side-tabs
 1. [](#bugfix)
    * Fixed issue with `Truncator::truncateWords` and `Truncator::truncateLetters` when string not wrapped in tags [#2432](https://github.com/getgrav/grav/issues/2432)
    * Fixed `Undefined method closure::fields()` when getting avatar for user, thanks @Romarain [#2422](https://github.com/getgrav/grav/issues/2422)
    * Fixed cached images not being updated when source image is modified
    * Fixed deleting last list item in the form
    * Fixed issue with `Utils::url()` method would append extra `base_url` if URL already included it
    * Fixed `mkdir(...)` race condition
    * Fixed `Obtaining write lock failed on file...`
    * Fixed potential undefined property in `onPageNotFound` event handling
    * Fixed some potential issues/bugs found by phpstan
    * Fixed regression in GPM packages casted to Array (ref, getgrav/grav-plugin-admin@e3fc4ce)
    * Fixed session_start(): Setting option 'session.name' failed [#2408](https://github.com/getgrav/grav/issues/2408)
    * Fixed validation for select field type with selectize
    * Fixed validation for boolean toggles
    * Fixed non-namespaced exceptions in scheduler
    * Fixed trailing slash redirect in multlang environment [#2350](https://github.com/getgrav/grav/issues/2350)
    * Fixed some issues related to Medium objects losing query string attributes
    * Broke out Medium timestamp so it's not cleared on `reset()`s
    * Fixed issue with `redirect_trailing_slash` losing query string [#2269](https://github.com/getgrav/grav/issues/2269)
    * Fixed failed login if user attempts to log in with upper case non-english letters
    * Removed extra authenticated/authorized fields when saving existing user from a form
    * Fixed `Grav\Framework\Route::__toString()` returning relative URL, not relative route
    * Fixed handling of `append_url_extension` inside of `Page::templateFormat()` [#2264](https://github.com/getgrav/grav/issues/2264)
    * Fixed a broken language string [#2261](https://github.com/getgrav/grav/issues/2261)
    * Fixed clearing cache having no effect on Doctrine cache
    * Fixed `Medium::relativePath()` for streams
    * Fixed `Object` serialization breaking if overriding `jsonSerialize()` method
    * Fixed `YamlFormatter::decode()` when calling `init_set()` with integer
    * Fixed session throwing error in CLI if initialized
    * Fixed `Uri::hasStandardPort()` to support reverse proxy configurations [#1786](https://github.com/getgrav/grav/issues/1786)
    * Use `append_url_extension` from page header to set template format if set [#2604](https://github.com/getgrav/grav/pull/2064)
    * Fixed some bugs in Grav environment selection logic
    * Use login provider User avatar if set
    * Fixed `Folder::doDelete($folder, false)` removing symlink when it should not
    * Fixed asset manager to not add empty assets when they don't exist in the filesystem
    * Update `script` and `style` Twig tags to use the new `Assets` classes
    * Fixed asset pipeline to rewrite remote URLs as well as local [#2216](https://github.com/getgrav/grav/issues/2216)

# v1.5.10
## 03/21/2019

1. [](#new)
    * Added new `deferred` Twig extension

# v1.5.9
## 03/20/2019

1. [](#new)
    * Added new `onPageContent()` event for every call to `Page::content()`
1. [](#improved)
    * Fixed phpdoc generation
    * Updated vendor libraries
    * Force Toolbox v1.4.2
1. [](#bugfix)
    * EXIF fix for streams
    * Fix for User avatar not working due to uppercase or spaces in email [#2403](https://github.com/getgrav/grav/pull/2403)

# v1.5.8
## 02/07/2019

1. [](#improved)
    * Improved `User` unserialize to not to break the object if serialized data is not what expected
    * Removed unused parameter [#2357](https://github.com/getgrav/grav/pull/2357)

# v1.5.7
## 01/25/2019

1. [](#new)
    * Support for AWS Cloudfront forwarded scheme header [#2297](https://github.com/getgrav/grav/pull/2297)
1. [](#improved)
    * Set homepage with `https://` protocol [#2299](https://github.com/getgrav/grav/pull/2299)
    * Preserve accents in fields containing Twig expr. using unicode [#2279](https://github.com/getgrav/grav/pull/2279)
    * Updated vendor libraries
1. [](#bugfix)
    * Support spaces with filenames in responsive images [#2300](https://github.com/getgrav/grav/pull/2300)

# v1.5.6
## 12/14/2018

1. [](#improved)
    * Updated InitializeProcessor.php to use lang-safe redirect [#2268](https://github.com/getgrav/grav/pull/2268)
    * Improved user serialization to use less memory in the session

# v1.5.5
## 11/12/2018

1. [](#new)
    * Register theme prefixes as namespaces in Twig [#2210](https://github.com/getgrav/grav/pull/2210)
1. [](#improved)
    * Propogate error code between 400 and 600 for production sites [#2181](https://github.com/getgrav/grav/pull/2181)
1. [](#bugfix)
    * Remove hardcoded `302` when redirecting trailing slash [#2155](https://github.com/getgrav/grav/pull/2155)

# v1.5.4
## 11/05/2018

1. [](#improved)
    * Updated default page `index.md` with some consistency fixes [#2245](https://github.com/getgrav/grav/pull/2245)
1. [](#bugfix)
    * Fixed fatal error if calling `$session->invalidate()` when there's no active session
    * Fixed typo in media.yaml for `webm` extension [#2220](https://github.com/getgrav/grav/pull/2220)
    * Fixed markdown processing for telephone links [#2235](https://github.com/getgrav/grav/pull/2235)

# v1.5.3
## 10/08/2018

1. [](#new)
    * Added `Utils::getMimeByFilename()`, `Utils::getMimeByLocalFile()` and `Utils::checkFilename()` methods
    * Added configurable dangerous upload extensions in `security.yaml`
1. [](#improved)
    * Updated vendor libraries to latest

# v1.5.2
## 10/01/2018

1. [](#new)
    * Added new `Security` class for Grav security functionality including XSS checks
    * Added new `bin/grav security` command to scan for security issues
    * Added new `xss()` Twig function to allow for XSS checks on strings and arrays
    * Added `onHttpPostFilter` event to allow plugins to globally clean up XSS in the forms and tasks
    * Added `Deprecated` tab to DebugBar to catch future incompatibilities with later Grav versions
    * Added deprecation notices for features which will be removed in Grav 2.0
1. [](#improved)
    * Updated vendor libraries to latest
1. [](#bugfix)
    * Allow `$page->slug()` to be called before `$page->init()` without breaking the page
    * Fix for `Page::translatedLanguages()` to use routes always [#2163](https://github.com/getgrav/grav/issues/2163)
    * Fixed `nicetime()` twig function
    * Allow twig tags `{% script %}`, `{% style %}` and `{% switch %}` to be placed outside of blocks
    * Session expires in 30 mins independent from config settings [login#178](https://github.com/getgrav/grav-plugin-login/issues/178)

# v1.5.1
## 08/23/2018

1. [](#new)
    * Added static `Grav\Common\Yaml` class which should be used instead of `Symfony\Component\Yaml\Yaml`
1. [](#improved)
    * Updated deprecated Twig code so it works in both in Twig 1.34+ and Twig 2.4+
    * Switched to new Grav Yaml class to support Native + Fallback YAML libraries
1. [](#bugfix)
    * Broken handling of user folder in Grav URI object [#2151](https://github.com/getgrav/grav/issues/2151)

# v1.5.0
## 08/17/2018

1. [](#new)
    * Set minimum requirements to [PHP 5.6.4](https://getgrav.org/blog/raising-php-requirements-2018)
    * Updated Doctrine Collections to 1.4
    * Updated Symfony Components to 3.4 (with compatibility mode to fall back to Symfony YAML 2.8)
    * Added `Uri::method()` to get current HTTP method (GET/POST etc)
    * `FormatterInterface`: Added `getSupportedFileExtensions()` and `getDefaultFileExtension()` methods
    * Added option to disable `SimpleCache` key validation
    * Added support for multiple repo locations for `bin/grav install` command
    * Added twig filters for casting values: `|string`, `|int`, `|bool`, `|float`, `|array`
    * Made `ObjectCollection::matching()` criteria expressions to behave more like in Twig
    * Criteria: Added support for `LENGTH()`, `LOWER()`, `UPPER()`, `LTRIM()`, `RTRIM()` and `TRIM()`
    * Added `Grav\Framework\File\Formatter` classes for encoding/decoding YAML, Markdown, JSON, INI and PHP serialized strings
    * Added `Grav\Framework\Session` class to replace `RocketTheme\Toolbox\Session\Session`
    * Added `Grav\Common\Media` interfaces and trait; use those in `Page` and `Media` classes
    * Added `Grav\Common\Page` interface to allow custom page types in the future
    * Added setting to disable sessions from the site [#2013](https://github.com/getgrav/grav/issues/2013)
    * Added new `strict_mode` settings in `system.yaml` for compatibility
1. [](#improved)
    * Improved `Utils::url()` to support query strings
    * Display better exception message if Grav fails to initialize
    * Added `muted` and `playsinline` support to videos [#2124](https://github.com/getgrav/grav/pull/2124)
    * Added `MediaTrait::clearMediaCache()` to allow cache to be cleared
    * Added `MediaTrait::getMediaCache()` to allow custom caching
    * Improved session handling, allow all session configuration options in `system.session.options`
1. [](#bugfix)
    * Fix broken form nonce logic [#2121](https://github.com/getgrav/grav/pull/2121)
    * Fixed issue with uppercase extensions and fallback media URLs [#2133](https://github.com/getgrav/grav/issues/2133)
    * Fixed theme inheritance issue with `camel-case` that includes numbers [#2134](https://github.com/getgrav/grav/issues/2134)
    * Typo in demo typography page [#2136](https://github.com/getgrav/grav/pull/2136)
    * Fix for incorrect plugin order in debugger panel
    * Made `|markdown` filter HTML safe
    * Fixed bug in `ContentBlock` serialization
    * Fixed `Route::withQueryParam()` to accept array values
    * Fixed typo in truncate function [#1943](https://github.com/getgrav/grav/issues/1943)
    * Fixed blueprint field validation: Allow numeric inputs in text fields

# v1.4.8
## 07/31/2018

1. [](#improved)
    * Add Grav version to debug bar messages tab [#2106](https://github.com/getgrav/grav/pull/2106)
    * Add Nginx config for ddev project to `webserver-configs` [#2117](https://github.com/getgrav/grav/pull/2117)
    * Vendor library updates
1. [](#bugfix)
    * Don't allow `null` to be set as Page content

# v1.4.7
## 07/13/2018

1. [](#improved)
    * Use `getFilename` instead of `getBasename` [#2087](https://github.com/getgrav/grav/issues/2087)
1. [](#bugfix)
    * Fix for modular page preview [#2066](https://github.com/getgrav/grav/issues/2066)
    * `Page::routeCanonical()` should be string not array [#2069](https://github.com/getgrav/grav/issues/2069)

# v1.4.6
## 06/20/2018

1. [](#improved)
    * Manually re-added the improved SSL off-loading that was lost with Grav v1.4.0 merge [#1888](https://github.com/getgrav/grav/pull/1888)
    * Handle multibyte strings in `truncateLetters()` [#2007](https://github.com/getgrav/grav/pull/2007)
    * Updated robots.txt to include `/user/images/` folder [#2043](https://github.com/getgrav/grav/pull/2043)
    * Add getter methods for original and action to the Page object [#2005](https://github.com/getgrav/grav/pull/2005)
    * Modular template extension follows the master page extension [#2044](https://github.com/getgrav/grav/pull/2044)
    * Vendor library updates
1. [](#bugfix)
    * Handle `errors.display` system property better in admin plugin [admin#1452](https://github.com/getgrav/grav-plugin-admin/issues/1452)
    * Fix classes on non-http based protocol links [#2034](https://github.com/getgrav/grav/issues/2034)
    * Fixed crash on IIS (Windows) with open_basedir in effect [#2053](https://github.com/getgrav/grav/issues/2053)
    * Fixed incorrect routing with setup.php based base [#1892](https://github.com/getgrav/grav/issues/1892)
    * Fixed image resource memory deallocation [#2045](https://github.com/getgrav/grav/pull/2045)
    * Fixed issue with Errors `display:` option not handling integers properly [admin#1452](https://github.com/getgrav/grav-plugin-admin/issues/1452)

# v1.4.5
## 05/15/2018

1. [](#bugfix)
    * Fixed an issue with some users getting **2FA** prompt after upgrade [admin#1442](https://github.com/getgrav/grav-plugin-admin/issues/1442)
    * Do not crash when generating URLs with arrays as parameters [#2018](https://github.com/getgrav/grav/pull/2018)
    * Utils::truncateHTML removes whitespace when generating summaries [#2004](https://github.com/getgrav/grav/pull/2004)

# v1.4.4
## 05/11/2018

1. [](#new)
    * Added support for `Uri::post()` and `Uri::getConentType()`
    * Added a new `Medium:thumbnailExists()` function [#1966](https://github.com/getgrav/grav/issues/1966)
    * Added `authorized` support for 2FA
1. [](#improved)
    * Added default configuration for images [#1979](https://github.com/getgrav/grav/pull/1979)
    * Added dedicated PHPUnit assertions [#1990](https://github.com/getgrav/grav/pull/1990)
1. [](#bugfix)
    * Use `array_key_exists` instead of `in_array + array_keys` [#1991](https://github.com/getgrav/grav/pull/1991)
    * Fixed an issue with `custom_base_url` always causing 404 errors
    * Improve support for regex redirects with query and params [#1983](https://github.com/getgrav/grav/issues/1983)
    * Changed collection-based date sorting to `SORT_REGULAR` for better server compatibility [#1910](https://github.com/getgrav/grav/issues/1910)
    * Fix hardcoded string in modular blueprint [#1933](https://github.com/getgrav/grav/pull/1993)

# v1.4.3
## 04/12/2018

1. [](#new)
    * moved Twig `sortArrayByKey` logic into `Utils::` class
1. [](#improved)
    * Rolled back Parsedown library to stable `1.6.4` until a better solution for `1.8.0` compatibility can fe found
    * Updated vendor libraries to latest versions
1. [](#bugfix)
    * Fix for bad reference to `ZipArchive` in `GPM::Installer`

# v1.4.2
## 03/21/2018

1. [](#new)
    * Added new `|nicefilesize` Twig filter for pretty file (auto converts to bytes, kB, MB, GB, etc)
    * Added new `regex_filter()` Twig function to values in arrays
1. [](#improved)
    * Added bosnian to lang codes [#1917](﻿https://github.com/getgrav/grav/issues/1917)
    * Improved Zip extraction error codes [#1922](﻿https://github.com/getgrav/grav/issues/1922)
1. [](#bugfix)
    * Fixed an issue with Markdown Video and Audio that broke after Parsedown 1.7.0 Security updates [#1924](﻿https://github.com/getgrav/grav/issues/1924)
    * Fix for case-sensitive page metadata [admin#1370](https://github.com/getgrav/grav-plugin-admin/issues/1370)
    * Fixed missing composer requirements for the new `Grav\Framework\Uri` classes
    * Added missing PSR-7 vendor library required for URI additions in Grav 1.4.0

# v1.4.1
## 03/11/2018

1. [](#bugfix)
    * Fixed session timing out because of session cookie was not being sent

# v1.4.0
## 03/09/2018

1. [](#new)
    * Added `Grav\Framework\Uri` classes extending PSR-7 `HTTP message UriInterface` implementation
    * Added `Grav\Framework\Route` classes to allow route/link manipulation
    * Added `$grav['uri]->getCurrentUri()` method to get `Grav\Framework\Uri\Uri` instance for the current URL
    * Added `$grav['uri]->getCurrentRoute()` method to get `Grav\Framework\Route\Route` instance for the current URL
    * Added ability to have `php` version dependencies in GPM assets
    * Added new `{% switch %}` twig tag for more elegant if statements
    * Added new `{% markdown %}` twig tag
    * Added **Route Overrides** to the default page blueprint
    * Added new `Collection::toExtendedArray()` method that's particularly useful for Json output of data
    * Added new `|yaml_encode` and `|yaml_decode` Twig filter to convert to and from YAML
    * Added new `read_file()` Twig function to allow you to load and display a file in Twig (Supports streams and regular paths)
    * Added a new `Medium::exists()` method to check for file existence
    * Moved Twig `urlFunc()` to `Utils::url()` as its so darn handy
    * Transferred overall copyright from RocketTheme, LLC, to Trilby Media LLC
    * Added `theme_var`, `header_var` and `body_class` Twig functions for themes
    * Added `Grav\Framework\Cache` classes providing PSR-16 `Simple Cache` implementation
    * Added `Grav\Framework\ContentBlock` classes for nested HTML blocks with CSS/JS assets
    * Added `Grav\Framework\Object` classes for creating collections of objects
    * Added `|nicenumber` Twig filter
    * Added `{% try %} ... {% catch %} Error: {{ e.message }} {% endcatch %}` tag to allow basic exception handling inside Twig
    * Added `{% script %}` and `{% style %}` tags for Twig templates
    * Deprecated GravTrait
1. [](#improved)
    * Improved `Session` initialization
    * Added ability to set a `theme_var()` option in page frontmatter
    * Force clearing PHP `clearstatcache` and `opcache-reset` on `Cache::clear()`
    * Better `Page.collection()` filtering support including ability to have non-published pages in collections
    * Stopped Chrome from auto-completing admin user profile form [#1847](https://github.com/getgrav/grav/issues/1847)
    * Support for empty `switch` field like a `checkbox`
    * Made `modular` blueprint more flexible
    * Code optimizations to `Utils` class [#1830](https://github.com/getgrav/grav/pull/1830)
    * Objects: Add protected function `getElement()` to get serialized value for a single property
    * `ObjectPropertyTrait`: Added protected functions `isPropertyLoaded()`, `offsetLoad()`, `offsetPrepare()` and `offsetSerialize()`
    * `Grav\Framework\Cache`: Allow unlimited TTL
    * Optimizations & refactoring to the test suite [#1779](https://github.com/getgrav/grav/pull/1779)
    * Slight modification of Whoops error colors
    * Added new configuration option `system.session.initialize` to delay session initialization if needed by a plugin
    * Updated vendor libraries to latest versions
    * Removed constructor from `ObjectInterface`
    * Make it possible to include debug bar also into non-HTML responses
    * Updated built-in JQuery to latest 3.3.1
1. [](#bugfix)
    * Fixed issue with image alt tag always getting empted out unless set in markdown
    * Fixed issue with remote PHP version determination for Grav updates [#1883](https://github.com/getgrav/grav/issues/1883)
    * Fixed issue with _illegal scheme offset_ in `Uri::convertUrl()` [page-inject#8](https://github.com/getgrav/grav-plugin-page-inject/issues/8)
    * Properly validate YAML blueprint fields so admin can save as proper YAML now  [addresses many issues]
    * Fixed OpenGraph metatags so only Twitter uses `name=`, and all others use `property=` [#1849](https://github.com/getgrav/grav/issues/1849)
    * Fixed an issue with `evaluate()` and `evaluate_twig()` Twig functions that throws invalid template error
    * Fixed issue with `|sort_by_key` twig filter if the input was null or not an array
    * Date ordering should always be numeric [#1810](https://github.com/getgrav/grav/issues/1810)
    * Fix for base paths containing special characters [#1799](https://github.com/getgrav/grav/issues/1799)
    * Fix for session cookies in paths containing special characters
    * Fix for `vundefined` error for version numbers in GPM [form#222](https://github.com/getgrav/grav-plugin-form/issues/222)
    * Fixed `BadMethodCallException` thrown in GPM updates [#1784](https://github.com/getgrav/grav/issues/1784)
    * NOTE: Parsedown security release now escapes `&` to `&amp;` in Markdown links

# v1.3.10
## 12/06/2017

1. [](#bugfix)
    * Reverted GPM Local pull request as it broken admin [#1742](https://github.com/getgrav/grav/issues/1742)

# v1.3.9
## 12/05/2017

1. [](#new)
    * Added new core Twig templates for `partials/metadata.html.twig` and `partials/messages.html.twig`
    * Added ability to work with GPM locally [#1742](https://github.com/getgrav/grav/issues/1742)
    * Added new HTML5 audio controls [#1756](https://github.com/getgrav/grav/issues/1756)
    * Added `Medium::copy()` method to create a copy of a medium object
    * Added new `force_lowercase_urls` functionality on routes and slugs
    * Added new `item-list` filter type to remove empty items
    * Added new `setFlashCookieObject()` and `getFlashCookieObject()` methods to `Session` object
    * Added new `intl_enabled` option to disable PHP intl module collation when not needed
1. [](#bugfix)
    * Fixed an issue with checkbox field validation [form#216](https://github.com/getgrav/grav-plugin-form/issues/216)
    * Fixed issue with multibyte Markdown link URLs [#1749](https://github.com/getgrav/grav/issues/1749)
    * Fixed issue with multibyte folder names [#1751](https://github.com/getgrav/grav/issues/1751)
    * Fixed several issues related to `system.custom_base_url` that were broken [#1736](https://github.com/getgrav/grav/issues/1736)
    * Dynamically added pages via `Pages::addPage()` were not firing `onPageProcessed()` event causing forms not to be processed
    * Fixed `Page::active()` and `Page::activeChild()` to work with UTF-8 characters in the URL [#1727](https://github.com/getgrav/grav/issues/1727)
    * Fixed typo in `modular.yaml` causing media to be ignored [#1725](https://github.com/getgrav/grav/issues/1725)
    * Reverted `case_insensitive_urls` option as it was causing issues with taxonomy [#1733](https://github.com/getgrav/grav/pull/1733)
    * Removed an extra `/` in `CompileFile.php` [#1693](https://github.com/getgrav/grav/pull/1693)
    * Uri::Encode user and password to prevent issues in browsers
    * Fixed "Invalid AJAX response" When using Built-in PHP Webserver in Windows [#1258](https://github.com/getgrav/grav-plugin-admin/issues/1258)
    * Remove support for `config.user`, it was broken and bad practise
    * Make sure that `clean cache` uses valid path [#1745](https://github.com/getgrav/grav/pull/1745)
    * Fixed token creation issue with `Uri` params like `/id:3`
    * Fixed CSS Pipeline failing with Google remote fonts if the file was minified [#1261](https://github.com/getgrav/grav-plugin-admin/issues/1261)
    * Forced `field.multiple: true` to allow use of min/max options in `checkboxes.validate`

# v1.3.8
## 10/26/2017

1. [](#new)
    * Added Page `media_order` capability to manually order page media via a page header
1. [](#bugfix)
    * Fixed GPM update issue with filtered slugs [#1711](https://github.com/getgrav/grav/issues/1711)
    * Fixed issue with missing image file not throwing 404 properly [#1713](https://github.com/getgrav/grav/issues/1713)

# v1.3.7
## 10/18/2017

1. [](#bugfix)
    * Regression Uri: `base_url_absolute` always has the port number [#1690](https://github.com/getgrav/grav-plugin-admin/issues/1690)
    * Uri: Prefer using REQUEST_SCHEME instead of HTTPS [#1698](https://github.com/getgrav/grav-plugin-admin/issues/1698)
    * Fixed routing paths with urlencoded spaces and non-latin letters [#1688](https://github.com/getgrav/grav-plugin-admin/issues/1688)

# v1.3.6
## 10/12/2017

1. [](#bugfix)
    * Regression: Ajax error in Nginx [admin#1244](https://github.com/getgrav/grav-plugin-admin/issues/1244)
    * Remove the `_url=$uri` portion of the the Nginx `try_files` command [admin#1244](https://github.com/getgrav/grav-plugin-admin/issues/1244)

# v1.3.5
## 10/11/2017

1. [](#improved)
    * Refactored `URI` class with numerous bug fixes, and optimizations
    * Override `system.media.upload_limit` with PHP's `post_max_size` or `upload_max_filesize`
    * Updated `bin/grav clean` command to remove unnecessary vendor files (save some bytes)
    * Added a `http_status_code` Twig function to allow setting HTTP status codes from Twig directly.
    * Deter XSS attacks via URI path/uri methods (credit:newbthenewbd)
    * Added support for `$uri->toArray()` and `(string)$uri`
    * Added support for `type` on `Asstes::addInlineJs()` [#1683](https://github.com/getgrav/grav/pull/1683)
1. [](#bugfix)
    * Fixed method signature error with `GPM\InstallCommand::processPackage()` [#1682](https://github.com/getgrav/grav/pull/1682)

# v1.3.4
## 09/29/2017

1. [](#new)
    * Added filter support for Page collections (routable/visible/type/access/etc.)
1. [](#improved)
    * Implemented `Composer\CaBundle` for SSL Certs [#1241](https://github.com/getgrav/grav/issues/1241)
    * Refactored the Assets sorting logic
    * Improved language overrides to merge only 'extra' translations [#1514](https://github.com/getgrav/grav/issues/1514)
    * Improved support for Assets with query strings [#1451](https://github.com/getgrav/grav/issues/1451)
    * Twig extension cleanup
1. [](#bugfix)
    * Fixed an issue where fallback was not supporting dynamic page generation
    * Fixed issue with Image query string not being fully URL encoded [#1622](https://github.com/getgrav/grav/issues/1622)
    * Fixed `Page::summary()` when using delimiter and multibyte UTF8 Characters [#1644](https://github.com/getgrav/grav/issues/1644)
    * Fixed missing `.json` thumbnail throwing error when adding media [grav-plugin-admin#1156](https://github.com/getgrav/grav-plugin-admin/issues/1156)
    * Fixed insecure session cookie initialization [#1656](https://github.com/getgrav/grav/pull/1656)

# v1.3.3
## 09/07/2017

1. [](#new)
    * Added support for 2-Factor Authentication in admin profile
    * Added `gaussianBlur` media method [#1623](https://github.com/getgrav/grav/pull/1623)
    * Added new `|chunk_split()`, `|basename`, and `|dirname` Twig filter
    * Added new `tl` Twig filter/function to support specific translations [#1618](https://github.com/getgrav/grav/issues/1618)
1. [](#improved)
    * User `authorization` now requires a check for `authenticated` - REQUIRED: `Login v2.4.0`
    * Added options to `Page::summary()` to support size without HTML tags [#1554](https://github.com/getgrav/grav/issues/1554)
    * Forced `natsort` on plugins to ensure consistent plugin load ordering across platforms [#1614](https://github.com/getgrav/grav/issues/1614)
    * Use new `multilevel` field to handle Asset Collections [#1201](https://github.com/getgrav/grav-plugin-admin/issues/1201)
    * Added support for redis `password` option [#1620](https://github.com/getgrav/grav/issues/1620)
    * Use 302 rather than 301 redirects by default [#1619](https://github.com/getgrav/grav/issues/1619)
    * GPM Installer will try to load alphanumeric version of the class if no standard class found [#1630](https://github.com/getgrav/grav/issues/1630)
    * Add current page position to `User` class [#1632](https://github.com/getgrav/grav/issues/1632)
    * Added option to enable case insensitive URLs [#1638](https://github.com/getgrav/grav/issues/1638)
    * Updated vendor libraries
    * Updated `travis.yml` to add support for PHP 7.1 as well as 7.0.21 for test suite
1. [](#bugfix)
    * Fixed UTF8 multibyte UTF8 character support in `Page::summary()` [#1554](https://github.com/getgrav/grav/issues/1554)

# v1.3.2
## 08/16/2017

1. [](#new)
    * Added a new `cache_control` system and page level property [#1591](https://github.com/getgrav/grav/issues/1591)
    * Added a new `clear_images_by_default` system property to stop cache clear events from removing processed images [#1481](https://github.com/getgrav/grav/pull/1481)
    * Added new `onTwigLoader()` event to enable utilization of loader methods
    * Added new `Twig::addPath()` and `Twig::prependPath()` methods to wrap loader methods and support namespacing [#1604](https://github.com/getgrav/grav/issues/1604)
    * Added new `array_key_exists()` Twig function wrapper
    * Added a new `Collection::intersect()` method [#1605](https://github.com/getgrav/grav/issues/1605)
1. [](#bugfix)
    * Allow `session.timeout` field to be set to `0` via blueprints [#1598](https://github.com/getgrav/grav/issues/1598)
    * Fixed `Data::exists()` and `Data::raw()` functions breaking if `Data::file()` hasn't been called with non-null value
    * Fixed parent theme auto-loading in child themes of Gantry 5

# v1.3.1
## 07/19/2017

1. [](#bugfix)
    * Fix ordering for Linux + International environments [#1574](https://github.com/getgrav/grav/issues/1574)
    * Check if medium thumbnail exists before resetting
    * Update Travis' auth token

# v1.3.0
## 07/16/2017

1. [](#bugfix)
    * Fixed an undefined variable `$difference` [#1563](https://github.com/getgrav/grav/pull/1563)
    * Fix broken range slider [grav-plugin-admin#1153](https://github.com/getgrav/grav-plugin-admin/issues/1153)
    * Fix natural sort when > 100 pages [#1564](https://github.com/getgrav/grav/pull/1564)

# v1.3.0-rc.5
## 07/05/2017

1. [](#new)
    * Setting `system.session.timeout` to 0 clears the session when the browser session ends [#1538](https://github.com/getgrav/grav/pull/1538)
    * Created a `CODE_OF_CONDUCT.md` so everyone knows how to behave :)
1. [](#improved)
    * Renamed new `media()` Twig function to `media_directory()` to avoid conflict with Page's `media` object
1. [](#bugfix)
    * Fixed global media files disappearing after a reload [#1545](https://github.com/getgrav/grav/issues/1545)
    * Fix for broken regex redirects/routes via `site.yaml`
    * Sanitize the error message in the error handler page

# v1.3.0-rc.4
## 06/22/2017

1. [](#new)
    * Added `lower` and `upper` Twig filters
    * Added `pathinfo()` Twig function
    * Added 165 new thumbnail images for use in `media.yaml`
1. [](#improved)
    * Improved error message when running `bin/grav install` instead of `bin/gpm install`, and also when running on a non-skeleton site [#1027](https://github.com/getgrav/grav/issues/1027)
    * Updated vendor libraries
1. [](#bugfix)
    * Don't rebuild metadata every time, only when file does not exist
    * Restore GravTrait in ConsoleTrait [grav-plugin-login#119](https://github.com/getgrav/grav-plugin-login/issues/119)
    * Fix Windows routing with built-in server [#1502](https://github.com/getgrav/grav/issues/1502)
    * Fix [#1504](https://github.com/getgrav/grav/issues/1504) `process_twig` and `frontmatter.yaml`
    * Nicetime fix: 0 seconds from now -> just now [#1509](https://github.com/getgrav/grav/issues/1509)

# v1.3.0-rc.3
## 05/22/2017

1. [](#new)
    * Added new unified `Utils::getPagePathFromToken()` method which is used by various plugins (Admin, Forms, Downloads, etc.)
1. [](#improved)
    * Optionally remove unpublished pages from the translated languages, move into untranslated list [#1482](https://github.com/getgrav/grav/pull/1482)
    * Improved reliability of `hash` file-check method
1. [](#bugfix)
    * Updated to latest Toolbox library to fix issue with some blueprints rendering in admin plugin [#1117](https://github.com/getgrav/grav-plugin-admin/issues/1117)
    * Fix output handling in RenderProcessor [#1483](https://github.com/getgrav/grav/pull/1483)

# v1.3.0-rc.2
## 05/17/2017

1. [](#new)
    * Added new `media` and `vardump` Twig functions
1. [](#improved)
    * Put in various checks to ensure Exif is available before trying to use it
    * Add timestamp to configuration settings [#1445](https://github.com/getgrav/grav/pull/1445)
1. [](#bugfix)
    * Fix an issue saving YAML textarea fields in expert mode [#1480](https://github.com/getgrav/grav/pull/1480)
    * Moved `onOutputRendered()` back into Grav core

# v1.3.0-rc.1
## 05/16/2017

1. [](#new)
    * Added support for a single array field in the forms
    * Added EXIF support with automatic generation of Page Media metafiles
    * Added Twig function to get EXIF data on any image file
    * Added `Pages::baseUrl()`, `Pages::homeUrl()` and `Pages::url()` functions
    * Added `base32_encode`, `base32_decode`, `base64_encode`, `base64_decode` Twig filters
    * Added `Debugger::getCaller()` to figure out where the method was called from
    * Added support for custom output providers like Slim Framework
    * Added `Grav\Framework\Collection` classes for creating collections
1. [](#improved)
    * Add more controls over HTML5 video attributes (autoplay, poster, loop controls) [#1442](https://github.com/getgrav/grav/pull/1442)
    * Removed logging statement for invalid slug [#1459](https://github.com/getgrav/grav/issues/1459)
    * Groups selection pre-filled in user form
    * Improve error handling in `Folder::move()`
    * Added extra parameter for `Twig::processSite()` to include custom context
    * Updated RocketTheme Toolbox vendor library
1. [](#bugfix)
    * Fix to force route/redirect matching from the start of the route by default [#1446](https://github.com/getgrav/grav/issues/1446)
    * Edit check for valid slug [#1459](https://github.com/getgrav/grav/issues/1459)

# v1.2.4
## 04/24/2017

1. [](#improved)
    * Added optional ignores for `Installer::sophisticatedInstall()` [#1447](https://github.com/getgrav/grav/issues/1447)
1. [](#bugfix)
    * Allow multiple calls to `Themes::initTheme()` without throwing errors
    * Fixed querystrings in root pages with multi-lang enabled [#1436](https://github.com/getgrav/grav/issues/1436)
    * Allow support for `Pages::getList()` with `show_modular` option [#1080](https://github.com/getgrav/grav-plugin-admin/issues/1080)

# v1.2.3
## 04/19/2017

1. [](#improved)
    * Added new `pwd_regex` and `username_regex` system configuration options to allow format modifications
    * Allow `user/accounts.yaml` overrides and implemented more robust theme initialization
    * improved `getList()` method to do more powerful things
    * Fix Typo in GPM [#1427](https://github.com/getgrav/grav/issues/1427)

# v1.2.2
## 04/11/2017

1. [](#bugfix)
    * Fix for redirects breaking [#1420](https://github.com/getgrav/grav/issues/1420)
    * Fix issue in direct-install with github-style dependencies [#1405](https://github.com/getgrav/grav/issues/1405)

# v1.2.1
## 04/10/2017

1. [](#improved)
    * Added various `ancestor` helper methods in Page and Pages classes [#1362](https://github.com/getgrav/grav/pull/1362)
    * Added new `parents` field and switched Page blueprints to use this
    * Added `isajaxrequest()` Twig function [#1400](https://github.com/getgrav/grav/issues/1400)
    * Added ability to inline CSS and JS code via Asset manager [#1377](https://github.com/getgrav/grav/pull/1377)
    * Add query string in lighttpd default config [#1393](https://github.com/getgrav/grav/issues/1393)
    * Add `--all-yes` and `--destination` options for `bin/gpm direct-install` [#1397](https://github.com/getgrav/grav/pull/1397)
1. [](#bugfix)
    * Fix for direct-install of plugins with `languages.yaml` [#1396](https://github.com/getgrav/grav/issues/1396)
    * When determining language from HTTP_ACCEPT_LANGUAGE, also try base language only [#1402](https://github.com/getgrav/grav/issues/1402)
    * Fixed a bad method signature causing warning when running tests on `GPMTest` object

# v1.2.0
## 03/31/2017

1. [](#new)
    * Added file upload for user avatar in user/admin blueprint
1. [](#improved)
    * Analysis fixes
    * Switched to stable composer lib versions

# v1.2.0-rc.3
## 03/22/2017

1. [](#new)
    * Refactored Page re-ordering to handle all siblings at once
    * Added `language_codes` to Twig init to allow for easy language name/code/native-name lookup
1. [](#improved)
    * Added an _Admin Overrides_ section with option to choose the order of children in Pages Management
1. [](#bugfix)
    * Fixed loading issues with improperly named themes (use old broken method first) [#1373](https://github.com/getgrav/grav/issues/1373)
    * Simplified modular/twig processing logic and fixed an issue with system process config [#1351](https://github.com/getgrav/grav/issues/1351)
    * Cleanup package files via GPM install to make them more windows-friendly [#1361](https://github.com/getgrav/grav/pull/1361)
    * Fix for page-level debugger override changing the option site-wide
    * Allow `url()` twig function to pass-through external links

# v1.2.0-rc.2
## 03/17/2017

1. [](#improved)
    * Updated vendor libraries to latest
    * Added the ability to disable debugger on a per-page basis with `debugger: false` in page frontmatter
1. [](#bugfix)
    * Fixed an issue with theme inheritance and hyphenated base themes [#1353](https://github.com/getgrav/grav/issues/1353)
    * Fixed an issue when trying to use an `@2x` derivative on a non-image media file [#1341](https://github.com/getgrav/grav/issues/1341)

# v1.2.0-rc.1
## 03/13/2017

1. [](#new)
    * Added default setting to only allow `direct-installs` from official GPM.  Can be configured in `system.yaml`
    * Added a new `Utils::isValidUrl()` method
    * Added optional parameter to `|markdown(false)` filter to toggle block/line processing (default|true = `block`)
    * Added new `Page::folderExists()` method
1. [](#improved)
    * `Twig::evaluate()` now takes current environment and context into account
    * Genericized `direct-install` so it can be called via Admin plugin
1. [](#bugfix)
    * Fixed a minor bug in Number validation [#1329](https://github.com/getgrav/grav/issues/1329)
    * Fixed exception when trying to find user account and there is no `user://accounts` folder
    * Fixed issue when setting `Page::expires(0)` [Admin #1009](https://github.com/getgrav/grav-plugin-admin/issues/1009)
    * Removed ID from `nonce_field()` Twig function causing validation errors [Form #115](https://github.com/getgrav/grav-plugin-form/issues/115)

# v1.1.17
## 02/17/2017

1. [](#bugfix)
    * Fix for double extensions getting added during some redirects [#1307](https://github.com/getgrav/grav/issues/1307)
    * Fix syntax error in PHP 5.3. Move the version check before requiring the autoloaded deps
    * Fix Whoops displaying error page if there is PHP core warning or error [Admin #980](https://github.com/getgrav/grav-plugin-admin/issues/980)

# v1.1.16
## 02/10/2017

1. [](#new)
    * Exposed the Pages cache ID for use by plugins (e.g. Form) via `Pages::getPagesCacheId()`
    * Added `Languages::resetFallbackPageExtensions()` regarding [#1276](https://github.com/getgrav/grav/pull/1276)
1. [](#improved)
    * Allowed CLI to use non-volatile cache drivers for better integration with CLI and Web caches
    * Added Gantry5-compatible query information to Caddy configuration
    * Added some missing docblocks and type-hints
    * Various code cleanups (return types, missing variables in doclbocks, etc.)
1. [](#bugfix)
    * Fix blueprints slug validation [https://github.com/getgrav/grav-plugin-admin/issues/955](https://github.com/getgrav/grav-plugin-admin/issues/955)

# v1.1.15
## 01/30/2017

1. [](#new)
    * Added a new `Collection::merge()` method to allow merging of multiple collections [#1258](https://github.com/getgrav/grav/pull/1258)
    * Added [OpenCollective](https://opencollective.com/grav) backer/sponsor info to `README.md`
1. [](#improved)
    * Add an additional parameter to GPM::findPackage to avoid throwing an exception, for use in Twig [#1008](https://github.com/getgrav/grav/issues/1008)
    * Skip symlinks if found while clearing cache [#1269](https://github.com/getgrav/grav/issues/1269)
1. [](#bugfix)
    * Fixed an issue when page collection with header-based `sort.by` returns an array [#1264](https://github.com/getgrav/grav/issues/1264)
    * Fix `Response` object to handle `303` redirects when `open_basedir` in effect [#1267](https://github.com/getgrav/grav/issues/1267)
    * Silence `E_WARNING: Zend OPcache API is restricted by "restrict_api" configuration directive`

# v1.1.14
## 01/18/2017

1. [](#bugfix)
    * Fixed `Page::collection()` returning array and not Collection object when header variable did not exist
    * Revert `Content-Encoding: identity` fix, and let you set `cache: allow_webserver_gzip:` option to switch to `identity` [#548](https://github.com/getgrav/grav/issues/548)

# v1.1.13
## 01/17/2017

1. [](#new)
    * Added new `never_cache_twig` page option in `system.yaml` and frontmatter. Allows dynamic Twig logic in regular and modular Twig templates [#1244](https://github.com/getgrav/grav/pull/1244)
1. [](#improved)
    * Several improvements to aid theme development [#232](https://github.com/getgrav/grav/pull/1232)
    * Added `hash` cache check option and made dropdown more descriptive [Admin #923](https://github.com/getgrav/grav-plugin-admin/issues/923)
1. [](#bugfix)
    * Fixed cross volume file system operations [#635](https://github.com/getgrav/grav/issues/635)
    * Fix issue with pages folders validation not accepting uppercase letters
    * Fix renaming the folder name if the page, in the default language, had a custom slug set in its header
    * Fixed issue with `Content-Encoding: none`. It should really be `Content-Encoding: identity` instead
    * Fixed broken `hash` method on page modifications detection
    * Fixed issue with multi-lang pages not caching independently without unique `.md` file [#1211](https://github.com/getgrav/grav/issues/1211)
    * Fixed all `$_GET` parameters missing in Nginx (please update your nginx.conf) [#1245](https://github.com/getgrav/grav/issues/1245)
    * Fixed issue in trying to process broken symlink [#1254](https://github.com/getgrav/grav/issues/1254)

# v1.1.12
## 12/26/2016

1. [](#bugfix)
    * Fixed issue with JSON calls throwing errors due to debugger enabled [#1227](https://github.com/getgrav/grav/issues/1227)

# v1.1.11
## 12/22/2016

1. [](#improved)
    * Fall back properly to HTML if template type not found
1. [](#bugfix)
    * Fix issue with modular pages folders validation [#900](https://github.com/getgrav/grav-plugin-admin/issues/900)

# v1.1.10
## 12/21/2016

1. [](#improved)
    * Improve detection of home path. Also allow `~/.grav` on Windows, drop `ConsoleTrait::isWindows()` method, used only for that [#1204](https://github.com/getgrav/grav/pull/1204)
    * Reworked PHP CLI router [#1219](https://github.com/getgrav/grav/pull/1219)
    * More robust theme/plugin logic in `bin/gpm direct-install`
1. [](#bugfix)
    * Fixed case where extracting a package would cause an error during rename
    * Fix issue with using `Yaml::parse` direcly on a filename, now deprecated
    * Add pattern for frontend validation of folder slugs [#891](https://github.com/getgrav/grav-plugin-admin/issues/891)
    * Fix issue with Inflector when translation is disabled [SimpleSearch #87](https://github.com/getgrav/grav-plugin-simplesearch/issues/87)
    * Explicitly expose `array_unique` Twig filter [Admin #897](https://github.com/getgrav/grav-plugin-admin/issues/897)

# v1.1.9
## 12/13/2016

1. [](#new)
    * RC released as stable
1. [](#improved)
    * Better error handling in cache clear
    * YAML syntax fixes for the future compatibility
    * Added new parameter `remove` for `onBeforeCacheClear` event
    * Add support for calling Media object as function to get medium by filename
1. [](#bugfix)
    * Added checks before accessing admin reference during `Page::blueprints()` call. Allows to access `page.blueprints` from Twig in the frontend

# v1.1.9-rc.3
## 12/07/2016

1. [](#new)
    * Add `ignore_empty` property to be used on array fields, if positive only save options with a value
    * Use new `permissions` field in user account
    * Add `range(int start, int end, int step)` twig function to generate an array of numbers between start and end, inclusive
    * New retina Media image derivatives array support (`![](image.jpg?derivatives=[640,1024,1440])`) [#1147](https://github.com/getgrav/grav/pull/1147)
    * Added stream support for images (`![Sepia Image](image://image.jpg?sepia)`)
    * Added stream support for links (`[Download PDF](user://data/pdf/my.pdf)`)
    * Added new `onBeforeCacheClear` event to add custom paths to cache clearing process
1. [](#improved)
    * Added alias `selfupdate` to the `self-upgrade` `bin/gpm` CLI command
    * Synced `webserver-configs/htaccess.txt` with `.htaccess`
    * Use permissions field in group details.
    * Updated vendor libraries
    * Added a warning on GPM update to update Grav first if needed [#1194](https://github.com/getgrav/grav/pull/1194)
 1. [](#bugfix)
    * Fix page collections problem with `@page.modular` [#1178](https://github.com/getgrav/grav/pull/1178)
    * Fix issue with using a multiple taxonomy filter of which one had no results, thanks to @hughbris [#1184](https://github.com/getgrav/grav/issues/1184)
    * Fix saving permissions in group
    * Fixed issue with redirect of a page getting moved to a different location

# v1.1.9-rc.2
## 11/26/2016

1. [](#new)
    * Added two new sort order options for pages: `publish_date` and `unpublish_date` [#1173](https://github.com/getgrav/grav/pull/1173))
1. [](#improved)
    * Multisite: Create image cache folder if it doesn't exist
    * Add 2 new language values for French [#1174](https://github.com/getgrav/grav/issues/1174)
1. [](#bugfix)
    * Fixed issue when we have a meta file without corresponding media [#1179](https://github.com/getgrav/grav/issues/1179)
    * Update class namespace for Admin class [Admin #874](https://github.com/getgrav/grav-plugin-admin/issues/874)

# v1.1.9-rc.1
## 11/09/2016

1. [](#new)
    * Added a `CompiledJsonFile` object to better handle Json files.
    * Added Base32 encode/decode class
    * Added a new `User::find()` method
1. [](#improved)
    * Moved `messages` object into core Grav from login plugin
    * Added `getTaxonomyItemKeys` to the Taxonomy object [#1124](https://github.com/getgrav/grav/issues/1124)
    * Added a `redirect_me` Twig function [#1124](https://github.com/getgrav/grav/issues/1124)
    * Added a Caddyfile for newer Caddy versions [#1115](https://github.com/getgrav/grav/issues/1115)
    * Allow to override sorting flags for page header-based or default ordering. If the `intl` PHP extension is loaded, only these flags are available: https://secure.php.net/manual/en/collator.asort.php. Otherwise, you can use the PHP standard sorting flags (https://secure.php.net/manual/en/array.constants.php) [#1169](https://github.com/getgrav/grav/issues/1169)
1. [](#bugfix)
    * Fixed an issue with site redirects/routes, not processing with extension (.html, .json, etc.)
    * Don't truncate HTML if content length is less than summary size [#1125](https://github.com/getgrav/grav/issues/1125)
    * Return max available number when calling random() on a collection passing an int > available items [#1135](https://github.com/getgrav/grav/issues/1135)
    * Use correct ratio when applying image filters to image alternatives [#1147](https://github.com/getgrav/grav/issues/1147)
    * Fixed URI path in multi-site when query parameters were used in front page

# v1.1.8
## 10/22/2016

1. [](#bugfix)
    * Fixed warning with unset `ssl` option when using GPM [#1132](https://github.com/getgrav/grav/issues/1132)

# v1.1.7
## 10/22/2016

1. [](#improved)
    * Improved the capabilities of Image derivatives [#1107](https://github.com/getgrav/grav/pull/1107)
1. [](#bugfix)
    * Only pass verify_peer settings to cURL and fopen if the setting is disabled [#1120](https://github.com/getgrav/grav/issues/1120)

# v1.1.6
## 10/19/2016

1. [](#new)
    * Added ability for Page to override the output format (`html`, `xml`, etc..) [#1067](https://github.com/getgrav/grav/issues/1067)
    * Added `Utils::getExtensionByMime()` and cleaned up `Utils::getMimeByExtension` + tests
    * Added a `cache.check.method: 'hash'` option in `system.yaml` that checks all files + dates inclusively
    * Include jQuery 3.x in the Grav assets
    * Added the option to automatically fix orientation on images based on their Exif data, by enabling `system.images.auto_fix_orientation`.
1. [](#improved)
    * Add `batch()` function to Page Collection class
    * Added new `cache.redis.socket` setting that allow to pass a UNIX socket as redis server
    * It is now possible to opt-out of the SSL verification via the new `system.gpm.verify_peer` setting. This is sometimes necessary when receiving a "GPM Unable to Connect" error. More details in ([#1053](https://github.com/getgrav/grav/issues/1053))
    * It is now possible to force the use of either `curl` or `fopen` as `Response` connection method, via the new `system.gpm.method` setting. By default this is set to 'auto' and gives priority to 'fopen' first, curl otherwise.
    * InstallCommand can now handle Licenses
    * Uses more helpful `1x`, `2x`, `3x`, etc names in the Retina derivatives cache files.
    * Added new method `Plugins::isPluginActiveAdmin()` to check if plugin route is active in Admin plugin
    * Added new `Cache::setEnabled` and `Cache::getEnabled` to enable outside control of cache
    * Updated vendor libs including Twig `1.25.0`
    * Avoid git ignoring any vendor folder in a Grav site subfolder (but still ignore the main `vendor/` folder)
    * Added an option to get just a route back from `Uri::convertUrl()` function
    * Added option to control split session [#1096](https://github.com/getgrav/grav/pull/1096)
    * Added new `verbosity` levels to `system.error.display` to allow for system error messages [#1091](https://github.com/getgrav/grav/pull/1091)
    * Improved the API for Grav plugins to access the Parsedown parser directly [#1062](https://github.com/getgrav/grav/pull/1062)
1. [](#bugfix)
    * Fixed missing `progress` method in the DirectInstall Command
    * `Response` class now handles better unsuccessful requests such as 404 and 401
    * Fixed saving of `external` page types [Admin #789](https://github.com/getgrav/grav-plugin-admin/issues/789)
    * Fixed issue deleting parent folder of folder with `param_sep` in the folder name [admin #796](https://github.com/getgrav/grav-plugin-admin/issues/796)
    * Fixed an issue with streams in `bin/plugin`
    * Fixed `jpeg` file format support in Media

# v1.1.5
## 09/09/2016

1. [](#new)
    * Added new `bin/gpm direct-install` command to install local and remote zip archives
1. [](#improved)
    * Refactored `onPageNotFound` event to fire after `onPageInitialized`
    * Follow symlinks in `Folder::all()`
    * Twig variable `base_url` now supports multi-site by path feature
    * Improved `bin/plugin` to list plugins with commands faster by limiting the depth of recursion
1. [](#bugfix)
    * Quietly skip missing streams in `Cache::clearCache()`
    * Fix issue in calling page.summary when no content is present in a page
    * Fix for HUGE session timeouts [#1050](https://github.com/getgrav/grav/issues/1050)

# v1.1.4
## 09/07/2016

1. [](#new)
    * Added new `tmp` folder at root. Accessible via stream `tmp://`. Can be cleared with `bin/grav clear --tmp-only` as well as `--all`.
    * Added support for RTL in `LanguageCodes` so you can determine if a language is RTL or not
    * Ability to set `custom_base_url` in system configuration
    * Added `override` and `force` options for Streams setup
1. [](#improved)
    * Important vendor updates to provide PHP 7.1 beta support!
    * Added a `Util::arrayFlatten()` static function
    * Added support for 'external_url' page header to enable easier external URL based menu items
    * Improved the UI for CLI GPM Index view to use a table
    * Added `@page.modular` Collection type [#988](https://github.com/getgrav/grav/issues/988)
    * Added support for `self@`, `page@`, `taxonomy@`, `root@` Collection syntax for cleaner YAML compatibility
    * Improved GPM commands to allow for `-y` to automate **yes** responses and `-o` for **update** and **selfupgrade** to overwrite installations [#985](https://github.com/getgrav/grav/issues/985)
    * Added randomization to `safe_email` Twig filter for greater security [#998](https://github.com/getgrav/grav/issues/998)
    * Allow `Utils::setDotNotation` to merge data, rather than just set
    * Moved default `Image::filter()` to the `save` action to ensure they are applied last [#984](https://github.com/getgrav/grav/issues/984)
    * Improved the `Truncator` code to be more reliable [#1019](https://github.com/getgrav/grav/issues/1019)
    * Moved media blueprints out of core (now in Admin plugin)
1. [](#bugfix)
    * Removed 307 redirect code option as it is not well supported [#743](https://github.com/getgrav/grav-plugin-admin/issues/743)
    * Fixed issue with folders with name `*.md` are not confused with pages [#995](https://github.com/getgrav/grav/issues/995)
    * Fixed an issue when filtering collections causing null key
    * Fix for invalid HTML when rendering GIF and Vector media [#1001](https://github.com/getgrav/grav/issues/1001)
    * Use pages.markdown.extra in the user's system.yaml [#1007](https://github.com/getgrav/grav/issues/1007)
    * Fix for `Memcached` connection [#1020](https://github.com/getgrav/grav/issues/1020)

# v1.1.3
## 08/14/2016

1. [](#bugfix)
    * Fix for lightbox media function throwing error [#981](https://github.com/getgrav/grav/issues/981)

# v1.1.2
## 08/10/2016

1. [](#new)
    * Allow forcing SSL by setting `system.force_ssl` (Force SSL in the Admin System Config) [#899](https://github.com/getgrav/grav/pull/899)
1. [](#improved)
    * Improved `authorize` Twig extension to accept a nested array of authorizations  [#948](https://github.com/getgrav/grav/issues/948)
    * Don't add timestamps on remote assets as it can cause conflicts
    * Grav now looks at types from `media.yaml` when retrieving page mime types [#966](https://github.com/getgrav/grav/issues/966)
    * Added support for dumping exceptions in the Debugger
1. [](#bugfix)
    * Fixed `Folder::delete` method to recursively remove files and folders and causing Upgrade to fail.
    * Fix [#952](https://github.com/getgrav/grav/issues/952) hyphenize the session name.
    * If no parent is set and siblings collection is called, return a new and empty collection [grav-plugin-sitemap/issues/22](https://github.com/getgrav/grav-plugin-sitemap/issues/22)
    * Prevent exception being thrown when calling the Collator constructor failed in a Windows environment with the Intl PHP Extension enabled [#961](https://github.com/getgrav/grav/issues/961)
    * Fix for markdown images not properly rendering `id` attribute [#956](https://github.com/getgrav/grav/issues/956)

# v1.1.1
## 07/16/2016

1. [](#improved)
    * Made `paramsRegex()` static to allow it to be called statically
1. [](#bugfix)
    * Fixed backup when using very long site titles with invalid characters [grav-plugin-admin#701](https://github.com/getgrav/grav-plugin-admin/issues/701)
    * Fixed a typo in the `webserver-configs/nginx.conf` example

# v1.1.0
## 07/14/2016

1. [](#improved)
    * Added support for validation of multiple email in the `type: email` field [grav-plugin-email#31](https://github.com/getgrav/grav-plugin-email/issues/31)
    * Unified PHP code header styling
    * Added 6 more languages and updated language codes
    * set default "releases" option to `stable`
1. [](#bugfix)
    * Fix backend validation for file fields marked as required [grav-plugin-form#78](https://github.com/getgrav/grav-plugin-form/issues/78)

# v1.1.0-rc.3
## 06/21/2016

1. [](#new)
    * Add a onPageFallBackUrl event when starting the fallbackUrl() method to allow the Login plugin to protect the page media
    * Conveniently allow ability to retrieve user information via config object [#913](https://github.com/getgrav/grav/pull/913) - @Vivalldi
    * Grav served images can now use header caching [#905](https://github.com/getgrav/grav/pull/905)
1. [](#improved)
    * Take asset modification timestamp into consideration in pipelining [#917](https://github.com/getgrav/grav/pull/917) - @Sommerregen
1. [](#bugfix)
    * Respect `enable_asset_timestamp` settings for pipelined Assets [#906](https://github.com/getgrav/grav/issues/906)
    * Fixed collections end dates for 32-bit systems [#902](https://github.com/getgrav/grav/issues/902)
    * Fixed a recent regression (1.1.0-rc1) with parameter separator different than `:`

# v1.1.0-rc.2
## 06/14/2016

1. [](#new)
    * Added getters and setters for Assets to allow manipulation of CSS/JS/Collection based assets via plugins [#876](https://github.com/getgrav/grav/issues/876)
1. [](#improved)
    * Pass the exception to the `onFatalException()` event
    * Updated to latest jQuery 2.2.4 release
    * Moved list items in `system/config/media.yaml` config into a `types:` key which allows you delete default items.
    * Updated `webserver-configs/nginx.conf` with `try_files` fix from @mrhein and @rondlite [#743](https://github.com/getgrav/grav/pull/743)
    * Updated cache references to include `memecache` and `redis` [#887](https://github.com/getgrav/grav/issues/887)
    * Updated composer libraries
1. [](#bugfix)
    * Fixed `Utils::normalizePath()` that was truncating 0's [#882](https://github.com/getgrav/grav/issues/882)

# v1.1.0-rc.1
## 06/01/2016

1. [](#new)
    * Added `Utils::getDotNotation()` and `Utils::setDotNotation()` methods + tests
    * Added support for `xx-XX` locale language lookups in `LanguageCodes` class [#854](https://github.com/getgrav/grav/issues/854)
    * New CSS/JS Minify library that does a more reliable job [#864](https://github.com/getgrav/grav/issues/864)
1. [](#improved)
    * GPM installation of plugins and themes into correct multisite folders [#841](https://github.com/getgrav/grav/issues/841)
    * Use `Page::rawRoute()` in blueprints for more reliable mulit-language support
1. [](#bugfix)
    * Fixes for `zlib.output_compression` as well as `mod_deflate` GZIP compression
    * Fix for corner-case redirect logic causing infinite loops and out-of-memory errors
    * Fix for saving fields in expert mode that have no `Validation::typeX()` methods [#626](https://github.com/getgrav/grav-plugin-admin/issues/626)
    * Detect if user really meant to extend parent blueprint, not another one (fixes old page type blueprints)
    * Fixed a bug in `Page::relativePagePath()` when `Page::$name` is not defined
    * Fix for poor handling of params + query element in `Uri::processParams()` [#859](https://github.com/getgrav/grav/issues/859)
    * Fix for double encoding in markdown links [#860](https://github.com/getgrav/grav/issues/860)
    * Correctly handle language strings to determine if it's in admin or not [#627](https://github.com/getgrav/grav-plugin-admin/issues/627)

# v1.1.0-beta.5
## 05/23/2016

1. [](#improved)
    * Updated jQuery from 2.2.0 to 2.2.3
    * Set `Uri::ip()` to static by default so it can be used in form fields
    * Improved `Session` class with flash storage
    * `Page::getContentMeta()` now supports an optional key.
1. [](#bugfix)
    * Fixed "Invalid slug set in YAML frontmatter" when setting `Page::slug()` with empty string [#580](https://github.com/getgrav/grav-plugin-admin/issues/580)
    * Only `.gitignore` Grav's vendor folder
    * Fix trying to remove Grav with `GPM uninstall` of a plugin with Grav dependency
    * Fix Page Type blueprints not being able to extend their parents
    * `filterFile` validation method always returns an array of files, behaving like `multiple="multiple"`
    * Fixed [#835](https://github.com/getgrav/grav-plugin-admin/issues/835) check for empty image file first to prevent getimagesize() fatal error
    * Avoid throwing an error when Grav's Gzip and mod_deflate are enabled at the same time on a non php-fpm setup

# v1.1.0-beta.4
## 05/09/2016

1. [](#bugfix)
    * Drop dependencies calculations if plugin is installed via symlink
    * Drop Grav from dependencies calculations
    * Send slug name as part of installed packages
    * Fix for summary entities not being properly decoded [#825](https://github.com/getgrav/grav/issues/825)


# v1.1.0-beta.3
## 05/04/2016

1. [](#improved)
    * Pass the Page type when calling `onBlueprintCreated`
    * Changed `Page::cachePageContent()` form **private** to **public** so a page can be recached via plugin
1. [](#bugfix)
    * Fixed handling of `{'loading':'async'}` with Assets Pipeline
    * Fix for new modular page modal `Page` field requiring a value [#529](https://github.com/getgrav/grav-plugin-admin/issues/529)
    * Fix for broken `bin/gpm version` command
    * Fix handling "grav" as a dependency
    * Fix when installing multiple packages and one is the dependency of another, don't try to install it twice
    * Fix using name instead of the slug to determine a package folder. Broke for packages whose name was 2+ words

# v1.1.0-beta.2
## 04/27/2016

1. [](#new)
    * Added new `Plugin::getBlueprint()` and `Theme::getBlueprint()` method
    * Allow **page blueprints** to be added via Plugins.
1. [](#improved)
    * Moved to new `data-*@` format in blueprints
    * Updated composer-based libraries
    * Moved some hard-coded `CACHE_DIR` references to use locator
    * Set `twig.debug: true` by default
1. [](#bugfix)
    * Fixed issue with link rewrites and local assets pipeline with `absolute_urls: true`
    * Allow Cyrillic slugs [#520](https://github.com/getgrav/grav-plugin-admin/issues/520)
    * Fix ordering issue with accented letters [#784](https://github.com/getgrav/grav/issues/784)
    * Fix issue with Assets pipeline and missing newlines causing invalid JavaScript

# v1.1.0-beta.1
## 04/20/2016

1. [](#new)
    * **Blueprint Improvements**: The main improvements to Grav take the form of a major rewrite of our blueprint functionality. Blueprints are an essential piece of functionality within Grav that helps define configuration fields. These allow us to create a definition of a form field that can be rendered in the administrator plugin and allow the input, validation, and storage of values into the various configuration and page files that power Grav. Grav 1.0 had extensive support for building and extending blueprints, but Grav 1.1 takes this even further and adds improvements to our existing system.
    * **Extending Blueprints**: You could extend forms in Grav 1.0, but now you can use a newer `extends@:` default syntax rather than the previous `'@extends'` string that needed to be quoted in YAML. Also this new format allows for the defining of a `context` which lets you define where to look for the base blueprint. Another new feature is the ability to extend from multiple blueprints.
    * **Embedding/Importing Blueprints**: One feature that has been requested is the ability to embed or import one blueprint into another blueprint. This allows you to share fields or sub-form between multiple forms. This is accomplished via the `import@` syntax.
    * **Removing Existing Fields and Properties**: Another new feature is the ability to remove completely existing fields or properties from an extended blueprint. This allows the user a lot more flexibility when creating custom forms by simply using the new `unset@: true` syntax. To remove a field property you would use `unset-<property>@: true` in your extended field definition, for example: `unset-options@: true`.
    * **Replacing Existing Fields and Properties**: Similar to removing, you can now replace an existing field or property with the `replace@: true` syntax for the whole field, and `replace-<property>@: true` for a specific property.
    * **Field Ordering**: Probably the most frequently requested blueprint functionality that we have added is the ability to change field ordering. Imagine that you want to extend the default page blueprint but add a new tab. Previously, this meant your tab would be added at the end of the form, but now you can define that you wish the new tab to be added right after the `content` tab. This works for any field too, so you can extend a blueprint and add your own custom fields anywhere you wish! This is accomplished by using the new `ordering@:` syntax with either an existing property name or an integer.
    * **Configuration Properties**: Another useful new feature is the ability to directly access Grav configuration in blueprints with `config-<property>@` syntax. For example you can set a default for a field via `config-default@: site.author.name` which will use the author.name value from the `site.yaml` file as the `default` value for this field.
    * **Function Calls**: The ability to call PHP functions for values has been improved in Grav 1.1 to be more powerful. You can use the `data-<property>@` syntax to call static methods to obtain values. For example: `data-default@: '\Grav\Plugin\Admin::route'`. You can now even pass parameters to these methods.
    * **Validation Rules**: You can now define a custom blueprint-level validation rule and assign this rule to a form field.
    * **Custom Form Field Types**: This advanced new functionality allows you to create a custom field type via a new plugin event called getFormFieldTypes(). This allows you to provide extra functionality or instructions on how to handle the form form field.
    * **GPM Versioning**: A new feature that we have wanted to add to our GPM package management system is the ability to control dependencies by version. We have opted to use a syntax very similar to the Composer Package Manager that is already familiar to most PHP developers. This new versioning system allows you to define specific minimum version requirements of dependent packages within Grav. This should ensure that we have less (hopefully none!) issues when you update one package that also requires a specific minimum version of another package. The admin plugin for example may have an update that requires a specific version of Grav itself.
    * **GPM Testing Channel**: GPM repository now comes with both a `stable` and `testing` channel. A new setting in `system.gpm.releases` allow to switch between the two channels. Developers will be able to decide whether their resource is going to be in a pre-release state or stable. Only users who switch to the **testing** channel will be able to install a pre-release version.
    * **GPM Events**: Packages (plugins and themes) can now add event handlers to hook in the package GPM events: install, update, uninstall. A package can listen for events before and after each of these events, and can execute any PHP code, and optionally halt the procedure or return a message.
    * Refactor of the process chain breaking out `Processors` into individual classes to allow for easier modification and addition. Thanks to toovy for this work. - [#745](https://github.com/getgrav/grav/pull/745)
    * Added multipart downloads, resumable downloads, download throttling, and video streaming in the `Utils::download()` method.
    * Added optional config to allow Twig processing in page frontmatter - [#788](https://github.com/getgrav/grav/pull/788)
    * Added the ability to provide blueprints via a plugin (previously limited to Themes only).
    * Added Developer CLI Tools to easily create a new theme or plugin
    * Allow authentication for proxies - [#698](https://github.com/getgrav/grav/pull/698)
    * Allow to override the default Parsedown behavior - [#747](https://github.com/getgrav/grav/pull/747)
    * Added an option to allow to exclude external files from the pipeline, and to render the pipeline before/after excluded files
    * Added the possibility to store translations of themes in separate files inside the `languages` folder
    * Added a method to the Uri class to return the base relative URL including the language prefix, or the base relative url if multilanguage is not enabled
    * Added a shortcut for pages.find() alias
1. [](#improved)
    * Now supporting hostnames with localhost environments for better vhost support/development
    * Refactor hard-coded paths to use PHP Streams that allow a setup file to configure where certain parts of Grav are stored in the physical filesystem.
    * If multilanguage is active, include the Intl Twig Extension to allow translating dates automatically (http://twig.sensiolabs.org/doc/extensions/intl.html)
    * Allow having local themes with the same name as GPM themes, by adding `gpm: false` to the theme blueprint - [#767](https://github.com/getgrav/grav/pull/767)
    * Caddyfile and Lighttpd config files updated
    * Removed `node_modules` folder from backups to make them faster
    * Display error when `bin/grav install` hasn't been run instead of throwing exception. Prevents "white page" errors if error display is off
    * Improved command line flow when installing multiple packages: don't reinstall packages if already installed, ask once if should use symlinks if symlinks are found
    * Added more tests to our testing suite
    * Added x-ua-compatible to http_equiv metadata processing
    * Added ability to have a per-page `frontmatter.yaml` file to set header frontmatter defaults. Especially useful for multilang scenarios - [#775](https://github.com/getgrav/grav/pull/775)
    * Removed deprecated `bin/grav newuser` CLI command.  use `bin/plugin login newuser` instead.
    * Added `webm` and `ogv` video types to the default media types list.
1. [](#bugfix)
    * Fix Zend Opcache `opcache.validate_timestamps=0` not detecting changes in compiled yaml and twig files
    * Avoid losing params, query and fragment from the URL when auto-redirecting to a language-specific route - [#759](https://github.com/getgrav/grav/pull/759)
    * Fix for non-pipeline assets getting lost when pipeline is cached to filesystem
    * Fix for double encoding resulting from Markdown Extra
    * Fix for a remote link breaking all CSS rewrites for pipeline
    * Fix an issue with Retina alternatives not clearing properly between repeat uses
    * Fix for non standard http/s external markdown links - [#738](https://github.com/getgrav/grav/issues/738)
    * Fix for `find()` calling redirects via `dispatch()` causing infinite loops - [#781](https://github.com/getgrav/grav/issues/781)

# v1.0.10
## 02/11/2016

1. [](#new)
    * Added new `Page::contentMeta()` mechanism to store content-level meta data alongside content
    * Added Japanese language translation
1. [](#improved)
    * Updated some vendor libraries
1. [](#bugfix)
    * Hide `streams` blueprint from Admin plugin
    * Fix translations of languages with `---` in YAML files

# v1.0.9
## 02/05/2016

1. [](#new)
    * New **Unit Testing** via Codeception http://codeception.com/
    * New **page-level SSL** functionality when using `absolute_urls`
    * Added `reverse_proxy` config option for issues with non-standard ports
    * Added `proxy_url` config option to support GPM behind proxy servers #639
    * New `Pages::parentsRawRoutes()` method
    * Enhanced `bin/gpm info` CLI command with Changelog support #559
    * Ability to add empty *Folder* via admin plugin
    * Added latest `jQuery 2.2.0` library to core
    * Added translations from Crowdin
1. [](#improved)
    * [BC] Metadata now supports only flat arrays. To use open graph metas and the likes (ie, 'og:title'), simply specify it in the key.
    * Refactored `Uri::convertUrl()` method to be more reliable + tests created
    * Date for last update of a modular sub-page sets modified date of modular page itself
    * Split configuration up into two steps
    * Moved Grav-based `base_uri` variables into `Uri::init()`
    * Refactored init in `URI` to better support testing
    * Allow `twig_vars` to be exposed earlier and merged later
    * Avoid setting empty metadata
    * Accept single group access as a string rather than requiring an array
    * Return `$this` in Page constructor and init to allow chaining
    * Added `ext-*` PHP requirements to `composer.json`
    * Use Whoops 2.0 library while supporting old style
    * Removed redundant old default-hash fallback mechanisms
    * Commented out default redirects and routes in `site.yaml`
    * Added `/tests` folder to deny's of all `webserver-configs/*` files
    * Various PS and code style fixes
1. [](#bugfix)
    * Fix default generator metadata
    * Fix for broken image processing caused by `Uri::convertUrl()` bugs
    * Fix loading JS and CSS from collections #623
    * Fix stream overriding
    * Remove the URL extension for home link
    * Fix permissions when the user has no access level set at all
    * Fix issue with user with multiple groups getting denied on first group
    * Fixed an issue with `Pages()` internal cache lookup not being unique enough
    * Fix for bug with `site.redirects` and `site.routes` being an empty list
    * [Markdown] Don't process links for **special protocols**
    * [Whoops] serve JSON errors when request is JSON


# v1.0.8
## 01/08/2016

1. [](#new)
    * Added `rotate`, `flip` and `fixOrientation` image medium methods
1. [](#bugfix)
    * Removed IP from Nonce generation. Should be more reliable in a variety of scenarios

# v1.0.7
## 01/07/2016

1. [](#new)
    * Added `composer create-project` as an additional installation method #585
    * New optional system config setting to strip home from page routs and urls #561
    * Added Greek, Finnish, Norwegian, Polish, Portuguese, and Romanian languages
    * Added new `Page->topParent()` method to return top most parent of a page
    * Added plugins configuration tab to debugger
    * Added support for APCu and PHP7.0 via new Doctrine Cache release
    * Added global setting for `twig_first` processing (false by default)
    * New configuration options for Session settings #553
1. [](#improved)
    * Switched to SSL for GPM calls
    * Use `URI->host()` for session domain
    * Add support for `open_basedir` when installing packages via GPM
    * Improved `Utils::generateNonceString()` method to handle reverse proxies
    * Optimized core thumbnails saving 38% in file size
    * Added new `bin/gpm index --installed-only` option
    * Improved GPM errors to provider more helpful diagnostic of issues
    * Removed old hardcoded PHP version references
    * Moved `onPageContentProcessed()` event so it's fired more reliably
    * Maintain md5 keys during sorting of Assets #566
    * Update to Caddyfile for Caddy web server
1. [](#bugfix)
    * Fixed an issue with cache/config checksum not being set on cache load
    * Fix for page blueprint and theme inheritance issue #534
    * Set `ZipBackup` timeout to 10 minutes if possible
    * Fix case where we only have inline data for CSS or JS  #565
    * Fix `bin/grav sandbox` command to work with new `webserver-config` folder
    * Fix for markdown attributes on external URLs
    * Fixed issue where `data:` page header was acting as `publish_date:`
    * Fix for special characters in URL parameters (e.g. /tag:c++) #541
    * Safety check for an array of nonces to only use the first one

# v1.0.6
## 12/22/2015

1. [](#new)
    * Set minimum requirements to [PHP 5.5.9](http://bit.ly/1Jt9OXO)
    * Added `saveConfig` to Themes
1. [](#improved)
    * Updated Whoops to new 2.0 version (PHP 7.0 compatible)
    * Moved sample web server configs into dedicated directory
    * FastCGI will use Apache's `mod_deflate` if gzip turned off
1. [](#bugfix)
    * Fix broken media image operators
    * Only call extra method of blueprints if blueprints exist
    * Fix lang prefix in url twig variables #523
    * Fix case insensitive HTTPS check #535
    * Field field validation handles case `multiple` missing

# v1.0.5
## 12/18/2015

1. [](#new)
    * Add ability to extend markdown with plugins
    * Added support for plugins to have individual language files
    * Added `7z` to media formats
    * Use Grav's fork of Parsedown until PR is merged
    * New function to persist plugin configuration to disk
    * GPM `selfupgrade` will now check PHP version requirements
1. [](#improved)
    * If the field allows multiple files, return array
    * Handle non-array values in file validation
1. [](#bugfix)
    * Fix when looping `fields` param in a `list` field
    * Properly convert commas to spaces for media attributes
    * Forcing Travis VM to HI timezone to address future files in zip file

# v1.0.4
## 12/12/2015

1. [](#bugfix)
    * Needed to put default image folder permissions for YAML compatibility

# v1.0.3
## 12/11/2015

1. [](#bugfix)
    * Fixed issue when saving config causing incorrect image cache folder perms

# v1.0.2
## 12/11/2015

1. [](#bugfix)
    * Fix for timing display in debugbar

# v1.0.1
## 12/11/2015

1. [](#improved)
    * Reduced package sizes by removing extra vendor dev bits
1. [](#bugfix)
    * Fix issue when you enable debugger from admin plugin

# v1.0.0
## 12/11/2015

1. [](#new)
    * Add new link attributes via markdown media
    * Added setters to set state of CSS/JS pipelining
    * Added `user/accounts` to `.gitignore`
    * Added configurable permissions option for Image cache
1. [](#improved)
    * Hungarian translation updated
    * Refactored Theme initialization for improved flexibility
    * Wrapped security section of account blueprints in an 'super user' authorize check
    * Minor performance optimizations
    * Updated core page blueprints with markdown preview option
    * Added useful cache info output to Debugbar
    * Added `iconv` polyfill library used by Symfony 2.8
    * Force lowercase of username in a few places for case sensitive filesystems
1. [](#bugfix)
    * Fix for GPM problems "Call to a member function set() on null"
    * Fix for individual asset pipeline values not functioning
    * Fix `Page::copy()` and `Page::move()` to support multiple moves at once
    * Fixed page moving of a page with no content
    * Fix for wrong ordering when moving many pages
    * Escape root path in page medium files to work with special characters
    * Add missing parent constructor to Themes class
    * Fix missing file error in `bin/grav sandbox` command
    * Fixed changelog differ when upgrading Grav
    * Fixed a logic error in `Validation->validate()`
    * Make `$container` available in `setup.php` to fix multi-site

# v1.0.0-rc.6
## 12/01/2015

1. [](#new)
    * Refactor Config classes for improved performance!
    * Refactor Data classes to use `NestedArrayAccess` instead of `DataMutatorTrait`
    * Added support for `classes` and `id` on medium objects to set CSS values
    * Data objects: Allow function call chaining
    * Data objects: Lazy load blueprints only if needed
    * Automatically create unique security salt for each configuration
    * Added Hungarian translation
    * Added support for User groups
1. [](#improved)
    * Improved robots.txt to disallow crawling of non-user folders
    * Nonces only generated once per action and process
    * Added IP into Nonce string calculation
    * Nonces now use random string with random salt to improve performance
    * Improved list form handling #475
    * Vendor library updates
1. [](#bugfix)
    * Fixed help output for `bin/plugin`
    * Fix for nested logic for lists and form parsing #273
    * Fix for array form fields and last entry not getting deleted
    * Should not be able to set parent to self #308

# v1.0.0-rc.5
## 11/20/2015

1. [](#new)
    * Added **nonce** functionality for all admin forms for improved security
    * Implemented the ability for Plugins to provide their own CLI commands through `bin/plugin`
    * Added Croatian translation
    * Added missing `umask_fix` property to `system.yaml`
    * Added current theme's config to global config. E.g. `config.theme.dropdown_enabled`
    * Added `append_url_extension` option to system config & page headers
    * Users have a new `state` property to allow disabling/banning
    * Added new `Page.relativePagePath()` helper method
    * Added new `|pad` Twig filter for strings (uses `str_pad()`)
    * Added `lighttpd.conf` for Lightly web server
1. [](#improved)
    * Clear previously applied operations when doing a reset on image media
    * Password no longer required when editing user
    * Improved support for trailing `/` URLs
    * Improved `.nginx.conf` configuration file
    * Improved `.htaccess` security
    * Updated vendor libs
    * Updated `composer.phar`
    * Use streams instead of paths for `clearCache()`
    * Use PCRE_UTF8 so unicode strings can be regexed in Truncator
    * Handle case when login plugin is disabled
    * Improved `quality` functionality in media handling
    * Added some missing translation strings
    * Deprecated `bin/grav newuser` in favor of `bin/plugin login new-user`
    * Moved fallback types to use any valid media type
    * Renamed `system.pages.fallback_types` to `system.media.allowed_fallback_types`
    * Removed version number in default `generator` meta tag
    * Disable time limit in case of slow downloads
    * Removed default hash in `system.yaml`
1. [](#bugfix)
    * Fix for media using absolute URLs causing broken links
    * Fix theme auto-loading #432
    * Don't create empty `<style>` or `<script>` scripts if no data
    * Code cleanups
    * Fix undefined variable in Config class
    * Fix exception message when label is not set
    * Check in `Plugins::get()` to ensure plugins exists
    * Fixed GZip compression making output buffering work correctly with all servers and browsers
    * Fixed date representation in system config

# v1.0.0-rc.4
## 10/29/2015

1. [](#bugfix)
    * Fixed a fatal error if you have a collection with missing or invalid `@page: /route`

# v1.0.0-rc.3
## 10/29/2015

1. [](#new)
    * New Page collection options! `@self.parent, @self.siblings, @self.descendants` + more
    * White list of file types for fallback route functionality (images by default)
1. [](#improved)
    * Assets switched from defines to streams
1. [](#bugfix)
    * README.md typos fixed
    * Fixed issue with routes that have lang string in them (`/en/english`)
    * Trim strings before validation so whitespace is not satisfy 'required'

# v1.0.0-rc.2
## 10/27/2015

1. [](#new)
    * Added support for CSS Asset groups
    * Added a `wrapped_site` system option for themes/plugins to use
    * Pass `Page` object as event to `onTwigPageVariables()` event hook
    * New `Data.items()` method to get all items
1. [](#improved)
    * Missing pipelined remote asset will now fail quietly
    * More reliably handle inline JS and CSS to remove only surrounding HTML tags
    * `Medium.meta` returns new Data object so null checks are possible
    * Improved Medium metadata merging to allow for automatic title/alt/class attributes
    * Moved Grav object to global variable rather than template variable (useful for macros)
    * German language improvements
    * Updated bundled composer
1. [](#bugfix)
    * Accept variety of `true` values in `User.authorize()` method
    * Fix for `Validation` throwing an error if no label set

# v1.0.0-rc.1
## 10/23/2015

1. [](#new)
    * Use native PECL YAML parser if installed for 4X speed boost in parsing YAML files
    * Support for inherited theme class
    * Added new default language prepend system configuration option
    * New `|evaluate` Twig filter to evaluate a string as twig
    * New system option to ignore all **hidden** files and folders
    * New system option for default redirect code
    * Added ability to append specific `[30x]` codes to redirect URLs
    * Added `url_taxonomy_filters` for page collections
    * Added `@root` page and `recurse` flag for page collections
    * Support for **multiple** page collection types as an array
    * Added Dutch language file
    * Added Russian language file
    * Added `remove` method to User object
1. [](#improved)
    * Moved hardcoded mimetypes to `media.yaml` to be treated as Page media files
    * Set `errors: display: false` by default in `system.yaml`
    * Strip out extra slashes in the URI
    * Validate hostname to ensure it is valid
    * Ignore more SCM folders in Backups
    * Removed `home_redirect` settings from `system.yaml`
    * Added Page `media` as root twig object for consistency
    * Updated to latest vendor libraries
    * Optimizations to Asset pipeline logic for minor speed increase
    * Block direct access to a variety of files in `.htaccess` for increased security
    * Debugbar vendor library update
    * Always fallback to english if other translations are not available
1. [](#bugfix)
    * Fix for redirecting external URL with multi-language
    * Fix for Asset pipeline not respecting asset groups
    * Fix language files with child/parent theme relationships
    * Fixed a regression issue resulting in incorrect default language
    * Ensure error handler is initialized before URI is processed
    * Use default language in Twig if active language is not set
    * Fixed issue with `safeEmailFilter()` Twig filter not separating with `;` properly
    * Fixed empty YAML file causing error with native PECL YAML parser
    * Fixed `SVG` mimetype
    * Fixed incorrect `Cache-control: max-age` value format

# v0.9.45
## 10/08/2015

1. [](#bugfix)
    * Fixed a regression issue resulting in incorrect default language

# v0.9.44
## 10/07/2015

1. [](#new)
    * Added Redis back as a supported cache mechanism
    * Allow Twig `nicetime` translations
    * Added `-y` option for 'Yes to all' in `bin/gpm update`
    * Added CSS `media` attribute to the Assets manager
    * New German language support
    * New Czech language support
    * New French language support
    * Added `modulus` twig filter
1. [](#improved)
    * URL decode in medium actions to allow complex syntax
    * Take into account `HTTP_HOST` before `SERVER_NAME` (helpful with Nginx)
    * More friendly cache naming to ease manual management of cache systems
    * Added default Apache resource for `DirectoryIndex`
1. [](#bugfix)
    * Fix GPM failure when offline
    * Fix `open_basedir` error in `bin/gpm install`
    * Fix an HHVM error in Truncator
    * Fix for XSS vulnerability with params
    * Fix chaining for responsive size derivatives
    * Fix for saving pages when removing the page title and all other header elements
    * Fix when saving array fields
    * Fix for ports being included in `HTTP_HOST`
    * Fix for Truncator to handle PHP tags gracefully
    * Fix for locate style lang codes in `getNativeName()`
    * Urldecode image basenames in markdown

# v0.9.43
## 09/16/2015

1. [](#new)
    * Added new `AudioMedium` for HTML5 audio
    * Added ability for Assets to be added and displayed in separate *groups*
    * New support for responsive image derivative sizes
1. [](#improved)
    * GPM theme install now uses a `copy` method so new files are not lost (e.g. `/css/custom.css`)
    * Code analysis improvements and cleanup
    * Removed Twig panel from debugger (no longer supported in Twig 1.20)
    * Updated composer packages
    * Prepend active language to `convertUrl()` when used in markdown links
    * Added some pre/post flight options for installer via blueprints
    * Hyphenize the site name in the backup filename
1. [](#bugfix)
    * Fix broken routable logic
    * Check for `phpinfo()` method in case it is restricted by hosting provider
    * Fixes for windows when running GPM
    * Fix for ampersand (`&`) causing error in `truncateHtml()` via `Page.summary()`

# v0.9.42
## 09/11/2015

1. [](#bugfix)
    * Fixed `User.authorise()` to be backwards compabile

# v0.9.41
## 09/11/2015

1. [](#new)
    * New and improved multibyte-safe TruncateHTML function and filter
    * Added support for custom page date format
    * Added a `string` Twig filter to render as json_encoded string
    * Added `authorize` Twig filter
    * Added support for theme inheritance in the admin
    * Support for multiple content collections on a page
    * Added configurable files/folders ignores for pages
    * Added the ability to set the default PHP locale and override via multi-lang configuration
    * Added ability to save as YAML via admin
    * Added check for `mbstring` support
    * Added new `redirect` header for pages
1. [](#improved)
    * Changed dependencies from `develop` to `master`
    * Updated logging to log everything from `debug` level on (was `warning`)
    * Added missing `accounts/` folder
    * Default to performing a 301 redirect for URIs with trailing slashes
    * Improved Twig error messages
    * Allow validating of forms from anywhere such as plugins
    * Added logic so modular pages are by default non-routable
    * Hide password input in `bin/grav newuser` command
1. [](#bugfix)
    * Fixed `Pages.all()` not returning modular pages
    * Fix for modular template types not getting found
    * Fix for `markdown_extra:` overriding `markdown:extra:` setting
    * Fix for multi-site routing
    * Fix for multi-lang page name error
    * Fixed a redirect loop in `URI` class
    * Fixed a potential error when `unsupported_inline_types` is empty
    * Correctly generate 2x retina image
    * Typo fixes in page publish/unpublish blueprint

# v0.9.40
## 08/31/2015

1. [](#new)
    * Added some new Twig filters: `defined`, `rtrim`, `ltrim`
    * Admin support for customizable page file name + template override
1. [](#improved)
    * Better message for incompatible/unsupported Twig template
    * Improved User blueprints with better help
    * Switched to composer **install** rather than **update** by default
    * Admin autofocus on page title
    * `.htaccess` hardening (`.htaccess` & `htaccess.txt`)
    * Cache safety checks for missing folders
1. [](#bugfix)
    * Fixed issue with unescaped `o` character in date formats

# v0.9.39
## 08/25/2015

1. [](#bugfix)
    * `Page.active()` not triggering on **homepage**
    * Fix for invalid session name in Opera browser

# v0.9.38
## 08/24/2015

1. [](#new)
    * Added `language` to **user** blueprint
    * Added translations to blueprints
    * New extending logic for blueprints
    * Blueprints are now loaded with Streams to allow for better overrides
    * Added new Symfony `dump()` method
1. [](#improved)
    * Catch YAML header parse exception so site doesn't die
    * Better `Page.parent()` logic
    * Improved GPM display layout
    * Tweaked default page layout
    * Unset route and slug for improved reliability of route changes
    * Added requirements to README.md
    * Updated various libraries
    * Allow use of custom page date field for dateRange collections
1. [](#bugfix)
    * Slug fixes with GPM
    * Unset plaintext password on save
    * Fix for trailing `/` not matching active children

# v0.9.37
## 08/12/2015

3. [](#bugfix)
    * Fixed issue when saving `header.process` in page forms via the **admin plugin**
    * Fixed error due to use of `set_time_limit` that might be disabled on some hosts

# v0.9.36
## 08/11/2015

1. [](#new)
    * Added a new `newuser` CLI command to create user accounts
    * Added `default` blueprint for all templates
    * Support `user` and `system` language translation merging
1. [](#improved)
    * Added isSymlink method in GPM to determine if Grav is symbolically linked or not
    * Refactored page recursing
    * Updated blueprints to use new toggles
    * Updated blueprints to use current date for date format fields
    * Updated composer.phar
    * Use sessions for admin even when disabled for site
    * Use `GRAV_ROOT` in session identifier

# v0.9.35
## 08/06/2015

1. [](#new)
    * Added `body_classes` field
    * Added `visiblity` toggle and help tooltips on new page form
    * Added new `Page.unsetRoute()` method to allow admin to regenerate the route
2. [](#improved)
    * User save no longer stores username each time
    * Page list form field now shows all pages except root
    * Removed required option from page title
    * Added configuration settings for running Nginx in sub directory
3. [](#bugfix)
    * Fixed deep translation merging
    * Fixed broken **metadata** merging with site defaults
    * Fixed broken **summary** field
    * Fixed broken robots field
    * Fixed GPM issue when using cURL, throwing an `Undefined offset: 1` exception
    * Removed duplicate hidden page `type` field

# v0.9.34
## 08/04/2015

1. [](#new)
    * Added new `cache_all` system setting + media `cache()` method
    * Added base languages configuration
    * Added property language to page to help plugins identify page language
    * New `Utils::arrayFilterRecursive()` method
2. [](#improved)
    * Improved Session handling to support site and admin independently
    * Allow Twig variables to be modified in other events
    * Blueprint updates in preparation for Admin plugin
    * Changed `Inflector` from static to object and added multi-language support
    * Support for admin override of a page's blueprints
3. [](#bugfix)
    * Removed unused `use` in `VideoMedium` that was causing error
    * Array fix in `User.authorise()` method
    * Fix for typo in `translations_fallback`
    * Fixed moving page to the root

# v0.9.33
## 07/21/2015

1. [](#new)
    * Added new `onImageMediumSaved()` event (useful for post-image processing)
    * Added `Vary: Accept-Encoding` option
2. [](#improved)
    * Multilang-safe delimiter position
    * Refactored Twig classes and added optional umask setting
    * Removed `pageinit()` timing
    * `Page->routable()` now takes `published()` state into account
    * Improved how page extension is set
    * Support `Language->translate()` method taking string and array
3. [](#bugfix)
    * Fixed `backup` command to include empty folders

# v0.9.32
## 07/14/2015

1. [](#new)
    * Detect users preferred language via `http_accept_language` setting
    * Added new `translateArray()` language method
2. [](#improved)
    * Support `en` translations by default for plugins & themes
    * Improved default generator tag
    * Minor language tweaks and fixes
3. [](#bugfix)
    * Fix for session active language and homepage redirects
    * Ignore root-level page rather than throwing error

# v0.9.31
## 07/09/2015

1. [](#new)
    * Added xml, json, css and js to valid media file types
2. [](#improved)
    * Better handling of unsupported media type downloads
    * Improved `bin/grav backup` command to mimic admin plugin location/name
3. [](#bugfix)
    * Critical fix for broken language translations
    * Fix for Twig markdown filter error
    * Safety check for download extension

# v0.9.30
## 07/08/2015

1. [](#new)
    * BIG NEWS! Extensive Multi-Language support is all new in 0.9.30!
    * Translation support via Twig filter/function and PHP method
    * Page specific default route
    * Page specific route aliases
    * Canonical URL route support
    * Added built-in session support
    * New `Page.rawRoute()` to get a consistent folder-based route to a page
    * Added option to always redirect to default page on alias URL
    * Added language safe redirect function for use in core and plugins
2. [](#improved)
    * Improved `Page.active()` and `Page.activeChild()` methods to support route aliases
    * Various spelling corrections in `.php` comments, `.md` and `.yaml` files
    * `Utils::startsWith()` and `Utils::endsWith()` now support needle arrays
    * Added a new timer around `pageInitialized` event
    * Updated jQuery library to v2.1.4
3. [](#bugfix)
    * In-page CSS and JS files are now handled properly
    * Fix for `enable_media_timestamp` not working properly

# v0.9.29
## 06/22/2015

1. [](#new)
    * New and improved Regex-powered redirect and route alias logic
    * Added new `onBuildPagesInitialized` event for memory critical or time-consuming plugins
    * Added a `setSummary()` method for pages
2. [](#improved)
    * Improved `MergeConfig()` logic for more control
    * Travis skeleton build trigger implemented
    * Set composer.json versions to stable versions where possible
    * Disabled `last_modified` and `etag` page headers by default (causing too much page caching)
3. [](#bugfix)
    * Preload classes during `bin/gpm selfupgrade` to avoid issues with updated classes
    * Fix for directory relative _down_ links

# v0.9.28
## 06/16/2015

1. [](#new)
    * Added method to set raw markdown on a page
    * Added ability to enabled system and page level `etag` and `last_modified` headers
2. [](#improved)
    * Improved image path processing
    * Improved query string handling
    * Optimization to image handling supporting URL encoded filenames
    * Use global `composer` when available rather than Grv provided one
    * Use `PHP_BINARY` constant rather than `php` executable
    * Updated Doctrine Cache library
    * Updated Symfony libraries
    * Moved `convertUrl()` method to Uri object
3. [](#bugfix)
    * Fix incorrect slug causing problems with CLI `uninstall`
    * Fix Twig runtime error with assets pipeline in sufolder installations
    * Fix for `+` in image filenames
    * Fix for dot files causing issues with page processing
    * Fix for Uri path detection on Windows platform
    * Fix for alternative media resolutions
    * Fix for modularTypes key properties

# v0.9.27
## 05/09/2015

1. [](#new)
    * Added new composer CLI command
    * Added page-level summary header overrides
    * Added `size` back for Media objects
    * Refactored Backup command in preparation for admin plugin
    * Added a new `parseLinks` method to Plugins class
    * Added `starts_with` and `ends_with` Twig filters
2. [](#improved)
    * Optimized install of vendor libraries for speed improvement
    * Improved configuration handling in preparation for admin plugin
    * Cache optimization: Don't cache Twig templates when you pass dynamic params
    * Moved `Utils::rcopy` to `Folder::rcopy`
    * Improved `Folder::doDelete`
    * Added check for required Curl in GPM
    * Updated included composer.phar to latest version
    * Various blueprint fixes for admin plugin
    * Various PSR and code cleanup tasks
3. [](#bugfix)
    * Fix issue with Gzip not working with `onShutDown()` event
    * Fix for URLs with trailing slashes
    * Handle condition where certain errors resulted in blank page
    * Fix for issue with theme name equal to base_url and asset pipeline
    * Fix to properly normalize font rewrite path
    * Fix for absolute URLs below the current page
    * Fix for `..` page references

# v0.9.26
## 04/24/2015

3. [](#bugfix)
    * Fixed issue with homepage routes failing with 'dirname' error

# v0.9.25
## 04/24/2015

1. [](#new)
    * Added support for E-Tag, Last-Modified, Cache-Control and Page-based expires headers
2. [](#improved)
    * Refactored media image handling to make it more flexible and support absolute paths
    * Refactored page modification check process to make it faster
    * User account improvements in preparation for admin plugin
    * Protect against timing attacks
    * Reset default system expires time to 0 seconds (can override if you need to)
3. [](#bugfix)
    * Fix issues with spaces in webroot when using `bin/grav install`
    * Fix for spaces in relative directory
    * Bug fix in collection filtering

# v0.9.24
## 04/15/2015

1. [](#new)
    * Added support for chunked downloads of Assets
    * Added new `onBeforeDownload()` event
    * Added new `download()` and `getMimeType()` methods to Utils class
    * Added configuration option for supported page types
    * Added assets and media timestamp options (off by default)
    * Added page expires configuration option
2. [](#bugfix)
    * Fixed issue with Nginx/Gzip and `ob_flush()` throwing error
    * Fixed assets actions on 'direct media' URLs
    * Fix for 'direct assets` with any parameters

# v0.9.23
## 04/09/2015

1. [](#bugfix)
    * Fix for broken GPM `selfupgrade` (Grav 0.9.21 and 0.9.22 will need to manually upgrade to this version)

# v0.9.22
## 04/08/2015

1. [](#bugfix)
    * Fix to normalize GRAV_ROOT path for Windows
    * Fix to normalize Media image paths for Windows
    * Fix for GPM `selfupgrade` when you are on latest version

# v0.9.21
## 04/07/2015

1. [](#new)
    * Major Media functionality enhancements: SVG, Animated GIF, Video support!
    * Added ability to configure default image quality in system configuration
    * Added `sizes` attributes for custom retina image breakpoints
2. [](#improved)
    * Don't scale @1x retina images
    * Add filter to Iterator class
    * Updated various composer packages
    * Various PSR fixes

# v0.9.20
## 03/24/2015

1. [](#new)
    * Added `addAsyncJs()` and `addDeferJs()` to Assets manager
    * Added support for extranal URL redirects
2. [](#improved)
    * Fix unpredictable asset ordering when set from plugin/system
    * Updated `nginx.conf` to ensure system assets are accessible
    * Ensure images are served as static files in Nginx
    * Updated vendor libraries to latest versions
    * Updated included composer.phar to latest version
3. [](#bugfix)
    * Fixed issue with markdown links to `#` breaking HTML

# v0.9.19
## 02/28/2015

1. [](#new)
    * Added named assets capability and bundled jQuery into Grav core
    * Added `first()` and `last()` to `Iterator` class
2. [](#improved)
    * Improved page modification routine to skip _dot files_
    * Only use files to calculate page modification dates
    * Broke out Folder iterators into their own classes
    * Various Sensiolabs Insight fixes
3. [](#bugfix)
    * Fixed `Iterator.nth()` method

# v0.9.18
## 02/19/2015

1. [](#new)
    * Added ability for GPM `install` to automatically install `_demo` content if found (w/backup)
    * Added ability for themes and plugins to have dependencies required to install via GPM
    * Added ability to override the system timezone rather than relying on server setting only
    * Added new Twig filter `random_string` for generating random id values
    * Added new Twig filter `markdown` for on-the-fly markdown processing
    * Added new Twig filter `absoluteUrl` to convert relative to absolute URLs
    * Added new `processTemplate()` method to Twig object for on-the-fly processing of twig template
    * Added `rcopy()` and `contains()` helper methods in Utils
2. [](#improved)
    * Provided new `param_sep` variable to better support Apache on Windows
    * Moved parsedown configuration into the trait
    * Added optional **deep-copy** option to `mergeConfig()` for plugins
    * Updated bundled `composer.phar` package
    * Various Sensiolabs Insight fixes - Silver level now!
    * Various PSR Fixes
3. [](#bugfix)
    * Fix for windows platforms not displaying installed themes/plugins via GPM
    * Fix page IDs not picking up folder-only pages

# v0.9.17
## 02/05/2015

1. [](#new)
    * Added **full HHVM support!** Get your speed on with Facebook's crazy fast PHP JIT compiler
2. [](#improved)
    * More flexible page summary control
    * Support **CamelCase** plugin and theme class names. Replaces dashes and underscores
    * Moved summary delimiter into `site.yaml` so it can be configurable
    * Various PSR fixes
3. [](#bugfix)
     * Fix for `mergeConfig()` not falling back to defaults
     * Fix for `addInlineCss()` and `addInlineJs()` Assets not working between Twig tags
     * Fix for Markdown adding HTML tags into inline CSS and JS

# v0.9.16
## 01/30/2015

1. [](#new)
    * Added **Retina** and **Responsive** image support via Grav media and `srcset` image attribute
    * Added image debug option that overlays responsive resolution
    * Added a new image cache stream
2. [](#improved)
    * Improved the markdown Lightbox functionality to better mimic Twig version
    * Fullsize Lightbox can now have filters applied
    * Added a new `mergeConfig()` method to Plugin class to merge system + page header configuration
    * Added a new `disable()` method to Plugin class to programmatically disable a plugin
    * Updated Parsedown and Parsedown Extra to address bugs
    * Various PSR fixes
3. [](#bugfix)
     * Fix bug with image dispatch in traditionally _non-routable_ pages
     * Fix for markdown link not working on non-current pages
     * Fix for markdown images not being found on homepage

# v0.9.15
## 01/23/2015

3. [](#bugfix)
     * Typo in video mime types
     * Fix for old `markdown_extra` system setting not getting picked up
     * Fix in regex for Markdown links with numeric values in path
     * Fix for broken image routing mechanism that got broken at some point
     * Fix for markdown images/links in pages with page slug override

# v0.9.14
## 01/23/2015

1. [](#new)
    * Added **GZip** support
    * Added multiple configurations via `setup.php`
    * Added base structure for unit tests
    * New `onPageContentRaw()` plugin event that processes before any page processing
    * Added ability to dynamically set Metadata on page
    * Added ability to dynamically configure Markdown processing via Parsedown options
2. [](#improved)
    * Refactored `page.content()` method to be more flexible and reliable
    * Various updates and fixes for streams resulting in better multi-site support
    * Updated Twig, Parsedown, ParsedownExtra, DoctrineCache libraries
    * Refactored Parsedown trait
    * Force modular pages to be non-visible in menus
    * Moved RewriteBase before Exploits in `.htaccess`
    * Added standard video formats to Media support
    * Added priority for inline assets
    * Check for uniqueness when adding multiple inline assets
    * Improved support for Twig-based URLs inside Markdown links and images
    * Improved Twig `url()` function
3. [](#bugfix)
    * Fix for HTML entities quotes in Metadata values
    * Fix for `published` setting to have precedent of `publish_date` and `unpublish_date`
    * Fix for `onShutdown()` events not closing connections properly in **php-fpm** environments

# v0.9.13
## 01/09/2015

1. [](#new)
    * Added new published `true|false` state in page headers
    * Added `publish_date` in page headers to automatically publish page
    * Added `unpublish_date` in page headers to automatically unpublish page
    * Added `dateRange()` capability for collections
    * Added ability to dynamically control Cache lifetime programmatically
    * Added ability to sort by anything in the page header. E.g. `sort: header.taxonomy.year`
    * Added various helper methods to collections: `copy, nonVisible, modular, nonModular, published, nonPublished, nonRoutable`
2. [](#improved)
    * Modified all Collection methods so they can be chained together: `$collection->published()->visible()`
    * Set default Cache lifetime to default of 1 week (604800 seconds) - was infinite
    * House-cleaning of some unused methods in Pages object
3. [](#bugfix)
    * Fix `uninstall` GPM command that was broken in last release
    * Fix for intermittent `undefined index` error when working with Collections
    * Fix for date of some pages being set to incorrect future timestamps

# v0.9.12
## 01/06/2015

1. [](#new)
    * Added an all-access robots.txt file for search engines
    * Added new GPM `uninstall` command
    * Added support for **in-page** Twig processing in **modular** pages
    * Added configurable support for `undefined` Twig functions and filters
2. [](#improved)
    * Fall back to default `.html` template if error occurs on non-html pages
    * Added ability to have PSR-1 friendly plugin names (CamelCase, no-dashes)
    * Fix to `composer.json` to deter API rate-limit errors
    * Added **non-exception-throwing** handler for undefined methods on `Medium` objects
3. [](#bugfix)
    * Fix description for `self-upgrade` method of GPM command
    * Fix for incorrect version number when performing GPM `update`
    * Fix for argument description of GPM `install` command
    * Fix for recalcitrant CodeKit mac application

# v0.9.11
## 12/21/2014

1. [](#new)
    * Added support for simple redirects as well as routes
2. [](#improved)
    * Handle Twig errors more cleanly
3. [](#bugfix)
    * Fix for error caused by invalid or missing user agent string
    * Fix for directory relative links and URL fragments (#pagelink)
    * Fix for relative links with no subfolder in `base_url`

# v0.9.10
## 12/12/2014

1. [](#new)
    * Added Facebook-style `nicetime` date Twig filter
2. [](#improved)
    * Moved `clear-cache` functionality into Cache object required for Admin plugin
3. [](#bugfix)
    * Fix for undefined index with previous/next buttons

# v0.9.9
## 12/05/2014

1. [](#new)
    * Added new `@page` collection type
    * Added `ksort` and `contains` Twig filters
    * Added `gist` Twig function
2. [](#improved)
    * Refactored Page previous/next/adjacent functionality
    * Updated to Symfony 2.6 for yaml/console/event-dispatcher libraries
    * More PSR code fixes
3. [](#bugfix)
    * Fix for over-escaped apostrophes in YAML

# v0.9.8
## 12/01/2014

1. [](#new)
    * Added configuration option to set default lifetime on cache saves
    * Added ability to set HTTP status code from page header
    * Implemented simple wild-card custom routing
2. [](#improved)
    * Fixed elusive double load to fully cache issue (crossing fingers...)
    * Ensure Twig tags are treated as block items in markdown
    * Removed some older deprecated methods
    * Ensure onPageContentProcessed() event only fires when not cached
    * More PSR code fixes
3. [](#bugfix)
    * Fix issue with miscalculation of blog separator location `===`

# v0.9.7
## 11/24/2014

1. [](#improved)
    * Nginx configuration updated
    * Added gitter.im badge to README
    * Removed `set_time_limit()` and put checks around `ignore_user_abort`
    * More PSR code fixes
2. [](#bugfix)
    * Fix issue with non-valid asset path showing up when they shouldn't
    * Fix for JS asset pipeline and scripts that don't end in `;`
    * Fix for schema-based markdown URLs broken routes (eg `mailto:`)

# v0.9.6
## 11/17/2014

1. [](#improved)
    * Moved base_url variables into Grav container
    * Forced media sorting to use natural sort order by default
    * Various PSR code tidying
    * Added filename, extension, thumb to all medium objects
2. [](#bugfix)
    * Fix for infinite loop in page.content()
    * Fix hostname for configuration overrides
    * Fix for cached configuration
    * Fix for relative URLs in markdown on installs with no base_url
    * Fix for page media images with uppercase extension

# v0.9.5
## 11/09/2014

1. [](#new)
    * Added quality setting to medium for compression configuration of images
    * Added new onPageContentProcessed() event that is post-content processing but pre-caching
2. [](#improved)
    * Added support for AND and OR taxonomy filtering.  AND by default (was OR)
    * Added specific clearing options for CLI clear-cache command
    * Moved environment method to URI so it can be accessible in plugins and themes
    * Set Grav's output variable to public so it can be manipulated in onOutputGenerated event
    * Updated vendor libraries to latest versions
    * Better handing of 'home' in active menu state detection
    * Various PSR code tidying
    * Improved some error messages and notices
3. [](#bugfix)
    * Force route rebuild when configuration changes
    * Fix for 'installed undefined' error in CLI versions command
    * Do not remove the JSON/Text error handlers
    * Fix for supporting inline JS and CSS when Asset pipeline enabled
    * Fix for Data URLs in CSS being badly formed
    * Fix Markdown links with fragment and query elements

# v0.9.4
## 10/29/2014

1. [](#new)
    * New improved Debugbar with messages, timing, config, twig information
    * New exception handling system utilizing Whoops
    * New logging system utilizing Monolog
    * Support for auto-detecting environment configuration
    * New version command for CLI
    * Integrate Twig dump() calls into Debugbar
2. [](#improved)
    * Selfupgrade now clears cache on successful upgrade
    * Selfupgrade now supports files without extensions
    * Improved error messages when plugin is missing
    * Improved security in .htaccess
    * Support CSS/JS/Image assets in vendor/system folders via .htaccess
    * Add support for system timers
    * Improved and optimized configuration loading
    * Automatically disable Debugbar on non-HTML pages
    * Disable Debugbar by default
3. [](#bugfix)
    * More YAML blueprint fixes
    * Fix potential double // in assets
    * Load debugger as early as possible

# v0.9.3
## 10/09/2014

1. [](#new)
    * GPM (Grav Package Manager) Added
    * Support for multiple Grav configurations
    * Dynamic media support via URL
    * Added inlineCss and inlineJs support for Assets
2. [](#improved)
    * YAML caching for increased performance
    * Use stream wrapper in pages, plugins and themes
    * Switched to RocketTheme toolbox for some core functionality
    * Renamed `setup` CLI command to `sandbox`
    * Broke cache types out into multiple directories in the cache folder
    * Removed vendor libs from github repository
    * Various PSR cleanup of code
    * Various Blueprint updates to support upcoming admin plugin
    * Added ability to filter page children for normal/modular/all
    * Added `sort_by_key` twig filter
    * Added `visible()` and `routable()` filters to page collections
    * Use session class in shutdown process
    * Improvements to modular page loading
    * Various code cleanup and optimizations
3. [](#bugfix)
    * Fixed file checking not updating the last modified time. For real this time!
    * Switched debugger to PRODUCTION mode by default
    * Various fixes in URI class for increased reliability

# v0.9.2
## 09/15/2014

1. [](#new)
    * New flexible site and page metadata support including ObjectGraph and Facebook
    * New method to get user IP address in URI object
    * Added new onShutdown() event that fires after connection is closed for Async features
2. [](#improved)
    * Skip assets pipeline minify on Windows platforms by default due to PHP issue 47689
    * Fixed multiple level menus not highlighting correctly
    * Updated some blueprints in preparation for admin plugin
    * Fail gracefully when theme does not exist
    * Add stream support into ResourceLocator::addPath()
    * Separate themes from plugins, add themes:// stream and onTask events
    * Added barDump() to Debugger
    * Removed stray test page
    * Override modified only if a non-markdown file was modified
    * Added assets attributes support
    * Auto-run composer install when running the Grav CLI
    * Vendor folder removed from repository
    * Minor configuration performance optimizations
    * Minor debugger performance optimizations
3. [](#bugfix)
    * Fix url() twig function when Grav isn't installed at root
    * Workaround for PHP bug 52065
    * Fixed getList() method on Pages object that was not working
    * Fix for open_basedir error
    * index.php now warns if not running on PHP 5.4
    * Removed memcached option (redundant)
    * Removed memcache from auto setup, added memcache server configuration option
    * Fix broken password validation
    * Back to proper PSR-4 Autoloader

# v0.9.1
## 09/02/2014

1. [](#new)
    * Added new `theme://` PHP stream for current theme
2. [](#improved)
    * Default to new `file` modification checking rather than `folder`
    * Added support for various markdown link formats to convert to Grav-friendly URLs
    * Moved configure() from Theme to Themes class
    * Fix autoloading without composer update -o
    * Added support for Twig url method
    * Minor code cleanup
3. [](#bugfix)
    * Fixed issue with page changes not being picked up
    * Fixed Minify to provide `@supports` tag compatibility
    * Fixed ResourceLocator not working with multiple paths
    * Fixed issue with Markdown process not stripping LFs
    * Restrict file type extensions for added security
    * Fixed template inheritance
    * Moved Browser class to proper location

# v0.9.0
## 08/25/2014

1. [](#new)
    * Addition of Dependency Injection Container
    * Refactored plugins to use Symfony Event Dispatcher
    * New Asset Manager to provide unified management of JavaScript and CSS
    * Asset Pipelining to provide unification, minify, and optimization of JavaScript and CSS
    * Grav Media support directly in Markdown syntax
    * Additional Grav Generator meta tag in default themes
    * Added support for PHP Stream Wrapper for resource location
    * Markdown Extra support
    * Browser object for fast browser detection
2. [](#improved)
    * PSR-4 Autoloader mechanism
    * Tracy Debugger new `detect` option to detect running environment
    * Added new `random` collection sort option
    * Make media images progressive by default
    * Additional URI filtering for improved security
    * Safety checks to ensure PHP 5.4.0+
    * Move to Slidebars side navigation in default Antimatter theme
    * Updates to `.htaccess` including section on `RewriteBase` which is needed for some hosting providers
3. [](#bugfix)
    * Fixed issue when installing in an apache userdir (~username) folder
    * Various mobile CSS issues in default themes
    * Various minor bug fixes


# v0.8.0
## 08/13/2014

1. [](#new)
    * Initial Release
