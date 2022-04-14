<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page;

use Grav\Common\Media\Interfaces\MediaObjectInterface;
use Grav\Common\Page\Medium\LocalMedia;
use Grav\Common\Page\Medium\GlobalMedia;

/**
 * Class Media
 * @package Grav\Common\Page
 */
class Media extends LocalMedia
{
    protected const VERSION = parent::VERSION . '.1';

    /** @var bool */
    protected $useGlobalMedia;

    /**
     * @param string|null $path
     * @param array|null $mediaOrder
     * @param bool   $load
     */
    public function __construct(?string $path, array $mediaOrder = null, bool $load = true)
    {
        $this->setPath($path);
        $this->useGlobalMedia = true;
        $this->indexFolder = $this->getPath();
        $this->indexTimeout = 60;
        $this->media_order = $mediaOrder;

        $path = $this->getPath();
        $this->exists = null !== $path && is_dir($path);

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
    public function offsetExists($offset): bool
    {
        return parent::offsetExists($offset) || ($this->useGlobalMedia && isset(GlobalMedia::getInstance()[$offset]));
    }

    /**
     * @param string $offset
     * @return MediaObjectInterface|null
     */
    public function offsetGet($offset): ?MediaObjectInterface
    {
        return parent::offsetGet($offset) ?? ($this->useGlobalMedia ? GlobalMedia::getInstance()[$offset] : null);
    }
}
