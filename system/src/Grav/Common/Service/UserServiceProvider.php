<?php

/**
 * @package    Grav\Common\Service
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Service;

use Grav\Common\Config\Config;
use Grav\Common\User\DataUser;
use Grav\Common\User\FlexUser;
use Grav\Common\User\User;
use Grav\Framework\File\Formatter\YamlFormatter;
use Grav\Framework\Flex\Flex;
use Grav\Framework\Flex\FlexDirectory;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\Event\EventDispatcher;

class UserServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $container['users'] = function (Container $container) {
            if ($container['config']->get('system.accounts.type') === 'flex') {
                return $this->flexUsers($container);
            }

            return $this->dataUsers($container);
        };
    }

    protected function dataUsers(Container $container)
    {
        define('GRAV_USER_INSTANCE', 'DATA');

        // Use User class for backwards compatibility.
        return new DataUser\UserCollection(User::class);
    }

    protected function flexUsers(Container $container)
    {
        define('GRAV_USER_INSTANCE', 'FLEX');

        /** @var Config $config */
        $config = $container['config'];

        $options = [
            'enabled' => true,
            'data' => [
                'object' => User::class, // Use User class for backwards compatibility.
                'collection' => FlexUser\UserCollection::class,
                'index' => FlexUser\UserIndex::class,
                'storage' => $this->getFlexStorage($config->get('system.accounts.storage', 'file'))
            ]
        ];

        $directory = new FlexDirectory('users', 'blueprints://user/accounts.yaml', $options);

        /** @var EventDispatcher $dispatcher */
        $dispatcher = $container['events'];
        $dispatcher->addListener('onFlexInit', function (Event $event) use ($directory) {
            /** @var Flex $flex */
            $flex = $event['flex'];
            $flex->addDirectory($directory);
        });

        return $directory->getIndex();
    }

    protected function getFlexStorage($config)
    {
        if (\is_array($config)) {
            return $config;
        }

        if ($config === 'folder') {
            return [
                'class' => FlexUser\Storage\UserFolderStorage::class,
                'options' => [
                    'formatter' => ['class' => YamlFormatter::class],
                    'folder' => 'account://',
                    'pattern' => '{FOLDER}/{KEY:2}/{KEY}/user.yaml',
                    'indexed' => true
                ]
            ];
        }

        return [
            'class' => FlexUser\Storage\UserFileStorage::class,
            'options' => [
                'formatter' => ['class' => YamlFormatter::class],
                'folder' => 'account://',
                'pattern' => '{FOLDER}/{KEY}.yaml',
                'indexed' => true
            ]
        ];
    }
}
