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
    protected $schemes = [];

    public function register(Container $container)
    {
        $self = $this;

        $container['locator'] = function($c) use ($self) {
            $locator = new UniformResourceLocator(ROOT_DIR);
            $self->init($c, $locator);

            return $locator;
        };

        $container['streams'] = function($c) use ($self) {
            $locator = $c['locator'];

            // Set locator to both streams.
            Stream::setLocator($locator);
            ReadOnlyStream::setLocator($locator);

            return new StreamBuilder($this->schemes);
        };
    }

    protected function init(Container $container, UniformResourceLocator $locator)
    {
        /** @var Config $config */
        $config = $container['config'];
        $schemes = (array) $config->get('streams.schemes', []);

        foreach ($schemes as $scheme => $config) {
            if (isset($config['paths'])) {
                $locator->addPath($scheme, '', $config['paths']);
            }
            if (isset($config['prefixes'])) {
                foreach ($config['prefixes'] as $prefix => $paths) {
                    $locator->addPath($scheme, $prefix, $paths);
                }
            }

            $type = !empty($config['type']) ? $config['type'] : 'ReadOnlyStream';
            if ($type[0] != '\\') {
                $type = '\\RocketTheme\\Toolbox\\StreamWrapper\\' . $type;
            }

            $this->schemes[$scheme] = $type;
        }
    }
}
