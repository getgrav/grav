<?php
namespace Grav\Common\Service;

use Grav\Component\DI\ServiceProviderInterface;
use Grav\Component\Filesystem\ResourceLocator;
use Grav\Component\Filesystem\StreamWrapper\ReadOnlyStream;
use Grav\Component\Filesystem\StreamWrapper\Stream;
use Pimple\Container;

class StreamsServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $self = $this;

        $container['locator'] = function($c) use ($self) {
            $locator = new ResourceLocator;
            $self->init($c, $locator);

            return $locator;
        };
    }

    protected function init(Container $container, ResourceLocator $locator)
    {
        $schemes = $container['config']->get('streams.schemes');

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
                $type = '\\Grav\\Component\\Filesystem\\StreamWrapper\\' . $type;
            }

            if (!stream_wrapper_register($scheme, $type)) {
                throw new \InvalidArgumentException("Stream '{$type}' could not be initialized.");
            }
        }
    }
}
