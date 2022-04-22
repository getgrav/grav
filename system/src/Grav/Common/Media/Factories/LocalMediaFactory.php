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
use Grav\Common\Utils;
use RuntimeException;

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

    /**
     * @param string $type
     * @param string $path
     * @return string
     */
    public function readFile(string $type, string $path): string
    {
        $filepath = GRAV_WEBROOT . '/' . $path;
        if (!is_file($filepath)) {
            throw new RuntimeException(sprintf("Reading media file failed: File '%s' not found", $path));
        }

        error_clear_last();
        $contents = @file_get_contents($filepath);
        if (false === $contents) {
            throw new RuntimeException('Reading media file failed: ' . (error_get_last()['message'] ?? sprintf('Cannot read %s', Utils::basename($filepath))));
        }

        return $contents;
    }

    /**
     * @param string $type
     * @param string $path
     * @return resource
     */
    public function readStream(string $type, string $path)
    {
        $filepath = GRAV_WEBROOT . '/' . $path;
        if (!is_file($filepath)) {
            throw new RuntimeException(sprintf("Reading media file failed: File '%s' not found", $path));
        }

        error_clear_last();
        $contents = @fopen($filepath, 'rb');
        if (false === $contents) {
            throw new RuntimeException('Reading media file failed: ' . (error_get_last()['message'] ?? sprintf('Cannot open %s', Utils::basename($filepath))));
        }

        return $contents;
    }
}
