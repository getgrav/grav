<?php
/**
 * @package    Grav\Core
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

if (!defined('GRAV_ROOT')) {
    die();
}

use Grav\Installer\Install;

require_once __DIR__ . '/src/Grav/Installer/Install.php';

return Install::instance();