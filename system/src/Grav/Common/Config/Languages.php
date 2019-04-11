<?php

/**
 * @package    Grav\Common\Config
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Config;

use Grav\Common\Data\Data;
use Grav\Common\Utils;

class Languages extends Data
{
    /**
     * @var string|null
     */
    protected $checksum;

    /**
     * @var string|null
     */
    protected $modified;

    /**
     * @var string|null
     */
    protected $timestamp;


    public function checksum($checksum = null)
    {
        if ($checksum !== null) {
            $this->checksum = $checksum;
        }

        return $this->checksum;
    }

    public function modified($modified = null)
    {
        if ($modified !== null) {
            $this->modified = $modified;
        }

        return $this->modified;
    }

    public function timestamp($timestamp = null)
    {
        if ($timestamp !== null) {
            $this->timestamp = $timestamp;
        }

        return $this->timestamp;
    }

    public function reformat()
    {
        if (isset($this->items['plugins'])) {
            $this->items = array_merge_recursive($this->items, $this->items['plugins']);
            unset($this->items['plugins']);
        }
    }

    public function mergeRecursive(array $data)
    {
        $this->items = Utils::arrayMergeRecursiveUnique($this->items, $data);
    }

    public function flattenByLang($lang)
    {
        $language = $this->items[$lang];
        return Utils::arrayFlattenDotNotation($language);
    }

    public function unflatten($array)
    {
        return Utils::arrayUnflattenDotNotation($array);
    }
}
