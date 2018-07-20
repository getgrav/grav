<?php
namespace Grav\Common\Media\Traits;

use Grav\Common\Cache;
use Grav\Common\Grav;
use Grav\Common\Media\Interfaces\MediaCollectionInterface;
use Grav\Common\Page\Media;
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
    abstract public function getMediaOrder();

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
           return 'user://' . substr($folder, strlen($user)+1);
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
        $cache = $this->getMediaCache();

        if ($this->media === null) {
            // Use cached media if possible.
            $cacheKey = md5('media' . $this->getCacheKey());
            if (!$media = $cache->fetch($cacheKey)) {
                $media = new Media($this->getMediaFolder(), $this->getMediaOrder());
                $cache->save($cacheKey, $media);
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
        $cache->save($cacheKey, $media);

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
    }

    /**
     * @return Cache
     */
    protected function getMediaCache()
    {
        return Grav::instance()['cache'];
    }

    /**
     * @return string
     */
    abstract protected function getCacheKey();
}
