<?php

/**
 * @package    Grav\Common\Helpers
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Helpers;

use Grav\Common\Grav;
use RocketTheme\Toolbox\File\MarkdownFile;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use Symfony\Component\Yaml\Yaml;

class YamlLinter
{
    public static function lint()
    {
        $errors = static::lintConfig();
        $errors = $errors + static::lintPages();
        $errors = $errors + static::lintBlueprints();
        
        return $errors;
    }

    public static function lintPages()
    {
        return static::recurseFolder('page://');
    }

    public static function lintConfig()
    {
        return static::recurseFolder('config://');
    }

    public static function lintBlueprints()
    {
        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];

        $current_theme = Grav::instance()['config']->get('system.pages.theme');
        $theme_path = 'themes://' . $current_theme . '/blueprints';

        $locator->addPath('blueprints', '', [$theme_path]);
        return static::recurseFolder('blueprints://');
    }

    public static function recurseFolder($path, $extensions = 'md|yaml')
    {
        $lint_errors = [];

        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];
        $flags = \RecursiveDirectoryIterator::SKIP_DOTS;
        if ($locator->isStream($path)) {
            $directory = $locator->getRecursiveIterator($path, $flags);
        } else {
            $directory = new \RecursiveDirectoryIterator($path, $flags);
        }
        $recursive = new \RecursiveIteratorIterator($directory, \RecursiveIteratorIterator::SELF_FIRST);
        $iterator = new \RegexIterator($recursive, '/^.+\.'.$extensions.'$/i');

        /** @var \RecursiveDirectoryIterator $file */
        foreach ($iterator as $filepath => $file) {
            try {
                Yaml::parse(static::extractYaml($filepath));
            } catch (\Exception $e) {
                $lint_errors[str_replace(GRAV_ROOT, '', $filepath)] = $e->getMessage();
            }
        }

        return $lint_errors;
    }

    protected static function extractYaml($path)
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        if ($extension === 'md') {
            $file = MarkdownFile::instance($path);
            $contents = $file->frontmatter();
        } else {
            $contents = file_get_contents($path);
        }
        return $contents;
    }

}
