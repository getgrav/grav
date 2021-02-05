<?php

/**
 * @package    Grav\Common\Helpers
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Helpers;

use Grav\Common\Grav;
use PHPExif\Reader\Reader;
use RuntimeException;
use function function_exists;

/**
 * Class Exif
 * @package Grav\Common\Helpers
 */
class Exif
{
    /** @var Reader */
    public $reader;

    /**
     * Exif constructor.
     * @throws RuntimeException
     */
    public function __construct()
    {
        if (Grav::instance()['config']->get('system.media.auto_metadata_exif')) {
            if (function_exists('exif_read_data') && class_exists(Reader::class)) {
                $this->reader = Reader::factory(Reader::TYPE_NATIVE);
            } else {
                throw new RuntimeException('Please enable the Exif extension for PHP or disable Exif support in Grav system configuration');
            }
        }
    }

    /**
     * @return Reader
     */
    public function getReader()
    {
        return $this->reader;
    }
}
