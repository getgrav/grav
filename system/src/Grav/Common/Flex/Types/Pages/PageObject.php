<?php

declare(strict_types=1);

/**
 * @package    Grav\Common\Flex
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
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
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Pages;
use Grav\Common\User\Interfaces\UserInterface;
use Grav\Common\Utils;
use Grav\Framework\Filesystem\Filesystem;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use Grav\Framework\Flex\Pages\FlexPageObject;
use Grav\Framework\Object\ObjectCollection;
use Grav\Framework\Route\Route;
use Grav\Framework\Route\RouteFactory;
use Grav\Plugin\Admin\Admin;
use RocketTheme\Toolbox\Event\Event;
use RuntimeException;
use stdClass;
use function array_key_exists;
use function count;
use function func_get_args;
use function in_array;
use function is_array;

/**
 * Class GravPageObject
 * @package Grav\Plugin\FlexObjects\Types\GravPages
 *
 * @property string $name
 * @property string $slug
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
            'translated' => false,
        ] + parent::getCachedMethods();
    }

    /**
     * @return void
     */
    public function initialize(): void
    {
        if (!$this->_initialized) {
            Grav::instance()->fireEvent('onPageProcessed', new Event(['page' => $this]));
            $this->_initialized = true;
        }
    }

    /**
     * @param string|array $query
     * @return Route|null
     */
    public function getRoute($query = []): ?Route
    {
        $path = $this->route();
        if (null === $path) {
            return null;
        }

        $route = RouteFactory::createFromString($path);
        if ($lang = $route->getLanguage()) {
            $grav = Grav::instance();
            if (!$grav['config']->get('system.languages.include_default_lang')) {
                /** @var Language $language */
                $language = $grav['language'];
                if ($lang === $language->getDefault()) {
                    $route = $route->withLanguage('');
                }
            }
        }
        if (is_array($query)) {
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
        $test = new stdClass();

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
     * @param array $variables
     * @return array
     */
    protected function onBeforeSave(array $variables)
    {
        $reorder = $variables[0] ?? true;

        $meta = $this->getMetaData();
        if (($meta['copy'] ?? false) === true) {
            $this->folder = $this->getKey();
        }

        // Figure out storage path to the new route.
        $parentKey = $this->getProperty('parent_key');
        if ($parentKey !== '') {
            $parentRoute = $this->getProperty('route');

            // Root page cannot be moved.
            if ($this->root()) {
                throw new RuntimeException(sprintf('Root page cannot be moved to %s', $parentRoute));
            }

            // Make sure page isn't being moved under itself.
            $key = $this->getStorageKey();

            /** @var PageObject|null $parent */
            $parent = $parentKey !== false ? $this->getFlexDirectory()->getObject($parentKey, 'storage_key') : null;
            if (!$parent) {
                // Page cannot be moved to non-existing location.
                throw new RuntimeException(sprintf('Page /%s cannot be moved to non-existing path %s', $key, $parentRoute));
            }

            // TODO: make sure that the page doesn't exist yet if moved/copied.
        }

        if ($reorder === true && !$this->root()) {
            $reorder = $this->_reorder;
        }

        // Force automatic reorder if item is supposed to be added to the last.
        if (!is_array($reorder) && (int)$this->order() >= 999999) {
            $reorder = [];
        }

        // Reorder siblings.
        $siblings = is_array($reorder) ? ($this->reorderSiblings($reorder) ?? []) : [];

        $data = $this->prepareStorage();
        unset($data['header']);

        foreach ($siblings as $sibling) {
            $data = $sibling->prepareStorage();
            unset($data['header']);
        }

        return ['reorder' => $reorder, 'siblings' => $siblings];
    }

    /**
     * @param array $variables
     * @return array
     */
    protected function onSave(array $variables): array
    {
        /** @var PageCollection $siblings */
        $siblings = $variables['siblings'];
        /** @var PageObject $sibling */
        foreach ($siblings as $sibling) {
            $sibling->save(false);
        }

        return $variables;
    }

    /**
     * @param array $variables
     */
    protected function onAfterSave(array $variables): void
    {
        $this->getFlexDirectory()->reloadIndex();
    }

    /**
     * @param UserInterface|null $user
     */
    public function check(UserInterface $user = null): void
    {
        parent::check($user);

        if ($user && $this->isMoved()) {
            $parentKey = $this->getProperty('parent_key');

            /** @var PageObject|null $parent */
            $parent = $this->getFlexDirectory()->getObject($parentKey, 'storage_key');
            if (!$parent || !$parent->isAuthorized('create', null, $user)) {
                throw new \RuntimeException('Forbidden', 403);
            }
        }
    }

    /**
     * @param array|bool $reorder
     * @return static
     */
    public function save($reorder = true)
    {
        $variables = $this->onBeforeSave(func_get_args());

        // Backwards compatibility with older plugins.
        $fireEvents = $reorder && $this->isAdminSite() && $this->getFlexDirectory()->getConfig('object.compat.events', true);
        $grav = $this->getContainer();
        if ($fireEvents) {
            $self = $this;
            $grav->fireEvent('onAdminSave', new Event(['type' => 'flex', 'directory' => $this->getFlexDirectory(), 'object' => &$self]));
            if ($self !== $this) {
                throw new RuntimeException('Switching Flex Page object during onAdminSave event is not supported! Please update plugin.');
            }
        }

        /** @var static $instance */
        $instance = parent::save();
        $variables = $this->onSave($variables);

        $this->onAfterSave($variables);

        // Backwards compatibility with older plugins.
        if ($fireEvents) {
            $grav->fireEvent('onAdminAfterSave', new Event(['type' => 'flex', 'directory' => $this->getFlexDirectory(), 'object' => $this]));
        }

        // Reset original after save events have all been called.
        $this->_originalObject = null;

        return $instance;
    }

    /**
     * @return static
     */
    public function delete()
    {
        $result = parent::delete();

        // Backwards compatibility with older plugins.
        $fireEvents = $this->isAdminSite() && $this->getFlexDirectory()->getConfig('object.compat.events', true);
        if ($fireEvents) {
            $this->getContainer()->fireEvent('onAdminAfterDelete', new Event(['object' => $this]));
        }

        return $result;
    }

    /**
     * Prepare move page to new location. Moves also everything that's under the current page.
     *
     * You need to call $this->save() in order to perform the move.
     *
     * @param PageInterface $parent New parent page.
     * @return $this
     */
    public function move(PageInterface $parent)
    {
        if (!$parent instanceof FlexObjectInterface) {
            throw new RuntimeException('Failed: Parent is not Flex Object');
        }

        $this->_reorder = [];
        $this->setProperty('parent_key', $parent->getStorageKey());
        $this->storeOriginal();

        return $this;
    }

    /**
     * @param UserInterface $user
     * @param string $action
     * @param string $scope
     * @param bool $isMe
     * @return bool|null
     */
    protected function isAuthorizedOverride(UserInterface $user, string $action, string $scope, bool $isMe): ?bool
    {
        // Special case: creating a new page means checking parent for its permissions.
        if ($action === 'create' && !$this->exists()) {
            $parent = $this->parent();
            if ($parent && method_exists($parent, 'isAuthorized')) {
                return $parent->isAuthorized($action, $scope, $user);
            }

            return false;
        }

        return parent::isAuthorizedOverride($user, $action, $scope, $isMe);
    }

    /**
     * @return bool
     */
    protected function isMoved(): bool
    {
        $storageKey = $this->getMasterKey();
        $filesystem = Filesystem::getInstance(false);
        $oldParentKey = ltrim($filesystem->dirname("/{$storageKey}"), '/');
        $newParentKey = $this->getProperty('parent_key');

        return $this->exists() && $oldParentKey !== $newParentKey;
    }

    /**
     * @param array $ordering
     * @return PageCollection|null
     * @phpstan-return ObjectCollection<string,PageObject>|null
     */
    protected function reorderSiblings(array $ordering)
    {
        $storageKey = $this->getMasterKey();
        $isMoved = $this->isMoved();
        $order = !$isMoved ? $this->order() : false;
        if ($order !== false) {
            $order = (int)$order;
        }

        $parent = $this->parent();
        if (!$parent) {
            throw new RuntimeException('Cannot reorder a page which has no parent');
        }

        /** @var PageCollection $siblings */
        $siblings = $parent->children();
        $siblings = $siblings->getCollection()->withOrdered();

        // Handle special case where ordering isn't given.
        if ($ordering === []) {
            if ($order >= 999999) {
                // Set ordering to point to be the last item, ignoring the object itself.
                $order = 0;
                foreach ($siblings as $sibling) {
                    if ($sibling->getKey() !== $this->getKey()) {
                        $order = max($order, (int)$sibling->order());
                    }
                }
                $this->order($order + 1);
            }

            // Do not change sibling ordering.
            return null;
        }

        $siblings = $siblings->orderBy(['order' => 'ASC']);

        if ($storageKey !== null) {
            if ($order !== false) {
                // Add current page back to the list if it's ordered.
                $siblings->set($storageKey, $this);
            } else {
                // Remove old copy of the current page from the siblings list.
                $siblings->remove($storageKey);
            }
        }

        // Add missing siblings into the end of the list, keeping the previous ordering between them.
        foreach ($siblings as $sibling) {
            $folder = (string)$sibling->getProperty('folder');
            $basename = preg_replace('|^\d+\.|', '', $folder);
            if (!in_array($basename, $ordering, true)) {
                $ordering[] = $basename;
            }
        }

        // Reorder.
        $ordering = array_flip(array_values($ordering));
        $count = count($ordering);
        foreach ($siblings as $sibling) {
            $folder = (string)$sibling->getProperty('folder');
            $basename = preg_replace('|^\d+\.|', '', $folder);
            $newOrder = $ordering[$basename] ?? null;
            $newOrder = null !== $newOrder ? $newOrder + 1 : (int)$sibling->order() + $count;
            $sibling->order($newOrder);
        }

        $siblings = $siblings->orderBy(['order' => 'ASC']);
        $siblings->removeElement($this);

        // If menu item was moved, just make it to be the last in order.
        if ($isMoved && $this->order() !== false) {
            $parentKey = $this->getProperty('parent_key');
            if ($parentKey === '') {
                /** @var PageIndex $index */
                $index = $this->getFlexDirectory()->getIndex();
                $newParent = $index->getRoot();
            } else {
                $newParent = $this->getFlexDirectory()->getObject($parentKey, 'storage_key');
                if (!$newParent instanceof PageInterface) {
                    throw new RuntimeException("New parent page '{$parentKey}' not found.");
                }
            }
            /** @var PageCollection $newSiblings */
            $newSiblings = $newParent->children();
            $newSiblings = $newSiblings->getCollection()->withOrdered();
            $order = 0;
            foreach ($newSiblings as $sibling) {
                $order = max($order, (int)$sibling->order());
            }
            $this->order($order + 1);
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
        } catch (RuntimeException $e) {
            $template = 'default' . ($name ? '.' . $name : '');

            $blueprint = $this->getFlexDirectory()->getBlueprint($template, 'blueprints://pages');
        }

        $isNew = $blueprint->get('initialized', false) === false;
        if ($isNew === true && $name === '') {
            // Support onBlueprintCreated event just like in Pages::blueprints($template)
            $blueprint->set('initialized', true);
            $blueprint->setFilename($template);

            Grav::instance()->fireEvent('onBlueprintCreated', new Event(['blueprint' => $blueprint, 'type' => $template]));
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
        if (!is_callable([$index, 'getLevelListing'])) {
            return [];
        }

        // Deal with relative paths.
        $initial = $options['initial'] ?? null;
        $var = $initial ? 'leaf_route' : 'route';
        $route = $options[$var] ?? '';
        if ($route !== '' && !str_starts_with($route, '/')) {
            $filesystem = Filesystem::getInstance();

            $route = "/{$this->getKey()}/{$route}";
            $route = $filesystem->normalize($route);

            $options[$var] = $route;
        }

        [$status, $message, $response,] = $index->getLevelListing($options);

        return [$status, $message, $response, $options[$var] ?? null];
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
        $language = $filters['language'] ?? null;
        if (null !== $language) {
            /** @var PageObject $test */
            $test = $this->getTranslation($language) ?? $this;
        } else {
            $test = $this;
        }

        foreach ($filters as $key => $value) {
            switch ($key) {
                case 'search':
                    $matches = $test->search((string)$value) > 0.0;
                    break;
                case 'page_type':
                    $types = $value ? explode(',', $value) : [];
                    $matches = in_array($test->template(), $types, true);
                    break;
                case 'extension':
                    $matches = Utils::contains((string)$value, $test->extension());
                    break;
                case 'routable':
                    $matches = $test->isRoutable() === (bool)$value;
                    break;
                case 'published':
                    $matches = $test->isPublished() === (bool)$value;
                    break;
                case 'visible':
                    $matches = $test->isVisible() === (bool)$value;
                    break;
                case 'module':
                    $matches = $test->isModule() === (bool)$value;
                    break;
                case 'page':
                    $matches = $test->isPage() === (bool)$value;
                    break;
                case 'folder':
                    $matches = $test->isPage() === !$value;
                    break;
                case 'translated':
                    $matches = $test->hasTranslation() === (bool)$value;
                    break;
                default:
                    $matches = true;
                    break;
            }

            // If current filter does not match, we still may have match as a parent.
            if ($matches === false) {
                if (!$recursive) {
                    return false;
                }

                /** @var PageIndex $index */
                $index = $this->children()->getIndex();

                return $index->filterBy($filters, true)->count() > 0;
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
        // Change parent page if needed.
        if (array_key_exists('route', $elements) && isset($elements['folder'], $elements['name'])) {
            $elements['template'] = $elements['name'];

            // Figure out storage path to the new route.
            $parentKey = trim($elements['route'] ?? '', '/');
            if ($parentKey !== '') {
                /** @var PageObject|null $parent */
                $parent = $this->getFlexDirectory()->getObject($parentKey);
                $parentKey = $parent ? $parent->getStorageKey() : $parentKey;
            }

            $elements['parent_key'] = $parentKey;
        }

        // Deal with ordering=bool and order=page1,page2,page3.
        if ($this->root()) {
            // Root page doesn't have ordering.
            unset($elements['ordering'], $elements['order']);
        } elseif (array_key_exists('ordering', $elements) && array_key_exists('order', $elements)) {
            // Store ordering.
            $ordering = $elements['order'] ?? null;
            $this->_reorder = !empty($ordering) ? explode(',', $ordering) : [];

            $order = false;
            if ((bool)($elements['ordering'] ?? false)) {
                $order = $this->order();
                if ($order === false) {
                    $order = 999999;
                }
            }

            $elements['order'] = $order;
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
        $newLang = $this->getProperty('lang') ?? '';

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
            'folder' => preg_replace('|^\d+\.|', '', $this->getProperty('folder') ?? ''),
            'template' => preg_replace('|modular/|', '', $this->getProperty('template') ?? ''),
            'lang' => $newLang
        ] + parent::prepareStorage();

        return $elements;
    }
}
