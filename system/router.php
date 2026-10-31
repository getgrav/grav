<?php

/**
 * @package    Grav\Core
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

if (PHP_SAPI !== 'cli-server') {
    die('This script cannot be run from browser. Run it from a CLI.');
}

$_SERVER['PHP_CLI_ROUTER'] = true;

$root = $_SERVER['DOCUMENT_ROOT'];
$path = $_SERVER['SCRIPT_NAME'];
if ($path !== '/index.php' && is_file($root . $path)) {
    if (!(
        // Block dot files and folders except .well-known directories
        preg_match('`/\.(?!well-known/)`', (string) $path)
        // Block all direct access for these folders
        || preg_match('`^/(\.git|cache|bin|logs|backup|tmp|webserver-configs|tests)/`ui', (string) $path)
        // Block all direct access to these sensitive user folders, whatever the file type
        || preg_match('`^/user/(env/[^/]+/)?config/`ui', (string) $path)
        // Block user/accounts too, but allow avatar images (SVG stays blocked)
        || preg_match('`^/user/(env/[^/]+/)?accounts/(?![^/]+/[^/]+\.(jpe?g|png|gif|webp|avif|bmp|ico)$)`ui', (string) $path)
        // Block user/data too, but allow public media and asset uploads (SVG stays blocked)
        || preg_match('`^/user/(env/[^/]+/)?data/(?!.*\.(jpe?g|png|gif|webp|avif|bmp|ico|mp4|webm|ogg|ogv|mov|mp3|wav|m4a|flac|pdf|woff2|woff|ttf|otf|eot|css|js)$)`ui', (string) $path)
        // Block running scripts in the public cache folders (image derivatives and combined assets)
        || preg_match('`^/(images|assets)/(.*)\.(php|php2|php3|php4|php5|php7|php8|phar|phtml|pht|phtm|phps|pl|py|cgi|sh|bat)$`ui', (string) $path)
        // Block access to specific file types for these system folders
        || preg_match('`^/(system|vendor)/(.*)\.(txt|xml|md|html|htm|shtml|shtm|json|yaml|yml|php|php2|php3|php4|php5|phar|phtml|pl|py|cgi|twig|sh|bat)$`ui', (string) $path)
        // Block access to specific file types for these user folders
        || preg_match('`^/(user)/(.*)\.(txt|md|json|yaml|yml|php|php2|php3|php4|php5|phar|phtml|pl|py|cgi|twig|sh|bat)$`ui', (string) $path)
        // Block all direct access to .md files
        || preg_match('`\.md$`ui', (string) $path)
        // Block access to specific files in the root folder
        || preg_match('`^/(LICENSE\.txt|composer\.lock|composer\.json|\.htaccess)$`ui', (string) $path)
    )) {
        return false;
    }
}

$grav_index = 'index.php';

/* Check the GRAV_BASEDIR environment variable and use if set */

$grav_basedir = getenv('GRAV_BASEDIR') ?: '';
if ($grav_basedir) {
    $grav_index = ltrim($grav_basedir, '/') . DIRECTORY_SEPARATOR . $grav_index;
    $grav_basedir = DIRECTORY_SEPARATOR . trim($grav_basedir, DIRECTORY_SEPARATOR);
    define('GRAV_ROOT', str_replace(DIRECTORY_SEPARATOR, '/', getcwd()) . $grav_basedir);
}

$_SERVER = array_merge($_SERVER, $_ENV);
$_SERVER['SCRIPT_FILENAME'] = $_SERVER['DOCUMENT_ROOT'] . $grav_basedir .DIRECTORY_SEPARATOR . 'index.php';
$_SERVER['SCRIPT_NAME'] = $grav_basedir . DIRECTORY_SEPARATOR . 'index.php';
$_SERVER['PHP_SELF'] = $grav_basedir . DIRECTORY_SEPARATOR . 'index.php';

error_log(sprintf('%s:%d [%d]: %s', $_SERVER['REMOTE_ADDR'], $_SERVER['REMOTE_PORT'], http_response_code(), $_SERVER['REQUEST_URI']), 4);

require $grav_index;
