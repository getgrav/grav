<?php

/**
 * @package    Grav\Core
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

if (PHP_SAPI !== 'cli-server') {
    die('This script cannot be run from browser. Run it from a CLI.');
}

$_SERVER['PHP_CLI_ROUTER'] = true;

if (is_file($_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . $_SERVER['SCRIPT_NAME'])) {
    return false;
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
