<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media\Interfaces;

use Grav\Common\Data\Blueprint;
use Grav\Common\Page\Medium\ImageFile;
use Grav\Common\Page\Medium\Medium;

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
     * @param string|null $path
     * @return void
     */
    public function setPath(?string $path);

    /**
     * Get medium by filename.
     *
     * @param string $filename
     * @return Medium|null
     */
    public function get($filename);

    /**
     * Get a list of all media.
     *
     * @return MediaObjectInterface[]
     */
    public function all();

    /**
     * Get a list of all image media.
     *
     * @return MediaObjectInterface[]
     */
    public function images();

    /**
     * Get a list of all video media.
     *
     * @return MediaObjectInterface[]
     */
    public function videos();

    /**
     * Get a list of all audio media.
     *
     * @return MediaObjectInterface[]
     */
    public function audios();

    /**
     * Get a list of all file media.
     *
     * @return MediaObjectInterface[]
     */
    public function files();

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
     * @return void
     */
    public function add($name, $file);

    /**
     * Create Medium from a file.
     *
     * @param  string $file
     * @param  array  $params
     * @return Medium|null
     */
    public function createFromFile($file, array $params = []);

    /**
     * Create Medium from array of parameters
     *
     * @param  array          $items
     * @param  Blueprint|null $blueprint
     * @return Medium|null
     */
    public function createFromArray(array $items = [], Blueprint $blueprint = null);

    /**
     * @param MediaObjectInterface $mediaObject
     * @return ImageFile
     */
    public function getImageFileObject(MediaObjectInterface $mediaObject): ImageFile;
}
