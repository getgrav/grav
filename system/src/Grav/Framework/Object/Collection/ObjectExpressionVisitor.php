<?php

/**
 * @package    Grav\Framework\Object
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Object\Collection;

use Closure;
use Doctrine\Common\Collections\Expr\ClosureExpressionVisitor;
use Doctrine\Common\Collections\Expr\Comparison;
use RuntimeException;
use function in_array;
use function is_array;
use function is_callable;
use function is_string;
use function strlen;

/**
 * Class ObjectExpressionVisitor
 * @package Grav\Framework\Object\Collection
 */
class ObjectExpressionVisitor extends ClosureExpressionVisitor
{
    /**
     * Accesses the field of a given object.
     *
     * @param object $object
     * @param string $field
     * @return mixed
     */
    public static function getObjectFieldValue($object, $field)
    {
        $op = $value = null;

        $pos = strpos($field, '(');
        if (false !== $pos) {
            [$op, $field] = explode('(', $field, 2);
            $field = rtrim($field, ')');
        }

        if (isset($object[$field])) {
            $value = $object[$field];
        } else {
            $accessors = array('', 'get', 'is');

            foreach ($accessors as $accessor) {
                $accessor .= $field;

                if (!is_callable([$object, $accessor])) {
                    continue;
                }

                $value = $object->{$accessor}();
                break;
            }
        }

        if ($op) {
            $function = 'filter' . ucfirst(strtolower($op));
            if (method_exists(static::class, $function)) {
                $value = static::$function($value);
            }
        }

        return $value;
    }

    /**
     * @param string $str
     * @return string
     */
    public static function filterLower($str)
    {
        return mb_strtolower($str);
    }

    /**
     * @param string $str
     * @return string
     */
    public static function filterUpper($str)
    {
        return mb_strtoupper($str);
    }

    /**
     * @param string $str
     * @return int
     */
    public static function filterLength($str)
    {
        return mb_strlen($str);
    }

    /**
     * @param string $str
     * @return string
     */
    public static function filterLtrim($str)
    {
        return ltrim($str);
    }

    /**
     * @param string $str
     * @return string
     */
    public static function filterRtrim($str)
    {
        return rtrim($str);
    }

    /**
     * @param string $str
     * @return string
     */
    public static function filterTrim($str)
    {
        return trim($str);
    }

    /**
     * Helper for sorting arrays of objects based on multiple fields + orientations.
     *
     * Comparison between two strings is natural and case insensitive.
     *
     * @param string   $name
     * @param int      $orientation
     * @param Closure|null $next
     *
     * @return Closure
     */
    public static function sortByField($name, $orientation = 1, Closure $next = null)
    {
        if (!$next) {
            $next = function ($a, $b) {
                return 0;
            };
        }

        return function ($a, $b) use ($name, $next, $orientation) {
            $aValue = static::getObjectFieldValue($a, $name);
            $bValue = static::getObjectFieldValue($b, $name);

            if ($aValue === $bValue) {
                return $next($a, $b);
            }

            // For strings we use natural case insensitive sorting.
            if (is_string($aValue) && is_string($bValue)) {
                return strnatcasecmp($aValue, $bValue) * $orientation;
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
                    return in_array(static::getObjectFieldValue($object, $field), $value, true);
                };

            case Comparison::NIN:
                return function ($object) use ($field, $value) {
                    return !in_array(static::getObjectFieldValue($object, $field), $value, true);
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
                    return in_array($value, $fieldValues, true);
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
                throw new RuntimeException("Unknown comparison operator: " . $comparison->getOperator());
        }
    }
}
