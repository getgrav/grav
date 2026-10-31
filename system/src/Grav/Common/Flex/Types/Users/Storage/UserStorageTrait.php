<?php

declare(strict_types=1);

/**
 * @package    Grav\Common\Flex
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Flex\Types\Users\Storage;

use Grav\Common\User\DataUser\User;
use RuntimeException;
use function is_string;

/**
 * Account storage which does not let an outdated account object write over the stored account.
 *
 * Saving an account object writes the whole account as the object knows it. If somebody else has saved or removed
 * the account since the object was loaded (or since a copy of it went into the session), writing it would undo their
 * change. Rows read from this storage carry a checksum of the stored account in `__META`, which stays with the object
 * (also when it is serialized). A save of such a row is refused (RuntimeException, code 409) when the stored account
 * is no longer the one the object was loaded from. Saves and removals hold the lock DataUser\User::save() takes.
 */
trait UserStorageTrait
{
    /**
     * @param string $key
     * @return array|null
     */
    protected function loadRow(string $key): ?array
    {
        $row = parent::loadRow($key);
        if (null !== $row && !isset($row['__ERROR'])) {
            $row['__META']['account_checksum'] = $this->getAccountChecksum($row);
        }

        return $row;
    }

    /**
     * @param string $key
     * @param array $row
     * @return array
     * @throws RuntimeException
     */
    protected function saveRow(string $key, array $row): array
    {
        $meta = $row['__META'] ?? [];

        // The stored account the row was loaded from, if any (not for a new account or a copy).
        $storedKey = $meta['storage_key'] ?? null;
        if (!is_string($storedKey) || $storedKey === '' || str_contains($storedKey, '@@') || !empty($meta['copy'])) {
            $storedKey = null;
        }
        $lockKey = $storedKey ?? (string)($row[$this->keyField] ?? $key);

        $lock = str_contains($lockKey, '@@') ? null : User::lockAccountFile($this->getAccountFilename($lockKey));
        try {
            $loaded = $meta['account_checksum'] ?? null;
            if (null !== $storedKey && null !== $loaded && $this->readAccountChecksum($storedKey) !== $loaded) {
                throw new RuntimeException('User account has been changed since it was loaded', 409);
            }

            $row = parent::saveRow($key, $row);

            // A later save of the same object compares against what was written now.
            $row['__META']['account_checksum'] = $this->readAccountChecksum((string)$row['__META']['storage_key']);
        } finally {
            $lock?->free();
        }

        return $row;
    }

    /**
     * {@inheritdoc}
     * @see FlexStorageInterface::deleteRows()
     */
    public function deleteRows(array $rows): array
    {
        $list = [];
        foreach ($rows as $key => $row) {
            // Not while a save is checking and writing the same account.
            $lock = User::lockAccountFile($this->getAccountFilename((string)$key));
            try {
                $list[$key] = parent::deleteRows([$key => $row])[$key] ?? null;
            } finally {
                $lock->free();
            }
        }

        return $list;
    }

    /**
     * @param string $key
     * @return string
     */
    protected function getAccountFilename(string $key): string
    {
        return $this->resolvePath($this->getPathFromKey($this->normalizeKey($key)));
    }

    /**
     * Checksum of the stored account, null if there is none.
     *
     * @param string $key
     * @return string|null
     */
    protected function readAccountChecksum(string $key): ?string
    {
        $file = $this->getFile($this->getPathFromKey($key));
        clearstatcache(true, $file->filename());
        if (!$file->exists()) {
            $file->free();

            return null;
        }

        // Read the file itself rather than what has been read of it earlier: loadRow() decodes what is read here.
        $file->load();
        $row = parent::loadRow($key);

        return null !== $row && !isset($row['__ERROR']) ? $this->getAccountChecksum($row) : null;
    }

    /**
     * @param array $row
     * @return string
     */
    protected function getAccountChecksum(array $row): string
    {
        unset($row['__META'], $row['__ERROR']);

        return hash('sha256', serialize($row));
    }
}
