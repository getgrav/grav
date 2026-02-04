<?php

/**
 * @package    Grav\Common\Helpers
 *
 * @copyright  Copyright (c) 2015 - 2025 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Helpers;

use Exception;
use Grav\Common\Grav;
use Grav\Common\Utils;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
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
    public static function lint(?string $folder = null, ?callable $callback = null)
    {
        if (null !== $folder) {
            $folder = $folder ?: GRAV_ROOT;

            return static::recurseFolder($folder, '(md|yaml)', $callback);
        }

        return array_merge(
            static::lintConfig($callback),
            static::lintPages($callback),
            static::lintBlueprints($callback)
        );
    }

    /**
     * @param callable|null $callback Optional callback for progress: function(string $file, bool $success, ?string $error)
     * @return array
     */
    public static function lintPages(?callable $callback = null)
    {
        return static::recurseFolder('page://', '(md|yaml)', $callback);
    }

    /**
     * @param callable|null $callback Optional callback for progress: function(string $file, bool $success, ?string $error)
     * @return array
     */
    public static function lintConfig(?callable $callback = null)
    {
        return static::recurseFolder('config://', '(md|yaml)', $callback);
    }

    /**
     * @param callable|null $callback Optional callback for progress: function(string $file, bool $success, ?string $error)
     * @return array
     */
    public static function lintBlueprints(?callable $callback = null)
    {
        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];

        $current_theme = Grav::instance()['config']->get('system.pages.theme');
        $theme_path = 'themes://' . $current_theme . '/blueprints';

        $locator->addPath('blueprints', '', [$theme_path]);
        return static::recurseFolder('blueprints://', '(md|yaml)', $callback);
    }

    /**
     * @param string $path
     * @param string $extensions
     * @param callable|null $callback Optional callback for progress: function(string $file, bool $success, ?string $error)
     * @return array
     */
    public static function recurseFolder($path, $extensions = '(md|yaml)', ?callable $callback = null)
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
                Yaml::parse(static::extractYaml($filepath));
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
