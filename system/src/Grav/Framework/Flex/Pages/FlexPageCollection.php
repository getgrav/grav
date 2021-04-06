<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Pages;

use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Framework\Flex\FlexCollection;
use function array_search;
use function assert;
use function is_int;

/**
 * Class FlexPageCollection
 * @package Grav\Plugin\FlexObjects\Types\FlexPages
 * @template T of \Grav\Framework\Flex\Interfaces\FlexObjectInterface
 * @extends FlexCollection<T>
 */
class FlexPageCollection extends FlexCollection
{
    /**
     * @return array
     */
    public static function getCachedMethods(): array
    {
        return [
            // Collection filtering
            'withPublished' => true,
            'withVisible' => true,
            'withRoutable' => true,

            'isFirst' => true,
            'isLast' => true,

            // Find objects
            'prevSibling' => false,
            'nextSibling' => false,
            'adjacentSibling' => false,
            'currentPosition' => true,

            'getNextOrder' => false,
        ] + parent::getCachedMethods();
    }

    /**
     * @param bool $bool
     * @return static
     * @phpstan-return static<T>
     */
    public function withPublished(bool $bool = true)
    {
        $list = array_keys(array_filter($this->call('isPublished', [$bool])));

        return $this->select($list);
    }

    /**
     * @param bool $bool
     * @return static
     * @phpstan-return static<T>
     */
    public function withVisible(bool $bool = true)
    {
        $list = array_keys(array_filter($this->call('isVisible', [$bool])));

        return $this->select($list);
    }

    /**
     * @param bool $bool
     * @return static
     * @phpstan-return static<T>
     */
    public function withRoutable(bool $bool = true)
    {
        $list = array_keys(array_filter($this->call('isRoutable', [$bool])));

        return $this->select($list);
    }

    /**
     * Check to see if this item is the first in the collection.
     *
     * @param  string $path
     * @return bool True if item is first.
     */
    public function isFirst($path): bool
    {
        $keys = $this->getKeys();
        $first = reset($keys);

        return $path === $first;
    }

    /**
     * Check to see if this item is the last in the collection.
     *
     * @param  string $path
     * @return bool True if item is last.
     */
    public function isLast($path): bool
    {
        $keys = $this->getKeys();
        $last = end($keys);

        return $path === $last;
    }

    /**
     * Gets the previous sibling based on current position.
     *
     * @param  string $path
     * @return PageInterface|false  The previous item.
     * @phpstan-return T|false
     */
    public function prevSibling($path)
    {
        return $this->adjacentSibling($path, -1);
    }

    /**
     * Gets the next sibling based on current position.
     *
     * @param  string $path
     * @return PageInterface|false The next item.
     * @phpstan-return T|false
     */
    public function nextSibling($path)
    {
        return $this->adjacentSibling($path, 1);
    }

    /**
     * Returns the adjacent sibling based on a direction.
     *
     * @param  string  $path
     * @param  int $direction either -1 or +1
     * @return PageInterface|false    The sibling item.
     * @phpstan-return T|false
     */
    public function adjacentSibling($path, $direction = 1)
    {
        $keys = $this->getKeys();
        $pos = array_search($path, $keys, true);

        if ($pos !== false) {
            $pos += $direction;
            if (isset($keys[$pos])) {
                return $this[$keys[$pos]];
            }
        }

        return false;
    }

    /**
     * Returns the item in the current position.
     *
     * @param  string $path the path the item
     * @return int|null The index of the current page, null if not found.
     */
    public function currentPosition($path): ?int
    {
        $pos = array_search($path, $this->getKeys(), true);

        return $pos !== false ? $pos : null;
    }

    /**
     * @return string
     */
    public function getNextOrder()
    {
        $directory = $this->getFlexDirectory();

        $collection = $directory->getIndex();
        $keys = $collection->getStorageKeys();

        // Assign next free order.
        /** @var FlexPageObject|null $last */
        $last = null;
        $order = 0;
        foreach ($keys as $folder => $key) {
            preg_match(FlexPageIndex::ORDER_PREFIX_REGEX, $folder, $test);
            $test = $test[0] ?? null;
            if ($test && $test > $order) {
                $order = $test;
                $last = $key;
            }
        }

        $last = $collection[$last];

        return sprintf('%d.', $last ? $last->value('order') + 1 : 1);
    }
}
