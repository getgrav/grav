<?php

/**
 * @package    Grav\Framework\Object
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Object;

use Doctrine\Common\Collections\Criteria;
use Grav\Framework\Collection\ArrayCollection;
use Grav\Framework\Object\Access\NestedPropertyCollectionTrait;
use Grav\Framework\Object\Base\ObjectCollectionTrait;
use Grav\Framework\Object\Collection\ObjectExpressionVisitor;
use Grav\Framework\Object\Interfaces\NestedObjectCollectionInterface;
use InvalidArgumentException;
use function array_slice;

/**
 * Class contains a collection of objects.
 *
 * @template TKey of array-key
 * @template T of \Grav\Framework\Object\Interfaces\ObjectInterface
 * @extends ArrayCollection<TKey,T>
 * @implements NestedObjectCollectionInterface<TKey,T>
 */
class ObjectCollection extends ArrayCollection implements NestedObjectCollectionInterface
{
    /** @phpstan-use ObjectCollectionTrait<TKey,T> */
    use ObjectCollectionTrait;
    use NestedPropertyCollectionTrait {
        NestedPropertyCollectionTrait::group insteadof ObjectCollectionTrait;
    }

    /**
     * @param array $elements
     * @param string|null $key
     * @throws InvalidArgumentException
     */
    public function __construct(array $elements = [], $key = null)
    {
        parent::__construct($this->setElements($elements));

        $this->setKey($key ?? '');
    }

    /**
     * @param array $ordering
     * @return static
     * @phpstan-return static<TKey,T>
     */
    public function orderBy(array $ordering)
    {
        $criteria = Criteria::create()->orderBy($ordering);

        return $this->matching($criteria);
    }

    /**
     * @param int $start
     * @param int|null $limit
     * @return static
     * @phpstan-return static<TKey,T>
     */
    public function limit($start, $limit = null)
    {
        /** @phpstan-var static<TKey,T> */
        return $this->createFrom($this->slice($start, $limit));
    }

    /**
     * @param Criteria $criteria
     * @return static
     * @phpstan-return static<TKey,T>
     */
    public function matching(Criteria $criteria)
    {
        $expr     = $criteria->getWhereExpression();
        $filtered = $this->getElements();

        if ($expr) {
            $visitor  = new ObjectExpressionVisitor();
            $filter   = $visitor->dispatch($expr);
            $filtered = array_filter($filtered, $filter);
        }

        if ($orderings = $criteria->getOrderings()) {
            $next = null;
            foreach (array_reverse($orderings) as $field => $ordering) {
                $next = ObjectExpressionVisitor::sortByField($field, $ordering === Criteria::DESC ? -1 : 1, $next);
            }

            /** @phpstan-ignore-next-line */
            if ($next) {
                uasort($filtered, $next);
            }
        }

        $offset = $criteria->getFirstResult();
        $length = $criteria->getMaxResults();

        if ($offset || $length) {
            $filtered = array_slice($filtered, (int)$offset, $length);
        }

        /** @phpstan-var static<TKey,T> */
        return $this->createFrom($filtered);
    }

    /**
     * @return array
     * @phpstan-return array<TKey,T>
     */
    protected function getElements()
    {
        return $this->toArray();
    }

    /**
     * @param array $elements
     * @return array
     * @phpstan-return array<TKey,T>
     */
    protected function setElements(array $elements)
    {
        /** @phpstan-var array<TKey,T> $elements */
        return $elements;
    }
}
