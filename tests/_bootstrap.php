<?php
// This is global bootstrap for autoloading

namespace Grav;

use Codeception\Util\Fixtures;
use Faker\Factory;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

ini_set('error_log', __DIR__ . '/error.log');

// Ensure vendor libraries exist
$autoload = __DIR__ . '/../vendor/autoload.php';

if (!is_file($autoload)) {
    throw new \RuntimeException("Please run: <i>bin/grav install</i>");
}

use Grav\Common\Grav;

// Register the auto-loader.
$loader = require_once $autoload;

if (version_compare($ver = PHP_VERSION, $req = GRAV_PHP_MIN, '<')) {
    throw new \RuntimeException(sprintf('You are running PHP %s, but Grav needs at least <strong>PHP %s</strong> to run.', $ver, $req));
}

// Set timezone to default, falls back to system if php.ini not set
date_default_timezone_set(@date_default_timezone_get());

// Set internal encoding if mbstring loaded
if (!extension_loaded('mbstring')) {
    throw new \RuntimeException("'mbstring' extension is not loaded.  This is required for Grav to run correctly");
}
mb_internal_encoding('UTF-8');

// Get the Grav instance
$grav = Grav::instance(['loader' => $loader]);

$grav['uri']->init();
$grav['debugger']->init();
$grav['assets']->init();

$grav['config']->set('system.cache.enabled', false);

/** @var UniformResourceLocator $locator */
$locator = $grav['locator'];
$locator->addPath('tests', '', 'tests', false);

// Set default $_SERVER value used for nonces
empty( $_SERVER['HTTP_CLIENT_IP'] ) && $_SERVER['HTTP_CLIENT_IP'] = '127.0.0.1';

$fake = Factory::create();
Fixtures::add('grav', $grav);
Fixtures::add('fake', $fake);