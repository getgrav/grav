<?php

declare(strict_types=1);

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Flex;

use Grav\Common\File\CompiledJsonFile;
use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use Grav\Framework\Flex\Interfaces\FlexStorageInterface;
use Grav\Framework\Flex\Pages\FlexPageIndex;

/**
 * Class GravPageObject
 * @package Grav\Plugin\FlexObjects\Types\GravPages
 */
class PageIndex extends FlexPageIndex
{
    const VERSION = parent::VERSION . '.5';
    const ORDER_LIST_REGEX = '/(\/\d+)\.[^\/]+/u';
    const PAGE_ROUTE_REGEX = '/\/\d+\./u';

    /** @var FlexObjectInterface */
    protected $_root;
    protected $_params;

    /**
     * @param array $entries
     * @param FlexDirectory|null $directory
     */
    public function __construct(array $entries = [], FlexDirectory $directory = null)
    {
        // Remove root if it's taken.
        if (isset($entries[''])) {
            $this->_root = $entries[''];
            unset($entries['']);
        }

        parent::__construct($entries, $directory);
    }

    /**
     * @param array $entries
     * @param string|null $keyField
     * @return $this|FlexPageIndex
     */
    protected function createFrom(array $entries, string $keyField = null)
    {
        /** @var static $index */
        $index = parent::createFrom($entries, $keyField);
        $index->_root = $this->getRoot();

        return $index;
    }

    /**
     * @param FlexStorageInterface $storage
     * @return array
     */
    public static function loadEntriesFromStorage(FlexStorageInterface $storage) : array
    {
        // Load saved index.
        $index = static::loadIndex($storage);

        $timestamp = $index['timestamp'] ?? 0;
        if ($timestamp > time() - 2) {
            return $index['index'];
        }

        // Load up to date index.
        $entries = parent::loadEntriesFromStorage($storage);

        return static::updateIndexFile($storage, $index['index'], $entries, ['include_missing' => true]);
    }

    /**
     * @param string $key
     * @return FlexObjectInterface|PageInterface|null
     */
    public function get($key)
    {
        if (mb_strpos($key, '|') !== false) {
            [$key, $params] = explode('|', $key, 2);
        }

        $element = parent::get($key);
        if (isset($params)) {
            $element = $element->getTranslation(ltrim($params, '.'));
        }

        return $element;
    }

    /**
     * @return FlexObjectInterface|PageInterface
     */
    public function getRoot()
    {
        $root = $this->_root;
        if (is_array($root)) {
            $this->_root = $this->getFlexDirectory()->createObject(['__META' => $root], '/');
        }

        return $this->_root;
    }

    /**
     * Get the collection params
     *
     * @return array
     */
    public function getParams(): array
    {
        return $this->_params ?? [];
    }

    /**
     * Set parameters to the Collection
     *
     * @param array $params
     *
     * @return $this
     */
    public function setParams(array $params)
    {
        $this->_params = $this->_params ? array_merge($this->_params, $params) : $params;

        return $this;
    }

    /**
     * Get the collection params
     *
     * @return array
     */
    public function params(): array
    {
        return $this->getParams();
    }

    /**
     * @param FlexStorageInterface $storage
     * @return CompiledJsonFile|\Grav\Common\File\CompiledYamlFile|null
     */
    protected static function getIndexFile(FlexStorageInterface $storage)
    {
        // Load saved index file.
        $grav = Grav::instance();
        $locator = $grav['locator'];

        $filename = $locator->findResource('user-data://flex/indexes/pages.json', true, true);

        return CompiledJsonFile::instance($filename);
    }
}
