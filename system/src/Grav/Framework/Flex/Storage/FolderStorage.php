<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Storage;

use FilesystemIterator;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Utils;
use Grav\Framework\Filesystem\Filesystem;
use Grav\Framework\Flex\Interfaces\FlexStorageInterface;
use RocketTheme\Toolbox\File\File;
use InvalidArgumentException;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RuntimeException;
use SplFileInfo;
use function array_key_exists;
use function basename;
use function count;
use function is_scalar;
use function is_string;
use function mb_strpos;
use function mb_substr;

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
    /** @var string[] */
    protected $variables = ['FOLDER' => '%1$s', 'KEY' => '%2$s', 'KEY:2' => '%3$s', 'FILE' => '%4$s', 'EXT' => '%5$s'];
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
     * @return bool
     */
    public function isIndexed(): bool
    {
        return $this->indexed;
    }

    /**
     * @return void
     */
    public function clearCache(): void
    {
        $this->meta = [];
    }

    /**
     * @param string[] $keys
     * @param bool $reload
     * @return array
     */
    public function getMetaData(array $keys, bool $reload = false): array
    {
        $list = [];
        foreach ($keys as $key) {
            $list[$key] = $this->getObjectMeta((string)$key, $reload);
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
        $meta = $this->getObjectMeta($key);

        return array_key_exists('exists', $meta) ? $meta['exists'] : !empty($meta['storage_timestamp']);
    }

    /**
     * {@inheritdoc}
     * @see FlexStorageInterface::createRows()
     */
    public function createRows(array $rows): array
    {
        $list = [];
        foreach ($rows as $key => $row) {
            $list[$key] = $this->saveRow('@@', $row);
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
            if (null === $row || is_scalar($row)) {
                // Only load rows which haven't been loaded before.
                $key = (string)$key;
                $list[$key] = $this->loadRow($key);

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
            $list[$key] = $this->hasKey($key) ? $this->saveRow($key, $row) : null;
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

                if ($this->canDeleteFolder($key)) {
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
            $list[$key] = $this->saveRow($key, $row);
        }

        return $list;
    }

    /**
     * @param string $src
     * @param string $dst
     * @return bool
     * @throws RuntimeException
     */
    public function copyRow(string $src, string $dst): bool
    {
        if ($this->hasKey($dst)) {
            throw new RuntimeException("Cannot copy object: key '{$dst}' is already taken");
        }

        if (!$this->hasKey($src)) {
            return false;
        }

        $srcPath = $this->getStoragePath($src);
        $dstPath = $this->getStoragePath($dst);
        if (!$srcPath || !$dstPath) {
            return false;
        }

        return $this->copyFolder($srcPath, $dstPath);
    }

    /**
     * {@inheritdoc}
     * @see FlexStorageInterface::renameRow()
     * @throws RuntimeException
     */
    public function renameRow(string $src, string $dst): bool
    {
        if (!$this->hasKey($src)) {
            return false;
        }

        $srcPath = $this->getStoragePath($src);
        $dstPath = $this->getStoragePath($dst);
        if (!$srcPath || !$dstPath) {
            throw new RuntimeException("Destination path '{$dst}' is empty");
        }

        if ($srcPath === $dstPath) {
            return true;
        }

        if ($this->hasKey($dst)) {
            throw new RuntimeException("Cannot rename object '{$src}': key '{$dst}' is already taken $srcPath $dstPath");
        }

        return $this->moveFolder($srcPath, $dstPath);
    }

    /**
     * {@inheritdoc}
     * @see FlexStorageInterface::getStoragePath()
     */
    public function getStoragePath(string $key = null): ?string
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
    public function getMediaPath(string $key = null): ?string
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
            'key:2' => mb_substr($key, 0, 2),
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
        return Utils::basename($path);
    }

    /**
     * Prepares the row for saving and returns the storage key for the record.
     *
     * @param array $row
     * @return void
     */
    protected function prepareRow(array &$row): void
    {
        if (array_key_exists($this->keyField, $row)) {
            $key = $row[$this->keyField];
            if ($key === $this->normalizeKey($key)) {
                unset($row[$this->keyField]);
            }
        }
    }

    /**
     * @param string $key
     * @return array
     */
    protected function loadRow(string $key): ?array
    {
        $path = $this->getPathFromKey($key);
        $file = $this->getFile($path);
        try {
            $data = (array)$file->content();
            if (isset($data[0])) {
                throw new RuntimeException('Broken object file');
            }

            // Add key field to the object.
            $keyField = $this->keyField;
            if ($keyField !== 'storage_key' && !isset($data[$keyField])) {
                $data[$keyField] = $key;
            }
        } catch (RuntimeException $e) {
            $data = ['__ERROR' => $e->getMessage()];
        } finally {
            $file->free();
            unset($file);
        }

        $data['__META'] = $this->getObjectMeta($key);

        return $data;
    }

    /**
     * @param string $key
     * @param array $row
     * @return array
     */
    protected function saveRow(string $key, array $row): array
    {
        try {
            if (isset($row[$this->keyField])) {
                $key = $row[$this->keyField];
            }
            if (strpos($key, '@@') !== false) {
                $key = $this->getNewKey();
            }

            $key = $this->normalizeKey($key);

            // Check if the row already exists and if the key has been changed.
            $oldKey = $row['__META']['storage_key'] ?? null;
            if (is_string($oldKey) && $oldKey !== $key) {
                $isCopy = $row['__META']['copy'] ?? false;
                if ($isCopy) {
                    $this->copyRow($oldKey, $key);
                } else {
                    $this->renameRow($oldKey, $key);
                }
            }

            $this->prepareRow($row);
            unset($row['__META'], $row['__ERROR']);

            $path = $this->getPathFromKey($key);
            $file = $this->getFile($path);

            $file->save($row);

        } catch (RuntimeException $e) {
            throw new RuntimeException(sprintf('Flex saveFile(%s): %s', $path ?? $key, $e->getMessage()));
        } finally {
            /** @var UniformResourceLocator $locator */
            $locator = Grav::instance()['locator'];
            $locator->clearCache();

            if (isset($file)) {
                $file->free();
                unset($file);
            }
        }

        $row['__META'] = $this->getObjectMeta($key, true);

        return $row;
    }

    /**
     * @param File $file
     * @return array|string
     */
    protected function deleteFile(File $file)
    {
        $filename = $file->filename();
        try {
            $data = $file->content();
            if ($file->exists()) {
                $file->delete();
            }
        } catch (RuntimeException $e) {
            throw new RuntimeException(sprintf('Flex deleteFile(%s): %s', $filename, $e->getMessage()));
        } finally {
            /** @var UniformResourceLocator $locator */
            $locator = Grav::instance()['locator'];
            $locator->clearCache();

            $file->free();
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
        } catch (RuntimeException $e) {
            throw new RuntimeException(sprintf('Flex copyFolder(%s, %s): %s', $src, $dst, $e->getMessage()));
        } finally {
            /** @var UniformResourceLocator $locator */
            $locator = Grav::instance()['locator'];
            $locator->clearCache();
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
        } catch (RuntimeException $e) {
            throw new RuntimeException(sprintf('Flex moveFolder(%s, %s): %s', $src, $dst, $e->getMessage()));
        } finally {
            /** @var UniformResourceLocator $locator */
            $locator = Grav::instance()['locator'];
            $locator->clearCache();
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
            return Folder::delete($this->resolvePath($path), $include_target);
        } catch (RuntimeException $e) {
            throw new RuntimeException(sprintf('Flex deleteFolder(%s): %s', $path, $e->getMessage()));
        } finally {
            /** @var UniformResourceLocator $locator */
            $locator = Grav::instance()['locator'];
            $locator->clearCache();
        }
    }

    /**
     * @param string $key
     * @return bool
     */
    protected function canDeleteFolder(string $key): bool
    {
        return true;
    }

    /**
     * Returns list of all stored keys in [key => timestamp] pairs.
     *
     * @return array
     */
    protected function buildIndex(): array
    {
        $this->clearCache();

        $path = $this->getStoragePath();
        if (!$path || !file_exists($path)) {
            return [];
        }

        if ($this->prefixed) {
            $list = $this->buildPrefixedIndexFromFilesystem($path);
        } else {
            $list = $this->buildIndexFromFilesystem($path);
        }

        ksort($list, SORT_NATURAL | SORT_FLAG_CASE);

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

        if ($key && strpos($key, '@@') === false) {
            $filename = $this->getPathFromKey($key);
            $modified = is_file($filename) ? filemtime($filename) : 0;
        } else {
            $modified = 0;
        }

        $meta = [
            'storage_key' => $key,
            'storage_timestamp' => $modified
        ];

        $this->meta[$key] = $meta;

        return $meta;
    }

    /**
     * @param string $path
     * @return array
     */
    protected function buildIndexFromFilesystem($path)
    {
        $flags = FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS;

        $iterator = new FilesystemIterator($path, $flags);
        $list = [];
        /** @var SplFileInfo $info */
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

    /**
     * @param string $path
     * @return array
     */
    protected function buildPrefixedIndexFromFilesystem($path)
    {
        $flags = FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS;

        $iterator = new FilesystemIterator($path, $flags);
        $list = [[]];
        /** @var SplFileInfo $info */
        foreach ($iterator as $filename => $info) {
            if (!$info->isDir() || strpos($info->getFilename(), '.') === 0) {
                continue;
            }

            $list[] = $this->buildIndexFromFilesystem($filename);
        }

        return array_merge(...$list);
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
     * @return void
     */
    protected function initOptions(array $options): void
    {
        $extension = $this->dataFormatter->getDefaultFileExtension();

        /** @var string $pattern */
        $pattern = !empty($options['pattern']) ? $options['pattern'] : $this->dataPattern;

        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];
        $folder = $options['folder'];
        if ($locator->isStream($folder)) {
            $folder = $locator->getResource($folder, false);
        }

        $this->dataFolder = $folder;
        $this->dataFile = $options['file'] ?? 'item';
        $this->dataExt = $extension;
        if (mb_strpos($pattern, '{FILE}') === false && mb_strpos($pattern, '{EXT}') === false) {
            if (isset($options['file'])) {
                $pattern .= '/{FILE}{EXT}';
            } else {
                $filesystem = Filesystem::getInstance(true);
                $this->dataFile = Utils::basename($pattern, $extension);
                $pattern = $filesystem->dirname($pattern) . '/{FILE}{EXT}';
            }
        }
        $this->prefixed = (bool)($options['prefixed'] ?? strpos($pattern, '/{KEY:2}/'));
        $this->indexed = (bool)($options['indexed'] ?? false);
        $this->keyField = $options['key'] ?? 'storage_key';
        $this->keyLen = (int)($options['key_len'] ?? 32);
        $this->caseSensitive = (bool)($options['case_sensitive'] ?? true);

        $pattern = Utils::simpleTemplate($pattern, $this->variables);
        if (!$pattern) {
            throw new RuntimeException('Bad storage folder pattern');
        }

        $this->dataPattern = $pattern;
    }
}
