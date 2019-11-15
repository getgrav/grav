<?php

/**
 * @package    Grav\Common\Service
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Service;

use Grav\Common\Config\Config;
use Grav\Common\Flex\Users\Storage\UserFolderStorage;
use Grav\Common\User\DataUser;
use Grav\Common\User\User;
use Grav\Framework\Flex\Flex;
use Grav\Framework\Flex\FlexDirectory;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use RocketTheme\Toolbox\Event\Event;
use Symfony\Component\EventDispatcher\EventDispatcher;

class AccountsServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $container['accounts'] = function (Container $container) {
            $type = $this->initialize($container);
            return $type === 'flex' ? $this->flexAccounts($container) : $this->dataAccounts($container);
        };

        $container['user_groups'] = static function () {
            return (new FlexDirectory('grav-user-groups', 'blueprints://flex/user-groups.yaml', ['enabled' => true]))->getIndex();
        };

        $container['users'] = $container->factory(static function (Container $container) {
            user_error('Grav::instance()[\'users\'] is deprecated since Grav 1.6, use Grav::instance()[\'accounts\'] instead', E_USER_DEPRECATED);

            return $container['accounts'];
        });
    }

    protected function initialize(Container $container): string
    {
        $isDefined = defined('GRAV_USER_INSTANCE');
        $type = strtolower($isDefined ? GRAV_USER_INSTANCE : $container['config']->get('system.accounts.type', 'data'));

        if ($type === 'flex') {
            if (!$isDefined) {
                define('GRAV_USER_INSTANCE', 'FLEX');
            }

            /** @var EventDispatcher $dispatcher */
            $dispatcher = $container['events'];
            $dispatcher->addListener('onFlexInit', static function (Event $event) use ($container) {
                /** @var Flex $flex */
                $flex = $event['flex'];
                $flex->addDirectory($container['accounts']->getFlexDirectory());
                $flex->addDirectory($container['user_groups']->getFlexDirectory());
            });
        } elseif (!$isDefined) {
            define('GRAV_USER_INSTANCE', 'DATA');
        }

        return $type;
    }

    protected function dataAccounts(Container $container)
    {
        // Use User class for backwards compatibility.
        return new DataUser\UserCollection(User::class);
    }

    protected function flexAccounts(Container $container)
    {
        /** @var Config $config */
        $config = $container['config'];

        $options = [
            'enabled' => true,
            'data' => [
                'storage' => $this->getFlexStorage($config->get('system.accounts.storage', 'file')),
            ]
        ] + ($config->get('plugins.flex-objects.object') ?: []);

        $directory = new FlexDirectory('grav-accounts', 'blueprints://flex/accounts.yaml', $options);

        return $directory->getIndex();
    }

    protected function getFlexStorage($config)
    {
        if (\is_array($config)) {
            return $config;
        }

        if ($config === 'folder') {
            return [
                'class' => UserFolderStorage::class,
                'options' => [
                    'file' => 'user',
                    'pattern' => '{FOLDER}/{KEY:2}/{KEY}/{FILE}{EXT}',
                    'key' => 'username',
                ],
            ];
        }

        return [];
    }
}
