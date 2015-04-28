<?php
namespace Grav\Common\Service;

use Grav\Common\Config\Config;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use RocketTheme\Toolbox\Blueprints\Blueprints;

/**
 * The Config class contains configuration information.
 *
 * @author RocketTheme
 * @license MIT
 */
class ConfigServiceProvider implements ServiceProviderInterface
{
    private $environment;
    private $setup;

    public function register(Container $container)
    {
        $self = $this;

        // Pre-load setup.php as it contains our initial configuration.
        $file = GRAV_ROOT . '/setup.php';
        $this->setup = is_file($file) ? (array) include $file : [];
        $this->environment = isset($this->setup['environment']) ? $this->setup['environment'] : null;

        $container['blueprints'] = function ($c) use ($self) {
            return $self->loadMasterBlueprints($c);
        };

        $container['config'] = function ($c) use ($self) {
            return $self->loadMasterConfig($c);
        };
    }

    public function loadMasterConfig(Container $container)
    {
        $environment = $this->getEnvironment($container);

        $config = new Config($this->setup, $container, $environment);

        return $config;
    }

    public function loadMasterBlueprints(Container $container)
    {
        $environment = $this->getEnvironment($container);
        $file = CACHE_DIR . 'compiled/blueprints/master-'.$environment.'.php';
        $data = is_file($file) ? (array) include $file : [];

        return new Blueprints($data, $container);
    }

    public function getEnvironment(Container $container)
    {
        if (!isset($this->environment)) {
            $this->environment = $container['uri']->environment();
        }

        return $this->environment;
    }
}
