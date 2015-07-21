# v0.9.33
## 07/21/2015

1. [](#new)
    * Added new `onImageMediumSaved()` event (useful for post-image processing)
    * Added `Vary: Accept-Encoding` option
2. [](#improved)
    * Multilang-safe delimeter position
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
    * Use `PHP_BINARY` contant rather than `php` executable
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
