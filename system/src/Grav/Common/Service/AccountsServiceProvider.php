<?php

/**
 * @package    Grav\Common\Service
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Service;

use Grav\Common\Config\Config;
use Grav\Common\Debugger;
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

class AccountsServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $container['accounts'] = function (Container $container) {
            $type = strtolower(defined('GRAV_USER_INSTANCE') ? GRAV_USER_INSTANCE : $container['config']->get('system.accounts.type', 'data'));
            if ($type === 'flex') {
                /** @var Debugger $debugger */
                $debugger = $container['debugger'];
                $debugger->addMessage('User Accounts: Flex Directory');
                return $this->flexAccounts($container);
            }

            return $this->dataAccounts($container);
        };

        $container['users'] = $container->factory(function (Container $container) {
            user_error('Grav::instance()[\'users\'] is deprecated since Grav 1.6, use Grav::instance()[\'accounts\'] instead', E_USER_DEPRECATED);

            return $container['accounts'];
        });
    }

    protected function dataAccounts(Container $container)
    {
        if (!defined('GRAV_USER_INSTANCE')) {
            define('GRAV_USER_INSTANCE', 'DATA');
        }

        // Use User class for backwards compatibility.
        return new DataUser\UserCollection(User::class);
    }

    protected function flexAccounts(Container $container)
    {
        if (!defined('GRAV_USER_INSTANCE')) {
            define('GRAV_USER_INSTANCE', 'FLEX');
        }

        /** @var Config $config */
        $config = $container['config'];

        $options = [
            'enabled' => true,
            'data' => [
                'object' => User::class, // Use User class for backwards compatibility.
                'collection' => FlexUser\UserCollection::class,
                'index' => FlexUser\UserIndex::class,
                'storage' => $this->getFlexStorage($config->get('system.accounts.storage', 'file')),
                'search' => [
                    'options' => [
                        'contains' => 1
                    ],
                    'fields' => [
                        'key',
                        'email'
                    ]
                ]
            ]
        ] + ($config->get('plugins.flex-objects.object') ?: []);

        $directory = new FlexDirectory('accounts', 'blueprints://user/accounts.yaml', $options);

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
                    'key' => 'username',
                    'indexed' => true
                ],
            ];
        }

        return [
            'class' => FlexUser\Storage\UserFileStorage::class,
            'options' => [
                'formatter' => ['class' => YamlFormatter::class],
                'folder' => 'account://',
                'pattern' => '{FOLDER}/{KEY}.yaml',
                'key' => 'storage_key',
                'indexed' => true
            ],
        ];
    }
}
