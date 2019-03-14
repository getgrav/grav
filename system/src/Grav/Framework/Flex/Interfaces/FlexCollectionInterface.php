<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Interfaces;

use Grav\Framework\Object\Interfaces\NestedObjectInterface;
use Grav\Framework\Object\Interfaces\ObjectCollectionInterface;
use Grav\Framework\Flex\FlexDirectory;

/**
 * Interface FlexCollectionInterface
 * @package Grav\Framework\Flex\Interfaces
 */
interface FlexCollectionInterface extends ObjectCollectionInterface, NestedObjectInterface
{

    /**
     * @param array $entries
     * @param FlexDirectory $directory
     * @param string $keyField
     * @return static
     */
    public static function createFromArray(array $entries, FlexDirectory $directory, string $keyField = null) : FlexCollectionInterface;

    /**
     * @param array $elements
     * @param FlexDirectory $type
     * @throws \InvalidArgumentException
     */
    public function __construct(array $elements, FlexDirectory $type);

    /**
     * @param string $search
     * @param string|string[]|null $properties
     * @param array|null $options
     * @return FlexCollectionInterface
     */
    public function search(string $search, $properties = null, array $options = null); // : FlexCollection

    /**
     * @return FlexDirectory
     */
    public function getFlexDirectory(); //: FlexDirectory;

    /**
     * @param string|null $keyField
     * @return FlexCollectionInterface
     */
    public function withKeyField(string $keyField = null): FlexCollectionInterface;

    /**
     * @return FlexIndexInterface
     */
    public function getIndex(): FlexIndexInterface;
}
