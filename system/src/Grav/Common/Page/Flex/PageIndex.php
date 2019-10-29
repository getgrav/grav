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
use Grav\Common\Utils;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Flex\Interfaces\FlexCollectionInterface;
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
     * @param array $options
     * @return array
     */
    public function getLevelListing(array $options): array
    {
        $options += [
            'field' => null,
            'route' => null,
            'leaf_route' => null,
            'sortby' => null,
            'order' => SORT_ASC,
            'lang' => null,
            'filters' => [],
        ];

        $options['filters'] += [
            'type' => ['root', 'dir'],
        ];

        return $this->getLevelListingRecurse($options);
    }

    /**
     * @param array $options
     * @return array
     */
    protected function getLevelListingRecurse(array $options): array
    {
        $filters = $options['filters'];
        $filter_type = (array)$filters['type'];

        $field = $options['field'];
        $route = $options['route'];
        $leaf_route = $options['leaf_route'];
        $sortby = $options['sortby'];
        $order = $options['order'];
        $language = $options['lang'];

        $status = 'error';
        $msg = null;
        $response = [];
        $children = null;
        $sub_route = null;
        $extra = null;

        // Handle leaf_route
        $leaf = null;
        if ($leaf_route && $route !== $leaf_route) {
            $nodes = explode('/', $leaf_route);
            $sub_route =  '/' . implode('/', array_slice($nodes, 1, $options['level']++));
            $options['route'] = $sub_route;

            [$status,,$leaf,$extra] = $this->getLevelListingRecurse($options);
        }

        // Handle no route, assume page tree root
        if (!$route) {
            $page = $this->getRoot();
        } else {
            $page = $this->get(trim($route, '/'));
        }
        $path = $page ? $page->path() : null;

        if ($field) {
            $settings = $this->getFlexDirectory()->getBlueprint()->schema()->getProperty($field);
            $filters = array_merge([], $filters, $settings['filters'] ?? []);
            $filter_type = $filters['type'] ?? $filter_type;
        }

        if ($page) {
            if ($page->root() && (!$filters['type'] || in_array('root', $filter_type, true))) {
                if ($field) {
                    $response[] = [
                        'name' => '<root>',
                        'value' => '/',
                        'item-key' => '',
                        'filename' => '.',
                        'extension' => '',
                        'type' => 'root',
                        'modified' => $page->modified(),
                        'size' => 0,
                        'symlink' => false
                    ];
                } else {
                    $response[] = [
                        'item-key' => '',
                        'icon' => 'root',
                        'title' => '<root>',
                        'route' => '/',
                        'raw_route' => null,
                        'modified' => $page->modified(),
                        'child_count' => 0,
                        'extras' => [
                            'template' => null,
                            'langs' => [],
                            'published' => false,
                            'published_date' => null,
                            'unpublished_date' => null,
                            'visible' => false,
                            'routable' => false,
                            'tags' => ['non-routable'],
                            'actions' => [],
                        ]
                    ];
                }
            }

            $status = 'success';
            $msg = 'PLUGIN_ADMIN.PAGE_ROUTE_FOUND';

            $children = $page->children();

            /** @var PageObject $child */
            foreach ($children as $child) {
                $selected = $child->path() === $extra;
                $includeChildren = \is_array($leaf) && !empty($leaf) && $selected;
                if (!$selected && !$child->filterBy($filters)) {
                    continue;
                }
                if ($field) {
                    $payload = [
                        'name' => $child->title(),
                        'value' => $child->rawRoute(),
                        'item-key' => basename($child->rawRoute()),
                        'filename' => $child->folder(),
                        'extension' => $child->extension(),
                        'type' => 'dir',
                        'modified' => $child->modified(),
                        'size' => count($child->children()),
                        'symlink' => false
                    ];
                } else {
                    // TODO: all these features are independent from each other, we cannot just have one icon/color to catch all.
                    // TODO: maybe icon by home/modular/page/folder (or even from blueprints) and color by visibility etc..
                    if ($child->home()) {
                        $icon = 'home';
                    } elseif ($child->modular()) {
                        $icon = 'modular';
                    } elseif ($child->visible()) {
                        $icon = 'visible';
                    } elseif ($child->isPage()) {
                        $icon = 'page';
                    } else {
                        // TODO: add support
                        $icon = 'folder';
                    }
                    $tags = [
                        $child->published() ? 'published' : 'non-published',
                        $child->visible() ? 'visible' : 'non-visible',
                        $child->routable() ? 'routable' : 'non-routable'
                    ];
                    $lang = $child->findTranslation($language) ?? 'n/a';
                    $extras = [
                        'template' => $child->template(),
                        'lang' => $lang ?: null,
                        'translated' => $lang ? $child->hasTranslation($language, false) : null,
                        'langs' => $child->getAllLanguages(true) ?: null,
                        'published' => $child->published(),
                        'published_date' => $this->jsDate($child->publishDate()),
                        'unpublished_date' => $this->jsDate($child->unpublishDate()),
                        'visible' => $child->visible(),
                        'routable' => $child->routable(),
                        'tags' => $tags,
                        'actions' => null,
                    ];
                    $extras = array_filter($extras, static function ($v) {
                        return $v !== null;
                    });
                    $payload = [
                        'item-key' => basename($child->rawRoute()),
                        'icon' => $icon,
                        'title' => $child->title(),
                        'route' => [
                            'display' => $child->getRoute()->toString(false) ?: '/',
                            'raw' => $child->rawRoute(),
                        ],
                        'modified' => $this->jsDate($child->modified()),
                        'child_count' => count($child->children()) ?: null,
                        'extras' => $extras
                    ];
                    $payload = array_filter($payload, static function ($v) {
                        return $v !== null;
                    });
                }

                // Add children if any
                if ($includeChildren) {
                    $payload['children'] = array_values($leaf);
                }

                $response[] = $payload;
            }
        } else {
            $msg = 'PLUGIN_ADMIN.PAGE_ROUTE_NOT_FOUND';
        }

        // Sorting
        if ($sortby) {
            $response = Utils::sortArrayByKey($response, $sortby, $order);
        }

        if ($field) {
            $temp_array = [];
            foreach ($response as $index => $item) {
                $temp_array[$item['type']][$index] = $item;
            }

            $sorted = Utils::sortArrayByArray($temp_array, $filter_type);
            $response = Utils::arrayFlatten($sorted);
        }

        return [$status, $msg ?? 'PLUGIN_ADMIN.NO_ROUTE_PROVIDED', $response, $path];
    }

    /**
     * @param int|null $timestamp
     * @return string|null
     */
    private function jsDate(int $timestamp = null): ?string
    {
        if (!$timestamp) {
            return null;
        }

        $config = Grav::instance()['config'];
        $dateFormat = $config->get('system.pages.dateformat.long');

        return date($dateFormat, $timestamp) ?: null;
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
