<?php
/**
 * @package    Grav.Common.Helpers
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Helpers;

use Grav\Common\Grav;

class Exif
{
    public $reader;

    public function __construct()
    {
        if (function_exists('exif_read_data')) {
            $this->reader = \PHPExif\Reader\Reader::factory(\PHPExif\Reader\Reader::TYPE_NATIVE);
        } else {
            if (Grav::instance()['config']->get('system.media.auto_metadata_exif')) {
                throw new \Exception('Please enable the Exif extension for PHP or disable Exif support in Grav system configuration');
            }
        }
    }
}
