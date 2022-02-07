<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Interfaces;

use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Interfaces\RenderInterface;

/**
 * Defines common interface shared with both Flex Objects and Collections.
 *
 * @used-by \Grav\Framework\Flex\FlexObject
 * @since 1.6
 */
interface FlexCommonInterface extends RenderInterface
{
    /**
     * Get Flex Type of the object / collection.
     *
     * @return string Returns Flex Type of the collection.
     * @api
     */
    public function getFlexType(): string;

    /**
     * Get Flex Directory for the object / collection.
     *
     * @return FlexDirectory    Returns associated Flex Directory.
     * @api
     */
    public function getFlexDirectory(): FlexDirectory;

    /**
     * Test whether the feature is implemented in the object / collection.
     *
     * @param string $name
     * @return bool
     */
    public function hasFlexFeature(string $name): bool;

    /**
     * Get full list of features the object / collection implements.
     *
     * @return array
     */
    public function getFlexFeatures(): array;

    /**
     * Get last updated timestamp for the object / collection.
     *
     * @return int Returns Unix timestamp.
     * @api
     */
    public function getTimestamp(): int;

    /**
     * Get a cache key which is used for caching the object / collection.
     *
     * @return string Returns cache key.
     */
    public function getCacheKey(): string;

    /**
     * Get cache checksum for the object / collection.
     *
     * If checksum changes, cache gets invalided.
     *
     * @return string Returns cache checksum.
     */
    public function getCacheChecksum(): string;
}
