<?php

declare(strict_types=1);

/**
 * @package    Grav\Common\Flex
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Flex\Types\Users;

use Grav\Common\Debugger;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Flex\FlexIndex;
use Grav\Common\Grav;
use Grav\Common\User\Interfaces\UserCollectionInterface;
use Grav\Common\User\Interfaces\UserInterface;
use Grav\Framework\Flex\Interfaces\FlexStorageInterface;
use Monolog\Logger;
use function count;
use function is_string;

/**
 * Class UserIndex
 * @package Grav\Common\Flex\Types\Users
 *
 * @extends FlexIndex<UserObject,UserCollection>
 */
class UserIndex extends FlexIndex implements UserCollectionInterface
{
    public const VERSION = parent::VERSION . '.2';

    /**
     * @param FlexStorageInterface $storage
     * @return array
     */
    public static function loadEntriesFromStorage(FlexStorageInterface $storage): array
    {
        // Load saved index.
        $index = static::loadIndex($storage);

        $version = $index['version'] ?? 0;
        $force = static::VERSION !== $version;

        // TODO: Following check flex index to be out of sync after some saves, disabled until better solution is found.
        //$timestamp = $index['timestamp'] ?? 0;
        //if (!$force && $timestamp && $timestamp > time() - 1) {
        //    return $index['index'];
        //}

        // Load up-to-date index.
        $entries = parent::loadEntriesFromStorage($storage);

        return static::updateIndexFile($storage, $index['index'], $entries, ['force_update' => $force]);
    }

    /**
     * @param array $meta
     * @param array $data
     * @param FlexStorageInterface $storage
     * @return void
     */
    public static function updateObjectMeta(array &$meta, array $data, FlexStorageInterface $storage): void
    {
        // Username can also be number and stored as such.
        $key = (string)($data['username'] ?? $meta['key'] ?? $meta['storage_key']);
        $meta['key'] = static::filterUsername($key, $storage);
        $meta['email'] = isset($data['email']) ? mb_strtolower($data['email']) : null;
    }

    /**
     * Load user account.
     *
     * Always creates user object. To check if user exists, use $this->exists().
     *
     * @param string $username
     * @return UserObject
     */
    public function load($username): UserInterface
    {
        $username = (string)$username;

        if ($username !== '') {
            $key = static::filterUsername($username, $this->getFlexDirectory()->getStorage());
            $user = $this->get($key);
            if ($user) {
                return $user;
            }
        } else {
            $key = '';
        }

        $directory = $this->getFlexDirectory();

        /** @var UserObject $object */
        $object = $directory->createObject(
            [
                'username' => $username,
                'state' => 'enabled'
            ],
            $key
        );

        return $object;
    }

    /**
     * Delete user account.
     *
     * @param string $username
     * @return bool True if user account was found and was deleted.
     */
    public function delete($username): bool
    {
        $user = $this->load($username);

        $exists = $user->exists();
        if ($exists) {
            $user->delete();
        }

        return $exists;
    }

    /**
     * Find a user by username, email, etc
     *
     * @param string $query the query to search for
     * @param array $fields the fields to search
     * @return UserObject
     */
    public function find($query, $fields = ['username', 'email']): UserInterface
    {
        if (is_string($query) && $query !== '') {
            foreach ((array)$fields as $field) {
                if ($field === 'key') {
                    $user = $this->get($query);
                } elseif ($field === 'storage_key') {
                    $user = $this->withKeyField('storage_key')->get($query);
                } elseif ($field === 'flex_key') {
                    $user = $this->withKeyField('flex_key')->get($query);
                } elseif ($field === 'email') {
                    $email = mb_strtolower($query);
                    $user = $this->withKeyField('email')->get($email);
                } elseif ($field === 'username') {
                    $username = static::filterUsername($query, $this->getFlexDirectory()->getStorage());
                    $user = $this->get($username);
                } else {
                    $user = $this->__call('find', [$query, $field]);
                }
                if ($user) {
                    return $user;
                }
            }
        }

        return $this->load('');
    }

    /**
     * @param string $key
     * @param FlexStorageInterface $storage
     * @return string
     */
    protected static function filterUsername(string $key, FlexStorageInterface $storage): string
    {
        return method_exists($storage, 'normalizeKey') ? $storage->normalizeKey($key) : $key;
    }

    /**
     * @param FlexStorageInterface $storage
     * @return CompiledYamlFile|null
     */
    protected static function getIndexFile(FlexStorageInterface $storage)
    {
        // Load saved index file.
        $grav = Grav::instance();
        $locator = $grav['locator'];
        $filename = $locator->findResource('user-data://flex/indexes/accounts.yaml', true, true);

        return CompiledYamlFile::instance($filename);
    }

    /**
     * @param array $entries
     * @param array $added
     * @param array $updated
     * @param array $removed
     */
    protected static function onChanges(array $entries, array $added, array $updated, array $removed): void
    {
        $message = sprintf('Flex: User index updated, %d objects (%d added, %d updated, %d removed).', count($entries), count($added), count($updated), count($removed));

        $grav = Grav::instance();

        /** @var Logger $logger */
        $logger = $grav['log'];
        $logger->addDebug($message);

        /** @var Debugger $debugger */
        $debugger = $grav['debugger'];
        $debugger->addMessage($message, 'debug');
    }
}
