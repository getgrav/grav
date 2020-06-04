<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Medium;

use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Common\Language\Language;
use Grav\Common\Media\Interfaces\MediaCollectionInterface;
use Grav\Common\Media\Interfaces\MediaObjectInterface;
use Grav\Common\Media\Interfaces\MediaUploadInterface;
use Grav\Common\Media\Traits\MediaUploadTrait;
use Grav\Common\Page\Pages;
use Grav\Common\Utils;
use RocketTheme\Toolbox\ArrayTraits\ArrayAccess;
use RocketTheme\Toolbox\ArrayTraits\Countable;
use RocketTheme\Toolbox\ArrayTraits\Export;
use RocketTheme\Toolbox\ArrayTraits\ExportInterface;
use RocketTheme\Toolbox\ArrayTraits\Iterator;

abstract class AbstractMedia implements ExportInterface, MediaCollectionInterface, MediaUploadInterface
{
    use ArrayAccess;
    use Countable;
    use Iterator;
    use Export;
    use MediaUploadTrait;

    /** @var array */
    protected $items = [];
    /** @var string|null */
    protected $path;
    /** @var array */
    protected $images = [];
    /** @var array */
    protected $videos = [];
    /** @var array */
    protected $audios = [];
    /** @var array */
    protected $files = [];
    /** @var array|null */
    protected $media_order;

    /**
     * Return media path.
     *
     * @return string|null
     */
    public function getPath(): ?string
    {
        return $this->path;
    }

    /**
     * @param string|null $path
     */
    public function setPath(?string $path): void
    {
        $this->path = $path;
    }

    /**
     * Get medium by filename.
     *
     * @param string $filename
     * @return Medium|null
     */
    public function get($filename)
    {
        return $this->offsetGet($filename);
    }

    /**
     * Call object as function to get medium by filename.
     *
     * @param string $filename
     * @return mixed
     */
    public function __invoke($filename)
    {
        return $this->offsetGet($filename);
    }

    /**
     * Set file modification timestamps (query params) for all the media files.
     *
     * @param string|int|null $timestamp
     * @return $this
     */
    public function setTimestamps($timestamp = null)
    {
        /** @var Medium $instance */
        foreach ($this->items as $instance) {
            $instance->setTimestamp($timestamp);
        }

        return $this;
    }

    /**
     * Get a list of all media.
     *
     * @return MediaObjectInterface[]
     */
    public function all()
    {
        $this->items = $this->orderMedia($this->items);

        return $this->items;
    }

    /**
     * Get a list of all image media.
     *
     * @return MediaObjectInterface[]
     */
    public function images()
    {
        $this->images = $this->orderMedia($this->images);

        return $this->images;
    }

    /**
     * Get a list of all video media.
     *
     * @return MediaObjectInterface[]
     */
    public function videos()
    {
        $this->videos = $this->orderMedia($this->videos);

        return $this->videos;
    }

    /**
     * Get a list of all audio media.
     *
     * @return MediaObjectInterface[]
     */
    public function audios()
    {
        $this->audios = $this->orderMedia($this->audios);

        return $this->audios;
    }

    /**
     * Get a list of all file media.
     *
     * @return MediaObjectInterface[]
     */
    public function files()
    {
        $this->files = $this->orderMedia($this->files);

        return $this->files;
    }

    /**
     * @param string $name
     * @param MediaObjectInterface|null $file
     */
    public function add($name, $file)
    {
        if (null === $file) {
            return;
        }

        $this->offsetSet($name, $file);

        switch ($file->type) {
            case 'image':
                $this->images[$name] = $file;
                break;
            case 'video':
                $this->videos[$name] = $file;
                break;
            case 'audio':
                $this->audios[$name] = $file;
                break;
            default:
                $this->files[$name] = $file;
        }
    }

    /**
     * Order the media based on the page's media_order
     *
     * @param array $media
     * @return array
     */
    protected function orderMedia($media)
    {
        if (null === $this->media_order) {
            $path = $this->getPath();
            if (null !== $path) {
                /** @var Pages $pages */
                $pages = Grav::instance()['pages'];
                $page = $pages->get($path);
                if ($page && isset($page->header()->media_order)) {
                    $this->media_order = array_map('trim', explode(',', $page->header()->media_order));
                }
            }
        }

        if (!empty($this->media_order) && is_array($this->media_order)) {
            $media = Utils::sortArrayByArray($media, $this->media_order);
        } else {
            ksort($media, SORT_NATURAL | SORT_FLAG_CASE);
        }

        return $media;
    }

    /**
     * Get filename, extension and meta part.
     *
     * @param  string $filename
     * @return array
     */
    protected function getFileParts($filename)
    {
        if (preg_match('/(.*)@(\d+)x\.(.*)$/', $filename, $matches)) {
            $name = $matches[1];
            $extension = $matches[3];
            $extra = (int) $matches[2];
            $type = 'alternative';

            if ($extra === 1) {
                $type = 'base';
                $extra = null;
            }
        } else {
            $fileParts = explode('.', $filename);

            $name = array_shift($fileParts);
            $extension = null;
            $extra = null;
            $type = 'base';

            while (($part = array_shift($fileParts)) !== null) {
                if ($part !== 'meta' && $part !== 'thumb') {
                    if (null !== $extension) {
                        $name .= '.' . $extension;
                    }
                    $extension = $part;
                } else {
                    $type = $part;
                    $extra = '.' . $part . '.' . implode('.', $fileParts);
                    break;
                }
            }
        }

        return [$name, $extension, $type, $extra];
    }

    protected function getGrav(): Grav
    {
        return Grav::instance();
    }

    protected function getConfig(): Config
    {
        return $this->getGrav()['config'];
    }

    protected function getLanguage(): Language
    {
        return $this->getGrav()['language'];
    }

    protected function clearCache(): void
    {
        // TODO: Implement clearCache() method.
    }
}
