<?php

/**
 * @package    Grav\Common\Helpers
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Helpers;

use Grav\Common\Grav;

class Exif
{
    public $reader;

    /**
     * Exif constructor.
     * @throws \RuntimeException
     */
    public function __construct()
    {
        if (Grav::instance()['config']->get('system.media.auto_metadata_exif')) {
            if (function_exists('exif_read_data') && class_exists('\PHPExif\Reader\Reader')) {
                $this->reader = \PHPExif\Reader\Reader::factory(\PHPExif\Reader\Reader::TYPE_NATIVE);
            } else {
                throw new \RuntimeException('Please enable the Exif extension for PHP or disable Exif support in Grav system configuration');
            }
        }
    }

    public function getReader()
    {
        if ($this->reader) {
            return $this->reader;
        }

        return false;
    }
}
