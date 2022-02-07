<?php

/**
 * @package    Grav\Common\GPM
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\GPM;

use Grav\Common\GPM\Remote\GravCore;
use InvalidArgumentException;

/**
 * Class Upgrader
 *
 * @package Grav\Common\GPM
 */
class Upgrader
{
    /** @var GravCore Remote details about latest Grav version */
    private $remote;

    /** @var string|null */
    private $min_php;

    /**
     * Creates a new GPM instance with Local and Remote packages available
     *
     * @param boolean  $refresh  Applies to Remote Packages only and forces a refetch of data
     * @param callable|null $callback Either a function or callback in array notation
     * @throws InvalidArgumentException
     */
    public function __construct($refresh = false, $callback = null)
    {
        $this->remote = new Remote\GravCore($refresh, $callback);
    }

    /**
     * Returns the release date of the latest version of Grav
     *
     * @return string
     */
    public function getReleaseDate()
    {
        return $this->remote->getDate();
    }

    /**
     * Returns the version of the installed Grav
     *
     * @return string
     */
    public function getLocalVersion()
    {
        return GRAV_VERSION;
    }

    /**
     * Returns the version of the remotely available Grav
     *
     * @return string
     */
    public function getRemoteVersion()
    {
        return $this->remote->getVersion();
    }

    /**
     * Returns an array of assets available to download remotely
     *
     * @return array
     */
    public function getAssets()
    {
        return $this->remote->getAssets();
    }

    /**
     * Returns the changelog list for each version of Grav
     *
     * @param string|null $diff the version number to start the diff from
     * @return array return the changelog list for each version
     */
    public function getChangelog($diff = null)
    {
        return $this->remote->getChangelog($diff);
    }

    /**
     * Make sure this meets minimum PHP requirements
     *
     * @return bool
     */
    public function meetsRequirements()
    {
        if (version_compare(PHP_VERSION, $this->minPHPVersion(), '<')) {
            return false;
        }

        return true;
    }

    /**
     * Get minimum PHP version from remote
     *
     * @return string
     */
    public function minPHPVersion()
    {
        if (null === $this->min_php) {
            $this->min_php = $this->remote->getMinPHPVersion();
        }

        return $this->min_php;
    }

    /**
     * Checks if the currently installed Grav is upgradable to a newer version
     *
     * @return bool True if it's upgradable, False otherwise.
     */
    public function isUpgradable()
    {
        return version_compare($this->getLocalVersion(), $this->getRemoteVersion(), '<');
    }

    /**
     * Checks if Grav is currently symbolically linked
     *
     * @return bool True if Grav is symlinked, False otherwise.
     */
    public function isSymlink()
    {
        return $this->remote->isSymlink();
    }
}
