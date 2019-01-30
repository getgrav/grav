<?php
/**
 * @package    Grav.Common.Service
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Service;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class RequestServiceProvider implements ServiceProviderInterface
{
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

            $request = $creator->fromGlobals();

            // TODO: Remove workaround for bad host header (if using 'php -S localhost:8000 system/router.php').
            $hosts = $request->getHeader('Host');
            $host = end($hosts);

            return $request->withHeader('Host', $host);
        };
    }
}
