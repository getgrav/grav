<?php

/**
 * @package    Grav\Core
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

// Some standard defines
define('GRAV', true);
define('GRAV_VERSION', '1.7.37.1');
define('GRAV_SCHEMA', '1.7.0_2020-11-20_1');
define('GRAV_TESTING', false);

// PHP minimum requirement
if (!defined('GRAV_PHP_MIN')) {
    define('GRAV_PHP_MIN', '7.3.6');
}

// Directory separator
if (!defined('DS')) {
    define('DS', '/');
}

// Absolute path to Grav root. This is where Grav is installed into.
if (!defined('GRAV_ROOT')) {
    $path = rtrim(str_replace(DIRECTORY_SEPARATOR, DS, getenv('GRAV_ROOT') ?: getcwd()), DS);
    define('GRAV_ROOT', $path);
}
// Absolute path to Grav webroot. This is the path where your site is located in.
if (!defined('GRAV_WEBROOT')) {
    $path = rtrim(getenv('GRAV_WEBROOT') ?: GRAV_ROOT, DS);
    define('GRAV_WEBROOT', $path);
}
// Relative path to user folder. This path needs to be located under GRAV_WEBROOT.
if (!defined('GRAV_USER_PATH')) {
    $path = rtrim(getenv('GRAV_USER_PATH') ?: 'user', DS);
    define('GRAV_USER_PATH', $path);
}
// Absolute or relative path to system folder. Defaults to GRAV_ROOT/system
// If system folder is outside of webroot, see https://github.com/getgrav/grav/issues/3297#issuecomment-810294972
if (!defined('GRAV_SYSTEM_PATH')) {
    $path = rtrim(getenv('GRAV_SYSTEM_PATH') ?: 'system', DS);
    define('GRAV_SYSTEM_PATH', $path);
}
// Absolute or relative path to cache folder. Defaults to GRAV_ROOT/cache
if (!defined('GRAV_CACHE_PATH')) {
    $path = rtrim(getenv('GRAV_CACHE_PATH') ?: 'cache', DS);
    define('GRAV_CACHE_PATH', $path);
}
// Absolute or relative path to logs folder. Defaults to GRAV_ROOT/logs
if (!defined('GRAV_LOG_PATH')) {
    $path = rtrim(getenv('GRAV_LOG_PATH') ?: 'logs', DS);
    define('GRAV_LOG_PATH', $path);
}
// Absolute or relative path to tmp folder. Defaults to GRAV_ROOT/tmp
if (!defined('GRAV_TMP_PATH')) {
    $path = rtrim(getenv('GRAV_TMP_PATH') ?: 'tmp', DS);
    define('GRAV_TMP_PATH', $path);
}
// Absolute or relative path to backup folder. Defaults to GRAV_ROOT/backup
if (!defined('GRAV_BACKUP_PATH')) {
    $path = rtrim(getenv('GRAV_BACKUP_PATH') ?: 'backup', DS);
    define('GRAV_BACKUP_PATH', $path);
}
unset($path);

// INTERNAL: Do not use!
define('USER_DIR', GRAV_WEBROOT . '/' . GRAV_USER_PATH . '/');
define('CACHE_DIR', (!preg_match('`^(/|[a-z]:[\\\/])`ui', GRAV_CACHE_PATH) ? GRAV_ROOT . '/' : '') . GRAV_CACHE_PATH . '/');

// DEPRECATED: Do not use!
define('CACHE_PATH', GRAV_CACHE_PATH . DS);
define('USER_PATH', GRAV_USER_PATH . DS);
define('ROOT_DIR', GRAV_ROOT . DS);
define('ASSETS_DIR', GRAV_WEBROOT . '/assets/');
define('IMAGES_DIR', GRAV_WEBROOT . '/images/');
define('ACCOUNTS_DIR', USER_DIR . 'accounts/');
define('PAGES_DIR', USER_DIR . 'pages/');
define('DATA_DIR', USER_DIR . 'data/');
define('PLUGINS_DIR', USER_DIR . 'plugins/');
define('THEMES_DIR', USER_DIR . 'themes/');
define('SYSTEM_DIR', (!preg_match('`^(/|[a-z]:[\\\/])`ui', GRAV_SYSTEM_PATH) ? GRAV_ROOT . '/' : '') . GRAV_SYSTEM_PATH . '/');
define('LIB_DIR', SYSTEM_DIR . 'src/');
define('VENDOR_DIR', GRAV_ROOT . '/vendor/');
define('LOG_DIR', (!preg_match('`^(/|[a-z]:[\\\/])`ui', GRAV_LOG_PATH) ? GRAV_ROOT . '/' : '') . GRAV_LOG_PATH . '/');
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
