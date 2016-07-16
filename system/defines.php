<?php
/**
 * @package    Grav.Core
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

// Some standard defines
define('GRAV', true);
define('GRAV_VERSION', '1.1.1');
define('GRAV_TESTING', false);
define('DS', '/');
define('GRAV_PHP_MIN', '5.5.9');

// Directories and Paths
if (!defined('GRAV_ROOT')) {
    define('GRAV_ROOT', str_replace(DIRECTORY_SEPARATOR, DS, getcwd()));
}
define('ROOT_DIR', GRAV_ROOT . '/');
define('USER_PATH', 'user/');
define('USER_DIR', ROOT_DIR . USER_PATH);
define('CACHE_DIR', ROOT_DIR . 'cache/');

// DEPRECATED: Do not use!
define('ASSETS_DIR', ROOT_DIR . 'assets/');
define('IMAGES_DIR', ROOT_DIR . 'images/');
define('ACCOUNTS_DIR', USER_DIR .'accounts/');
define('PAGES_DIR', USER_DIR .'pages/');
define('DATA_DIR', USER_DIR .'data/');
define('SYSTEM_DIR', ROOT_DIR .'system/');
define('LIB_DIR', SYSTEM_DIR .'src/');
define('PLUGINS_DIR', USER_DIR .'plugins/');
define('THEMES_DIR', USER_DIR .'themes/');
define('VENDOR_DIR', ROOT_DIR .'vendor/');
define('LOG_DIR', ROOT_DIR .'logs/');
// END DEPRECATED

// Some extensions
define('CONTENT_EXT', '.md');
define('TEMPLATE_EXT', '.html.twig');
define('TWIG_EXT', '.twig');
define('PLUGIN_EXT', '.php');
define('YAML_EXT', '.yaml');

// Content types
define('RAW_CONTENT', 1);
define('TWIG_CONTENT', 2);
define('TWIG_CONTENT_LIST', 3);
define('TWIG_TEMPLATES', 4);
