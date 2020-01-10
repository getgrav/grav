<?php

/**
 * @package    Grav\Framework\Acl
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Acl;

use Grav\Common\Utils;

class Access implements \JsonSerializable, \IteratorAggregate, \Countable
{
    /** @var array */
    private $rules;
    /** @var array */
    private $ops;
    /** @var array */
    private $acl = [];

    /**
     * Access constructor.
     * @param string|array|null $acl
     * @param array|null $rules
     */
    public function __construct($acl = null, array $rules = null)
    {
        $this->rules = $rules ?? [];
        $this->ops = ['+' => true, '-' => false];
        if (is_string($acl)) {
            $this->acl = $this->resolvePermissions($acl);
        } elseif (is_array($acl)) {
            $this->acl = $this->normalizeAcl($acl);
        }
    }

    /**
     * Checks user authorization to the action.
     *
     * @param  string $action
     * @param  string|null $scope
     * @return bool|null
     */
    public function authorize(string $action, string $scope = null): ?bool
    {
        if (null !== $scope) {
            $action = $scope !== 'test' ? "{$scope}.{$action}" : $action;
        }

        return $this->get($action);
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return Utils::arrayUnflattenDotNotation($this->acl);
    }

    /**
     * @return array
     */
    public function getAllActions(): array
    {
        return array_filter($this->acl, static function($val) { return $val !== null; });
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param string $action
     * @return bool|null
     */
    public function get(string $action)
    {
        // Get access value.
        if (isset($this->acl[$action])) {
            return $this->acl[$action];
        }

        // If no value is defined, check the parent access (all true|false).
        $pos = strrpos($action, '.');
        $value = $pos ? $this->get(substr($action, 0, $pos)) : null;

        // Cache result for faster lookup.
        $this->acl[$action] = $value;

        return $value;
    }

    /**
     * @return \Traversable
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->acl);
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->acl);
    }

    /**
     * @param array $acl
     * @return array
     */
    protected function normalizeAcl(array $acl): array
    {
        if (empty($acl)) {
            return [];
        }

        // Normalize access control list.
        $list = [];
        foreach (Utils::arrayFlattenDotNotation($acl) as $key => $value) {
            if (is_bool($value)) {
                $list[$key] = $value;
            } elseif ($value === 0 || $value === 1) {
                $list[$key] = (bool)$value;
            } elseif($value === null) {
                continue;
            } elseif ($this->rules && is_string($value)) {
                $list[$key] = $this->resolvePermissions($value);
            } elseif (Utils::isPositive($value)) {
                $list[$key] = true;
            } elseif (Utils::isNegative($value)) {
                $list[$key] = false;
            }
        }

        return $list;
    }

    /**
     * @param string $access
     * @return array
     */
    protected function resolvePermissions(string $access): array
    {
        $len = strlen($access);
        $op = true;
        $list = [];
        for($count = 0; $count < $len; $count++) {
            $letter = $access[$count];
            if (isset($this->rules[$letter])) {
                $list[$this->rules[$letter]] = $op;
                $op = true;
            } elseif (isset($this->ops[$letter])) {
                $op = $this->ops[$letter];
            }
        }

        return $list;
    }
}
