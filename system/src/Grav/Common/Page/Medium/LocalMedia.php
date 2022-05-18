<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Medium;

use Exception;
use FilesystemIterator;
use Grav\Common\Data\Blueprint;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Media\Interfaces\MediaObjectInterface;
use Grav\Common\Security;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use function dirname;
use function is_array;

/**
 * Class AbstractMedia
 * @package Grav\Common\Page\Medium
 */
abstract class LocalMedia extends AbstractMedia
{
    /**
     * @return string
     */
    public function getType(): string
    {
        return 'local';
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'local';
    }

    /**
     * Return media path.
     *
     * @param string|null $filename
     * @return string|null
     */
    public function getPath(string $filename = null): ?string
    {
        if (!$this->path) {
            return null;
        }

        return GRAV_WEBROOT . '/' . $this->path . ($filename ? '/' . $filename : '');
    }

    /**
     * @param string $filename
     * @return string
     */
    public function getUrl(string $filename): string
    {
        return $this->getPath($filename);
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
            $locator = $this->getLocator();
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
     * @return Medium|null
     */
    public function createFromArray(array $items = []): ?MediaObjectInterface
    {
        return MediumFactory::fromArray($items);
    }

    /**
     * @param string $filename
     * @return string
     * @throws RuntimeException
     */
    public function readFile(string $filename, array $info = null): string
    {
        error_clear_last();
        $filepath = $this->getRealPath($filename);
        $contents = @file_get_contents($filepath);
        if (false === $contents) {
            throw new RuntimeException('Reading media file failed: ' . (error_get_last()['message'] ?? sprintf('Cannot read %s', $filename)));
        }

        return $contents;
    }

    /**
     * @param string $filename
     * @return resource
     * @throws RuntimeException
     */
    public function readStream(string $filename, array $info = null)
    {
        error_clear_last();
        $filepath = $this->getRealPath($filename);
        $contents = @fopen($filepath, 'rb');
        if (false === $contents) {
            throw new RuntimeException('Reading media file failed: ' . (error_get_last()['message'] ?? sprintf('Cannot open %s', $filename)));
        }

        return $contents;
    }

    /**
     * @param string $filename
     * @return bool
     */
    protected function fileExists(string $filename): bool
    {
        $filepath = $this->getRealPath($filename);

        return is_file($filepath);
    }

    /**
     * Internal method to get real path to the media file.
     *
     * @param string $filename
     * @param array|null $info
     * @return string|null
     */
    protected function getRealPath(string $filename, array $info = null): ?string
    {
        return $this->getPath($filename);
    }

    /**
     * @param string $filename
     * @return array
     */
    protected function readImageSize(string $filename, array $info = null): array
    {
        error_clear_last();
        $filepath = $this->getRealPath($filename);
        $sizes = @getimagesize($filepath);
        if (false === $sizes) {
            throw new RuntimeException(error_get_last()['message'] ?? 'Unable to get image size');
        }

        $sizes = ['width' => $sizes[0], 'height' => $sizes[1], 'mime' => $sizes['mime']];

        // TODO: This is going to be slow without any indexing!
        /*
        // Add missing jpeg exif data.
        $exifReader = $this->getExifReader();
        if (null !== $exifReader && !isset($sizes['exif']) && $sizes['mime'] === 'image/jpeg') {
        try {
            $exif = $exifReader->read($filepath);
            $sizes['exif'] = array_diff_key($exif->getData(), array_flip($this->standard_exif));
        } catch (\RuntimeException $e) {
        }
        */

        return $sizes;
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
     * Internal logic to move uploaded file to the media folder.
     *
     * @param UploadedFileInterface $uploadedFile
     * @param string $filename
     */
    protected function doMoveUploadedFile(UploadedFileInterface $uploadedFile, string $filename): void
    {
        $filepath = $this->getRealPath($filename);

        Folder::create(dirname($filepath));

        $uploadedFile->moveTo($filepath);
    }

    /**
     * Internal logic to copy file within the same media folder.
     *
     * @param string $src
     * @param string $dst
     */
    protected function doCopy(string $src, string $dst): void
    {
        $src = $this->getRealPath($src);
        $dst = $this->getRealPath($dst);

        Folder::create(dirname($dst));

        copy($src, $dst);
    }

    /**
     * Internal logic to rename file.
     *
     * @param string $from
     * @param string $to
     */
    protected function doRename(string $from, string $to): void
    {
        $fromPath = $this->getRealPath($from);
        if (!is_file($fromPath)) {
            return;
        }

        $toPath = $this->getRealPath($to);

        if (is_file($toPath)) {
            // TODO: translate error message
            throw new RuntimeException(sprintf('%s already exists', $to), 500);
        }

        $result = rename($fromPath, $toPath);
        if (!$result) {
            // TODO: translate error message
            throw new RuntimeException(sprintf('%s -> %s', $from, $to), 500);
        }

        // TODO: Add missing logic to handle retina files (move out of here!).
        /*
        if (is_file($fromPath . '.meta.yaml')) {
            $result = rename($fromPath . '.meta.yaml', $toPath . '.meta.yaml');
            if (!$result) {
                // TODO: translate error message
                throw new RuntimeException(sprintf('Meta %s -> %s', $from, $to), 500);
            }
        }
        */
    }

    /**
     * Internal logic to remove a file.
     *
     * @param string $filename
     * @return void
     */
    protected function doRemove(string $filename): void
    {
        $filepath = $this->getRealPath($filename);
        if ($this->fileExists($filepath)) {
            $result = unlink($filepath);
            if (!$result) {
                throw new RuntimeException($filename, 500);
            }
        }

        // TODO: move this out of here!
        /*
        // Remove associated metadata.
        $this->doRemoveMetadata($filename, $path);

        // Remove associated 2x, 3x and their .meta.yaml files.
        $dir = scandir($targetPath, SCANDIR_SORT_NONE);
        if (false === $dir) {
            throw new RuntimeException($this->translate('PLUGIN_ADMIN.FILE_COULD_NOT_BE_DELETED') . ': ' . $filename, 500);
        }

        $basename = $filesystem->basename($filename);
        $fileParts = (array)$filesystem->pathinfo($filename);

        foreach ($dir as $file) {
            $preg_name = preg_quote($fileParts['filename'], '`');
            $preg_ext = preg_quote($fileParts['extension'] ?? '.', '`');
            $preg_filename = preg_quote($basename, '`');

            if (preg_match("`({$preg_name}@\d+x\.{$preg_ext}(?:\.meta\.yaml)?$|{$preg_filename}\.meta\.yaml)$`", $file)) {
                $testPath = $targetPath . '/' . $file;
                if ($locator->isStream($testPath)) {
                    $testPath = (string)$locator->findResource($testPath, true, true);
                    $locator->clearCache($testPath);
                }

                if (is_file($testPath)) {
                    $result = unlink($testPath);
                    if (!$result) {
                        throw new RuntimeException($this->translate('PLUGIN_ADMIN.FILE_COULD_NOT_BE_DELETED') . ': ' . $filename, 500);
                    }
                }
            }
        }
        */
    }

    /**
     * @param string $filename
     * @return void
     * @throws Exception
     */
    protected function doSanitizeSvg(string $filename): void
    {
        $filepath = $this->getRealPath($filename);

        Security::sanitizeSVG($filepath);
    }

    /**
     * @param string|null $path
     * @return void
     */
    protected function setPath(?string $path): void
    {
        // Make path relative from GRAV_WEBROOT.
        $locator = $this->getLocator();
        if ($locator->isStream($path)) {
            $path = $locator->findResource($path, false) ?: null;
        } else {
            $path = Folder::getRelativePath($path, GRAV_WEBROOT) ?: null;
        }

        $this->path = $path;
    }
}
