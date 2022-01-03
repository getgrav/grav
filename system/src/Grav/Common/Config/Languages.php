<?php

/**
 * @package    Grav\Common\Config
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Config;

use Grav\Common\Data\Data;
use Grav\Common\Utils;

/**
 * Class Languages
 * @package Grav\Common\Config
 */
class Languages extends Data
{
    /** @var string|null */
    protected $checksum;

    /** @var bool */
    protected $modified = false;

    /** @var int */
    protected $timestamp = 0;

    /**
     * @param string|null $checksum
     * @return string|null
     */
    public function checksum($checksum = null)
    {
        if ($checksum !== null) {
            $this->checksum = $checksum;
        }

        return $this->checksum;
    }

    /**
     * @param bool|null $modified
     * @return bool
     */
    public function modified($modified = null)
    {
        if ($modified !== null) {
            $this->modified = $modified;
        }

        return $this->modified;
    }

    /**
     * @param int|null $timestamp
     * @return int
     */
    public function timestamp($timestamp = null)
    {
        if ($timestamp !== null) {
            $this->timestamp = $timestamp;
        }

        return $this->timestamp;
    }

    /**
     * @return void
     */
    public function reformat()
    {
        if (isset($this->items['plugins'])) {
            $this->items = array_merge_recursive($this->items, $this->items['plugins']);
            unset($this->items['plugins']);
        }
    }

    /**
     * @param array $data
     * @return void
     */
    public function mergeRecursive(array $data)
    {
        $this->items = Utils::arrayMergeRecursiveUnique($this->items, $data);
    }

    /**
     * @param string $lang
     * @return array
     */
    public function flattenByLang($lang)
    {
        $language = $this->items[$lang];
        return Utils::arrayFlattenDotNotation($language);
    }

    /**
     * @param array $array
     * @return array
     */
    public function unflatten($array)
    {
        return Utils::arrayUnflattenDotNotation($array);
    }
}
