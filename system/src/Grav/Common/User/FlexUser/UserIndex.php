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

    protected static function getIndexData($key, ?array $row)
    {
        return [
            'key' => mb_strtolower($row['username'] ?? $key),
            'email' => mb_strtolower($row['email'] ?? ''),
        ];
    }

    protected static function getIndexFile(FlexStorageInterface $storage)
    {
        // Load saved index file.
        $grav = Grav::instance();
        $locator = $grav['locator'];
        $filename = $locator->findResource('user-data://accounts/index.yaml', true, true);

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
