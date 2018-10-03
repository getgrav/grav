<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Interfaces;

use Grav\Common\Data\Blueprint;
use Grav\Framework\Object\Interfaces\NestedObjectInterface;
use Grav\Framework\Flex\FlexDirectory;

/**
 * Interface FlexObjectInterface
 * @package Grav\Framework\Flex\Interfaces
 */
interface FlexObjectInterface extends NestedObjectInterface, \ArrayAccess
{
    /**
     * @param array $elements
     * @param string $key
     * @param FlexDirectory $type
     * @throws \InvalidArgumentException
     */
    public function __construct(array $elements, $key, FlexDirectory $type);

    /**
     * @return FlexDirectory
     */
    public function getFlexDirectory() : FlexDirectory;

    /**
     * @return int
     */
    public function getTimestamp() : int;

    /**
     * @return Blueprint
     */
    public function getBlueprint();
}
