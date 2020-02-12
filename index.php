<?php

/**
 * @package    Grav.Core
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav;

\define('GRAV_REQUEST_TIME', microtime(true));
\define('GRAV_PHP_MIN', '7.1.3');

if (version_compare($ver = PHP_VERSION, $req = GRAV_PHP_MIN, '<')) {
    die(sprintf('You are running PHP %s, but Grav needs at least <strong>PHP %s</strong> to run.', $ver, $req));
}

if (PHP_SAPI === 'cli-server' && !isset($_SERVER['PHP_CLI_ROUTER'])) {
    die("PHP webserver requires a router to run Grav, please use: <pre>php -S {$_SERVER['SERVER_NAME']}:{$_SERVER['SERVER_PORT']} system/router.php</pre>");
}

// Set timezone to default, falls back to system if php.ini not set
date_default_timezone_set(@date_default_timezone_get());

// Set internal encoding.
if (!\extension_loaded('mbstring')) {
    die("'mbstring' extension is not loaded.  This is required for Grav to run correctly");
}
@ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

// Ensure vendor libraries exist
$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) {
    die('Please run: <i>bin/grav install</i>');
}

// Register the auto-loader.
$loader = require $autoload;

use Grav\Common\Grav;
use RocketTheme\Toolbox\Event\Event;

// Get the Grav instance
$grav = Grav::instance(
    array(
        'loader' => $loader
    )
);

// Process the page
try {
    $grav->process();
} catch (\Error $e) {
    $grav->fireEvent('onFatalException', new Event(array('exception' => $e)));
    throw $e;
} catch (\Exception $e) {
    $grav->fireEvent('onFatalException', new Event(array('exception' => $e)));
    throw $e;
}
