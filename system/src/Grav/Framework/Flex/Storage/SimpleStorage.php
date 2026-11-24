<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Storage;

use Grav\Common\Data\Data;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Utils;
use Grav\Framework\Filesystem\Filesystem;
use InvalidArgumentException;
use LogicException;
use RocketTheme\Toolbox\File\File;
use RuntimeException;
use function array_key_exists;
use function is_scalar;
use function is_string;

/**
 * Class SimpleStorage
 * @package Grav\Framework\Flex\Storage
 */
class SimpleStorage extends AbstractFilesystemStorage
{
    /** @var string */
    protected $dataFolder;
    /** @var string */
    protected $dataPattern;
    /** @var string|null */
    protected $prefix;
    /** @var array|null */
    protected $data;
    /** @var array Rows as they were last read from or written to the file. */
    protected $stored = [];
    /** @var int */
    protected $modified = 0;

    /**
     * {@inheritdoc}
     * @see FlexStorageInterface::__construct()
     */
    public function __construct(array $options)
    {
        if (!isset($options['folder'])) {
            throw new InvalidArgumentException("Argument \$options is missing 'folder'");
        }

        $formatter = $options['formatter'] ?? $this->detectDataFormatter($options['folder']);
        $this->initDataFormatter($formatter);

        $filesystem = Filesystem::getInstance(true);

        $extension = $this->dataFormatter->getDefaultFileExtension();
        $pattern = Utils::basename($options['folder']);

        $this->dataPattern = Utils::basename($pattern, $extension) . $extension;
        $this->dataFolder = $filesystem->dirname($options['folder']);
        $this->keyField = $options['key'] ?? 'storage_key';
        $this->keyLen = (int)($options['key_len'] ?? 32);
        $this->prefix = $options['prefix'] ?? null;

        // Make sure that the data folder exists.
        if (!file_exists($this->dataFolder)) {
            try {
                Folder::create($this->dataFolder);
            } catch (RuntimeException $e) {
                throw new RuntimeException(sprintf('Flex: %s', $e->getMessage()));
            }
        }
    }

    /**
     * @return void
     */
    public function clearCache(): void
    {
        $this->data = null;
        $this->modified = 0;
    }

    /**
     * @param string[] $keys
     * @param bool $reload
     * @return array
     */
    public function getMetaData(array $keys, bool $reload = false): array
    {
        if (null === $this->data || $reload) {
            $this->buildIndex();
        }

        $list = [];
        foreach ($keys as $key) {
            $list[$key] = $this->getObjectMeta((string)$key);
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
        if (null === $this->data) {
            $this->buildIndex();
        }

        return $key && !str_contains($key, '@@') && isset($this->data[$key]);
    }

    /**
     * {@inheritdoc}
     * @see FlexStorageInterface::createRows()
     */
    public function createRows(array $rows): array
    {
        if (null === $this->data) {
            $this->buildIndex();
        }

        $list = [];
        foreach ($rows as $key => $row) {
            $list[$key] = $this->saveRow('@@', $rows);
        }

        if ($list) {
            $this->save();
        }

        return $list;
    }

    /**
     * {@inheritdoc}
     * @see FlexStorageInterface::readRows()
     */
    public function readRows(array $rows, ?array &$fetched = null): array
    {
        if (null === $this->data) {
            $this->buildIndex();
        }

        $list = [];
        foreach ($rows as $key => $row) {
            if (null === $row || is_scalar($row)) {
                // Only load rows which haven't been loaded before.
                $key = (string)$key;
                $list[$key] = $this->hasKey($key) ? $this->loadRow($key) : null;
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
        if (null === $this->data) {
            $this->buildIndex();
        }

        $save = false;
        $list = [];
        foreach ($rows as $key => $row) {
            $key = (string)$key;
            if ($this->hasKey($key)) {
                $list[$key] = $this->saveRow($key, $row);
                $save = true;
            } else {
                $list[$key] = null;
            }
        }

        if ($save) {
            $this->save();
        }

        return $list;
    }

    /**
     * {@inheritdoc}
     * @see FlexStorageInterface::deleteRows()
     */
    public function deleteRows(array $rows): array
    {
        if (null === $this->data) {
            $this->buildIndex();
        }

        $list = [];
        foreach ($rows as $key => $row) {
            $key = (string)$key;
            if ($this->hasKey($key)) {
                unset($this->data[$key]);
                $list[$key] = $row;
            }
        }

        if ($list) {
            $this->save();
        }

        return $list;
    }

    /**
     * {@inheritdoc}
     * @see FlexStorageInterface::replaceRows()
     */
    public function replaceRows(array $rows): array
    {
        if (null === $this->data) {
            $this->buildIndex();
        }

        $list = [];
        foreach ($rows as $key => $row) {
            $list[$key] = $this->saveRow((string)$key, $row);
        }

        if ($list) {
            $this->save();
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
            throw new RuntimeException("Cannot copy object: key '{$dst}' is already taken");
        }

        if (!$this->hasKey($src)) {
            return false;
        }

        $this->data[$dst] = $this->data[$src];

        return true;
    }

    /**
     * {@inheritdoc}
     * @see FlexStorageInterface::renameRow()
     */
    public function renameRow(string $src, string $dst): bool
    {
        if (null === $this->data) {
            $this->buildIndex();
        }

        if ($this->hasKey($dst)) {
            throw new RuntimeException("Cannot rename object: key '{$dst}' is already taken");
        }

        if (!$this->hasKey($src)) {
            return false;
        }

        // Change single key in the array without changing the order or value.
        $keys = array_keys($this->data);
        $keys[array_search($src, $keys, true)] = $dst;

        $data = array_combine($keys, $this->data);
        if (false === $data) {
            throw new LogicException('Bad data');
        }

        $this->data = $data;

        return true;
    }

    /**
     * {@inheritdoc}
     * @see FlexStorageInterface::getStoragePath()
     */
    public function getStoragePath(?string $key = null): ?string
    {
        return $this->dataFolder . '/' . $this->dataPattern;
    }

    /**
     * {@inheritdoc}
     * @see FlexStorageInterface::getMediaPath()
     */
    public function getMediaPath(?string $key = null): ?string
    {
        return null;
    }

    /**
     * Prepares the row for saving and returns the storage key for the record.
     *
     * @param array $row
     */
    protected function prepareRow(array &$row): void
    {
        unset($row[$this->keyField]);
    }

    /**
     * @param string $key
     * @return array
     */
    protected function loadRow(string $key): ?array
    {
        $data = $this->data[$key] ?? [];
        if ($this->keyField !== 'storage_key') {
            $data[$this->keyField] = $key;
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
            if (str_contains((string) $key, '@@')) {
                $key = $this->getNewKey();
            }

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

            $this->data[$key] = $row;
        } catch (RuntimeException $e) {
            throw new RuntimeException(sprintf('Flex saveRow(%s): %s', $key, $e->getMessage()));
        }

        $row['__META'] = $this->getObjectMeta($key, true);

        return $row;
    }

    /**
     * @param string $key
     * @param bool $variations
     * @return array
     */
    public function parseKey(string $key, bool $variations = true): array
    {
        return [
            'key' => $key,
        ];
    }

    protected function save(): void
    {
        if (null === $this->data) {
            $this->buildIndex();
        }

        try {
            $path = $this->getStoragePath();
            if (!$path) {
                throw new RuntimeException('Storage path is not defined');
            }
            $file = $this->getFile($path);

            // All the rows share one file and $this->data is that file as it was when it was first read. Somebody
            // else may have saved the file since then, and writing the rows as they are known in here would undo
            // that. Hold a lock shared by all the saves of this file, read the file again and only apply the
            // changes which were made in here.
            $lock = $this->lockFile($file->filename());
            clearstatcache(true, $file->filename());
            $file->load();
            $content = (array)$file->content();
            $stored = $this->prefix ? (array)(new Data($content))->get($this->prefix, []) : $content;
            if ($stored !== $this->stored) {
                $this->data = $this->mergeRows($stored);
            }

            if ($this->prefix) {
                $data = new Data($content);
                $content = $data->set($this->prefix, $this->data)->toArray();
            } else {
                $content = $this->data;
            }
            $file->save($content);
            $this->modified = (int)$file->modified(); // cast false to 0
            $this->stored = $this->data;
        } catch (RuntimeException $e) {
            throw new RuntimeException(sprintf('Flex save(): %s', $e->getMessage()));
        } finally {
            if (isset($file)) {
                $file->free();
                unset($file);
            }
            if (isset($lock)) {
                $lock->free();
            }
        }
    }

    /**
     * Applies the changes which were made to the rows in here to the rows which are in the file now.
     *
     * @param array $rows Rows in the file.
     * @return array
     */
    protected function mergeRows(array $rows): array
    {
        // Rows which were removed or renamed in here.
        foreach (array_diff_key($this->stored, $this->data) as $key => $row) {
            unset($rows[$key]);
        }

        // Rows which were added or changed in here. All the other rows stay as they are in the file.
        foreach ($this->data as $key => $row) {
            if (!array_key_exists($key, $this->stored) || $this->stored[$key] !== $row) {
                $rows[$key] = $row;
            }
        }

        return $rows;
    }

    /**
     * Takes the lock shared by everything which saves the storage file.
     *
     * @param string $filename
     * @return File The lock, release it with free().
     */
    protected function lockFile(string $filename): File
    {
        $lock = File::instance(CACHE_DIR . 'compiled/files/' . md5($filename) . '.lock');
        try {
            $lock->lock();
        } catch (RuntimeException $e) {
            // Carry on without the lock if it cannot be created, like CompiledFile::save() does.
        }

        return $lock;
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
     * Returns list of all stored keys in [key => timestamp] pairs.
     *
     * @return array
     */
    protected function buildIndex(): array
    {
        $path = $this->getStoragePath();
        if (!$path) {
            $this->data = $this->stored = [];

            return [];
        }

        $file = $this->getFile($path);
        $this->modified = (int)$file->modified(); // cast false to 0

        $content = (array) $file->content();
        if ($this->prefix) {
            $data = new Data($content);
            $content = $data->get($this->prefix, []);
        }

        $file->free();
        unset($file);

        $this->data = $this->stored = $content;

        $list = [];
        foreach ($this->data as $key => $info) {
            $list[$key] = $this->getObjectMeta((string)$key);
        }

        return $list;
    }

    /**
     * @param string $key
     * @param bool $reload
     * @return array
     */
    protected function getObjectMeta(string $key, bool $reload = false): array
    {
        $modified = isset($this->data[$key]) ? $this->modified : 0;

        return [
            'storage_key' => $key,
            'key' => $key,
            'storage_timestamp' => $modified
        ];
    }

    /**
     * @return string
     */
    protected function getNewKey(): string
    {
        if (null === $this->data) {
            $this->buildIndex();
        }

        // Make sure that the key doesn't exist.
        do {
            $key = $this->generateKey();
        } while (isset($this->data[$key]));

        return $key;
    }
}
