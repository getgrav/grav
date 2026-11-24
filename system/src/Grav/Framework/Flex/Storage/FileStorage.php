<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Storage;

use FilesystemIterator;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Utils;
use Grav\Framework\Flex\Interfaces\FlexStorageInterface;
use RuntimeException;
use SplFileInfo;

/**
 * Class FileStorage
 * @package Grav\Framework\Flex\Storage
 */
class FileStorage extends FolderStorage
{
    /**
     * {@inheritdoc}
     * @see FlexStorageInterface::__construct()
     */
    public function __construct(array $options)
    {
        $this->dataPattern = '{FOLDER}/{KEY}{EXT}';

        if (!isset($options['formatter']) && isset($options['pattern'])) {
            $options['formatter'] = $this->detectDataFormatter($options['pattern']);
        }

        parent::__construct($options);
    }

    /**
     * {@inheritdoc}
     * @see FlexStorageInterface::getMediaPath()
     */
    public function getMediaPath(?string $key = null): ?string
    {
        $path = $this->getStoragePath();
        if (!$path) {
            return null;
        }

        return $key ? "{$path}/{$key}" : $path;
    }

    /**
     * @param string $src
     * @param string $dst
     * @return bool
     */
    public function copyRow(string $src, string $dst): bool
    {
        if ($this->hasKey($dst)) {
            throw new RuntimeException("Cannot copy object: key '{$dst}' is already taken");
        }

        if (!$this->hasKey($src)) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     * @see FlexStorageInterface::renameRow()
     */
    public function renameRow(string $src, string $dst): bool
    {
        if (!$this->hasKey($src)) {
            return false;
        }

        // Keys which differ only by case are the same file on a case insensitive filesystem.
        if ($this->normalizeKey($src) !== $this->normalizeKey($dst) && $this->hasKey($dst)) {
            throw new RuntimeException("Cannot rename object '{$src}': key '{$dst}' is already taken");
        }

        // The new key becomes a path as well.
        $this->assertValidKey($dst);

        // Move the old file instead of removing it: the object must not be lost if saving it with the new key fails.
        $path = $this->resolvePath($this->getPathFromKey($dst));
        Folder::create(dirname($path));

        $file = $this->getFile($this->getPathFromKey($src));
        $success = $file->rename($path);
        $file->free();
        unset($file, $this->meta[$src], $this->meta[$dst]);

        if (!$success) {
            throw new RuntimeException("Cannot rename object '{$src}' to '{$dst}'");
        }

        return true;
    }

    /**
     * @param string $src
     * @param string $dst
     * @return bool
     */
    protected function copyFolder(string $src, string $dst): bool
    {
        // Nothing to copy.
        return true;
    }

    /**
     * @param string $src
     * @param string $dst
     * @return bool
     */
    protected function moveFolder(string $src, string $dst): bool
    {
        // Nothing to move.
        return true;
    }

    /**
     * @param string $key
     * @return bool
     */
    protected function canDeleteFolder(string $key): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function getKeyFromPath(string $path): string
    {
        return Utils::basename($path, $this->dataFormatter->getDefaultFileExtension());
    }

    /**
     * {@inheritdoc}
     */
    protected function buildIndex(): array
    {
        $this->clearCache();

        $path = $this->getStoragePath();
        if (!$path || !file_exists($path)) {
            return [];
        }

        $flags = FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS;
        $iterator = new FilesystemIterator($path, $flags);
        $list = [];
        /** @var SplFileInfo $info */
        foreach ($iterator as $filename => $info) {
            if (!$info->isFile() || !($key = $this->getKeyFromPath($filename)) || str_starts_with($info->getFilename(), '.')) {
                continue;
            }

            $list[$key] = $this->getObjectMeta($key);
        }

        ksort($list, SORT_NATURAL | SORT_FLAG_CASE);

        return $list;
    }
}
