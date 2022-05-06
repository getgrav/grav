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
     * @phpstan-pure
     */
    public function getCollectionTypes(): array;

    /**
     * @param array $settings
     * @return MediaCollectionInterface|null
     * @phpstan-impure
     */
    public function createCollection(array $settings): ?MediaCollectionInterface;

    /**
     * @param string $uri
     * @param string|null $type
     * @return string
     */
    public function readFile(string $uri, string $type = null): string;

    /**
     * @param string $uri
     * @param string|null $type
     * @return resource
     */
    public function readStream(string $uri, string $type = null);
}
