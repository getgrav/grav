# UPGRADE FROM 1.6 to 1.7

## Composer dependencies

* Updated Symfony Components to 4.4

## Configuration

* Added new configuration option `system.debugger.censored` to hide potentially sensitive information
* Added new configuration option `system.languages.include_default_lang_file_extension` to keep default language in `.md` files if set to `false`
* Added configuration option to set fallback content languages individually for every language

## Pages

* Added experimental support for `Flex Pages` (**Flex-Objects** plugin required)
* Added page specific permissions for `Flex Pages`
* [DEPRECATED] `$page->modular()` in favor of `$page->isModule()`
* Fixed `Page::modular()` and `Page::modularTwig()` returning `null` for folders and other non-initialized pages
* Fixed 404 error when you click to non-routable menu item with children: redirect to the first child instead
* Fixed wrong `Pages::dispatch()` calls (with redirect) when we really meant to call `Pages::find()`
* Added `Pages::getCollection()` method
* Moved `collection()` and `evaluate()` logic from `Page` class into `Pages` class

## CLI

* Added new `-r <job-id>` option for Scheduler CLI command to force-run a job
* Added `bin/grav page-system-validator [-r|--record] [-c|--check]` to test Flex Pages
* Added a new `bin/grav server` CLI command to easily run Symfony or PHP built-in webservers
* Improved `Scheduler` cron command check and more useful CLI information
* Improved `bin/grav yamllinter` CLI command by adding an option to find YAML Linting issues from the whole site or custom folder
    
## Users

* Improved `Flex Users`: obey blueprints and allow Flex to be used in admin only
* Improved user and group ACL to support deny permissions (`Flex Users` only)
* Changed `UserInterface::authorize()` to return `null` having the same meaning as `false` if access is denied because of no matching rule
* [DEPRECATED] `Grav\Common\User\Group` in favor of `$grav['user_groups']`, which contains Flex UserGroup collection

## Flex

* Greatly improved speed of loading Flex collections
* [DEPRECATED] `FlexDirectory::update()` and `FlexDirectory::remove()`
* [BC BREAK] Moved all Flex type classes under `Grav\Common\Flex`
* [BC BREAK] `FlexStorageInterface::getStoragePath()` and `getMediaPath()` can now return null
* [BC BREAK] Flex objects no longer return temporary key if they do not have one; empty key is returned instead
* Added `hasFlexFeature()` method to test if `FlexObject` or `FlexCollection` implements a given feature
* Added `getFlexFeatures()` method to return all features that `FlexObject` or `FlexCollection` implements
* Added `FlexStorage::getMetaData()` to get updated object meta information for listed keys
* `FlexDirectory::getObject()` can now be called without any parameters to create a new object

## Templating

* Added support for Twig 2.12 (still using Twig 1.42)
* Added a new `{% cache %}` Twig tag eliminating need for `twigcache` extension.
* Added `array_diff()` twig function
* Added `template_from_string()` twig function
* Improved twig `|array` filter to work with iterators and objects with `toArray()` method
* Improved twig `authorize()` function to work better with nested rule parameters

## Translations

* Improved language support
* Improved language support for `Route` class
* Translations: rename MODULAR to MODULE everywhere
* Added `Language::getPageExtensions()` to get full list of supported page language extensions
* [BC BREAK] Fixed `Language::getFallbackPageExtensions()` to fall back only to default language instead of going through all languages

## Events

* Use `Symfony EventDispatcher` directly and not rockettheme/toolbox wrapper

## Debugging

* Added support for [Clockwork](https://underground.works/clockwork) developer tools (now default debugger)
* Added support for [Tideways XHProf](https://github.com/tideways/php-xhprof-extension) PHP Extension for profiling method calls
* Added Twig profiling for Clockwork debugger

## Misc

* Added `Utils::isAssoc()` and `Utils::isNegative()` helper methods
* Added `Utils::simpleTemplate()` method for very simple variable templating
* Support customizable null character replacement in `CSVFormatter::decode()`
* Added new `Security::sanitizeSVG()` function
* Added `$grav->close()` method to properly terminate the request with a response
* [BC BREAK] Make `Route` objects immutable

## Admin

* [BC BREAK] Added support for not instantiating pages, useful to speed up tasks
