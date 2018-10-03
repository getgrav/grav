<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
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
     * @param array $elements
     * @param FlexDirectory $type
     * @throws \InvalidArgumentException
     */
    public function __construct(array $elements, FlexDirectory $type);

    /**
     * @return FlexDirectory
     */
    public function getFlexDirectory(); //: FlexDirectory;
}
