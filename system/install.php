<?php
/**
 * @package    Grav\Core
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

if (!defined('GRAV_ROOT')) {
    die();
}

require_once __DIR__ . '/src/Grav/Installer/Install.php';

return Grav\Installer\Install::instance();
