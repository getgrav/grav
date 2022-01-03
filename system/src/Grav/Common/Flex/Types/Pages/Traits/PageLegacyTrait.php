<?php

declare(strict_types=1);

/**
 * @package    Grav\Common\Flex
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Flex\Types\Pages\Traits;

use Grav\Common\Grav;
use Grav\Common\Page\Collection;
use Grav\Common\Page\Interfaces\PageCollectionInterface;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Pages;
use Grav\Common\Utils;
use Grav\Framework\Flex\Interfaces\FlexIndexInterface;
use InvalidArgumentException;
use function is_array;
use function is_string;

/**
 * Implements PageLegacyInterface.
 */
trait PageLegacyTrait
{
    /**
     * Returns children of this page.
     *
     * @return FlexIndexInterface|PageCollectionInterface|Collection
     */
    public function children()
    {
        if (Utils::isAdminPlugin()) {
            return parent::children();
        }

        /** @var Pages $pages */
        $pages = Grav::instance()['pages'];
        $path = $this->path() ?? '';

        return $pages->children($path);
    }

    /**
     * Check to see if this item is the first in an array of sub-pages.
     *
     * @return bool True if item is first.
     */
    public function isFirst(): bool
    {
        if (Utils::isAdminPlugin()) {
            return parent::isFirst();
        }

        $path = $this->path();
        $parent = $this->parent();
        $collection = $parent ? $parent->collection('content', false) : null;
        if (null !== $path && $collection instanceof PageCollectionInterface) {
            return $collection->isFirst($path);
        }

        return true;
    }

    /**
     * Check to see if this item is the last in an array of sub-pages.
     *
     * @return bool True if item is last
     */
    public function isLast(): bool
    {
        if (Utils::isAdminPlugin()) {
            return parent::isLast();
        }

        $path = $this->path();
        $parent = $this->parent();
        $collection = $parent ? $parent->collection('content', false) : null;
        if (null !== $path && $collection instanceof PageCollectionInterface) {
            return $collection->isLast($path);
        }

        return true;
    }

    /**
     * Returns the adjacent sibling based on a direction.
     *
     * @param  int $direction either -1 or +1
     * @return PageInterface|false             the sibling page
     */
    public function adjacentSibling($direction = 1)
    {
        if (Utils::isAdminPlugin()) {
            return parent::adjacentSibling($direction);
        }

        $path = $this->path();
        $parent = $this->parent();
        $collection = $parent ? $parent->collection('content', false) : null;
        if (null !== $path && $collection instanceof PageCollectionInterface) {
            $child = $collection->adjacentSibling($path, $direction);
            if ($child instanceof PageInterface) {
                return $child;
            }
        }

        return false;
    }

    /**
     * Helper method to return an ancestor page.
     *
     * @param string|null $lookup Name of the parent folder
     * @return PageInterface|null page you were looking for if it exists
     */
    public function ancestor($lookup = null)
    {
        if (Utils::isAdminPlugin()) {
            return parent::ancestor($lookup);
        }

        /** @var Pages $pages */
        $pages = Grav::instance()['pages'];

        return $pages->ancestor($this->getProperty('parent_route'), $lookup);
    }

    /**
     * Method that contains shared logic for inherited() and inheritedField()
     *
     * @param string $field Name of the parent folder
     * @return array
     */
    protected function getInheritedParams($field): array
    {
        if (Utils::isAdminPlugin()) {
            return parent::getInheritedParams($field);
        }

        /** @var Pages $pages */
        $pages = Grav::instance()['pages'];

        $inherited = $pages->inherited($this->getProperty('parent_route'), $field);
        $inheritedParams = $inherited ? (array)$inherited->value('header.' . $field) : [];
        $currentParams = (array)$this->getFormValue('header.' . $field);
        if ($inheritedParams && is_array($inheritedParams)) {
            $currentParams = array_replace_recursive($inheritedParams, $currentParams);
        }

        return [$inherited, $currentParams];
    }

    /**
     * Helper method to return a page.
     *
     * @param string $url the url of the page
     * @param bool $all
     * @return PageInterface|null page you were looking for if it exists
     */
    public function find($url, $all = false)
    {
        if (Utils::isAdminPlugin()) {
            return parent::find($url, $all);
        }

        /** @var Pages $pages */
        $pages = Grav::instance()['pages'];

        return $pages->find($url, $all);
    }

    /**
     * Get a collection of pages in the current context.
     *
     * @param string|array $params
     * @param bool $pagination
     * @return PageCollectionInterface|Collection
     * @throws InvalidArgumentException
     */
    public function collection($params = 'content', $pagination = true)
    {
        if (Utils::isAdminPlugin()) {
            return parent::collection($params, $pagination);
        }

        if (is_string($params)) {
            // Look into a page header field.
            $params = (array)$this->getFormValue('header.' . $params);
        } elseif (!is_array($params)) {
            throw new InvalidArgumentException('Argument should be either header variable name or array of parameters');
        }

        $context = [
            'pagination' => $pagination,
            'self' => $this
        ];

        /** @var Pages $pages */
        $pages = Grav::instance()['pages'];

        return $pages->getCollection($params, $context);
    }

    /**
     * @param string|array $value
     * @param bool $only_published
     * @return PageCollectionInterface|Collection
     */
    public function evaluate($value, $only_published = true)
    {
        if (Utils::isAdminPlugin()) {
            return parent::collection($value, $only_published);
        }

        $params = [
            'items' => $value,
            'published' => $only_published
        ];
        $context = [
            'event' => false,
            'pagination' => false,
            'url_taxonomy_filters' => false,
            'self' => $this
        ];

        /** @var Pages $pages */
        $pages = Grav::instance()['pages'];

        return $pages->getCollection($params, $context);
    }
}
