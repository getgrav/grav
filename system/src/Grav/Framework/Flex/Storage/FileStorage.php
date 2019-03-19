<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Storage;

use Grav\Framework\Flex\Interfaces\FlexStorageInterface;

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
        $this->dataPattern = '{FOLDER}/{KEY}';

        if (!isset($options['formatter']) && isset($options['pattern'])) {
            $options['formatter'] = $this->detectDataFormatter($options['pattern']);
        }

        parent::__construct($options);
    }

    /**
     * {@inheritdoc}
     * @see FlexStorageInterface::getMediaPath()
     */
    public function getMediaPath(string $key = null): string
    {
        return $key ? \dirname($this->getStoragePath($key)) . '/' . $key : $this->getStoragePath();
    }

    /**
     * {@inheritdoc}
     */
    protected function getKeyFromPath(string $path): string
    {
        return basename($path, $this->dataFormatter->getDefaultFileExtension());
    }

    /**
     * {@inheritdoc}
     */
    protected function buildIndex(): array
    {
        if (!file_exists($this->getStoragePath())) {
            return [];
        }

        $flags = \FilesystemIterator::KEY_AS_PATHNAME | \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS;
        $iterator = new \FilesystemIterator($this->getStoragePath(), $flags);
        $list = [];
        /** @var \SplFileInfo $info */
        foreach ($iterator as $filename => $info) {
            if (!$info->isFile() || !($key = $this->getKeyFromPath($filename)) || strpos($info->getFilename(), '.') === 0) {
                continue;
            }

            $list[$key] = [
                'storage_key' => $key,
                'storage_timestamp' => $info->getMTime()
            ];
        }

        ksort($list, SORT_NATURAL);

        return $list;
    }
}
