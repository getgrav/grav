<?php
namespace Grav\Common;

if (version_compare($ver = PHP_VERSION, $req = '5.4.0', '<')) {
    throw new \RuntimeException(sprintf('You are running PHP %s, but Grav needs at least <strong>PHP %s</strong> to run.', $ver, $req));
}

use Tracy\Debugger;

// Register system libraries to the auto-loader.
$loader = require_once __DIR__ . '/system/autoload.php';

if (!ini_get('date.timezone')) {
    date_default_timezone_set('UTC');
}

// Use output buffering to prevent headers from being sent too early.
ob_start();

// Start the timer and enable debugger in production mode as we do not have system configuration yet.
// Debugger catches all errors and logs them, for example if the script doesn't have write permissions.
Debugger::timer();
Debugger::enable(Debugger::DEVELOPMENT, is_dir(LOG_DIR) ? LOG_DIR : null);

$grav = new Grav;

try {
    // Register all the Grav bits into registry.
    $registry = Registry::instance();
    $registry->store('autoloader', $loader);
    $registry->store('Grav', $grav);
    $registry->store('Uri', new Uri);
    $registry->store('Config', Config::instance(CACHE_DIR . 'config.php'));
    $registry->store('Cache', new Cache);
    $registry->store('Twig', new Twig);
    $registry->store('Pages', new Page\Pages);
    $registry->store('Taxonomy', new Taxonomy);

    $grav->process();

} catch (\Exception $e) {
    $grav->fireEvent('onFatalException', $e);
    throw $e;
}

ob_end_flush();
