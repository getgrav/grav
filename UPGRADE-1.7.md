# UPGRADE FROM 1.6 TO 1.7

## ADMINISTRATORS

### YAML files

* Please run `bin/grav yamllinter` to find any YAML parsing errors in your site. You should run this command before and after upgrade. Grav falls back to older YAML parser if it detects an error, but it will slow down your site.

## Forms

* **BC BREAK** Fixed `validation: strict`. Please search through all your forms if you were using this feature. If you were, either remove the line or test if the form still works.
* Added configuration option `system.strict_mode.blueprint_compat` to maintain old `validation: strict` behavior
  * If you disable compatibiity, form validation will be much more strict (recommended, but may break existing forms)

### Pages

* **BC BREAK** Fixed 404 error page when you go to non-routable page with routable child pages under it. Now you get redirected to the first routable child page instead. This is probably what you wanted in the first place. If you do not want this new behavior, you need to **TODO**

### Media

* Support for `webp` image format
* Markdown: Added support for native `loading=lazy` attributes on images.  Can be set in `system.images.defaults` or per md image with `?loading=lazy`

### Multi-language

* Improved language support
* **BC BREAK** Please check that your fallback languages are correct. Old implementation had a fallback to any other language, now only default language is being used unless you use `system.languages.content_fallback` configuration option to override the default behavior.

### Admin

* If you upgrade from older 1.7 RC, you need to go to Flex Objects plugin settings and turn on `Pages`, `User Accounts` and `User Groups` directories (upgrading 1.6 automatically turns them on)
* Disabling `User Accounts` and `User Groups` directories in Flex Objects plugin should be kept enabled; fine tuned **ACL** may not work without

### Sessions

* Session ID now changes on login to prevent session fixation issues

### CLI

* Added new `bin/grav server` CLI command to easily run Symfony or PHP built-in web servers
* Added new `bin/grav page-system-validator [-r|--record] [-c|--check]` CLI command to test Flex Pages
* Improved `Scheduler` cron command check and more useful CLI information
* Added new `-r <job-id>` option for Scheduler CLI command to force-run a job
* Improved `bin/grav yamllinter` CLI command by adding an option to find YAML Linting issues from the whole site or custom folder

### Configuration

* Added new configuration option `system.debugger.provider` to choose between debugbar and clockwork
* Added new configuration option `system.debugger.censored` to hide potentially sensitive information
* Added new configuration option `system.pages.type` to enable Flex Pages
* Added new configuration option `system.languages.include_default_lang_file_extension` to keep default language in `.md` files if set to `false`
* Added new configuration option `system.languages.content_fallback` to set fallback content languages individually for every language
* Added new configuration option `security.sanitize_svg` to remove potentially dangerous code from SVG files
* Added system configuration support for `HTTP_X_Forwarded` headers (host disabled by default)
* Added new configuration option `system.strict_mode.blueprint_compat` to maintain old `validation: strict` behavior

### Debugging

* Added support for [Clockwork](https://underground.works/clockwork) developer tools (now default debugger)
* Added support for [Tideways XHProf](https://github.com/tideways/php-xhprof-extension) PHP Extension for profiling method calls
* Added Twig profiling for Clockwork debugger

## DEVELOPERS

### Use composer autoloader

* Please add `composer.json` file to your plugin and run `composer update --no-dev` (and remember to keep it updated):

    composer.json
    ```json
    {
        "name": "getgrav/grav-plugin-example",
        "type": "grav-plugin",
        "description": "Example plugin for Grav CMS",
        "keywords": ["example", "plugin"],
        "homepage": "https://github.com/getgrav/grav-plugin-example",
        "license": "MIT",
        "authors": [
            {
                "name": "...",
                "email": "...",
                "homepage": "...",
                "role": "Developer"
            }
        ],
        "support": {
            "issues": "https://github.com/getgrav/grav-plugin-example/issues",
            "docs": "https://github.com/getgrav/grav-plugin-example/blob/master/README.md"
        },
        "require": {
            "php": ">=7.1.3"
        },
        "autoload": {
            "psr-4": {
                "Grav\\Plugin\\Example\\": "classes/",
                "Grav\\Plugin\\Console\\": "cli/"
            },
            "classmap":  [
                "example.php"
            ]
        },
        "config": {
            "platform": {
                "php": "7.1.3"
            }
        }
    }
    ```

  See [Composer schema](https://getcomposer.org/doc/04-schema.md)

* Please use autoloader instead of `require` in the code:

    example.php
    ```php
      /**
       * @return array
       */
      public static function getSubscribedEvents(): array
      {
          return [
              'onPluginsInitialized' => [
                  // This is only required in Grav 1.6. Grav 1.7 automatically calls $plugin->autolaod() method.
                  ['autoload', 100000],
              ]
          ];
      }

      /**
       * Composer autoload.
       *
       * @return \Composer\Autoload\ClassLoader
       */
      public function autoload(): \Composer\Autoload\ClassLoader
      {
          return require __DIR__ . '/vendor/autoload.php';
      }
    ```

* Plugins & Themes: Call `$plugin->autoload()` and `$theme->autoload()` automatically when object gets initialized
* Make sure your code does not use `require` or `include` for loading classes

### Plugin/Theme Blueprints (`blueprints.yaml`)

* Please add:
    ```yaml
    slug: folder-name
    type: plugin|theme
    ```
* Make sure you update your dependencies. I recommend setting Grav to either 1.6 or 1.7 and update your code/vendor to PHP 7.1
    ```yaml
    dependencies:
        - { name: grav, version: '>=1.6.0' }
    ```

### Sessions

* Added `Session::regenerateId()` method to properly prevent session fixation issues

### ACL

* `user.authorize()` now requires user to be authorized (passed 2FA check), unless the rule contains `login` in its name.
* Added support for more advanced ACL (CRUD)

* **BC BREAK** `user.authorize()` and Flex `object.isAuthorized()` now have two deny states: `false` and `null`.

    Make sure you do not have strict checks against false: `$user->authorize($action) === false` (PHP)  or `user.authorize(action) is same as(false)` (Twig).

    For the negative checks you should be using `!user->authorize($action)` (PHP) or `not user.authorize(action)` (Twig).

    The change has been done to allow strong deny rules by chaining the actions if previous ones do not match: `user.authorize(action1) ?? user.authorize(action2) ?? user.authorize(action3)`.

    Note that Twig function `authorize()` will still **keeps** the old behavior!

### Pages

* Added experimental support for `Flex Pages` in the frontend (not recommended to use yet)
* Admin uses `Flex Pages` by default (can be disabled from `Flex-Objects` plugin)
* Added page specific admin permissions support for `Flex Pages`
* Added root page support for `Flex Pages`
* Fixed wrong `Pages::dispatch()` calls (with redirect) when we really meant to call `Pages::find()`
* Added `Pages::getCollection()` method
* Moved `collection()` and `evaluate()` logic from `Page` class into `Pages` class
* **DEPRECATED** `$page->modular()` in favor of `$page->isModule()`
* **BC BREAK** Fixed `Page::modular()` and `Page::modularTwig()` returning `null` for folders and other non-initialized pages. Should not affect your code unless you were checking against `false` or `null`.
* **BC BREAK** Always use `\Grav\Common\Page\Interfaces\PageInterface` instead of `\Grav\Common\Page\Page` in method signatures
* Admin now uses `Flex Pages` by default, collection will behave in slightly different way
* **BC BREAK** `$page->topParent()` may return page itself instead of null

### Media

* Added `MediaTrait::freeMedia()` method to free media (and memory)
* Added support for uploading and deleting images directly in `Media` by using PSR-7
* **BC BREAK** Media no longer extends `Getters`, accessing `$media->$filename` no longer works, use `$media[$filename]` instead!

### Markdown

* **BC BREAK** Upgraded Parsedown to 1.7 for Parsedown-Extra 0.8. Plugins that extend Parsedown may need a fix to render as HTML
* Added new `Excerpts::processLinkHtml()` method

### Users

* Added experimental support for `Flex Users` in the frontend (not recommended to use yet)
* Admin uses `Flex Users` by default (can be disabled from `Flex-Objects` plugin)
* Improved `Flex Users`: obey blueprints and allow Flex to be used in admin only
* Improved `Flex Users`: user and group ACL now supports deny permissions
* Changed `UserInterface::authorize()` to return `null` having the same meaning as `false` if access is denied because of no matching rule
* **DEPRECATED** `\Grav\Common\User\Group` in favor of `$grav['user_groups']`, which contains Flex UserGroup collection
* **BC BREAK** Always use `\Grav\Common\User\Interfaces\UserInterface` instead of `\Grav\Common\User\User` in method signatures

### Flex

* Do not use `Framework` Flex classes directly, it's better to use or extend classes under `Grav\Common\Flex\Types\Generic` namespace
* Added `$grav['flex']` to access all registered Flex Directories
  * Added `FlexRegisterEvent` which triggers when `$grav['flex']` is being accessed the first time
* Added `hasFlexFeature()` method to test if `FlexObject` or `FlexCollection` implements a given feature
* Added `getFlexFeatures()` method to return all features that `FlexObject` or `FlexCollection` implements
* Added `FlexStorage::getMetaData()` to get updated object meta information for listed keys
* `FlexDirectory::getObject()` can now be called without any parameters to create a new object
* **DEPRECATED** `FlexDirectory::update()` and `FlexDirectory::remove()`
* **BC BREAK** Moved all Flex type classes under `Grav\Common\Flex`
* **BC BREAK** `FlexStorageInterface::getStoragePath()` and `getMediaPath()` can now return null
* **BC BREAK** Flex objects no longer return temporary key if they do not have one; empty key is returned instead
* You can add `edit_list.html.twig` file to a form field in order to customize look in the listing view

### Templating

* Added support for Twig 2.12 (still using Twig 1.42)
* Added a new `{% cache %}` Twig tag eliminating need for `twigcache` extension.
* Added `array_diff()` twig function
* Added `template_from_string()` twig function
* Improved `url()` twig function to take third parameter (`true`) to return URL on non-existing file instead of returning false
* Improved `|array` twig filter to work with iterators and objects with `toArray()` method
* Improved `authorize()` twig function to work better with nested rule parameters
* Improved `|yaml_serialize` twig filter: added support for `JsonSerializable` objects and other array-like objects

### Multi-language

* Improved language support for `Route` class
* Translations: rename MODULAR to MODULE everywhere
* Added `Language::getPageExtensions()` to get full list of supported page language extensions
* **BC BREAK** Fixed `Language::getFallbackPageExtensions()` to fall back only to default language instead of going through all languages

### Blueprints

* Added `flatten_array` filter to form field validation
* Added support for `security@: or: [admin.super, admin.pages]` in blueprints (nested AND/OR mode support)
* Blueprint validation: Added `validate: value_type: bool|int|float|string|trim` to `array` to filter all the values inside the array
* If your plugins has blueprints folder, initializing it in the event will be too late. Do this instead:

    ```php
    class MyPlugin extends Plugin
    {
        /** @var array */
        public $features = [
            'blueprints' => 0, // Use priority 0
        ];
    }
    ```

### Events

* Use `Symfony EventDispatcher` directly instead of `rockettheme/toolbox` wrapper.
* Added `$grav->dispatchEvent()` method for PSR-14 events
* Added `PluginsLoadedEvent` which triggers after plugins have been loaded but not yet initialized
* Added `SessionStartEvent` which triggers when session is started
* Added `FlexRegisterEvent` which triggers when `$grav['flex']` is being accessed the first time
* Added `PermissionsRegisterEvent` which triggers when `$grav['permissions']` is being accessed the first time
* Added `onAfterCacheClear` event
* Check `onAdminTwigTemplatePaths` event, it should NOT be:

    ```php
    public function onAdminTwigTemplatePaths($event)
    {
        // This code breaks all the other plugins in admin, including Flex Objects
        $event['paths'] = [__DIR__ . '/admin/themes/grav/templates'];
    }
    ```
    but:
    ```php
    public function onAdminTwigTemplatePaths($event)
    {
        // Add plugin template path for admin.
        $paths = $event['paths'];
        $paths[] = __DIR__ . '/admin/themes/grav/templates';
        $event['paths'] = $paths;
    }
    ```

### Misc

* Added `Utils::isAssoc()` and `Utils::isNegative()` helper methods
* Added `Utils::simpleTemplate()` method for very simple variable templating
* Support customizable null character replacement in `CSVFormatter::decode()`
* Added new `Security::sanitizeSVG()` function
* Added `$grav->close()` method to properly terminate the request with a response
* Added `Folder::hasChildren()` method to determine if a folder has child folders
* Support symlinks when saving `File`
* Added `Route::getBase()` method
* **BC BREAK** Make `Route` objects immutable. This means that you need to do: `{% set route = route.withExtension('.html') %}` (for all `withX` methods) to keep the updated version.
* Better `Content-Encoding` handling in Apache when content compression is disabled

### CLI

* **BC BREAK** Many plugins initialize Grav in a wrong way, it is not safe to initialize plugins and theme by yourself
    * Following calls require Grav 1.6.21 or later, so it is recommended to set Grav dependency to that version
    * Inside `serve()` method:
    * Call `$this->setLanguage($langCode);` before doing anything else if you want to set the language (or use default)
    * Call one of following:
        * `$this->initializeGrav();` Already called if you're in `bin/plugin`, otherwise you may need to call this one
        * `$this->initializePlugins();` This initializes grav, plugins (up to `onPluginsInitialized`)
        * `$this->initializeThemes();` This initializes grav, plugins and theme
        * `$this->initializePages();` This initializes grav, plugins, theme and everything needed by pages
* It is a good idea to prefix your CLI command classes with your plugin name, otherwise there may be naming conflicts (we already have some!)

### Composer dependencies

* Updated Symfony Components to 4.4, please update any deprecated features in your code
* **BC BREAK** Please run `bin/grav yamllinter -f user://` to find any YAML parsing errors in your site (including your plugins and themes).

## PLUGINS

### Admin

* **BC BREAK** Admin will not initialize frontend pages anymore, this has been done to greatly speed up Admin plugin.

    Please call `$grav['admin']->enablePages()` or `{% do admin.enablePages() %}` if you need to access frontend pages. This call can be safely made multiple times.

    If you're using `Flex Pages`, please use Flex Directory instead, it will make your code so much faster.

* Admin now uses Flex for editing `Accounts` and `Pages`. If your plugin hooks into either of those, please make sure they still work.

* Admin cache is enabled by default, make sure your plugin clears cache when needed. Please avoid clearing all cache!

### Shortcode Core

* **DEPRECATED** Every shortcode needs to have `init()` method, classes without it will stop working in the future.
