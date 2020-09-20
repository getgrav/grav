<?php

declare(strict_types=1);

/**
 * @package    Grav\Common\Flex
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Flex\Types\Users\Storage;

use Grav\Framework\Flex\Storage\FileStorage;

/**
 * Class UserFileStorage
 * @package Grav\Common\Flex\Types\Users\Storage
 */
class UserFileStorage extends FileStorage
{
    /** @var bool */
    public $caseSensitive;

    /**
     * @param string $key
     * @return string
     */
    public function normalizeKey(string $key): string
    {
        if ($this->caseSensitive === true) {
            return $key;
        }

        return mb_strtolower($key);
    }

    /**
     * {@inheritdoc}
     * @see FlexStorageInterface::getMediaPath()
     */
    public function getMediaPath(string $key = null): ?string
    {
        // There is no media support for file storage (fallback to common location).
        return null;
    }

    /**
     * Prepares the row for saving and returns the storage key for the record.
     *
     * @param array $row
     */
    protected function prepareRow(array &$row): void
    {
        parent::prepareRow($row);

        $access = $row['access'] ?? [];
        unset($row['access']);
        if ($access) {
            $row['access'] = $access;
        }
    }

    /**
     * @param array $options
     * @return void
     */
    protected function initOptions(array $options): void
    {
        parent::initOptions($options);

        $this->caseSensitive = $options['case_sensitive'] ?? false;
    }
}
