<?php declare(strict_types=1);

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media\Interfaces;

/**
 *
 */
interface MediaFactoryInterface
{
    /**
     * @return string[]
     */
    public function getCollectionTypes(): array;

    /**
     * @param array $settings
     * @return MediaCollectionInterface|null
     */
    public function createCollection(array $settings): ?MediaCollectionInterface;
}
