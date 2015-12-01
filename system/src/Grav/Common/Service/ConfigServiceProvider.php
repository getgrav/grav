<?php
namespace Grav\Common\Service;

use Grav\Common\Config\CompiledBlueprints;
use Grav\Common\Config\CompiledConfig;
use Grav\Common\Config\CompiledLanguages;
use Grav\Common\Config\Config;
use Grav\Common\Config\ConfigFileFinder;
use Grav\Common\Config\Setup;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

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
        $container['setup'] = function ($c) {
            return static::setup($c)->init();
        };

        $container['blueprints'] = function ($c) {
            return static::blueprints($c);
        };

        $container['config'] = function ($c) {
            return static::load($c);
        };

        $container['languages'] = function ($c) {
            return static::languages($c);
        };
    }

    public static function setup(Container $container)
    {
        return new Setup($container['uri']->environment());
    }

    public static function blueprints(Container $container)
    {
        /** Setup $setup */
        $setup = $container['setup'];

        /** @var UniformResourceLocator $locator */
        $locator = $container['locator'];

        $cache =  $locator->findResource('cache://compiled/blueprints', true, true);

        $files = [];
        $paths = $locator->findResources('blueprints://config');
        $files += (new ConfigFileFinder)->locateFiles($paths);
        $paths = $locator->findResources('plugins://');
        $files += (new ConfigFileFinder)->setBase('plugins')->locateInFolders($paths, 'blueprints');

        $blueprints = new CompiledBlueprints($cache, $files, GRAV_ROOT);

        return $blueprints->name("master-{$setup->environment}")->load();
    }

    public static function load(Container $container)
    {
        /** Setup $setup */
        $setup = $container['setup'];

        /** @var UniformResourceLocator $locator */
        $locator = $container['locator'];

        $cache =  $locator->findResource('cache://compiled/config', true, true);

        $files = [];
        $paths = $locator->findResources('config://');
        $files += (new ConfigFileFinder)->locateFiles($paths);
        $paths = $locator->findResources('plugins://');
        $files += (new ConfigFileFinder)->setBase('plugins')->locateInFolders($paths);

        $config = new CompiledConfig($cache, $files, GRAV_ROOT);
        $config->setBlueprints(function() use ($container) {
            return $container['blueprints'];
        });

        return $config->name("master-{$setup->environment}")->load();
    }

    public static function languages(Container $container)
    {
        /** Setup $setup */
        $setup = $container['setup'];

        /** @var Config $config */
        $config = $container['config'];

        /** @var UniformResourceLocator $locator */
        $locator = $container['locator'];

        $cache = $locator->findResource('cache://compiled/languages', true, true);
        $files = [];

        // Process languages only if enabled in configuration.
        if ($config->get('system.languages.translations', true)) {
            $paths = $locator->findResources('languages://');
            $files += (new ConfigFileFinder)->locateFiles($paths);
            $paths = $locator->findResources('plugins://');
            $files += (new ConfigFileFinder)->setBase('plugins')->locateInFolders($paths, 'languages');
        }

        $languages = new CompiledLanguages($cache, $files, GRAV_ROOT);

        return $languages->name("master-{$setup->environment}")->load();
    }
}
