<?php
/**
 * @package    Grav\Core
 *
 * @copyright  Copyright (c) 2015 - 2025 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

if (!defined('GRAV_ROOT')) {
    die();
}

// Check if Install class is already loaded (from an older Grav version)
// This happens when upgrading from older versions where the OLD Install class
// was loaded via autoloader before extracting the update package (e.g., via Install::forceSafeUpgrade())
if (class_exists('Grav\\Installer\\Install', false)) {
    // OLD Install class is already loaded. We cannot load the NEW one due to PHP limitations.
    // However, we can work around this by:
    // 1. Using a different class name for the NEW installer
    // 2. Or, accepting that the OLD Install class will run but ensuring it can still upgrade properly

    // For now, use the OLD Install class but set its location to this extracted package
    // so it processes files from here
    $install = Grav\Installer\Install::instance();

    // Use reflection to update the location property to point to this package
    $reflection = new \ReflectionClass($install);
    if ($reflection->hasProperty('location')) {
        $locationProp = $reflection->getProperty('location');
        $locationProp->setAccessible(true);
        $locationProp->setValue($install, __DIR__ . '/..');
    }

    return $install;
}

// Normal case: Install class not yet loaded, load the NEW one
require_once __DIR__ . '/src/Grav/Installer/Install.php';

return Grav\Installer\Install::instance();
