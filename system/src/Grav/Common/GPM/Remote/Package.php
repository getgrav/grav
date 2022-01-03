<?php

/**
 * @package    Grav\Common\GPM
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\GPM\Remote;

use Grav\Common\Data\Data;
use Grav\Common\GPM\Common\Package as BasePackage;

/**
 * Class Package
 * @package Grav\Common\GPM\Remote
 */
class Package extends BasePackage implements \JsonSerializable
{
    /**
     * Package constructor.
     * @param array $package
     * @param string|null $package_type
     */
    public function __construct($package, $package_type = null)
    {
        $data = new Data($package);
        parent::__construct($data, $package_type);
    }

    /**
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->data->toArray();
    }

    /**
     * Returns the changelog list for each version of a package
     *
     * @param string|null $diff the version number to start the diff from
     * @return array changelog list for each version
     */
    public function getChangelog($diff = null)
    {
        if (!$diff) {
            return $this->data['changelog'];
        }

        $diffLog = [];
        foreach ((array)$this->data['changelog'] as $version => $changelog) {
            preg_match("/[\w\-.]+/", $version, $cleanVersion);

            if (!$cleanVersion || version_compare($diff, $cleanVersion[0], '>=')) {
                continue;
            }

            $diffLog[$version] = $changelog;
        }

        return $diffLog;
    }
}
