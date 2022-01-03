<?php

declare(strict_types=1);

/**
 * @package    Grav\Common\Flex
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Flex\Types\Users\Storage;

use Grav\Framework\Flex\Storage\FolderStorage;

/**
 * Class UserFolderStorage
 * @package Grav\Common\Flex\Types\Users\Storage
 */
class UserFolderStorage extends FolderStorage
{
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
}
