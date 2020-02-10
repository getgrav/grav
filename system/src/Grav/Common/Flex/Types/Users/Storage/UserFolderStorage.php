<?php

declare(strict_types=1);

/**
 * @package    Grav\Common\Flex
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Flex\Types\Users\Storage;

use Grav\Framework\Flex\Storage\FolderStorage;

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
