<?php

/**
 * @package    Grav\Common\User
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\User\FlexUser;

use Grav\Common\Debugger;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use Grav\Common\User\Interfaces\UserInterface;
use Grav\Framework\Flex\FlexIndex;
use Grav\Framework\Flex\Interfaces\FlexStorageInterface;
use Monolog\Logger;

class UserIndex extends FlexIndex
{
    /**
     * @param FlexStorageInterface $storage
     * @return array
     */
    public static function loadEntriesFromStorage(FlexStorageInterface $storage) : array
    {
        // Load saved index.
        $index = static::loadEntriesFromIndex($storage);

        // Load up to date index.
        $entries = parent::loadEntriesFromStorage($storage);

        return static::updateIndexFile($storage, $index, $entries);
    }

    /**
     * Load user account.
     *
     * Always creates user object. To check if user exists, use $this->exists().
     *
     * @param string $username
     *
     * @return User
     */
    public function load($username): UserInterface
    {
        if ($username !== '') {
            $key = mb_strtolower($username);
            $user = $this->get($key);
            if ($user) {
                return $user;
            }
        } else {
            $key = '';
        }

        $directory = $this->getFlexDirectory();

        /** @var User $object */
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
     * Find a user by username, email, etc
     *
     * @param string $query the query to search for
     * @param array $fields the fields to search
     * @return User
     */
    public function find($query, $fields = ['username', 'email']): UserInterface
    {
        foreach ((array)$fields as $field) {
            if ($field === 'key') {
                $user = $this->get($query);
            } elseif ($field === 'storage_key') {
                $user = $this->withKeyField('storage_key')->get($query);
            } elseif ($field === 'flex_key') {
                $user = $this->withKeyField('flex_key')->get($query);
            } elseif ($field === 'email') {
                $user = $this->withKeyField('email')->get($query);
            } elseif ($field === 'username') {
                $user = $this->get(mb_strtolower($query));
            } else {
                $user = $this->__call('find', [$query, $field]);
            }
            if ($user) {
                return $user;
            }
        }

        return $this->load('');
    }

    protected static function updateIndexData(array &$entry, array $data)
    {
        $entry['key'] = mb_strtolower($entry['key']);
        $entry['email'] = isset($data['email']) ? mb_strtolower($data['email']) : null;
    }

    protected static function getIndexFile(FlexStorageInterface $storage)
    {
        // Load saved index file.
        $grav = Grav::instance();
        $locator = $grav['locator'];
        $filename = $locator->findResource('user-data://flex/indexes/accounts.yaml', true, true);

        return CompiledYamlFile::instance($filename);
    }

    protected static function onChanges(array $entries, array $added, array $updated, array $removed)
    {
        $message = sprintf('Flex: User index updated, %d objects (%d added, %d updated, %d removed).', \count($entries), \count($added), \count($updated), \count($removed));

        $grav = Grav::instance();

        /** @var Logger $logger */
        $logger = $grav['log'];
        $logger->addDebug($message);

        /** @var Debugger $debugger */
        $debugger = $grav['debugger'];
        $debugger->addMessage($message, 'debug');
    }
}
