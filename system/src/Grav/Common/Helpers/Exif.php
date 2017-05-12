<?php
/**
 * @package    Grav.Common.Helpers
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Helpers;

class Exif
{
    public $reader;

    public function __construct()
    {
        $this->reader = \PHPExif\Reader\Reader::factory(\PHPExif\Reader\Reader::TYPE_NATIVE);
    }
}
