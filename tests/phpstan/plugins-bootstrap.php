<?php

use Grav\Common\Grav;
use Grav\Common\Plugin;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

\define('GRAV_CLI', true);
\define('GRAV_REQUEST_TIME', microtime(true));
\define('GRAV_USER_INSTANCE', 'FLEX');

$autoload = require __DIR__ . '/../../vendor/autoload.php';

if (!ini_get('date.timezone')) {
    date_default_timezone_set('UTC');
}

if (!file_exists(GRAV_ROOT . '/index.php')) {
    exit('FATAL: Must be run from ROOT directory of Grav!');
}

$grav = Grav::instance(['loader' => $autoload]);
$grav->setup('tests');
$grav['config']->init();

// Find all plugins in Grav installation and autoload their classes.

/** @var UniformResourceLocator $locator */
$locator = Grav::instance()['locator'];
$iterator = $locator->getIterator('plugins://');
/** @var DirectoryIterator $directory */
foreach ($iterator as $directory) {
    if (!$directory->isDir()) {
        continue;
    }
    $plugin = $directory->getBasename();
    $file = $directory->getPathname() . '/' . $plugin . '.php';
    $classloader = null;
    if (file_exists($file)) {
        require_once $file;

        $pluginClass = "\\Grav\\Plugin\\{$plugin}Plugin";

        if (is_subclass_of($pluginClass, Plugin::class, true)) {
            $class = new $pluginClass($plugin, $grav);
            if (is_callable([$class, 'autoload'])) {
                $classloader = $class->autoload();
            }
        }
    }
    if (null === $classloader) {
        $autoloader = $directory->getPathname() . '/vendor/autoload.php';
        if (file_exists($autoloader)) {
            require $autoloader;
        }
    }
}

define('GANTRY_DEBUGGER', true);
define('GANTRY5_DEBUG', true);
define('GANTRY5_PLATFORM', 'grav');
define('GANTRY5_ROOT', GRAV_ROOT);
define('GANTRY5_VERSION', '@version@');
define('GANTRY5_VERSION_DATE', '@versiondate@');
define('GANTRYADMIN_PATH', '');
