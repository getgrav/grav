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
