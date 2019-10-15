<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media\Traits;

use Grav\Common\Cache;
use Grav\Common\Grav;
use Grav\Common\Media\Interfaces\MediaCollectionInterface;
use Grav\Common\Page\Media;
use Psr\SimpleCache\CacheInterface;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

trait MediaTrait
{
    protected $media;

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
     * @return null|string
     */
    public function getMediaUri()
    {
       $folder = $this->getMediaFolder();

       if (strpos($folder, '://')) {
           return $folder;
       }

       /** @var UniformResourceLocator $locator */
       $locator = Grav::instance()['locator'];
       $user = $locator->findResource('user://');
       if (strpos($folder, $user) === 0) {
           return 'user://' . substr($folder, \strlen($user)+1);
       }

       return null;
    }

    /**
     * Gets the associated media collection.
     *
     * @return MediaCollectionInterface  Representation of associated media.
     */
    public function getMedia()
    {
        if ($this->media === null) {
            $cache = $this->getMediaCache();

            // Use cached media if possible.
            $cacheKey = md5('media' . $this->getCacheKey());
            if (!$media = $cache->get($cacheKey)) {
                $media = new Media($this->getMediaFolder(), $this->getMediaOrder());
                $cache->set($cacheKey, $media);
            }
            $this->media = $media;
        }

        return $this->media;
    }

    /**
     * Sets the associated media collection.
     *
     * @param  MediaCollectionInterface  $media Representation of associated media.
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
     * Clear media cache.
     */
    protected function clearMediaCache()
    {
        $cache = $this->getMediaCache();
        $cacheKey = md5('media' . $this->getCacheKey());
        $cache->delete($cacheKey);

        $this->media = null;
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
