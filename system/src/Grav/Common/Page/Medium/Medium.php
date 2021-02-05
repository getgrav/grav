<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Medium;

use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use Grav\Common\Data\Data;
use Grav\Common\Data\Blueprint;
use Grav\Common\Media\Interfaces\MediaFileInterface;
use Grav\Common\Media\Interfaces\MediaLinkInterface;
use Grav\Common\Media\Traits\MediaFileTrait;
use Grav\Common\Media\Traits\MediaObjectTrait;

/**
 * Class Medium
 * @package Grav\Common\Page\Medium
 *
 * @property string $mime
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
        parent::__construct($items, $blueprint);

        if (Grav::instance()['config']->get('system.media.enable_media_timestamp', true)) {
            $this->timestamp = Grav::instance()['cache']->getKey();
        }

        $this->def('mime', 'application/octet-stream');

        if (!$this->offsetExists('size')) {
            $path = $this->get('filepath');
            $this->def('size', filesize($path));
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
     * Add meta file for the medium.
     *
     * @param string $filepath
     */
    public function addMetaFile($filepath)
    {
        $this->metadata = (array)CompiledYamlFile::instance($filepath)->content();
        $this->merge($this->metadata);
    }

    /**
     * @return array
     */
    public function getMeta(): array
    {
        return [
            'mime' => $this->mime,
            'size' => $this->size,
            'modified' => $this->modified,
        ];
    }

    /**
     * Return string representation of the object (html).
     *
     * @return string
     */
    public function __toString()
    {
        return $this->html();
    }

    /**
     * @param string $thumb
     */
    protected function createThumbnail($thumb)
    {
        return MediumFactory::fromFile($thumb, ['type' => 'thumbnail']);
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
