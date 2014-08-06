<?php
require_once(__DIR__ . '/../system/defines.php');

// Use composer auto-loader and just add our namespace into it.
$loader = require_once(__DIR__ . '/../vendor/autoload.php');
$loader->addPsr4('Grav\\', LIB_DIR . 'Grav');

return $loader;
