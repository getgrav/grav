<?php
namespace Grav\Common\Service;

use Grav\Common\Config\Config;
use Pimple\Container;
use RocketTheme\Toolbox\DI\ServiceProviderInterface;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RocketTheme\Toolbox\StreamWrapper\ReadOnlyStream;
use RocketTheme\Toolbox\StreamWrapper\Stream;
use RocketTheme\Toolbox\StreamWrapper\StreamBuilder;

class StreamsServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $container['locator'] = function($c) {
            $locator = new UniformResourceLocator(ROOT_DIR);

            /** @var Config $config */
            $config = $c['config'];
            $config->initializeLocator($locator);

            return $locator;
        };

        $container['streams'] = function($c) {
            /** @var Config $config */
            $config = $c['config'];

            /** @var UniformResourceLocator $locator */
            $locator = $c['locator'];

            // Set locator to both streams.
            Stream::setLocator($locator);
            ReadOnlyStream::setLocator($locator);

            return new StreamBuilder($config->getStreams($c));
        };
    }
}
