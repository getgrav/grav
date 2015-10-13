<?php

// Some standard defines
define('GRAV', true);
define('GRAV_VERSION', '0.9.45');
define('DS', '/');

// Directories and Paths
defined('GRAV_ROOT') || define('GRAV_ROOT', str_replace(DIRECTORY_SEPARATOR, DS, getcwd()));
defined('ROOT_DIR') || define('ROOT_DIR', GRAV_ROOT . '/');
defined('USER_PATH') || define('USER_PATH', 'user/');
defined('USER_DIR') || define('USER_DIR', ROOT_DIR . USER_PATH);
defined('SYSTEM_DIR') || define('SYSTEM_DIR', ROOT_DIR .'system/');
defined('ASSETS_DIR') || define('ASSETS_DIR', ROOT_DIR . 'assets/');
defined('CACHE_DIR') || define('CACHE_DIR', ROOT_DIR . 'cache/');
defined('IMAGES_DIR') || define('IMAGES_DIR', ROOT_DIR . 'images/');
defined('LOG_DIR') || define('LOG_DIR', ROOT_DIR .'logs/');
defined('ACCOUNTS_DIR') || define('ACCOUNTS_DIR', USER_DIR .'accounts/');
defined('PAGES_DIR') || define('PAGES_DIR', USER_DIR .'pages/');

// DEPRECATED: Do not use!
define('DATA_DIR', USER_DIR .'data/');
define('LIB_DIR', SYSTEM_DIR .'src/');
define('PLUGINS_DIR', USER_DIR .'plugins/');
define('THEMES_DIR', USER_DIR .'themes/');
define('VENDOR_DIR', ROOT_DIR .'vendor/');
// END DEPRECATED

// Some extensions
defined('CONTENT_EXT') || define('CONTENT_EXT', '.md');
defined('TEMPLATE_EXT') || define('TEMPLATE_EXT', '.html.twig');
defined('TWIG_EXT') || define('TWIG_EXT', '.twig');
defined('PLUGIN_EXT') || define('PLUGIN_EXT', '.php');
defined('YAML_EXT') || define('YAML_EXT', '.yaml');

// Content types
define('RAW_CONTENT', 1);
define('TWIG_CONTENT', 2);
define('TWIG_CONTENT_LIST', 3);
define('TWIG_TEMPLATES', 4);
