<?php

// Some standard defines
define('GRAV', true);
define('GRAV_VERSION', '0.9.13');
define('DS', '/');

// Directories and Paths
if (!defined('GRAV_ROOT')) {
    define('GRAV_ROOT', getcwd() . '/');
}
define('USER_PATH', 'user/'); // @todo: deprecated but used fo USER_DIR into system/src/Grav/Common/Config/Config.php:212.
define('USER_DIR', GRAV_ROOT . USER_PATH); // @todo: deprecated but need for USER_PATH.
define('CACHE_DIR', GRAV_ROOT . 'cache/');
define('LOG_DIR', GRAV_ROOT .'logs/');

// DEPRECATED: Do not use!
define('ROOT_DIR', rtrim(GRAV_ROOT, '/'));
define('SYSTEM_DIR', GRAV_ROOT .'system/');
define('IMAGES_DIR', GRAV_ROOT . 'images/');
define('ASSETS_DIR', GRAV_ROOT . 'assets/');
define('VENDOR_DIR', GRAV_ROOT .'vendor/');
define('LIB_DIR', SYSTEM_DIR .'src/');
define('PLUGINS_DIR', USER_DIR .'plugins/');
define('THEMES_DIR', USER_DIR .'themes/');
define('PAGES_DIR', USER_DIR .'pages/');
define('ACCOUNTS_DIR', USER_DIR .'accounts/');
define('DATA_DIR', USER_DIR .'data/');
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

// Misc Defines
define('SUMMARY_DELIMITER', '===');
