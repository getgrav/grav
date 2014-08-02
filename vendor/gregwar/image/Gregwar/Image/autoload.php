<?php

$vendors = __DIR__.'/vendor/autoload.php';
if (file_exists($vendors)) {
    require($vendors);
}

/**
* Registers an autoload for all the classes in Gregwar\Image
*/
spl_autoload_register(function ($className) {
    $namespace = 'Gregwar\\Image';

    if (strpos($className, $namespace) === 0) {
        $className = str_replace($namespace, '', $className);
        $fileName = __DIR__ . '/' . str_replace('\\', '/', $className) . '.php';
        if (file_exists($fileName)) {
            require($fileName);
        }
    }
});
