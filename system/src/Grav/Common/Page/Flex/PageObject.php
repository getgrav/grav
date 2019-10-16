<?php

declare(strict_types=1);

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Flex;

use Grav\Common\Data\Blueprint;
use Grav\Common\Grav;
use Grav\Common\Page\Flex\Traits\PageContentTrait;
use Grav\Common\Page\Flex\Traits\PageLegacyTrait;
use Grav\Common\Page\Flex\Traits\PageRoutableTrait;
use Grav\Common\Page\Flex\Traits\PageTranslateTrait;
use Grav\Common\Page\Pages;
use Grav\Common\Utils;
use Grav\Framework\Flex\FlexObject;
use Grav\Framework\Flex\Pages\FlexPageObject;
use Grav\Framework\Route\Route;
use Grav\Framework\Route\RouteFactory;
use Grav\Plugin\Admin\Admin;
use RocketTheme\Toolbox\Event\Event;

/**
 * Class GravPageObject
 * @package Grav\Plugin\FlexObjects\Types\GravPages
 *
 * @property string $name
 * @property string $route
 * @property string $folder
 * @property int|false $order
 * @property string $template
 * @property string $language
 */
class PageObject extends FlexPageObject
{
    use PageContentTrait;
    use PageLegacyTrait;
    use PageRoutableTrait;
    use PageTranslateTrait;

    /** @var string Language code, eg: 'en' */
    protected $language;

    /** @var string File format, eg. 'md' */
    protected $format;

    private $_initialized = false;

    /**
     * @return array
     */
    public static function getCachedMethods(): array
    {
        return [
            'path' => true,
            'full_order' => true
        ] + parent::getCachedMethods();
    }

    public function initialize(): void
    {
        if (!$this->_initialized) {
            Grav::instance()->fireEvent('onPageProcessed', new Event(['page' => $this]));
            $this->_initialized = true;
        }
    }

    /**
     * @param string|array $query
     * @return Route
     */
    public function getRoute($query = []): Route
    {
        $route = RouteFactory::createFromString($this->route());
        if (\is_array($query)) {
            foreach ($query as $key => $value) {
                $route = $route->withQueryParam($key, $value);
            }
        } else {
            $route = $route->withAddedPath($query);
        }

        return $route;
    }

    /**
     * @inheritdoc PageInterface
     */
    public function getFormValue(string $name, $default = null, string $separator = null)
    {
        $test = new \stdClass();

        $value = $this->pageContentValue($name, $test);
        if ($value !== $test) {
            return $value;
        }

        switch ($name) {
            case 'name':
                // TODO: this should not be template!
                return $this->getProperty('template');
            case 'route':
                $key = dirname($this->hasKey() ? '/' . $this->getKey() : '/');
                return $key !== '/' ? $key : null;
            case 'full_route':
                return $this->hasKey() ? '/' . $this->getKey() : '';
            case 'full_order':
                return $this->full_order();
            case 'lang':
                return $this->getLanguage() ?? '';
            case 'translations':
                return $this->getLanguages();
        }

        return parent::getFormValue($name, $default, $separator);
    }

    /**
     * @param array|bool $reorder
     * @return FlexObject|\Grav\Framework\Flex\Interfaces\FlexObjectInterface
     */
    public function save($reorder = true)
    {
        // Reorder siblings.
        if ($reorder === true) {
            $reorder = $this->_reorder ?: false;
        }
        $siblings = is_array($reorder) ? $this->reorderSiblings($reorder) : [];

        /** @var static $instance */
        $instance = parent::save();

        foreach ($siblings as $sibling) {
            $sibling->save(false);
        }

        return $instance;
    }

    /**
     * @param array $ordering
     * @return PageCollection
     */
    protected function reorderSiblings(array $ordering)
    {
        $storageKey = $this->getStorageKey();
        $oldParentKey = ltrim(dirname("/$storageKey"), '/');
        $newParentKey = $this->getProperty('parent_key');

        $slug = basename($this->getKey());
        $order = $oldParentKey === $newParentKey ? $this->order() : false;
        $k = $slug !== '' ? array_search($slug, $ordering, true) : false;
        if ($order === false) {
            if ($k !== false) {
                unset($ordering[$k]);
            }
        } elseif ($k === false) {
            $ordering[999999] = $slug;
        }
        $ordering = array_values($ordering);

        $parent = $this->parent();

        /** @var PageCollection $siblings */
        $siblings = $parent ? $parent->children() : null;
        $siblings = $siblings ? $siblings->withVisible()->getCollection() : null;
        if ($siblings) {
            $ordering = array_flip($ordering);
            if ($storageKey !== null) {
                $siblings->remove($storageKey);
                if (isset($ordering[$slug])) {
                    $siblings->set($storageKey, $this);
                }
            }
            $count = count($ordering);
            foreach ($siblings as $sibling) {
                $newOrder = $ordering[basename($sibling->getKey())] ?? null;
                $oldOrder = $sibling->order();
                $sibling->order(null !== $newOrder ? $newOrder + 1 : $oldOrder + $count);
            }
            $siblings = $siblings->orderBy(['order' => 'ASC']);
            $siblings->removeElement($this);
        } else {
            $siblings = $this->getFlexDirectory()->createCollection([]);
        }

        return $siblings;
    }

    public function full_order(): string
    {
        $path = $this->path();

        return preg_replace(PageIndex::ORDER_LIST_REGEX, '\\1', $path . '/' . $this->folder());
    }

    /**
     * @param string $name
     * @return Blueprint
     */
    protected function doGetBlueprint(string $name = ''): Blueprint
    {
        try {
            // Make sure that pages has been initialized.
            Pages::getTypes();

            if ($name === 'raw') {
                // Admin RAW mode.
                /** @var Admin $admin */
                $admin = Grav::instance()['admin'] ?? null;
                if ($admin) {
                    $template = $this->modular() ? 'modular_raw' : 'raw';

                    return $admin->blueprints("admin/pages/{$template}");
                }
            }

            $template = $this->getProperty('template') . ($name ? '.' . $name : '');

            $blueprint = $this->getFlexDirectory()->getBlueprint($template, 'blueprints://pages');
        } catch (\RuntimeException $e) {
            $template = 'default' . ($name ? '.' . $name : '');

            $blueprint = $this->getFlexDirectory()->getBlueprint($template, 'blueprints://pages');
        }

        return $blueprint;
    }

    public function getLevelListing(array $options): array
    {
        $default_filters = [
            'type' => ['root', 'dir'],
            'name' => null,
            'extension' => null,
        ];

        $filters = ($options['filters'] ?? []) + $default_filters;
        $filter_type = (array)$filters['type'];

        $field = $options['field'] ?? null;
        $route = $options['route'] ?? null;
        $leaf_route = $options['leaf_route'] ?? null;
        $sortby = $options['sortby'] ?? null;
        $order = $options['order'] ?? SORT_ASC;
        $language = $options['lang'] ?? null;

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

            [$status,,$leaf,$extra] = $this->getLevelListing($options);
        }

        /** @var PageCollection|PageIndex $collection */
        $collection = $this->getFlexDirectory()->getIndex();

        // Handle no route, assume page tree root
        if (!$route) {
            $page = $collection->getRoot();
        } else {
            $page = $collection->get(trim($route, '/'));
        }
        $path = $page ? $page->path() : null;

        if ($field) {
            $settings = $this->getBlueprint()->schema()->getProperty($field);
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

                    // filter types
                    if ($filter_type && !in_array($payload['type'], $filter_type, true)) {
                        continue;
                    }

                    // Simple filter for name or extension
                    if (($filters['name'] && Utils::contains($payload['basename'], $filters['name']))
                        || ($filters['extension'] && Utils::contains($payload['extension'], $filters['extension']))) {
                        continue;
                    }
                } else {
                    if ($child->home()) {
                        $icon = 'home';
                    } elseif ($child->modular()) {
                        $icon = 'modular';
                    } elseif ($child->visible()) {
                        $icon = 'visible';
                    } else {
                        $icon = 'page';
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
                if (\is_array($leaf) && !empty($leaf) && $child->path() === $extra) {
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

    private function jsDate(int $timestamp = null)
    {
        if (!$timestamp) {
            return null;
        }

        $config = Grav::instance()['config'];
        $dateFormat = $config->get('system.pages.dateformat.long');

        return date($dateFormat, $timestamp);
    }

    public function __debugInfo(): array
    {
        $list = parent::__debugInfo();

        return $list + [
            '_content_meta:private' => $this->getContentMeta(),
            '_content:private' => $this->getRawContent()
        ];
    }

    /**
     * @param array $elements
     * @param bool $extended
     */
    protected function filterElements(array &$elements, bool $extended = false): void
    {
        // Deal with ordering=bool and order=page1,page2,page3.
        if (array_key_exists('ordering', $elements) && array_key_exists('order', $elements)) {
            $ordering = (bool)($elements['ordering'] ?? false);
            $slug = preg_replace(PAGE_ORDER_PREFIX_REGEX, '', $this->getProperty('folder'));
            $list = !empty($elements['order']) ? explode(',', $elements['order']) : [];
            if ($ordering) {
                $order = array_search($slug, $list, true);
                if ($order !== false) {
                    $order++;
                } else {
                    $order = $this->getProperty('order') ?: 1;
                }
            } else {
                $order = false;
            }

            $this->_reorder = $list;
            $elements['order'] = $order;
        }

        // Change storage location if needed.
        if (array_key_exists('route', $elements) && isset($elements['folder'], $elements['name'])) {
            $elements['template'] = $elements['name'];
            $parentRoute = $elements['route'] ?? '';

            // Figure out storage path to the new route.
            $parentKey = trim($parentRoute, '/');
            if ($parentKey !== '') {
                // Make sure page isn't being moved under itself.
                $key = $this->getKey();
                if ($key === $parentKey || strpos($parentKey, $key . '/') === 0) {
                    throw new \RuntimeException(sprintf('Page %s cannot be moved to %s', '/' . $key, $parentRoute));
                }

                /** @var PageObject $parent */
                $parent = $this->getFlexDirectory()->getObject($parentKey);
                if (!$parent) {
                    // Page cannot be moved to non-existing location.
                    throw new \RuntimeException(sprintf('Page %s cannot be moved to non-existing path %s', '/' . $key, $parentRoute));
                }

                // If parent changes and page is visible, move it to be the last item.
                if ($parent && !empty($elements['order']) && $parent !== $this->parent()) {
                    $siblings = $parent->children();
                    $siblings = $siblings ? $siblings->visible()->sort(['order' => 'ASC']) : null;
                    if ($siblings && $siblings->count()) {
                        $elements['order'] = ((int)$siblings->last()->order()) + 1;
                    } else {
                        $elements['order'] = 1;
                    }
                }

                $parentKey = $parent->getStorageKey();
            }

            $elements['parent_key'] = $parentKey;
        }
        parent::filterElements($elements, true);
    }

    /**
     * @return array
     */
    public function prepareStorage(): array
    {
        $meta = $this->getMetaData();
        $oldLang = $meta['lang'] ?? '';
        $newLang = $this->getProperty('lang');

        // Always clone the page to the new language.
        if ($oldLang !== $newLang) {
            $meta['clone'] = true;
        }

        // Make sure that certain elements are always sent to the storage layer.
        $elements = [
            '__META' => $meta,
            'storage_key' => $this->getStorageKey(),
            'parent_key' => $this->getProperty('parent_key'),
            'order' => $this->getProperty('order'),
            'folder' => preg_replace('|^\d+\.|', '', $this->getProperty('folder')),
            'template' => preg_replace('|modular/|', '', $this->getProperty('template')),
            'lang' => $newLang
        ] + parent::prepareStorage();

        return $elements;
    }
}
