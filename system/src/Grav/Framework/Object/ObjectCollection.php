<?php

/**
 * @package    Grav\Framework\Object
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Object;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Grav\Framework\Collection\ArrayCollection;
use Grav\Framework\Object\Access\NestedPropertyCollectionTrait;
use Grav\Framework\Object\Base\ObjectCollectionTrait;
use Grav\Framework\Object\Collection\ObjectExpressionVisitor;
use Grav\Framework\Object\Interfaces\NestedObjectInterface;
use Grav\Framework\Object\Interfaces\ObjectCollectionInterface;

/**
 * Class contains a collection of objects.
 */
class ObjectCollection extends ArrayCollection implements ObjectCollectionInterface, NestedObjectInterface
{
    use ObjectCollectionTrait;
    use NestedPropertyCollectionTrait {
        NestedPropertyCollectionTrait::group insteadof ObjectCollectionTrait;
    }

    /**
     * @param array $elements
     * @param string $key
     * @throws \InvalidArgumentException
     */
    public function __construct(array $elements = [], $key = null)
    {
        parent::__construct($this->setElements($elements));

        $this->setKey($key ?? '');
    }

    /**
     * @param array $ordering
     * @return Collection|static
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
     */
    public function limit($start, $limit = null)
    {
        return $this->createFrom($this->slice($start, $limit));
    }

    /**
     * {@inheritDoc}
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
            /**
             * @var string $field
             * @var string $ordering
             */
            foreach (array_reverse($orderings) as $field => $ordering) {
                $next = ObjectExpressionVisitor::sortByField($field, $ordering === Criteria::DESC ? -1 : 1, $next);
            }

            if ($next) {
                uasort($filtered, $next);
            }
        }

        $offset = $criteria->getFirstResult();
        $length = $criteria->getMaxResults();

        if ($offset || $length) {
            $filtered = \array_slice($filtered, (int)$offset, $length);
        }

        return $this->createFrom($filtered);
    }

    protected function getElements()
    {
        return $this->toArray();
    }

    protected function setElements(array $elements)
    {
        return $elements;
    }
}
