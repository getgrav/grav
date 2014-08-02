<?php

// Initiate Autoload of Grav classes
spl_autoload_register(function ($class) {

    if (strpos($class, 'Grav\\Common') === 0 || strpos($class, 'Grav\\Console') === 0) {
        $filename = str_replace('\\', '/', LIB_DIR.$class.'.php');
        include($filename);
    }
});
