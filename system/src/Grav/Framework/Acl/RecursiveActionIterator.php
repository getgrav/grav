<?php

/**
 * @package    Grav\Framework\Acl
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Acl;

use RecursiveIterator;
use RocketTheme\Toolbox\ArrayTraits\Constructor;
use RocketTheme\Toolbox\ArrayTraits\Countable;
use RocketTheme\Toolbox\ArrayTraits\Iterator;

/**
 * Class Action
 * @package Grav\Framework\Acl
 * @implements RecursiveIterator<string,Action>
 */
class RecursiveActionIterator implements RecursiveIterator, \Countable
{
    use Constructor, Iterator, Countable;

    /**
     * @see \Iterator::key()
     * @return string
     */
    #[\ReturnTypeWillChange]
    public function key()
    {
        /** @var Action $current */
        $current = $this->current();

        return $current->name;
    }

    /**
     * @see \RecursiveIterator::hasChildren()
     * @return bool
     */
    public function hasChildren(): bool
    {
        /** @var Action $current */
        $current = $this->current();

        return $current->hasChildren();
    }

    /**
     * @see \RecursiveIterator::getChildren()
     * @return RecursiveActionIterator
     */
    public function getChildren(): self
    {
        /** @var Action $current */
        $current = $this->current();

        return new static($current->getChildren());
    }
}
