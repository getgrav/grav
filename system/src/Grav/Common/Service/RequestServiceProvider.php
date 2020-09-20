<?php

/**
 * @package    Grav\Common\Service
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Service;

use Grav\Common\Uri;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

/**
 * Class RequestServiceProvider
 * @package Grav\Common\Service
 */
class RequestServiceProvider implements ServiceProviderInterface
{
    /**
     * @param Container $container
     * @return void
     */
    public function register(Container $container)
    {
        $container['request'] = function () {
            $psr17Factory = new Psr17Factory();
            $creator = new ServerRequestCreator(
                $psr17Factory, // ServerRequestFactory
                $psr17Factory, // UriFactory
                $psr17Factory, // UploadedFileFactory
                $psr17Factory  // StreamFactory
            );

            return $creator->fromGlobals();
        };

        $container['route'] = $container->factory(function () {
            return clone Uri::getCurrentRoute();
        });
    }
}
