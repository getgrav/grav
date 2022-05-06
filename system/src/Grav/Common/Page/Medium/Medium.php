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
use Grav\Common\Media\Interfaces\ImageMediaInterface;
use Grav\Common\Media\Interfaces\MediaCollectionInterface;
use Grav\Common\Media\Interfaces\MediaFileInterface;
use Grav\Common\Media\Interfaces\MediaLinkInterface;
use Grav\Common\Media\Traits\MediaFileTrait;
use Grav\Common\Media\Traits\MediaObjectTrait;
use JsonSerializable;
use RocketTheme\Toolbox\ArrayTraits\Countable;
use RocketTheme\Toolbox\ArrayTraits\Export;
use RocketTheme\Toolbox\ArrayTraits\ExportInterface;
use RocketTheme\Toolbox\ArrayTraits\NestedArrayAccessWithGetters;
use RuntimeException;

/**
 * Class Medium
 * @package Grav\Common\Page\Medium
 *
 * @property string $filepath
 * @property string $filename
 * @property string $basename
 * @property string|null $extension
 * @property string $mime
 * @property int $size
 * @property int $modified
 * @property array|null $meta
 * @property bool|null $uploaded
 */
class Medium implements RenderableInterface, MediaFileInterface, JsonSerializable, \Countable, ExportInterface
{
    use NestedArrayAccessWithGetters;
    use Countable;
    use Export;
    use MediaObjectTrait;
    use MediaFileTrait;
    use ParsedownHtmlTrait;

    /** @var string[] */
    protected array $items;

    /**
     * Construct.
     *
     * @param array $items
     */
    public function __construct(array $items = [])
    {
        $items += [
            'mime' => 'application/octet-stream'
        ];
        $size = $items['size'] ?? null;
        $modified = $items['modified'] ?? null;
        if (null === $size || null === $modified) {
            user_error(__METHOD__ . '() missing size and modified properties are deprecated since Grav 1.8, pass those in $items array', E_USER_DEPRECATED);

            $path = $items['filepath'];
            if ($path && file_exists($path)) {
                $items['size'] = $size ?? filesize($path);
                $items['modified'] = $modified ?? filemtime($path);
            }
        }

        $this->items = $items;

        if ($this->getGrav()['config']->get('system.media.enable_media_timestamp', true)) {
            $this->timestamp = Grav::instance()['cache']->getKey();
        }

        $this->reset();
    }

    /**
     * Clone medium.
     */
    public function __clone()
    {
    }

    /**
     * @return array
     */
    public function __serialize(): array
    {
        return [
            'items' => $this->items,
            'nestedSeparator' => $this->nestedSeparator,
        ] + $this->serializeMediaObjectTrait();
    }

    /**
     * @param array $data
     * @return void
     */
    public function __unserialize(array $data): void
    {
        $this->items = $data['items'];
        $this->nestedSeparator = $data['nestedSeparator'];
        $this->unserializeMediaObjectTrait($data);
    }

    /**
     * @return array
     * @phpstan-pure
     */
    public function jsonSerialize(): array
    {
        return $this->items;
    }

    /**
     * @return string
     * @throws RuntimeException
     * @phpstan-pure
     */
    public function readFile(): string
    {
        return $this->getMedia()->readFile($this->filename, $this->getInfo());
    }

    /**
     * @return resource
     * @throws RuntimeException
     * @phpstan-pure
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
     * @phpstan-impure
     */
    public function addMetaFile(string $filepath): void
    {
        $this->metadata = (array)CompiledYamlFile::instance($filepath)->content();
        $this->items = array_merge($this->items, $this->metadata);
        $this->reset();
    }

    /**
     * Get basic file info.
     *
     * @return array
     * @phpstan-pure
     */
    public function getInfo(): array
    {
        $info = [
            'modified' => $this->modified,
            'size' => $this->size,
            'mime' => $this->mime,
            'meta' => $this->meta ?? [],
        ];

        return array_filter($info, static function($val) { return $val !== null; } );
    }

    /**
     * @return array
     * @phpstan-pure
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
     * @phpstan-pure
     */
    public function __toString(): string
    {
        return $this->html();
    }

    /**
     * @param string $thumb
     * @return ImageMediaInterface|null
     * @phpstan-pure
     */
    protected function createThumbnail(string $thumb): ?ImageMediaInterface
    {
        $thumbnail = $this->getMedia()->createFromFile($thumb, ['type' => 'thumbnail']);
        if (!$thumbnail instanceof ImageMediaInterface) {
            return null;
        }

        return $thumbnail;
    }

    /**
     * @param array $attributes
     * @return MediaLinkInterface
     * @phpstan-pure
     */
    protected function createLink(array $attributes): MediaLinkInterface
    {
        return new Link($attributes, $this);
    }

    /**
     * @return MediaCollectionInterface
     * @phpstan-pure
     */
    protected function getMedia(): MediaCollectionInterface
    {
        return $this->get('media', GlobalMedia::getInstance());
    }

    /**
     * @return Grav
     * @phpstan-pure
     */
    protected function getGrav(): Grav
    {
        return Grav::instance();
    }

    /**
     * @return array
     * @phpstan-pure
     */
    protected function getItems(): array
    {
        return $this->items;
    }
}
