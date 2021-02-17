<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media\Traits;

use Grav\Common\Cache;
use Grav\Common\Grav;
use Grav\Common\Media\Interfaces\MediaCollectionInterface;
use Grav\Common\Page\Media;
use Psr\SimpleCache\CacheInterface;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use function strlen;

/**
 * Trait MediaTrait
 * @package Grav\Common\Media\Traits
 */
trait MediaTrait
{
    /** @var MediaCollectionInterface|null */
    protected $media;
    /** @var bool */
    protected $_loadMedia = true;

    /**
     * Get filesystem path to the associated media.
     *
     * @return string|null
     */
    abstract public function getMediaFolder();

    /**
     * Get display order for the associated media.
     *
     * @return array Empty array means default ordering.
     */
    public function getMediaOrder()
    {
        return [];
    }

    /**
     * Get URI ot the associated media. Method will return null if path isn't URI.
     *
     * @return string|null
     */
    public function getMediaUri()
    {
        $folder = $this->getMediaFolder();
        if (!$folder) {
            return null;
        }

        if (strpos($folder, '://')) {
            return $folder;
        }

       /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];
        $user = $locator->findResource('user://');
        if (strpos($folder, $user) === 0) {
            return 'user://' . substr($folder, strlen($user)+1);
        }

        return null;
    }

    /**
     * Gets the associated media collection.
     *
     * @return MediaCollectionInterface|Media  Representation of associated media.
     */
    public function getMedia()
    {
        $media = $this->media;
        if (null === $media) {
            $cache = $this->getMediaCache();
            $cacheKey = md5('media' . $this->getCacheKey());

            // Use cached media if possible.
            $media = $cache->get($cacheKey);
            if (!$media instanceof MediaCollectionInterface) {
                $media = new Media($this->getMediaFolder(), $this->getMediaOrder(), $this->_loadMedia);
                $cache->set($cacheKey, $media);
            }

            $this->media = $media;
        }

        return $media;
    }

    /**
     * Sets the associated media collection.
     *
     * @param  MediaCollectionInterface|Media  $media Representation of associated media.
     * @return $this
     */
    protected function setMedia(MediaCollectionInterface $media)
    {
        $cache = $this->getMediaCache();
        $cacheKey = md5('media' . $this->getCacheKey());
        $cache->set($cacheKey, $media);

        $this->media = $media;

        return $this;
    }

    /**
     * @return void
     */
    protected function freeMedia()
    {
        $this->media = null;
    }

    /**
     * Clear media cache.
     *
     * @return void
     */
    protected function clearMediaCache()
    {
        $cache = $this->getMediaCache();
        $cacheKey = md5('media' . $this->getCacheKey());
        $cache->delete($cacheKey);

        $this->freeMedia();
    }

    /**
     * @return CacheInterface
     */
    protected function getMediaCache()
    {
        /** @var Cache $cache */
        $cache = Grav::instance()['cache'];

        return $cache->getSimpleCache();
    }

    /**
     * @return string
     */
    abstract protected function getCacheKey(): string;
}
