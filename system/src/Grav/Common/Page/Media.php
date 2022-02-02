<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page;

use FilesystemIterator;
use Grav\Common\Media\Interfaces\MediaObjectInterface;
use Grav\Common\Utils;
use Grav\Common\Page\Medium\AbstractMedia;
use Grav\Common\Page\Medium\GlobalMedia;

/**
 * Class Media
 * @package Grav\Common\Page
 */
class Media extends AbstractMedia
{
    protected const VERSION = parent::VERSION . '.1';

    /**
     * @param string $path
     * @param array|null $media_order
     * @param bool   $load
     */
    public function __construct($path, array $media_order = null, $load = true)
    {
        $this->setPath($path);
        $this->media_order = $media_order;

        if ($load) {
            $this->init();
        }
    }

    /**
     * Return raw route to the page.
     *
     * @return string|null Route to the page or null if media isn't for a page.
     */
    public function getRawRoute(): ?string
    {
        $path = $this->getPath();
        if ($path) {
            /** @var Pages $pages */
            $pages = $this->getGrav()['pages'];
            $page = $pages->get($path);
            if ($page) {
                return $page->rawRoute();
            }
        }

        return null;
    }

    /**
     * Return page route.
     *
     * @return string|null Route to the page or null if media isn't for a page.
     */
    public function getRoute(): ?string
    {
        $path = $this->getPath();
        if ($path) {
            /** @var Pages $pages */
            $pages = $this->getGrav()['pages'];
            $page = $pages->get($path);
            if ($page) {
                return $page->route();
            }
        }

        return null;
    }

    /**
     * @param string $offset
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return parent::offsetExists($offset) ?: isset(GlobalMedia::getInstance()[$offset]);
    }

    /**
     * @param string $offset
     * @return MediaObjectInterface|null
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return parent::offsetGet($offset) ?: GlobalMedia::getInstance()[$offset];
    }


    /**
     * Load file listing from the filesystem.
     *
     * @return array
     */
    protected function loadFileInfo(): array
    {
        $media = [];
        $files = new FilesystemIterator($this->path, FilesystemIterator::UNIX_PATHS | FilesystemIterator::SKIP_DOTS);
        foreach ($files as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $path = $item->getPathname();
            $info = Utils::pathinfo($path);

            // Include extra information.
            $info['modified'] = $item->getMTime();
            $info['size'] = $item->getSize();

            $media[$info['basename']] = $info;
        }

        return $media;
    }

    /**
     * @return string|null
     * @deprecated 1.6 Use $this->getPath() instead.
     */
    public function path(): ?string
    {
        return $this->getPath();
    }
}
