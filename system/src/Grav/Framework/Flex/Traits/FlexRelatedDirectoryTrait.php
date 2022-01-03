<?php

declare(strict_types=1);

/**
 * @package    Grav\Common\Flex
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Traits;

use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Flex\Interfaces\FlexCollectionInterface;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use RuntimeException;
use function in_array;

/**
 * Trait GravTrait
 * @package Grav\Common\Flex\Traits
 */
trait FlexRelatedDirectoryTrait
{
    /**
     * @param string $type
     * @param string $property
     * @return FlexCollectionInterface<FlexObjectInterface>
     */
    protected function getCollectionByProperty($type, $property)
    {
        $directory = $this->getRelatedDirectory($type);
        $collection = $directory->getCollection();
        $list = $this->getNestedProperty($property) ?: [];

        /** @var FlexCollectionInterface<FlexObjectInterface> $collection */
        $collection = $collection->filter(static function ($object) use ($list) {
            return in_array($object->getKey(), $list, true);
        });

        return $collection;
    }

    /**
     * @param string $type
     * @return FlexDirectory
     * @throws RuntimeException
     */
    protected function getRelatedDirectory($type): FlexDirectory
    {
        $directory = $this->getFlexContainer()->getDirectory($type);
        if (!$directory) {
            throw new RuntimeException(ucfirst($type). ' directory does not exist!');
        }

        return $directory;
    }
}
