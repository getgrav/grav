<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Medium;

use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use Grav\Common\Data\Data;
use Grav\Common\Data\Blueprint;
use Grav\Common\Media\Interfaces\MediaCollectionInterface;
use Grav\Common\Media\Interfaces\MediaFileInterface;
use Grav\Common\Media\Interfaces\MediaLinkInterface;
use Grav\Common\Media\Interfaces\MediaObjectInterface;
use Grav\Common\Media\Traits\MediaFileTrait;
use Grav\Common\Media\Traits\MediaObjectTrait;
use RuntimeException;

/**
 * Class Medium
 * @package Grav\Common\Page\Medium
 *
 * @property string $filepath
 * @property string $filename
 * @property string $basename
 * @property string $mime
 * @property int $size
 * @property int $modified
 * @property array $metadata
 * @property int|string $timestamp
 */
class Medium extends Data implements RenderableInterface, MediaFileInterface
{
    use MediaObjectTrait;
    use MediaFileTrait;
    use ParsedownHtmlTrait;

    /**
     * Construct.
     *
     * @param array $items
     * @param Blueprint|null $blueprint
     */
    public function __construct($items = [], Blueprint $blueprint = null)
    {
        $items += [
            'mime' => 'application/octet-stream'
        ];
        $size = $items['size'] ?? null;
        $modified = $items['modified'] ?? null;
        if (null === $size || null === $modified) {
            $path = $items['filepath'];
            if ($path && file_exists($path)) {
                $items['size'] = $size ?? filesize($path);
                $items['modified'] = $modified ?? filemtime($path);
            }
        }

        parent::__construct($items, $blueprint);

        if (Grav::instance()['config']->get('system.media.enable_media_timestamp', true)) {
            $this->timestamp = Grav::instance()['cache']->getKey();
        }

        $this->reset();
    }

    /**
     * Clone medium.
     */
    public function __clone()
    {
        // Allows future compatibility as parent::__clone() works.
    }

    /**
     * @return string
     * @throws RuntimeException
     */
    public function readFile(): string
    {
        return $this->getMedia()->readFile($this->filename, $this->getInfo());
    }

    /**
     * @return resource
     * @throws RuntimeException
     */
    public function readStream()
    {
        return $this->getMedia()->readStream($this->filename, $this->getInfo());
    }

    /**
     * Add meta file for the medium.
     *
     * @param string $filepath
     * @return void
     */
    public function addMetaFile($filepath)
    {
        $this->metadata = (array)CompiledYamlFile::instance($filepath)->content();
        $this->merge($this->metadata);
        $this->reset();
    }

    /**
     * Get basic file info.
     *
     * @return array
     */
    public function getInfo(): array
    {
        // TODO: this may require some tweaking, works for now.
        $info = [
            'modified' => $this->modified,
            'size' => $this->size,
            'mime' => $this->mime,
            'width' => $this->width,
            'height' => $this->height,
            'orientation' => $this->orientation,
            'meta' => $this->meta ?? [],
        ];

        return array_filter($info, static function($val) { return $val !== null; } );
    }

    /**
     * @return array
     */
    public function getMeta(): array
    {
        // TODO: this may require some tweaking, works for now.
        $meta = $this->metadata + ($this->meta ?? []) + [
            'name' => $this->filename,
            'mime' => $this->mime,
            'size' => $this->size,
            'modified' => $this->modified,
        ];

        return array_filter($meta, static function($val) { return $val !== null; } );
    }

    /**
     * Return string representation of the object (html).
     *
     * @return string
     */
    #[\ReturnTypeWillChange]
    public function __toString()
    {
        return $this->html();
    }

    /**
     * @param string $thumb
     * @return MediaObjectInterface|null
     */
    protected function createThumbnail($thumb): ?MediaObjectInterface
    {
        return $this->getMedia()->createFromFile($thumb, ['type' => 'thumbnail']);
    }

    /**
     * @param array $attributes
     * @return MediaLinkInterface
     */
    protected function createLink(array $attributes)
    {
        return new Link($attributes, $this);
    }

    /**
     * @return MediaCollectionInterface
     */
    protected function getMedia(): MediaCollectionInterface
    {
        return $this->get('media', GlobalMedia::getInstance());
    }

    /**
     * @return Grav
     */
    protected function getGrav(): Grav
    {
        return Grav::instance();
    }

    /**
     * @return array
     */
    protected function getItems(): array
    {
        return $this->items;
    }
}
