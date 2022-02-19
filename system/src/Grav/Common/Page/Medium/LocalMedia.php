<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Medium;

use FilesystemIterator;
use Grav\Common\Data\Blueprint;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Media\Interfaces\MediaObjectInterface;
use Grav\Framework\File\Formatter\JsonFormatter;
use Grav\Framework\File\JsonFile;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RuntimeException;
use function is_array;

/**
 * Class AbstractMedia
 * @package Grav\Common\Page\Medium
 */
abstract class LocalMedia extends AbstractMedia
{
    /**
     * Return media path.
     *
     * @param string|null $filename
     * @return string|null
     */
    public function getPath(string $filename = null): ?string
    {
        return $this->path ? GRAV_WEBROOT . '/' . $this->path . ($filename ? '/' . $filename : '') : null;
    }

    /**
     * @param string|null $path
     * @return void
     */
    public function setPath(?string $path): void
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->getGrav()['locator'];

        // Make path relative from GRAV_WEBROOT.
        if ($locator->isStream($path)) {
            $path = $locator->findResource($path, false) ?: null;
        } else {
            $path = Folder::getRelativePath($path, GRAV_WEBROOT) ?: null;
        }

        $this->path = $path;
    }

    /**
     * Create Medium from a file.
     *
     * @param  string $filename
     * @param  array  $params
     * @return Medium|null
     */
    public function createFromFile(string $filename, array $params = []): ?MediaObjectInterface
    {
        $info = $this->index[$filename] ?? null;
        if (null === $info) {
            /** @var UniformResourceLocator $locator */
            $locator = $this->getGrav()['locator'];
            if ($locator->isStream($filename)) {
                $filename = (string)$locator->getResource($filename);
                if (!$filename) {
                    return null;
                }
            }

            // Find out if the file is in this media folder or fall back to MediumFactory.
            $relativePath = Folder::getRelativePath($filename, $this->getPath());
            $info = $this->index[$relativePath] ?? null;
            if (null === $info && file_exists($filename)) {
                return MediumFactory::fromFile($filename, $params);
            }

            $filename = $relativePath;
        }

        $this->addMediaDefaults($filename, $info);
        if (!is_array($info)) {
            return null;
        }

        $params += $info;

        return $this->createFromArray($params);
    }

    /**
     * Create Medium from array of parameters
     *
     * @param  array          $items
     * @param  Blueprint|null $blueprint
     * @return Medium|null
     */
    public function createFromArray(array $items = [], Blueprint $blueprint = null): ?MediaObjectInterface
    {
        return MediumFactory::fromArray($items, $blueprint);
    }

    /**
     * Create a new ImageMedium by scaling another ImageMedium object.
     *
     * @param  MediaObjectInterface $medium
     * @param  int $from
     * @param  int $to
     * @return MediaObjectInterface
     */
    public function scaledFromMedium(MediaObjectInterface $medium, int $from, int $to = 1): MediaObjectInterface
    {
        $result = MediumFactory::scaledFromMedium($medium, $from, $to);

        return is_array($result) ? $result['file'] : $result;
    }

    /**
     * @param string $filepath
     * @return string
     * @throws RuntimeException
     */
    public function readFile(string $filepath): string
    {
        error_clear_last();
        $contents = @file_get_contents($filepath);
        if (false === $contents) {
            throw new RuntimeException('Reading media file failed: ' . (error_get_last()['message'] ?? sprintf('Cannot read %s', $filepath)));
        }

        return $contents;
    }

    /**
     * @param string $filepath
     * @return resource
     * @throws RuntimeException
     */
    public function readStream(string $filepath)
    {
        error_clear_last();
        $contents = @fopen($filepath, 'rb');
        if (false === $contents) {
            throw new RuntimeException('Reading media file failed: ' . (error_get_last()['message'] ?? sprintf('Cannot open %s', $filepath)));
        }

        return $contents;
    }

    /**
     * @param string $filename
     * @param string $destination
     * @return bool
     */
    protected function fileExists(string $filename, string $destination): bool
    {
        return is_file("{$destination}/{$filename}");
    }

    /**
     * @param string $filepath
     * @return array
     */
    protected function readImageSize(string $filepath): array
    {
        error_clear_last();
        $info = @getimagesize($filepath);
        if (false === $info) {
            throw new RuntimeException(error_get_last()['message'] ?? 'Unable to get image size');
        }

        $info = [
            'width' => $info[0],
            'height' => $info[1],
            'mime' => $info['mime']
        ];

        // TODO: This is going to be slow without any indexing!
        /*
        // Add missing jpeg exif data.
        $exifReader = $this->getExifReader();
        if (null !== $exifReader && !isset($info['exif']) && $info['mime'] === 'image/jpeg') {
        try {
            $exif = $exifReader->read($filepath);
            $info['exif'] = array_diff_key($exif->getData(), array_flip($this->standard_exif));
        } catch (\RuntimeException $e) {
        }
        */

        return $info;
    }

    /**
     * Load file listing from the filesystem.
     *
     * @return array
     */
    protected function loadFileInfo(): array
    {
        $media = [];
        $files = new FilesystemIterator($this->path, FilesystemIterator::UNIX_PATHS | FilesystemIterator::SKIP_DOTS);
        foreach ($files as $item) {
            if (!$item->isFile()) {
                continue;
            }

            // Include extra information.
            $info = [
                'modified' => $item->getMTime(),
                'size' => $item->getSize()
            ];

            $media[$item->getFilename()] = $info;
        }

        return $media;
    }
}
