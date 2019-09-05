<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Storage;

use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Framework\Flex\Interfaces\FlexStorageInterface;
use RocketTheme\Toolbox\File\File;
use InvalidArgumentException;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Class FolderStorage
 * @package Grav\Framework\Flex\Storage
 */
class FolderStorage extends AbstractFilesystemStorage
{
    /** @var string Folder where all the data is stored. */
    protected $dataFolder;
    /** @var string Pattern to access an object. */
    protected $dataPattern = '{FOLDER}/{KEY}/{FILE}{EXT}';
    /** @var string Filename for the object. */
    protected $dataFile;
    /** @var string File extension for the object. */
    protected $dataExt;
    /** @var bool */
    protected $prefixed;
    /** @var bool */
    protected $indexed;
    /** @var array */
    protected $meta = [];

    /**
     * {@inheritdoc}
     */
    public function __construct(array $options)
    {
        if (!isset($options['folder'])) {
            throw new InvalidArgumentException("Argument \$options is missing 'folder'");
        }

        $this->initDataFormatter($options['formatter'] ?? []);
        $this->initOptions($options);
    }

    /**
     * @param string[] $keys
     * @return array
     */
    public function getMetaData(array $keys): array
    {
        $list = [];
        foreach ($keys as $key) {
            $list[$key] = $this->getObjectMeta($key);
        }

        return $list;
    }

    /**
     * {@inheritdoc}
     * @see FlexStorageInterface::getExistingKeys()
     */
    public function getExistingKeys(): array
    {
        return $this->buildIndex();
    }

    /**
     * {@inheritdoc}
     * @see FlexStorageInterface::hasKey()
     */
    public function hasKey(string $key): bool
    {
        return $key && strpos($key, '@@') === false && file_exists($this->getPathFromKey($key));
    }

    /**
     * {@inheritdoc}
     * @see FlexStorageInterface::createRows()
     */
    public function createRows(array $rows): array
    {
        $list = [];
        foreach ($rows as $key => $row) {
            // Create new file and save it.
            $key = $this->getNewKey();
            $path = $this->getPathFromKey($key);
            $file = $this->getFile($path);
            $list[$key] = $this->saveFile($file, $row);
            $list[$key]['__META'] = $this->getObjectMeta($key, true);
        }

        return $list;
    }

    /**
     * {@inheritdoc}
     * @see FlexStorageInterface::readRows()
     */
    public function readRows(array $rows, array &$fetched = null): array
    {
        $list = [];
        foreach ($rows as $key => $row) {
            if (null === $row || \is_scalar($row)) {
                // Only load rows which haven't been loaded before.
                $key = (string)$key;
                if (!$this->hasKey($key)) {
                    $list[$key] = null;
                } else {
                    $path = $this->getPathFromKey($key);
                    $file = $this->getFile($path);
                    $list[$key] = $this->loadFile($file);
                    $list[$key]['__META'] = $this->getObjectMeta($key);
                }
                if (null !== $fetched) {
                    $fetched[$key] = $list[$key];
                }
            } else {
                // Keep the row if it has been loaded.
                $list[$key] = $row;
            }
        }

        return $list;
    }

    /**
     * {@inheritdoc}
     * @see FlexStorageInterface::updateRows()
     */
    public function updateRows(array $rows): array
    {
        $list = [];
        foreach ($rows as $key => $row) {
            $key = (string)$key;
            if (!$this->hasKey($key)) {
                $list[$key] = null;
            } else {
                $path = $this->getPathFromKey($key);
                $file = $this->getFile($path);
                $list[$key] = $this->saveFile($file, $row);
                $list[$key]['__META'] = $this->getObjectMeta($key, true);
            }
        }

        return $list;
    }

    /**
     * {@inheritdoc}
     * @see FlexStorageInterface::deleteRows()
     */
    public function deleteRows(array $rows): array
    {
        $list = [];
        $baseMediaPath = $this->getMediaPath();
        foreach ($rows as $key => $row) {
            $key = (string)$key;
            if (!$this->hasKey($key)) {
                $list[$key] = null;
            } else {
                $path = $this->getPathFromKey($key);
                $file = $this->getFile($path);
                $list[$key] = $this->deleteFile($file);

                $storagePath = $this->getStoragePath($key);
                $mediaPath = $this->getMediaPath($key);

                if ($storagePath) {
                    $this->deleteFolder($storagePath, true);
                }
                if ($mediaPath && $mediaPath !== $storagePath && $mediaPath !== $baseMediaPath) {
                    $this->deleteFolder($mediaPath, true);
                }
            }
        }

        return $list;
    }

    /**
     * {@inheritdoc}
     * @see FlexStorageInterface::replaceRows()
     */
    public function replaceRows(array $rows): array
    {
        $list = [];
        foreach ($rows as $key => $row) {
            $key = (string)$key;
            if (strpos($key, '@@') !== false) {
                $key = $this->getNewKey();
            }
            $path = $this->getPathFromKey($key);
            $file = $this->getFile($path);
            $list[$key] = $this->saveFile($file, $row);
            $list[$key]['__META'] = $this->getObjectMeta($key, true);
        }

        return $list;
    }

    /**
     * @param string $src
     * @param string $dst
     * @return bool
     */
    public function copyRow(string $src, string $dst): bool
    {
        if ($this->hasKey($dst)) {
            throw new \RuntimeException("Cannot copy object: key '{$dst}' is already taken");
        }

        if (!$this->hasKey($src)) {
            return false;
        }

        return $this->copyFolder($this->getStoragePath($src), $this->getStoragePath($dst));
    }


    /**
     * {@inheritdoc}
     * @see FlexStorageInterface::renameRow()
     */
    public function renameRow(string $src, string $dst): bool
    {
        if ($this->hasKey($dst)) {
            throw new \RuntimeException("Cannot rename object: key '{$dst}' is already taken");
        }

        if (!$this->hasKey($src)) {
            return false;
        }

        return $this->moveFolder($this->getStoragePath($src), $this->getStoragePath($dst));
    }

    /**
     * {@inheritdoc}
     * @see FlexStorageInterface::getStoragePath()
     */
    public function getStoragePath(string $key = null): string
    {
        if (null === $key || $key === '') {
            $path = $this->dataFolder;
        } else {
            $parts = $this->parseKey($key, false);
            $options = [
                $this->dataFolder,      // {FOLDER}
                $parts['key'],          // {KEY}
                $parts['key:2'],        // {KEY:2}
                '***',                  // {FILE}
                '***'                   // {EXT}
            ];

            $path = rtrim(explode('***', sprintf($this->dataPattern, ...$options))[0], '/');
        }

        return $path;
    }

    /**
     * {@inheritdoc}
     * @see FlexStorageInterface::getMediaPath()
     */
    public function getMediaPath(string $key = null): string
    {
        return $this->getStoragePath($key);
    }

    /**
     * Get filesystem path from the key.
     *
     * @param string $key
     * @return string
     */
    public function getPathFromKey(string $key): string
    {
        $parts = $this->parseKey($key);
        $options = [
            $this->dataFolder,      // {FOLDER}
            $parts['key'],          // {KEY}
            $parts['key:2'],        // {KEY:2}
            $parts['file'],         // {FILE}
            $this->dataExt          // {EXT}
        ];

        return sprintf($this->dataPattern, ...$options);
    }

    /**
     * @param string $key
     * @param bool $variations
     * @return array
     */
    public function parseKey(string $key, bool $variations = true): array
    {
        $keys = [
            'key' => $key,
            'key:2' => \mb_substr($key, 0, 2),
        ];
        if ($variations) {
            $keys['file'] = $this->dataFile;
        }

        return $keys;
    }

    /**
     * Get key from the filesystem path.
     *
     * @param  string $path
     * @return string
     */
    protected function getKeyFromPath(string $path): string
    {
        return basename($path);
    }

    /**
     * @param File $file
     * @return array|null
     */
    protected function loadFile(File $file): ?array
    {
        if (!$file->exists()) {
            return null;
        }

        try {
            $content = (array)$file->content();
            if (isset($content[0])) {
                throw new \RuntimeException('Broken object file.');
            }
        } catch (\RuntimeException $e) {
            $content = ['__error' => $e->getMessage()];
        }

        return $content;
    }

    /**
     * @param File $file
     * @param array $data
     * @return array
     */
    protected function saveFile(File $file, array $data): array
    {
        try {
            unset($data['__META'], $data['__error']);
            $file->save($data);

            /** @var UniformResourceLocator $locator */
            $locator = Grav::instance()['locator'];
            if ($locator->isStream($file->filename())) {
                $locator->clearCache($file->filename());
            }
        } catch (\RuntimeException $e) {
            throw new \RuntimeException(sprintf('Flex saveFile(%s): %s', $file->filename(), $e->getMessage()));
        }

        return $data;
    }

    /**
     * @param File $file
     * @return array|string
     */
    protected function deleteFile(File $file)
    {
        try {
            $data = $file->content();
            if ($file->exists()) {
                $file->delete();
            }

            /** @var UniformResourceLocator $locator */
            $locator = Grav::instance()['locator'];
            if ($locator->isStream($file->filename())) {
                $locator->clearCache($file->filename());
            }
        } catch (\RuntimeException $e) {
            throw new \RuntimeException(sprintf('Flex deleteFile(%s): %s', $file->filename(), $e->getMessage()));
        }

        return $data;
    }

    /**
     * @param string $src
     * @param string $dst
     * @return bool
     */
    protected function copyFolder(string $src, string $dst): bool
    {
        try {
            Folder::copy($this->resolvePath($src), $this->resolvePath($dst));

            /** @var UniformResourceLocator $locator */
            $locator = Grav::instance()['locator'];
            if ($locator->isStream($src) || $locator->isStream($dst)) {
                $locator->clearCache();
            }
        } catch (\RuntimeException $e) {
            throw new \RuntimeException(sprintf('Flex copyFolder(%s, %s): %s', $src, $dst, $e->getMessage()));
        }

        return true;
    }

    /**
     * @param string $src
     * @param string $dst
     * @return bool
     */
    protected function moveFolder(string $src, string $dst): bool
    {
        try {
            Folder::move($this->resolvePath($src), $this->resolvePath($dst));

            /** @var UniformResourceLocator $locator */
            $locator = Grav::instance()['locator'];
            if ($locator->isStream($src) || $locator->isStream($dst)) {
                $locator->clearCache();
            }
        } catch (\RuntimeException $e) {
            throw new \RuntimeException(sprintf('Flex moveFolder(%s, %s): %s', $src, $dst, $e->getMessage()));
        }

        return true;
    }

    /**
     * @param string $path
     * @param bool $include_target
     * @return bool
     */
    protected function deleteFolder(string $path, bool $include_target = false): bool
    {
        try {
            $success = Folder::delete($this->resolvePath($path), $include_target);

            /** @var UniformResourceLocator $locator */
            $locator = Grav::instance()['locator'];
            if ($locator->isStream($path)) {
                $locator->clearCache();
            }

            return $success;
        } catch (\RuntimeException $e) {
            throw new \RuntimeException(sprintf('Flex deleteFolder(%s): %s', $path, $e->getMessage()));
        }
    }

    /**
     * Returns list of all stored keys in [key => timestamp] pairs.
     *
     * @return array
     */
    protected function buildIndex(): array
    {
        $path = $this->getStoragePath();
        if (!file_exists($path)) {
            return [];
        }

        if ($this->prefixed) {
            $list = $this->buildPrefixedIndexFromFilesystem($path);
        } else {
            $list = $this->buildIndexFromFilesystem($path);
        }

        ksort($list, SORT_NATURAL);

        return $list;
    }

    /**
     * @param string $key
     * @param bool $reload
     * @return array
     */
    protected function getObjectMeta(string $key, bool $reload = false): array
    {
        if (!$reload && isset($this->meta[$key])) {
            return $this->meta[$key];
        }

        $filename = $this->getPathFromKey($key);
        $modified = is_file($filename) ? filemtime($filename) : 0;

        $meta = [
            'storage_key' => $key,
            'storage_timestamp' => $modified
        ];

        $this->meta[$key] = $meta;

        return $meta;
    }


    protected function buildIndexFromFilesystem($path)
    {
        $flags = \FilesystemIterator::KEY_AS_PATHNAME | \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS;

        $iterator = new \FilesystemIterator($path, $flags);
        $list = [];
        /** @var \SplFileInfo $info */
        foreach ($iterator as $filename => $info) {
            if (!$info->isDir() || strpos($info->getFilename(), '.') === 0) {
                continue;
            }

            $key = $this->getKeyFromPath($filename);
            $meta = $this->getObjectMeta($key);
            if ($meta['storage_timestamp']) {
                $list[$key] = $meta;
            }
        }

        return $list;
    }

    protected function buildPrefixedIndexFromFilesystem($path)
    {
        $flags = \FilesystemIterator::KEY_AS_PATHNAME | \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS;

        $iterator = new \FilesystemIterator($path, $flags);
        $list = [];
        /** @var \SplFileInfo $info */
        foreach ($iterator as $filename => $info) {
            if (!$info->isDir() || strpos($info->getFilename(), '.') === 0) {
                continue;
            }

            $list[] = $this->buildIndexFromFilesystem($filename);
        }

        if (!$list) {
            return [];
        }

        return \count($list) > 1 ? array_merge(...$list) : $list[0];
    }

    /**
     * @return string
     */
    protected function getNewKey(): string
    {
        // Make sure that the file doesn't exist.
        do {
            $key = $this->generateKey();
        } while (file_exists($this->getPathFromKey($key)));

        return $key;
    }

    /**
     * @param array $options
     */
    protected function initOptions(array $options): void
    {
        $extension = $this->dataFormatter->getDefaultFileExtension();

        /** @var string $pattern */
        $pattern = !empty($options['pattern']) ? $options['pattern'] : $this->dataPattern;

        $this->dataFolder = $options['folder'];
        $this->dataFile = $options['file'] ?? 'item';
        $this->dataExt = $extension;
        if (\mb_strpos($pattern, '{FILE}') === false && \mb_strpos($pattern, '{EXT}') === false) {
            if (isset($options['file'])) {
                $pattern .= '/{FILE}{EXT}';
            } else {
                $this->dataFile = \basename($pattern, $extension);
                $pattern = \dirname($pattern) . '/{FILE}{EXT}';
            }
        }
        $this->prefixed = (bool)($options['prefixed'] ?? strpos($pattern, '/{KEY:2}/'));
        $this->indexed = (bool)($options['indexed'] ?? false);
        $this->keyField = $options['key'] ?? 'storage_key';

        $pattern = preg_replace(
            ['/{FOLDER}/', '/{KEY}/', '/{KEY:2}/', '/{FILE}/', '/{EXT}/'],
            ['%1$s',       '%2$s',    '%3$s',      '%4$s',     '%5$s'],
            $pattern
        );

        $this->dataPattern = $pattern;
    }
}
