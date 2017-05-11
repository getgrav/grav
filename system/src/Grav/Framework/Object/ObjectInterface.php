<?php
/**
 * @package    Grav\Framework\Object
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Object;

/**
 * Object Interface
 * @package Grav\Framework\Object
 */
interface ObjectInterface extends \ArrayAccess, \JsonSerializable
{
    /**
     * @param array $elements
     * @param string $key
     */
    public function __construct(array $elements = [], $key = null);

    /**
     * @return string
     */
    public function getKey();
}
