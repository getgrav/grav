<?php

/**
 * @package    Grav\Common\User
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\User;

use Grav\Common\Utils;

class Access implements \JsonSerializable, \IteratorAggregate, \Countable
{
    /** @var array */
    private $acl;

    public function __construct(array $acl = null)
    {
        $list = [];

        if ($acl) {
            // Normalize access control list.
            foreach (Utils::arrayFlattenDotNotation($acl) as $key => $value) {
                if (is_bool($value)) {
                    $list[$key] = $value;
                } elseif($value === null) {
                    continue;
                } elseif (Utils::isPositive($value)) {
                    $list[$key] = true;
                } elseif (Utils::isNegative($value)) {
                    $list[$key] = false;
                }
            }
        }

        $this->acl = $list;
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
            $action = "{$scope}.{$action}";
        }

        return $this->acl[$action] ?? null;
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return Utils::arrayUnflattenDotNotation($this->acl);
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
}
