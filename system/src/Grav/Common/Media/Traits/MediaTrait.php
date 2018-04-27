<?php
namespace Grav\Common\Media\Traits;

use Grav\Common\Cache;
use Grav\Common\Grav;
use Grav\Common\Media\Interfaces\MediaCollectionInterface;
use Grav\Common\Page\Media;

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
     * Gets the associated media collection.
     *
     * @return MediaCollectionInterface  Representation of associated media.
     */
    public function getMedia()
    {
        /** @var Cache $cache */
        $cache = Grav::instance()['cache'];

        if ($this->media === null) {
            // Use cached media if possible.
            $cacheKey = md5('media' . $this->getCacheKey());
            if (!$media = $cache->fetch($cacheKey)) {
                $media = new Media($this->getMediaFolder());
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
        $this->media = $media;

        return $this;
    }

    /**
     * @return string
     */
    abstract protected function getCacheKey();
}
