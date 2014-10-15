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

// Setup Whoops error handler
$whoops = new \Whoops\Run;

$error_page = new \Whoops\Handler\PrettyPageHandler;
$error_page->setPageTitle('Crikey! There was an error...');
$error_page->setEditor('sublime');
$error_page->addResourcePath(__DIR__ .'/system/assets');
$error_page->addCustomCss('whoops.css');

$whoops->pushHandler($error_page);
$whoops->register();


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

