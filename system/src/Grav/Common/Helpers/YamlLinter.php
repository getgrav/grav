<?php

/**
 * @package    Grav\Common\Helpers
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Helpers;

use Exception;
use Grav\Common\Grav;
use Grav\Common\Utils;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use RocketTheme\Toolbox\Compat\Yaml\Yaml as CompatYaml;
use RocketTheme\Toolbox\File\MarkdownFile;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use Symfony\Component\Yaml\Yaml;

/**
 * Class YamlLinter
 * @package Grav\Common\Helpers
 */
class YamlLinter
{
    /**
     * @param string|null $folder
     * @param callable|null $callback Optional callback for progress: function(string $file, bool $success, ?string $error)
     * @return array
     */
    public static function lint(?string $folder = null, ?callable $callback = null, bool $strict = false)
    {
        if (null !== $folder) {
            $folder = $folder ?: GRAV_ROOT;

            return static::recurseFolder($folder, '(md|yaml)', $callback, $strict);
        }

        return array_merge(
            static::lintConfig($callback, $strict),
            static::lintPages($callback, $strict),
            static::lintBlueprints($callback, $strict),
            static::lintEnvironments($callback, $strict)
        );
    }

    /**
     * @param callable|null $callback Optional callback for progress: function(string $file, bool $success, ?string $error)
     * @return array
     */
    public static function lintPages(?callable $callback = null, bool $strict = false)
    {
        return static::recurseFolder('page://', '(md|yaml)', $callback, $strict);
    }

    /**
     * @param callable|null $callback Optional callback for progress: function(string $file, bool $success, ?string $error)
     * @param bool $strict Use the stricter Compat YAML parser (matches runtime behavior)
     * @return array
     */
    public static function lintConfig(?callable $callback = null, bool $strict = false)
    {
        return static::recurseFolder('config://', '(md|yaml)', $callback, $strict);
    }

    /**
     * @param callable|null $callback Optional callback for progress: function(string $file, bool $success, ?string $error)
     * @param bool $strict Use the stricter Compat YAML parser (matches runtime behavior)
     * @return array
     */
    public static function lintEnvironments(?callable $callback = null, bool $strict = false)
    {
        $lint_errors = [];
        $user_path = GRAV_ROOT . '/' . GRAV_USER_PATH;

        // Scan Grav 1.6 style: user/<hostname>/config/
        foreach (glob($user_path . '/*/config', GLOB_ONLYDIR) as $envConfigDir) {
            $envName = basename(dirname($envConfigDir));
            // Skip known non-environment directories
            if (in_array($envName, ['config', 'plugins', 'themes', 'pages', 'accounts', 'data', 'assets'])) {
                continue;
            }
            $lint_errors = array_merge($lint_errors, static::recurseFolder($envConfigDir, '(md|yaml)', $callback, $strict));
        }

        // Scan Grav 1.7+ style: user/env/<hostname>/config/
        $envPath = $user_path . '/env';
        if (is_dir($envPath)) {
            foreach (glob($envPath . '/*/config', GLOB_ONLYDIR) as $envConfigDir) {
                $lint_errors = array_merge($lint_errors, static::recurseFolder($envConfigDir, '(md|yaml)', $callback, $strict));
            }
        }

        return $lint_errors;
    }

    /**
     * @param callable|null $callback Optional callback for progress: function(string $file, bool $success, ?string $error)
     * @param bool $strict Use the stricter Compat YAML parser (matches runtime behavior)
     * @return array
     */
    public static function lintBlueprints(?callable $callback = null, bool $strict = false)
    {
        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];

        $current_theme = Grav::instance()['config']->get('system.pages.theme');
        $theme_path = 'themes://' . $current_theme . '/blueprints';

        $locator->addPath('blueprints', '', [$theme_path]);
        return static::recurseFolder('blueprints://', '(md|yaml)', $callback, $strict);
    }

    /**
     * @param string $path
     * @param string $extensions
     * @param callable|null $callback Optional callback for progress: function(string $file, bool $success, ?string $error)
     * @return array
     */
    public static function recurseFolder($path, $extensions = '(md|yaml)', ?callable $callback = null, bool $strict = false)
    {
        $lint_errors = [];

        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];
        $flags = RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::FOLLOW_SYMLINKS;
        if ($locator->isStream($path)) {
            $directory = $locator->getRecursiveIterator($path, $flags);
        } else {
            $directory = new RecursiveDirectoryIterator($path, $flags);
        }
        $recursive = new RecursiveIteratorIterator($directory, RecursiveIteratorIterator::SELF_FIRST);
        $iterator = new RegexIterator($recursive, '/^.+\.'.$extensions.'$/ui');

        /** @var RecursiveDirectoryIterator $file */
        foreach ($iterator as $filepath => $file) {
            $relativePath = str_replace(GRAV_ROOT, '', $filepath);
            try {
                $yaml = static::extractYaml($filepath);
                if ($strict) {
                    CompatYaml::parse($yaml);
                } else {
                    Yaml::parse($yaml);
                }
                if ($callback) {
                    $callback($relativePath, true, null);
                }
            } catch (Exception $e) {
                $lint_errors[$relativePath] = $e->getMessage();
                if ($callback) {
                    $callback($relativePath, false, $e->getMessage());
                }
            }
        }

        return $lint_errors;
    }

    /**
     * @param string $path
     * @return string
     */
    protected static function extractYaml($path)
    {
        $extension = Utils::pathinfo($path, PATHINFO_EXTENSION);
        if ($extension === 'md') {
            $file = MarkdownFile::instance($path);
            $contents = $file->frontmatter();
            $file->free();
        } else {
            $contents = file_get_contents($path);
        }
        return $contents;
    }
}
