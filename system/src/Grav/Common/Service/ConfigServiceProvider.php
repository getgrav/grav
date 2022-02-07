<?php

/**
 * @package    Grav\Common\Service
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Service;

use DirectoryIterator;
use Grav\Common\Config\CompiledBlueprints;
use Grav\Common\Config\CompiledConfig;
use Grav\Common\Config\CompiledLanguages;
use Grav\Common\Config\Config;
use Grav\Common\Config\ConfigFileFinder;
use Grav\Common\Config\Setup;
use Grav\Common\Language\Language;
use Grav\Framework\Mime\MimeTypes;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use RocketTheme\Toolbox\File\YamlFile;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Class ConfigServiceProvider
 * @package Grav\Common\Service
 */
class ConfigServiceProvider implements ServiceProviderInterface
{
    /**
     * @param Container $container
     * @return void
     */
    public function register(Container $container)
    {
        $container['setup'] = function ($c) {
            $setup = new Setup($c);
            $setup->init();

            return $setup;
        };

        $container['blueprints'] = function ($c) {
            return static::blueprints($c);
        };

        $container['config'] = function ($c) {
            $config = static::load($c);

            // After configuration has been loaded, we can disable YAML compatibility if strict mode has been enabled.
            if (!$config->get('system.strict_mode.yaml_compat', true)) {
                YamlFile::globalSettings(['compat' => false, 'native' => true]);
            }

            return $config;
        };

        $container['mime'] = function ($c) {
            /** @var Config $config */
            $config = $c['config'];
            $mimes = $config->get('mime.types', []);
            foreach ($config->get('media.types', []) as $ext => $media) {
                if (!empty($media['mime'])) {
                    $mimes[$ext] = array_unique(array_merge([$media['mime']], $mimes[$ext] ?? []));
                }
            }

            return MimeTypes::createFromMimes($mimes);
        };

        $container['languages'] = function ($c) {
            return static::languages($c);
        };

        $container['language'] = function ($c) {
            return new Language($c);
        };
    }

    /**
     * @param Container $container
     * @return mixed
     */
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
        $paths = $locator->findResources('themes://');
        $files += (new ConfigFileFinder)->setBase('themes')->locateInFolders($paths, 'blueprints');

        $blueprints = new CompiledBlueprints($cache, $files, GRAV_ROOT);

        return $blueprints->name("master-{$setup->environment}")->load();
    }

    /**
     * @param Container $container
     * @return Config
     */
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
        $paths = $locator->findResources('themes://');
        $files += (new ConfigFileFinder)->setBase('themes')->locateInFolders($paths);

        $compiled = new CompiledConfig($cache, $files, GRAV_ROOT);
        $compiled->setBlueprints(function () use ($container) {
            return $container['blueprints'];
        });

        $config = $compiled->name("master-{$setup->environment}")->load();
        $config->environment = $setup->environment;

        return $config;
    }

    /**
     * @param Container $container
     * @return mixed
     */
    public static function languages(Container $container)
    {
        /** @var Setup $setup */
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
            $paths = static::pluginFolderPaths($paths, 'languages');
            $files += (new ConfigFileFinder)->locateFiles($paths);
        }

        $languages = new CompiledLanguages($cache, $files, GRAV_ROOT);

        return $languages->name("master-{$setup->environment}")->load();
    }

    /**
     * Find specific paths in plugins
     *
     * @param array $plugins
     * @param string $folder_path
     * @return array
     */
    protected static function pluginFolderPaths($plugins, $folder_path)
    {
        $paths = [];

        foreach ($plugins as $path) {
            $iterator = new DirectoryIterator($path);

            /** @var DirectoryIterator $directory */
            foreach ($iterator as $directory) {
                if (!$directory->isDir() || $directory->isDot()) {
                    continue;
                }

                // Path to the languages folder
                $lang_path = $directory->getPathName() . '/' . $folder_path;

                // If this folder exists, add it to the list of paths
                if (file_exists($lang_path)) {
                    $paths []= $lang_path;
                }
            }
        }
        return $paths;
    }
}
