<?php
/**
 * @package    Grav\Framework\Collection
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Collection;

use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\ClosureExpressionVisitor;
use Grav\Common\Grav;
use RocketTheme\Toolbox\ResourceLocator\RecursiveUniformResourceIterator;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Collection of objects stored into a filesystem.
 *
 * @package Grav\Framework\Collection
 */
class FileCollection extends AbstractLazyCollection
{
    const INCLUDE_FILES = 1;
    const INCLUDE_FOLDERS = 2;
    const RECURSIVE = 4;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var \RecursiveDirectoryIterator|RecursiveUniformResourceIterator
     */
    protected $iterator;

    /**
     * @var callable
     */
    protected $createObjectFunction;

    /**
     * @var callable
     */
    protected $filterFunction;

    /**
     * @var int
     */
    protected $flags;

    /**
     * @var int
     */
    protected $nestingLimit;

    /**
     * @param string $path
     * @param int    $flags
     */
    public function __construct($path, $flags = null)
    {
        $this->path = $path;
        $this->flags = (int) ($flags ?: FileCollection::INCLUDE_FILES | FileCollection::INCLUDE_FOLDERS | FileCollection::RECURSIVE);

        $this->setIterator();
        $this->setFilter();
        $this->setObjectBuilder();
        $this->setNestingLimit();
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
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

    public function setIterator()
    {
        $iteratorFlags = \RecursiveDirectoryIterator::SKIP_DOTS + \FilesystemIterator::UNIX_PATHS
            + \FilesystemIterator::CURRENT_AS_SELF + \FilesystemIterator::FOLLOW_SYMLINKS;

        if (strpos($this->path, '://')) {
            /** @var UniformResourceLocator $locator */
            $locator = Grav::instance()['locator'];
            $this->iterator = $locator->getRecursiveIterator($this->path, $iteratorFlags);
        } else {
            $this->iterator = new \RecursiveDirectoryIterator($this->path, $iteratorFlags);
        }
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
        if ($this->filterFunction) {
            $oldFilterFunction = $this->filterFunction;
            $this->filterFunction = function ($expr) use ($oldFilterFunction, $filterFunction) {
                return $oldFilterFunction($expr) && $filterFunction($expr);
            };
        } else {
            $this->filterFunction = $filterFunction;
        }

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

    public function setNestingLimit($limit = 99)
    {
        $this->nestingLimit = (int) $limit;

        return $this;
    }

    /**
     * @param Criteria $criteria
     * @return ArrayCollection
     * @todo Implement lazy matching
     */
    public function matching(Criteria $criteria)
    {
        $expr = $criteria->getWhereExpression();

        $oldFilter = $this->filterFunction;
        if ($expr) {
            $visitor = new ClosureExpressionVisitor();
            $filter = $visitor->dispatch($expr);
            $this->addFilter($filter);
        }

        $filtered = $this->doInitializeByIterator($this->iterator, $this->nestingLimit);
        $this->filterFunction = $oldFilter;

        if ($orderings = $criteria->getOrderings()) {
            $next = null;
            foreach (array_reverse($orderings) as $field => $ordering) {
                $next = ClosureExpressionVisitor::sortByField($field, $ordering == Criteria::DESC ? -1 : 1, $next);
            }

            uasort($filtered, $next);
        } else {
            ksort($filtered);
        }

        $offset = $criteria->getFirstResult();
        $length = $criteria->getMaxResults();

        if ($offset || $length) {
            $filtered = array_slice($filtered, (int)$offset, $length);
        }

        return new ArrayCollection($filtered);
    }

    /**
     * {@inheritDoc}
     */
    protected function doInitialize()
    {
        $filtered = $this->doInitializeByIterator($this->iterator, $this->nestingLimit);
        ksort($filtered);

        $this->collection = new ArrayCollection($filtered);
    }

    protected function doInitializeByIterator(\SeekableIterator $iterator, $nestingLimit)
    {
        $children = [];
        $objects = [];
        $filter = $this->filterFunction;
        $objectFunction = $this->createObjectFunction;

        /** @var \RecursiveDirectoryIterator $file */
        foreach ($iterator as $file) {
            // Skip files if they shouldn't be included.
            if (!($this->flags & static::INCLUDE_FILES) && $file->isFile()) {
                continue;
            }

            // Apply main filter.
            if ($filter && !$filter($file)) {
                continue;
            }

            // Include children if the recursive flag is set.
            if (($this->flags & static::RECURSIVE) && $nestingLimit > 0 && $file->hasChildren()) {
                $children[] = $file->getChildren();
            }

            // Skip folders if they shouldn't be included.
            if (!($this->flags & static::INCLUDE_FOLDERS) && $file->isDir()) {
                continue;
            }

            $object = $objectFunction($file);
            $objects[$object->key] = $object;
        }

        if ($children) {
            $objects += $this->doInitializeChildren($children, $nestingLimit - 1);
        }

        return $objects;
    }

    /**
     * @param \RecursiveDirectoryIterator[] $children
     * @return array
     */
    protected function doInitializeChildren(array $children, $nestingLimit)
    {
        $objects = [];

        foreach ($children as $iterator) {
            $objects += $this->doInitializeByIterator($iterator, $nestingLimit);
        }

        return $objects;
    }

    /**
     * @param \RecursiveDirectoryIterator $file
     * @return object
     */
    protected function createObject($file)
    {
        return (object) [
            'key' => $file->getSubPathName(),
            'type' => $file->isDir() ? 'folder' : 'file:' . $file->getExtension(),
            'url' => method_exists($file, 'getUrl') ? $file->getUrl() : null,
            'pathname' => $file->getPathname(),
            'mtime' => $file->getMTime()
        ];
    }
}
