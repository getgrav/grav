# UPGRADE FROM 1.6 TO 1.7

## ADMINISTRATORS

### YAML files

* Please run `bin/grav yamllinter` to find any YAML parsing errors in your site. You should run this command before and after upgrade. Grav falls back to older YAML parser if it detects an error, but it will slow down your site.

### Pages

* **BC BREAK** Fixed 404 error page when you go to non-routable page with routable child pages under it. Now you get redirected to the first routable child page instead. This is probably what you wanted in the first place. If you do not want this new behavior, you need to **TODO**

### Multi-language

* Improved language support
* **BC BREAK** Please check that your fallback languages are correct. Old implementation had a fallback to any other language, now only default language is being used unless you use `system.languages.content_fallback` configuration option to override the default behavior.

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

### Debugging

* Added support for [Clockwork](https://underground.works/clockwork) developer tools (now default debugger)
* Added support for [Tideways XHProf](https://github.com/tideways/php-xhprof-extension) PHP Extension for profiling method calls
* Added Twig profiling for Clockwork debugger

## DEVELOPERS

### ACL

* `user.authorize()` now requires user to be authorized (passed 2FA check), unless the rule contains `login` in its name.

* **BC BREAK** `user.authorize()` and Flex `object.isAuthorized()` now have two deny states: `false` and `null`. 

    Make sure you do not have strict checks against false: `$user->authorize($action) === false` (PHP)  or `user.authorize(action) is same as(false)` (Twig). 

    For the negative checks you should be using `!user->authorize($action)` (PHP) or `not user.authorize(action)` (Twig).

    The change has been done to allow strong deny rules by chaining the actions if previous ones do not match: `user.authorize(action1) ?? user.authorize(action2) ?? user.authorize(action3)`.
    
    Note that Twig function `authorize()` will still **keeps** the old behavior!

### Pages

* Added experimental support for `Flex Pages` (**Flex-Objects** plugin required)
* Added page specific permissions support for `Flex Pages`
* Fixed wrong `Pages::dispatch()` calls (with redirect) when we really meant to call `Pages::find()`
* Added `Pages::getCollection()` method
* Moved `collection()` and `evaluate()` logic from `Page` class into `Pages` class
* **DEPRECATED** `$page->modular()` in favor of `$page->isModule()`
* **BC BREAK** Fixed `Page::modular()` and `Page::modularTwig()` returning `null` for folders and other non-initialized pages. Should not affect your code unless you were checking against `false` or `null`.

### Users

* Improved `Flex Users`: obey blueprints and allow Flex to be used in admin only
* Improved `Flex Users`: user and group ACL now supports deny permissions
* Changed `UserInterface::authorize()` to return `null` having the same meaning as `false` if access is denied because of no matching rule
* **DEPRECATED** `Grav\Common\User\Group` in favor of `$grav['user_groups']`, which contains Flex UserGroup collection

### Flex

* Added `hasFlexFeature()` method to test if `FlexObject` or `FlexCollection` implements a given feature
* Added `getFlexFeatures()` method to return all features that `FlexObject` or `FlexCollection` implements
* Added `FlexStorage::getMetaData()` to get updated object meta information for listed keys
* `FlexDirectory::getObject()` can now be called without any parameters to create a new object
* **DEPRECATED** `FlexDirectory::update()` and `FlexDirectory::remove()`
* **BC BREAK** Moved all Flex type classes under `Grav\Common\Flex`
* **BC BREAK** `FlexStorageInterface::getStoragePath()` and `getMediaPath()` can now return null
* **BC BREAK** Flex objects no longer return temporary key if they do not have one; empty key is returned instead

### Templating

* Added support for Twig 2.12 (still using Twig 1.42)
* Added a new `{% cache %}` Twig tag eliminating need for `twigcache` extension.
* Added `array_diff()` twig function
* Added `template_from_string()` twig function
* Improved twig `|array` filter to work with iterators and objects with `toArray()` method
* Improved twig `authorize()` function to work better with nested rule parameters

### Multi-language

* Improved language support for `Route` class
* Translations: rename MODULAR to MODULE everywhere
* Added `Language::getPageExtensions()` to get full list of supported page language extensions
* **BC BREAK** Fixed `Language::getFallbackPageExtensions()` to fall back only to default language instead of going through all languages

### Events

* Use `Symfony EventDispatcher` directly instead of `rockettheme/toolbox` wrapper.

### Misc

* Added `Utils::isAssoc()` and `Utils::isNegative()` helper methods
* Added `Utils::simpleTemplate()` method for very simple variable templating
* Support customizable null character replacement in `CSVFormatter::decode()`
* Added new `Security::sanitizeSVG()` function
* Added `$grav->close()` method to properly terminate the request with a response
* **BC BREAK** Make `Route` objects immutable. This means that you need to do: `{% set route = route.withExtension('.html') %}` (for all `withX` methods) to keep the updated version.

### Composer dependencies

* Updated Symfony Components to 4.4, please update any deprecated features in your code
* **BC BREAK** Please run `bin/grav yamllinter -f user://` to find any YAML parsing errors in your site (including your plugins and themes).

### Admin

* **BC BREAK** Admin will not initialize frontend pages anymore, this has been done to greatly speed up Admin plugin. 

    Please call `$grav['admin']->enablePages()` or `{% do admin.enablePages() %}` if you need to access frontend pages. This call can be safely made multiple times.
    
    If you're using `Flex Pages`, please use Flex Directory instead, it will make your code so much faster.
