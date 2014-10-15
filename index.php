<?php
namespace Grav;

if (version_compare($ver = PHP_VERSION, $req = '5.4.0', '<')) {
    exit(sprintf('You are running PHP %s, but Grav needs at least <strong>PHP %s</strong> to run.', $ver, $req));
}

$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) {
    exit('Please run: <i>bin/grav install</i>');
}

use Grav\Common\Grav;

// Register the auto-loader.
$loader = require_once $autoload;

if (!ini_get('date.timezone')) {
    date_default_timezone_set('UTC');
}

$grav = Grav::instance(
    array(
        'loader' => $loader
    )
);

try {
    $grav->process();

} catch (\Exception $e) {
    $grav->fireEvent('onFatalException');
    throw $e;
}

