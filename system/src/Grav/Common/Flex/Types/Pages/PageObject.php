<?php

declare(strict_types=1);

/**
 * @package    Grav\Common\Flex
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Flex\Types\Pages;

use Grav\Common\Data\Blueprint;
use Grav\Common\Flex\Traits\FlexGravTrait;
use Grav\Common\Flex\Traits\FlexObjectTrait;
use Grav\Common\Grav;
use Grav\Common\Flex\Types\Pages\Traits\PageContentTrait;
use Grav\Common\Flex\Types\Pages\Traits\PageLegacyTrait;
use Grav\Common\Flex\Types\Pages\Traits\PageRoutableTrait;
use Grav\Common\Flex\Types\Pages\Traits\PageTranslateTrait;
use Grav\Common\Language\Language;
use Grav\Common\Page\Pages;
use Grav\Common\Utils;
use Grav\Framework\Filesystem\Filesystem;
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
    use FlexGravTrait;
    use FlexObjectTrait;
    use PageContentTrait;
    use PageLegacyTrait;
    use PageRoutableTrait;
    use PageTranslateTrait;

    /** @var string Language code, eg: 'en' */
    protected $language;

    /** @var string File format, eg. 'md' */
    protected $format;

    /** @var bool */
    private $_initialized = false;

    /**
     * @return array
     */
    public static function getCachedMethods(): array
    {
        return [
            'path' => true,
            'full_order' => true,
            'filterBy' => true,
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
                $filesystem = Filesystem::getInstance(false);
                $key = $filesystem->dirname($this->hasKey() ? '/' . $this->getKey() : '/');
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
     * {@inheritdoc}
     * @see FlexObjectInterface::getCacheKey()
     */
    public function getCacheKey(): string
    {
        $cacheKey = parent::getCacheKey();
        if ($cacheKey) {
            /** @var Language $language */
            $language = Grav::instance()['language'];
            $cacheKey .= '_' . $language->getActive();
        }

        return $cacheKey;
    }

    /**
     * @param array|bool $reorder
     * @return FlexObject|\Grav\Framework\Flex\Interfaces\FlexObjectInterface
     */
    public function save($reorder = true)
    {
        // Reorder siblings.
        if ($reorder === true && !$this->root()) {
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
        $filesystem = Filesystem::getInstance(false);

        $storageKey = $this->getStorageKey();
        $oldParentKey = ltrim($filesystem->dirname("/$storageKey"), '/');
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

        /** @var PageCollection|null $siblings */
        $siblings = $parent ? $parent->children() : null;
        /** @var PageCollection|null $siblings */
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
            /** @var PageCollection $siblings */
            $siblings = $siblings->orderBy(['order' => 'ASC']);
            $siblings->removeElement($this);
        } else {
            /** @var PageCollection $siblings */
            $siblings = $this->getFlexDirectory()->createCollection([]);
        }

        return $siblings;
    }

    /**
     * @return string
     */
    public function full_order(): string
    {
        $route = $this->path() . '/' . $this->folder();

        return preg_replace(PageIndex::ORDER_LIST_REGEX, '\\1', $route) ?? $route;
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

            // TODO: We need to move raw blueprint logic to Grav itself to remove admin dependency here.
            if ($name === 'raw') {
                // Admin RAW mode.
                if ($this->isAdminSite()) {
                    /** @var Admin $admin */
                    $admin = Grav::instance()['admin'];

                    $template = $this->isModule() ? 'modular_raw' : ($this->root() ? 'root_raw' : 'raw');

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

    /**
     * @param array $options
     * @return array
     */
    public function getLevelListing(array $options): array
    {
        $index = $this->getFlexDirectory()->getIndex();

        return method_exists($index, 'getLevelListing') ? $index->getLevelListing($options) : [];
    }

    /**
     * Filter page (true/false) by given filters.
     *
     * - search: string
     * - extension: string
     * - module: bool
     * - visible: bool
     * - routable: bool
     * - published: bool
     * - page: bool
     * - translated: bool
     *
     * @param array $filters
     * @param bool $recursive
     * @return bool
     */
    public function filterBy(array $filters, bool $recursive = false): bool
    {
        foreach ($filters as $key => $value) {
            switch ($key) {
                case 'search':
                    $matches = $this->search((string)$value) > 0.0;
                    break;
                case 'page_type':
                    $types = $value ? explode(',', $value) : [];
                    $matches = in_array($this->template(), $types, true);
                    break;
                case 'extension':
                    $matches = Utils::contains((string)$value, $this->extension());
                    break;
                case 'routable':
                    $matches = $this->isRoutable() === (bool)$value;
                    break;
                case 'published':
                    $matches = $this->isPublished() === (bool)$value;
                    break;
                case 'visible':
                    $matches = $this->isVisible() === (bool)$value;
                    break;
                case 'module':
                    $matches = $this->isModule() === (bool)$value;
                    break;
                case 'page':
                    $matches = $this->isPage() === (bool)$value;
                    break;
                case 'folder':
                    $matches = $this->isPage() === !$value;
                    break;
                case 'translated':
                    $matches = $this->hasTranslation() === (bool)$value;
                    break;
                default:
                    $matches = true;
                    break;
            }

            // If current filter does not match, we still may have match as a parent.
            if ($matches === false) {
                return $recursive && $this->children()->getIndex()->filterBy($filters, true)->count() > 0;
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     * @see FlexObjectInterface::exists()
     */
    public function exists(): bool
    {
        return $this->root ?: parent::exists();
    }

    /**
     * @return array
     */
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
            $slug = preg_replace(static::PAGE_ORDER_PREFIX_REGEX, '', $this->getProperty('folder'));
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

                /** @var PageObject|null $parent */
                $parent = $this->getFlexDirectory()->getObject($parentKey);
                if (!$parent) {
                    // Page cannot be moved to non-existing location.
                    throw new \RuntimeException(sprintf('Page %s cannot be moved to non-existing path %s', '/' . $key, $parentRoute));
                }

                // If parent changes and page is visible, move it to be the last item.
                if (!empty($elements['order']) && $parent !== $this->parent()) {
                    /** @var PageCollection $children */
                    $children = $parent->children();
                    $siblings = $children->visible()->sort(['order' => 'ASC']);
                    if ($siblings->count()) {
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
