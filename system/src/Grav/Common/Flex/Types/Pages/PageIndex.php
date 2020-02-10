<?php

declare(strict_types=1);

/**
 * @package    Grav\Common\Flex
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Flex\Types\Pages;

use Grav\Common\Debugger;
use Grav\Common\File\CompiledJsonFile;
use Grav\Common\Flex\Traits\FlexGravTrait;
use Grav\Common\Flex\Traits\FlexIndexTrait;
use Grav\Common\Grav;
use Grav\Common\Utils;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Flex\Interfaces\FlexCollectionInterface;
use Grav\Framework\Flex\Interfaces\FlexStorageInterface;
use Grav\Framework\Flex\Pages\FlexPageIndex;

/**
 * Class GravPageObject
 * @package Grav\Plugin\FlexObjects\Types\GravPages
 *
 * @method PageIndex dateRange($startDate, $endDate = false, $field = null)
 * @method PageIndex visible()
 * @method PageIndex nonVisible()
 * @method PageIndex modular()
 * @method PageIndex nonModular()
 * @method PageIndex published()
 * @method PageIndex nonPublished()
 * @method PageIndex routable()
 * @method PageIndex nonRoutable()
 * @method PageIndex ofType(string $type)
 * @method PageIndex ofOneOfTheseTypes(array $types)
 * @method PageIndex ofOneOfTheseAccessLevels(array $accessLevels)
 * @method PageIndex withModules(bool $bool = true)
 * @method PageIndex withPages(bool $bool = true)
 * @method PageIndex withTranslation(bool $bool = true, string $languageCode = null, bool $fallback = null)
 */
class PageIndex extends FlexPageIndex
{
    use FlexGravTrait;
    use FlexIndexTrait;

    public const VERSION = parent::VERSION . '.5';
    public const ORDER_LIST_REGEX = '/(\/\d+)\.[^\/]+/u';
    public const PAGE_ROUTE_REGEX = '/\/\d+\./u';

    /** @var PageObject|array */
    protected $_root;
    /** @var array|null */
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
     * @param FlexStorageInterface $storage
     * @return array
     */
    public static function loadEntriesFromStorage(FlexStorageInterface $storage): array
    {
        // Load saved index.
        $index = static::loadIndex($storage);

        $timestamp = $index['timestamp'] ?? 0;
        if ($timestamp && $timestamp > time() - 2) {
            return $index['index'];
        }

        // Load up to date index.
        $entries = parent::loadEntriesFromStorage($storage);

        return static::updateIndexFile($storage, $index['index'], $entries, ['include_missing' => true]);
    }

    /**
     * @param string $key
     * @return PageObject|null
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
     * @return PageObject
     */
    public function getRoot()
    {
        $root = $this->_root;
        if (is_array($root)) {
            $directory = $this->getFlexDirectory();
            $storage = $directory->getStorage();

            $defaults = [
                'header' => [
                    'routable' => false,
                    'permissions' => [
                        'inherit' => false
                    ]
                ]
            ];

            $row = $storage->readRows(['' => null])[''] ?? null;
            if (null !== $row) {
                if (isset($row['__ERROR'])) {
                    /** @var Debugger $debugger */
                    $debugger = Grav::instance()['debugger'];
                    $message = sprintf('Flex Pages: root page is broken in storage: %s', $row['__ERROR']);

                    $debugger->addException(new \RuntimeException($message));
                    $debugger->addMessage($message, 'error');

                    $row = ['__META' => $root];
                }

            } else {
                $row = ['__META' => $root];
            }

            $row = array_merge_recursive($defaults, $row);

            /** @var PageObject $root */
            $root = $this->getFlexDirectory()->createObject($row, '/', false);
            $root->name('root.md');
            $root->root(true);

            $this->_root = $root;
        }

        return $root;
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
     * Filter pages by given filters.
     *
     * - search: string
     * - page_type: string|string[]
     * - modular: bool
     * - visible: bool
     * - routable: bool
     * - published: bool
     * - page: bool
     * - translated: bool
     *
     * @param array $filters
     * @param bool $recursive
     * @return FlexCollectionInterface
     */
    public function filterBy(array $filters, bool $recursive = false)
    {
        if (!$filters) {
            return $this;
        }

        if ($recursive) {
            return $this->__call('filterBy', [$filters, true]);
        }

        $list = [];
        $index = $this;
        foreach ($filters as $key => $value) {
            switch ($key) {
                case 'search':
                    $index = $index->search((string)$value);
                    break;
                case 'page_type':
                    if (!is_array($value)) {
                        $value = is_string($value) && $value !== '' ? explode(',', $value) : [];
                    }
                    $index = $index->ofOneOfTheseTypes($value);
                    break;
                case 'routable':
                    $index = $index->withRoutable((bool)$value);
                    break;
                case 'published':
                    $index = $index->withPublished((bool)$value);
                    break;
                case 'visible':
                    $index = $index->withVisible((bool)$value);
                    break;
                case 'module':
                    $index = $index->withModules((bool)$value);
                    break;
                case 'page':
                    $index = $index->withPages((bool)$value);
                    break;
                case 'folder':
                    $index = $index->withPages(!$value);
                    break;
                case 'translated':
                    $index = $index->withTranslation((bool)$value);
                    break;
                default:
                    $list[$key] = $value;
            }
        }

        return $list ? $index->filterByParent($list) : $index;
    }

    /**
     * @param array $filters
     * @return FlexCollectionInterface
     */
    protected function filterByParent(array $filters)
    {
        return parent::filterBy($filters);
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
     * @param array $options
     * @return array
     */
    protected function getLevelListingRecurse(array $options): array
    {
        $filters = $options['filters'] ?? [];
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
            // Get forced filters from the field.
            $blueprint = $page ? $page->getBlueprint() : $this->getFlexDirectory()->getBlueprint();
            $settings = $blueprint->schema()->getProperty($field);
            $filters = array_merge([], $filters, $settings['filters'] ?? []);
        }

        // Clean up filter.
        $filter_type = (array)($filters['type'] ?? []);
        unset($filters['type']);
        $filters = array_filter($filters, static function($val) { return $val !== null && $val !== ''; });

        if ($page) {
            if ($page->root() && (!$filter_type || in_array('root', $filter_type, true))) {
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
                        'item-key' => '-root-',
                        'icon' => 'root',
                        'title' => 'Root', // FIXME
                        'route' => [
                            'display' => '&lt;root&gt;', // FIXME
                            'raw' => '_root',
                        ],
                        'modified' => $page->modified(),
                        'extras' => [
                            'template' => $page->template(),
                            //'lang' => null,
                            //'translated' => null,
                            'langs' => [],
                            'published' => false,
                            'visible' => false,
                            'routable' => false,
                            'tags' => ['root', 'non-routable'],
                            'actions' => ['edit'], // FIXME
                        ]
                    ];
                }
            }

            $status = 'success';
            $msg = 'PLUGIN_ADMIN.PAGE_ROUTE_FOUND';

            /** @var PageIndex $children */
            $children = $page->children()->getIndex();
            $selectedChildren = $children->filterBy($filters, true);

            /** @var PageObject $child */
            foreach ($selectedChildren as $child) {
                $selected = $child->path() === $extra;
                $includeChildren = \is_array($leaf) && !empty($leaf) && $selected;
                if ($field) {
                    $payload = [
                        'name' => $child->title(),
                        'value' => $child->rawRoute(),
                        'item-key' => basename($child->rawRoute() ?? ''),
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
                    } elseif ($child->isModule()) {
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
                    /** @var PageObject $child */
                    $child = $child->getTranslation($language) ?? $child;
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
                    $tmp = $child->children()->getIndex();
                    $child_count = $tmp->count();
                    $count = $filters ? $tmp->filterBy($filters, true)->count() : null;
                    $payload = [
                        'item-key' => basename($child->rawRoute() ?? $child->getKey()),
                        'icon' => $icon,
                        'title' => $child->title(),
                        'route' => [
                            'display' => $child->getRoute()->toString(false) ?: '/',
                            'raw' => $child->rawRoute(),
                        ],
                        'modified' => $this->jsDate($child->modified()),
                        'child_count' => $child_count ?: null,
                        'count' => $count ?? null,
                        'filters_hit' => $filters ? ($child->filterBy($filters, false) ?: null) : null,
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
     * @param FlexStorageInterface $storage
     * @return CompiledJsonFile|\Grav\Common\File\CompiledYamlFile|null
     */
    protected static function getIndexFile(FlexStorageInterface $storage)
    {
        if (!method_exists($storage, 'isIndexed') || !$storage->isIndexed()) {
            return null;
        }

        // Load saved index file.
        $grav = Grav::instance();
        $locator = $grav['locator'];

        $filename = $locator->findResource('user-data://flex/indexes/pages.json', true, true);

        return CompiledJsonFile::instance($filename);
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
}
