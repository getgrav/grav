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
use InvalidArgumentException;

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
    /** @var array */
    protected $data;

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

        $extension = $this->dataFormatter->getDefaultFileExtension();
        $pattern = basename($options['folder']);

        $this->dataPattern = basename($pattern, $extension) . $extension;
        $this->dataFolder = \dirname($options['folder']);

        // Make sure that the data folder exists.
        if (!file_exists($this->dataFolder)) {
            try {
                Folder::create($this->dataFolder);
            } catch (\RuntimeException $e) {
                throw new \RuntimeException(sprintf('Flex: %s', $e->getMessage()));
            }
        }
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

        return $key && strpos($key, '@@') === false && isset($this->data[$key]);
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
            $key = $this->getNewKey();
            $this->data[$key] = $list[$key] = $row;
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
    public function readRows(array $rows, array &$fetched = null): array
    {
        if (null === $this->data) {
            $this->buildIndex();
        }

        $list = [];
        foreach ($rows as $key => $row) {
            if (null === $row || (!\is_object($row) && !\is_array($row))) {
                // Only load rows which haven't been loaded before.
                $key = (string)$key;
                $list[$key] = $this->hasKey($key) ? $this->data[$key] : null;
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

        $list = [];
        foreach ($rows as $key => $row) {
            $key = (string)$key;
            if ($this->hasKey($key)) {
                $this->data[$key] = $list[$key] = $row;
            }
        }

        if ($list) {
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
            if (strpos($key, '@@')) {
                $key = $this->getNewKey();
            }
            $this->data[$key] = $list[$key] = $row;
        }

        if ($list) {
            $this->save();
        }

        return $list;
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
            throw new \RuntimeException("Cannot rename object: key '{$dst}' is already taken");
        }

        if (!$this->hasKey($src)) {
            return false;
        }

        // Change single key in the array without changing the order or value.
        $keys = array_keys($this->data);
        $keys[array_search($src, $keys, true)] = $dst;

        $data = array_combine($keys, $this->data);
        if (false === $data) {
            throw new \LogicException('Bad data');
        }

        $this->data = $data;

        return true;
    }

    /**
     * {@inheritdoc}
     * @see FlexStorageInterface::getStoragePath()
     */
    public function getStoragePath(string $key = null): string
    {
        return $this->dataFolder . '/' . $this->dataPattern;
    }

    /**
     * {@inheritdoc}
     * @see FlexStorageInterface::getMediaPath()
     */
    public function getMediaPath(string $key = null): string
    {
        return sprintf('%s/%s/%s', $this->dataFolder, basename($this->dataPattern, $this->dataFormatter->getDefaultFileExtension()), $key);
    }

    protected function save() : void
    {
        if (null === $this->data) {
            $this->buildIndex();
        }

        try {
            $file = $this->getFile($this->getStoragePath());
            $file->save($this->data);
            $file->free();
        } catch (\RuntimeException $e) {
            throw new \RuntimeException(sprintf('Flex save(): %s', $e->getMessage()));
        }
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
     * Returns list of all stored keys in [key => timestamp] pairs.
     *
     * @return array
     */
    protected function buildIndex(): array
    {
        $file = $this->getFile($this->getStoragePath());
        $modified = $file->modified();

        $this->data = (array) $file->content();

        $list = [];
        foreach ($this->data as $key => $info) {
            $list[$key] = [
                'storage_key' => $key,
                'storage_timestamp' => $modified
            ];
        }

        return $list;
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
