<?php
/**
 * @package    Grav\Framework\Collection
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Collection;

/**
 * Collection of objects stored into a filesystem.
 *
 * @package Grav\Framework\Collection
 */
class FileCollection extends AbstractFileCollection
{
    /**
     * @param string $path
     * @param int    $flags
     */
    public function __construct($path, $flags = null)
    {
        parent::__construct($path);

        $this->flags = (int)($flags ?: self::INCLUDE_FILES | self::INCLUDE_FOLDERS | self::RECURSIVE);

        $this->setIterator();
        $this->setFilter();
        $this->setObjectBuilder();
        $this->setNestingLimit();
    }

    /**
     * @return int
     */
    public function getFlags()
    {
        return $this->flags;
    }

    /**
     * @return int
     */
    public function getNestingLimit()
    {
        return $this->nestingLimit;
    }

    /**
     * @param int $limit
     * @return $this
     */
    public function setNestingLimit($limit = 99)
    {
        $this->nestingLimit = (int) $limit;

        return $this;
    }

    /**
     * @param callable|null $filterFunction
     * @return $this
     */
    public function setFilter(callable $filterFunction = null)
    {
        $this->filterFunction = $filterFunction;

        return $this;
    }

    /**
     * @param callable $filterFunction
     * @return $this
     */
    public function addFilter(callable $filterFunction)
    {
        parent::addFilter($filterFunction);

        return $this;
    }

    /**
     * @param callable|null $objectFunction
     * @return $this
     */
    public function setObjectBuilder(callable $objectFunction = null)
    {
        $this->createObjectFunction = $objectFunction ?: [$this, 'createObject'];

        return $this;
    }
}
