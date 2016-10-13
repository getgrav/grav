<?php
/**
 * @package    Grav.Core
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav;

// Ensure vendor libraries exist
$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) {
    die("Please run: <i>bin/grav install</i>");
}

use Grav\Common\Grav;
use RocketTheme\Toolbox\Event\Event;

// Register the auto-loader.
$loader = require_once $autoload;

if (version_compare($ver = PHP_VERSION, $req = GRAV_PHP_MIN, '<')) {
    die(sprintf('You are running PHP %s, but Grav needs at least <strong>PHP %s</strong> to run.', $ver, $req));
}

// Set timezone to default, falls back to system if php.ini not set
date_default_timezone_set(@date_default_timezone_get());

// Set internal encoding if mbstring loaded
if (!extension_loaded('mbstring')) {
    die("'mbstring' extension is not loaded.  This is required for Grav to run correctly");
}
mb_internal_encoding('UTF-8');

// Get the Grav instance
$grav = Grav::instance(
    array(
        'loader' => $loader
    )
);

// Process the page
try {
    $grav->process();
} catch (\Exception $e) {
    $grav->fireEvent('onFatalException', new Event(['exception' => $e]));
    throw $e;
}
