<?php
namespace Grav\Common\Config;

use Grav\Common\Grav;
use Grav\Component\Blueprints\Blueprints;
use Grav\Component\Filesystem\Folder;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

/**
 * The Config class contains configuration information.
 *
 * @author RocketTheme
 * @license MIT
 */
class ConfigServiceProvider implements ServiceProviderInterface
{
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
        $file = CACHE_DIR . 'compiled/config/master.php';
        $data = is_file($file) ? (array) include $file : [];
        if ($data) {
            try {
                $config = new Config($data, $container);
            } catch (\Exception $e) {
            }
        }

        if (!isset($config)) {
            $file = GRAV_ROOT . '/config.php';
            $data = is_file($file) ? (array) include $file : [];
            $config = new Config($data, $container);
        }

        return $config;
    }

    public function loadMasterBlueprints(Container $container)
    {
        $file = CACHE_DIR . 'compiled/blueprints/master.php';
        $data = is_file($file) ? (array) include $file : [];

        return new Blueprints($data, $container);
    }
}
