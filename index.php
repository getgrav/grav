<?php
namespace Grav;

if (version_compare($ver = PHP_VERSION, $req = '5.4.0', '<')) {
    throw new \RuntimeException(sprintf('You are running PHP %s, but Grav needs at least <strong>PHP %s</strong> to run.', $ver, $req));
}

// Ensure vendor libraries exist
$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) {
    throw new \RuntimeException("Please run: <i>bin/grav install</i>");
}

use Grav\Common\Grav;

// Register the auto-loader.
$loader = require_once $autoload;

// Set timezone to default, falls back to system if php.ini not set
date_default_timezone_set(@date_default_timezone_get());

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
    $grav->fireEvent('onFatalException');
    throw $e;
}

