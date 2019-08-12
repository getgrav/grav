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

$gravBasedir = getenv('GRAV_BASEDIR');
if ($gravBasedir === false) {
	$gravBasedir = '';
} else {
	$gravBasedir = DIRECTORY_SEPARATOR . trim($gravBasedir, DIRECTORY_SEPARATOR);
	// tell system/defines.php not to use the default GRAV_ROOT
    define('GRAV_ROOT', str_replace(DIRECTORY_SEPARATOR, '/', getcwd()). $gravBasedir);

}

$_SERVER = array_merge($_SERVER, $_ENV);
$_SERVER['SCRIPT_FILENAME'] = $_SERVER['DOCUMENT_ROOT'] . $gravBasedir .DIRECTORY_SEPARATOR . 'index.php';
$_SERVER['SCRIPT_NAME'] = $gravBasedir . DIRECTORY_SEPARATOR . 'index.php';
$_SERVER['PHP_SELF'] = $gravBasedir . DIRECTORY_SEPARATOR . 'index.php';

error_log(sprintf('%s:%d [%d]: %s', $_SERVER['REMOTE_ADDR'], $_SERVER['REMOTE_PORT'], http_response_code(), $_SERVER['REQUEST_URI']), 4);

if ($gravBasedir === '') {
	require 'index.php';
} else {
	require ltrim($gravBasedir, '/') . DIRECTORY_SEPARATOR . 'index.php';
}
