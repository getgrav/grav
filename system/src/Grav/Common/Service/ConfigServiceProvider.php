<?php
namespace Grav\Common\Service;

use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Common\Filesystem\Folder;
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

    public function register(Container $container)
    {
        $self = $this;

        $container['blueprints'] = function ($c) use ($self) {
            return $self->loadMasterBlueprints($c);
        };

        $container['config'] = function ($c) use ($self) {
            return $self->loadMasterConfig($c);
        };
    }

    public function loadMasterConfig(Container $container)
    {
        $environment = $this->getEnvironment();
        $file = CACHE_DIR . 'compiled/config/master-'.$environment.'.php';
        $data = is_file($file) ? (array) include $file : [];
        if ($data) {
            try {
                $config = new Config($data, $container, $environment);
            } catch (\Exception $e) {
            }
        }

        if (!isset($config)) {
            $file = GRAV_ROOT . '/setup.php';
            $data = is_file($file) ? (array) include $file : [];
            $config = new Config($data, $container, $environment);
        }

        return $config;
    }

    public function loadMasterBlueprints(Container $container)
    {
        $environment = $this->getEnvironment();
        $file = CACHE_DIR . 'compiled/blueprints/master-'.$environment.'.php';
        $data = is_file($file) ? (array) include $file : [];

        return new Blueprints($data, $container);
    }

    public function getEnvironment()
    {
        if (!$this->environment) {
            $address = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '::1';

            // check for localhost variations
            if ($address == '::1' || $address == '127.0.0.1') {
                $hostname = 'localhost';
            } else {
                $hostname = gethostname();
            }
            $this->environment = $hostname;
        }

        return $this->environment;
    }
}
