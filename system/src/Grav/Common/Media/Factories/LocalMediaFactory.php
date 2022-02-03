<?php declare(strict_types=1);

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media\Factories;

use Grav\Common\Media\Interfaces\MediaCollectionInterface;
use Grav\Common\Media\Interfaces\MediaFactoryInterface;
use Grav\Common\Page\Media;

/**
 *
 */
class LocalMediaFactory implements MediaFactoryInterface
{
    /**
     * @return string[]
     */
    public function getCollectionTypes(): array
    {
        return ['local'];
    }

    /**
     * @param array $settings
     * @return MediaCollectionInterface|null
     */
    public function createCollection(array $settings): ?MediaCollectionInterface
    {
        $path = (string)($settings['path'] ?? '');
        $order = (array)($settings['order'] ?? null);
        $load = (bool)($settings['load'] ?? true);

        return new Media($path, $order, $load);
    }
}
