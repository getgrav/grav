<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page;

use ArrayAccess;
use JsonSerializable;
use RocketTheme\Toolbox\ArrayTraits\Constructor;
use RocketTheme\Toolbox\ArrayTraits\Export;
use RocketTheme\Toolbox\ArrayTraits\ExportInterface;
use RocketTheme\Toolbox\ArrayTraits\NestedArrayAccessWithGetters;

/**
 * Class Header
 * @package Grav\Common\Page
 */
class Header implements ArrayAccess, ExportInterface, JsonSerializable
{
    use NestedArrayAccessWithGetters, Constructor, Export;

    /** @var array */
    protected $items;

    /**
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Reduce a value to plain header data: scalars, arrays and stdClass objects,
     * with a Header read as its array. Any other object becomes null, so a page
     * header only ever holds data, never a service or other live object.
     *
     * @param mixed $value
     * @return mixed
     */
    public static function plainData($value)
    {
        if ($value instanceof self) {
            $value = $value->toArray();
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = static::plainData($item);
            }

            return $value;
        }

        if ($value instanceof \stdClass) {
            $object = new \stdClass();
            foreach (get_object_vars($value) as $key => $item) {
                $object->{$key} = static::plainData($item);
            }

            return $object;
        }

        return is_object($value) ? null : $value;
    }
}
