<?php

/**
 * @package    Grav\Common\Helpers
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Helpers;

use Composer\Autoload\ClassLoader;

/**
 * One autoloader in front of the Composer loaders of the enabled plugins.
 *
 * Each plugin brings its own Composer loader, and PHP asks every one of them in turn until one
 * finds the class, so a plugin class near the end of the list walks through all the others
 * first. This loader indexes the class maps and PSR-4 prefixes of those loaders once, and for
 * each class asks only the loaders that could hold it, still in their original order. The
 * first loader that finds the class wins, exactly as in the full walk, so plugins that ship the
 * same library keep loading the same copy.
 *
 * The plugin loaders stay registered behind this one, unchanged: a class this loader does not
 * find (a class_exists() probe, or a prefix a plugin added to its loader later) goes on to them
 * as before, and Composer\InstalledVersions and Plugin::getAutoloader() see them as before.
 *
 * @internal
 */
final class PluginAutoloader
{
    /** @var ClassLoader[] Plugin loaders in autoload order. */
    private $loaders;

    /** @var array<int,array<string,string>> Class map of each loader. */
    private $classMaps = [];

    /** @var array<string,int[]> PSR-4 prefix => loaders that serve it, in order. */
    private $prefixes = [];

    /** @var int[] Loaders asked for every class: PSR-0, fallback directories, include path or APCu. */
    private $always = [];

    /**
     * @param ClassLoader[] $loaders Plugin loaders in autoload order.
     */
    public function __construct(array $loaders)
    {
        $this->loaders = array_values($loaders);

        foreach ($this->loaders as $i => $loader) {
            $classMap = $loader->getClassMap();
            if ($classMap) {
                $this->classMaps[$i] = $classMap;
            }
            foreach (array_keys($loader->getPrefixesPsr4()) as $prefix) {
                $this->prefixes[$prefix][] = $i;
            }
            if ($loader->getPrefixes() || $loader->getFallbackDirs() || $loader->getFallbackDirsPsr4()
                || $loader->getUseIncludePath() || $loader->getApcuPrefix() !== null
            ) {
                $this->always[] = $i;
            }
        }
    }

    /**
     * @return ClassLoader[]
     */
    public function getLoaders(): array
    {
        return $this->loaders;
    }

    /**
     * Put this loader in front of the plugin loaders: take them off the autoload stack,
     * register this one, then register them again in the same order behind it.
     *
     * @return void
     */
    public function register(): void
    {
        foreach ($this->loaders as $loader) {
            $loader->unregister();
        }
        spl_autoload_register([$this, 'loadClass']);
        foreach ($this->loaders as $loader) {
            $loader->register(false);
        }
    }

    /**
     * @return void
     */
    public function unregister(): void
    {
        spl_autoload_unregister([$this, 'loadClass']);
    }

    /**
     * @param string $class
     * @return true|null
     */
    public function loadClass(string $class): ?bool
    {
        foreach ($this->candidates($class) as $i) {
            if ($this->loaders[$i]->loadClass($class)) {
                return true;
            }
        }

        return null;
    }

    /**
     * Path of the file the plugin loaders would load for the class, or false.
     *
     * @param string $class
     * @return string|false
     */
    public function findFile(string $class)
    {
        foreach ($this->candidates($class) as $i) {
            $file = $this->loaders[$i]->findFile($class);
            if ($file !== false) {
                return $file;
            }
        }

        return false;
    }

    /**
     * The loaders that could find the class, in order. Every other loader would miss it.
     *
     * @param string $class
     * @return int[]
     */
    private function candidates(string $class): array
    {
        $candidates = $this->always;

        // A class map hit always loads, so loaders after the first one that maps the class never get asked.
        foreach ($this->classMaps as $i => $classMap) {
            if (isset($classMap[$class])) {
                $candidates[] = $i;
                break;
            }
        }

        if ($this->prefixes) {
            $subPath = $class;
            while (false !== $pos = strrpos($subPath, '\\')) {
                $subPath = substr($subPath, 0, $pos);
                foreach ($this->prefixes[$subPath . '\\'] ?? [] as $i) {
                    $candidates[] = $i;
                }
            }
        }

        if (isset($candidates[1])) {
            $candidates = array_unique($candidates);
            sort($candidates);
        }

        return $candidates;
    }
}
