<?php

use Grav\Common\Grav;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

\define('GRAV_CLI', true);
\define('GRAV_REQUEST_TIME', microtime(true));
\define('GRAV_USER_INSTANCE', 'FLEX');

$autoload = require __DIR__ . '/../../vendor/autoload.php';

if (version_compare($ver = PHP_VERSION, $req = GRAV_PHP_MIN, '<')) {
    exit(sprintf("You are running PHP %s, but Grav needs at least PHP %s to run.\n", $ver, $req));
}

if (!ini_get('date.timezone')) {
    date_default_timezone_set('UTC');
}

if (!file_exists(GRAV_ROOT . '/index.php')) {
    exit('FATAL: Must be run from ROOT directory of Grav!');
}

$grav = Grav::instance(array('loader' => $autoload));
$grav->setup('tests');
$grav['config']->init();

// Find all plugins in Grav installation and autoload their classes.

/** @var UniformResourceLocator $locator */
$locator = Grav::instance()['locator'];
$iterator = $locator->getIterator('plugins://');
foreach($iterator as $directory) {
    if (!$directory->isDir()) {
        continue;
    }
    $autoloader = $directory->getPathname() . '/vendor/autoload.php';
    if (file_exists($autoloader)) {
        require $autoloader;
    }
}

define('GANTRY_DEBUGGER', true);
define('GANTRY5_DEBUG', true);
define('GANTRY5_PLATFORM', 'grav');
define('GANTRY5_ROOT', rtrim(ROOT_DIR, '/'));
define('GANTRY5_VERSION', '@version@');
define('GANTRY5_VERSION_DATE', '@versiondate@');
define('GANTRYADMIN_PATH', '');
