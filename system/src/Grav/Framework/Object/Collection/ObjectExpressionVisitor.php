<?php
/**
 * @package    Grav\Framework\Object
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Object\Collection;

use Doctrine\Common\Collections\Expr\ClosureExpressionVisitor;
use Doctrine\Common\Collections\Expr\Comparison;

class ObjectExpressionVisitor extends ClosureExpressionVisitor
{
    /**
     * Accesses the field of a given object.
     *
     * @param object $object
     * @param string $field
     *
     * @return mixed
     */
    public static function getObjectFieldValue($object, $field)
    {
        if (isset($object[$field])) {
            return $object[$field];
        }

        $accessors = array('', 'get', 'is');

        foreach ($accessors as $accessor) {
            $accessor .= $field;

            if (!method_exists($object, $accessor)) {
                continue;
            }

            return $object->{$accessor}();
        }

        return null;
    }

    /**
     * Helper for sorting arrays of objects based on multiple fields + orientations.
     *
     * @param string   $name
     * @param int      $orientation
     * @param \Closure $next
     *
     * @return \Closure
     */
    public static function sortByField($name, $orientation = 1, \Closure $next = null)
    {
        if (!$next) {
            $next = function() {
                return 0;
            };
        }

        return function ($a, $b) use ($name, $next, $orientation) {
            $aValue = static::getObjectFieldValue($a, $name);
            $bValue = static::getObjectFieldValue($b, $name);

            if ($aValue === $bValue) {
                return $next($a, $b);
            }

            return (($aValue > $bValue) ? 1 : -1) * $orientation;
        };
    }

    /**
     * {@inheritDoc}
     */
    public function walkComparison(Comparison $comparison)
    {
        $field = $comparison->getField();
        $value = $comparison->getValue()->getValue(); // shortcut for walkValue()

        switch ($comparison->getOperator()) {
            case Comparison::EQ:
                return function ($object) use ($field, $value) {
                    return static::getObjectFieldValue($object, $field) === $value;
                };

            case Comparison::NEQ:
                return function ($object) use ($field, $value) {
                    return static::getObjectFieldValue($object, $field) !== $value;
                };

            case Comparison::LT:
                return function ($object) use ($field, $value) {
                    return static::getObjectFieldValue($object, $field) < $value;
                };

            case Comparison::LTE:
                return function ($object) use ($field, $value) {
                    return static::getObjectFieldValue($object, $field) <= $value;
                };

            case Comparison::GT:
                return function ($object) use ($field, $value) {
                    return static::getObjectFieldValue($object, $field) > $value;
                };

            case Comparison::GTE:
                return function ($object) use ($field, $value) {
                    return static::getObjectFieldValue($object, $field) >= $value;
                };

            case Comparison::IN:
                return function ($object) use ($field, $value) {
                    return \in_array(static::getObjectFieldValue($object, $field), $value, true);
                };

            case Comparison::NIN:
                return function ($object) use ($field, $value) {
                    return !\in_array(static::getObjectFieldValue($object, $field), $value, true);
                };

            case Comparison::CONTAINS:
                return function ($object) use ($field, $value) {
                    return false !== strpos(static::getObjectFieldValue($object, $field), $value);
                };

            case Comparison::MEMBER_OF:
                return function ($object) use ($field, $value) {
                    $fieldValues = static::getObjectFieldValue($object, $field);
                    if (!is_array($fieldValues)) {
                        $fieldValues = iterator_to_array($fieldValues);
                    }
                    return \in_array($value, $fieldValues, true);
                };

            case Comparison::STARTS_WITH:
                return function ($object) use ($field, $value) {
                    return 0 === strpos(static::getObjectFieldValue($object, $field), $value);
                };

            case Comparison::ENDS_WITH:
                return function ($object) use ($field, $value) {
                    return $value === substr(static::getObjectFieldValue($object, $field), -strlen($value));
                };


            default:
                throw new \RuntimeException("Unknown comparison operator: " . $comparison->getOperator());
        }
    }
}
