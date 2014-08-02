<?php
namespace Grav\Common;

use Tracy\Debugger;

require_once(__DIR__ . '/system/defines.php');

if (!ini_get('date.timezone')) {
    date_default_timezone_set('GMT');
}

// Use output buffering to prevent headers from being sent too early.
ob_start();

// Register all the classes to the auto-loader.
require_once(VENDOR_DIR .'autoload.php');
require_once(SYSTEM_DIR .'autoload.php');

// Create Required Folders if they don't exist
if (!file_exists(LOG_DIR)) mkdir(LOG_DIR);
if (!file_exists(CACHE_DIR)) mkdir(CACHE_DIR);
if (!file_exists(IMAGES_DIR)) mkdir(IMAGES_DIR);
if (!file_exists(DATA_DIR)) mkdir(DATA_DIR);

// Start the timer and enable debugger in production mode as we do not have system configuration yet.
// Debugger catches all errors and logs them, for example if the script doesn't have write permissions.
Debugger::timer();
Debugger::enable(Debugger::PRODUCTION, LOG_DIR);

// Register all the Grav bits into registry.
$registry = Registry::instance();
$registry->store('Grav', new Grav);
$registry->store('Uri', new Uri);
$registry->store('Config', Config::instance(CACHE_DIR . 'config.php'));
$registry->store('Cache', new Cache);
$registry->store('Twig', new Twig);
$registry->store('Pages', new Page\Pages);
$registry->store('Taxonomy', new Taxonomy);

/** @var Grav $grav */
$grav = $registry->retrieve('Grav');
$grav->process();

ob_end_flush();
