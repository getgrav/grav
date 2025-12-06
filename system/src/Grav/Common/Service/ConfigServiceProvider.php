<?php

/**
 * @package    Grav\Common\Service
 *
 * @copyright  Copyright (c) 2015 - 2025 Trilby Media, LLC. All rights reserved.
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
use RocketTheme\Toolbox\File\PhpFile;
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

        $container['blueprints'] = fn($c) => static::blueprints($c);

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

        $container['languages'] = fn($c) => static::languages($c);

        $container['language'] = fn($c) => new Language($c);
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

        $cache = $locator->findResource('cache://compiled/blueprints', true, true);

        // Try to load cached file list to avoid filesystem scanning on every request
        $files = static::loadCachedFileList($locator, $cache, 'blueprints', $setup->environment);

        if ($files === null) {
            // Cache miss - scan filesystem for blueprint files
            $files = [];
            $paths = $locator->findResources('blueprints://config');
            $files += (new ConfigFileFinder)->locateFiles($paths);
            $paths = $locator->findResources('plugins://');
            $files += (new ConfigFileFinder)->setBase('plugins')->locateInFolders($paths, 'blueprints');
            $paths = $locator->findResources('themes://');
            $files += (new ConfigFileFinder)->setBase('themes')->locateInFolders($paths, 'blueprints');

            // Save file list cache for next request
            static::saveCachedFileList($locator, $cache, 'blueprints', $setup->environment, $files);
        }

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

        $cache = $locator->findResource('cache://compiled/config', true, true);

        // Try to load cached file list to avoid filesystem scanning on every request
        $files = static::loadCachedFileList($locator, $cache, 'config', $setup->environment);

        if ($files === null) {
            // Cache miss - scan filesystem for config files
            $files = [];
            $paths = $locator->findResources('config://');
            $files += (new ConfigFileFinder)->locateFiles($paths);
            $paths = $locator->findResources('plugins://');
            $files += (new ConfigFileFinder)->setBase('plugins')->locateInFolders($paths);
            $paths = $locator->findResources('themes://');
            $files += (new ConfigFileFinder)->setBase('themes')->locateInFolders($paths);

            // Save file list cache for next request
            static::saveCachedFileList($locator, $cache, 'config', $setup->environment, $files);
        }

        $compiled = new CompiledConfig($cache, $files, GRAV_ROOT);
        $compiled->setBlueprints(fn() => $container['blueprints']);

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
            // Try to load cached file list to avoid filesystem scanning on every request
            $files = static::loadCachedFileList($locator, $cache, 'languages', $setup->environment);

            if ($files === null) {
                // Cache miss - scan filesystem for language files
                $files = [];
                $paths = $locator->findResources('languages://');
                $files += (new ConfigFileFinder)->locateFiles($paths);
                $paths = $locator->findResources('plugins://');
                $files += (new ConfigFileFinder)->setBase('plugins')->locateInFolders($paths, 'languages');
                $paths = static::pluginFolderPaths($paths, 'languages');
                $files += (new ConfigFileFinder)->locateFiles($paths);

                // Save file list cache for next request
                static::saveCachedFileList($locator, $cache, 'languages', $setup->environment, $files);
            }
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

    /**
     * Load cached file list if still valid (based on directory mtimes).
     *
     * @param UniformResourceLocator $locator
     * @param string $cacheDir
     * @param string $type
     * @param string $environment
     * @return array|null Returns cached files array or null if cache is invalid
     */
    protected static function loadCachedFileList(UniformResourceLocator $locator, string $cacheDir, string $type, string $environment): ?array
    {
        $cacheFile = "{$cacheDir}/filelist-{$type}-{$environment}.php";

        if (!file_exists($cacheFile)) {
            return null;
        }

        $cache = include $cacheFile;

        if (!is_array($cache) || !isset($cache['directories'], $cache['files'])) {
            return null;
        }

        // Validate cache by checking directory mtimes
        foreach ($cache['directories'] as $dir => $mtime) {
            // Check if directory still exists and mtime hasn't changed
            $currentMtime = @filemtime($dir);
            if ($currentMtime === false || $currentMtime !== $mtime) {
                return null;
            }
        }

        return $cache['files'];
    }

    /**
     * Save file list to cache with directory mtimes for validation.
     *
     * @param UniformResourceLocator $locator
     * @param string $cacheDir
     * @param string $type
     * @param string $environment
     * @param array $files
     * @return void
     */
    protected static function saveCachedFileList(UniformResourceLocator $locator, string $cacheDir, string $type, string $environment, array $files): void
    {
        // Collect all directories that were scanned based on type
        $directories = [];

        // Type-specific base directories
        if ($type === 'config') {
            $basePaths = $locator->findResources('config://');
            foreach ($basePaths as $path) {
                if (is_dir($path)) {
                    $directories[$path] = filemtime($path);
                }
            }
        } elseif ($type === 'blueprints') {
            $basePaths = $locator->findResources('blueprints://config');
            foreach ($basePaths as $path) {
                if (is_dir($path)) {
                    $directories[$path] = filemtime($path);
                }
            }
        } elseif ($type === 'languages') {
            $basePaths = $locator->findResources('languages://');
            foreach ($basePaths as $path) {
                if (is_dir($path)) {
                    $directories[$path] = filemtime($path);
                }
            }
        }

        // Get plugin directories (used by all types)
        $pluginPaths = $locator->findResources('plugins://');
        foreach ($pluginPaths as $path) {
            if (is_dir($path)) {
                $directories[$path] = filemtime($path);
                // Also track individual plugin directories for granular invalidation
                $iterator = new DirectoryIterator($path);
                foreach ($iterator as $dir) {
                    if ($dir->isDir() && !$dir->isDot()) {
                        $directories[$dir->getPathname()] = $dir->getMTime();
                    }
                }
            }
        }

        // Get theme directories (used by config and blueprints)
        if ($type !== 'languages') {
            $themePaths = $locator->findResources('themes://');
            foreach ($themePaths as $path) {
                if (is_dir($path)) {
                    $directories[$path] = filemtime($path);
                    // Also track individual theme directories
                    $iterator = new DirectoryIterator($path);
                    foreach ($iterator as $dir) {
                        if ($dir->isDir() && !$dir->isDot()) {
                            $directories[$dir->getPathname()] = $dir->getMTime();
                        }
                    }
                }
            }
        }

        $cache = [
            '@class' => static::class,
            'type' => $type,
            'environment' => $environment,
            'timestamp' => time(),
            'directories' => $directories,
            'files' => $files,
        ];

        // Ensure cache directory exists
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0775, true);
        }

        $cacheFile = "{$cacheDir}/filelist-{$type}-{$environment}.php";
        $file = PhpFile::instance($cacheFile);
        $file->save($cache);
        $file->free();
    }
}
