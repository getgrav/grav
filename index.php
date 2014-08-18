<?php
namespace Grav;

if (version_compare($ver = PHP_VERSION, $req = '5.4.0', '<')) {
    exit(sprintf('You are running PHP %s, but Grav needs at least <strong>PHP %s</strong> to run.', $ver, $req));
}

use Grav\Common\Grav;
use Grav\Common\Debugger;

// Register system libraries to the auto-loader.
$loader = require_once __DIR__ . '/system/autoload.php';

if (!ini_get('date.timezone')) {
    date_default_timezone_set('UTC');
}

$grav = Grav::instance(
    [
        'loader' => $loader,
        'debugger' => new Debugger(Debugger::DEVELOPMENT)
    ]
);

// Use output buffering to prevent headers from being sent too early.
ob_start();

try {
    $grav['debugger']->init();
    $grav->process();

} catch (\Exception $e) {
    $grav->fireEvent('onFatalException', $e);
    throw $e;
}

ob_end_flush();
