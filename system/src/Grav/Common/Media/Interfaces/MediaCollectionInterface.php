<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media\Interfaces;

/**
 * Class implements media collection interface.
 */
interface MediaCollectionInterface extends \Grav\Framework\Media\Interfaces\MediaCollectionInterface
{
    /**
     * Return media path.
     *
     * @return string|null
     */
    public function getPath();

    /**
     * Get a list of all media.
     *
     * @return MediaObjectInterface[]
     */
    public function all();

    /**
     * Set file modification timestamps (query params) for all the media files.
     *
     * @param string|int|null $timestamp
     * @return $this
     */
    public function setTimestamps($timestamp = null);

    /**
     * @param string $name
     * @param MediaObjectInterface $file
     */
    public function add($name, $file);
}
