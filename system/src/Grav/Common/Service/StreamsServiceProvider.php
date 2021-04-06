<?php

/**
 * @package    Grav\Common\Service
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Service;

use Grav\Common\Config\Setup;
use Pimple\Container;
use RocketTheme\Toolbox\DI\ServiceProviderInterface;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RocketTheme\Toolbox\StreamWrapper\ReadOnlyStream;
use RocketTheme\Toolbox\StreamWrapper\Stream;
use RocketTheme\Toolbox\StreamWrapper\StreamBuilder;

/**
 * Class StreamsServiceProvider
 * @package Grav\Common\Service
 */
class StreamsServiceProvider implements ServiceProviderInterface
{
    /**
     * @param Container $container
     * @return void
     */
    public function register(Container $container)
    {
        $container['locator'] = function (Container $container) {
            $locator = new UniformResourceLocator(GRAV_WEBROOT);

            /** @var Setup $setup */
            $setup = $container['setup'];
            $setup->initializeLocator($locator);

            return $locator;
        };

        $container['streams'] = function (Container $container) {
            /** @var Setup $setup */
            $setup = $container['setup'];

            /** @var UniformResourceLocator $locator */
            $locator = $container['locator'];

            // Set locator to both streams.
            Stream::setLocator($locator);
            ReadOnlyStream::setLocator($locator);

            return new StreamBuilder($setup->getStreams());
        };
    }
}
