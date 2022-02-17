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
     * @return bool
     */
    public function exists(): bool
    {
        $path = $this->getPath();

        return null !== $path && is_dir($path);
    }

    /**
     * Create Medium from a file.
     *
     * @param  string $filename
     * @param  array  $params
     * @return Medium|null
     */
    public function createFromFile($filename, array $params = []): ?MediaObjectInterface
    {
        $info = $this->index[$filename] ?? null;
        if (null === $info) {
            // Find out if the file is in this media folder or fall back to MediumFactory.
            $relativePath = Folder::getRelativePath($filename, $this->getPath());
            $info = $this->index[$relativePath] ?? null;
            if (null === $info && file_exists($filename)) {
                return MediumFactory::fromFile($filename);
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
     * @param MediaObjectInterface $mediaObject
     * @return ImageFile
     */
    public function getImageFileObject(MediaObjectInterface $mediaObject): ImageFile
    {
        $filepath = $mediaObject->get('filepath');

        return ImageFile::open($filepath);
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
            $exif = $exifReader->read($filepath);
            if ($exif) {
                $info['exif'] = array_diff_key($exif->getData(), array_flip($this->standard_exif));
            }
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

    /**
     * Get index file, which stores media file index.
     *
     * @return JsonFile|null
     */
    protected function getIndexFile(): ?JsonFile
    {
        $indexFolder = $this->getPath();
        $indexFile = 'media.json';
        if (null === $indexFolder || null === $indexFile) {
            return null;
        }

        return new JsonFile($indexFolder . '/' . $indexFile, new JsonFormatter(['encode_options' => JSON_PRETTY_PRINT]));
    }

    /**
     * @return array
     */
    protected function loadIndex(): array
    {
        // Read media index file.
        $indexFile = $this->getIndexFile();

        $index = $indexFile && $indexFile->exists() ? $indexFile->load() : [];
        $version = $index['version'] ?? null;
        $type = $index['type'] ?? null;
        $folder = $index['folder'] ?? null;
        if ($version !== static::VERSION || $folder !== $this->path || $type !== ($this->config['type'] ?? 'local')) {
            $index = [];
        }

        return [$index['files'] ?? [], $index['timestamp'] ?? 0];
    }

    /**
     * @param array $files
     * @param int|null $timestamp
     * @return void
     */
    protected function saveIndex(array $files, ?int $timestamp = null): void
    {
        $index = $this->getIndexFile();
        if (!$index || !$this->exists()) {
            return;
        }

        $index->save(
            [
                'timestamp' => $timestamp ?? time(),
                'type' => $this->config['type'] ?? 'local',
                'version' => static::VERSION,
                'folder' => $this->path,
                'files' => $files,
            ]
        );
    }
}
