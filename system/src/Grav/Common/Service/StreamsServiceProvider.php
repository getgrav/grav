<?php
namespace Grav\Common\Service;

use Grav\Common\Config\Config;
use Pimple\Container;
use RocketTheme\Toolbox\DI\ServiceProviderInterface;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RocketTheme\Toolbox\StreamWrapper\ReadOnlyStream;
use RocketTheme\Toolbox\StreamWrapper\Stream;

class StreamsServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $self = $this;

        $container['locator'] = function($c) use ($self) {
            $locator = new UniformResourceLocator;
            $self->init($c, $locator);

            return $locator;
        };
    }

    protected function init(Container $container, UniformResourceLocator $locator)
    {
        /** @var Config $config */
        $config = $container['config'];
        $schemes = $config->get('streams.schemes');

        if (!$schemes) {
            return;
        }

        // Set locator to both streams.
        Stream::setLocator($locator);
        ReadOnlyStream::setLocator($locator);

        $registered = stream_get_wrappers();

        foreach ($schemes as $scheme => $config) {
            if (isset($config['paths'])) {
                $locator->addPath($scheme, '', $config['paths']);
            }
            if (isset($config['prefixes'])) {
                foreach ($config['prefixes'] as $prefix => $paths) {
                    $locator->addPath($scheme, $prefix, $paths);
                }
            }

            if (in_array($scheme, $registered)) {
                stream_wrapper_unregister($scheme);
            }
            $type = !empty($config['type']) ? $config['type'] : 'ReadOnlyStream';
            if ($type[0] != '\\') {
                $type = '\\RocketTheme\\Toolbox\\StreamWrapper\\' . $type;
            }

            if (!stream_wrapper_register($scheme, $type)) {
                throw new \InvalidArgumentException("Stream '{$type}' could not be initialized.");
            }
        }
    }
}
