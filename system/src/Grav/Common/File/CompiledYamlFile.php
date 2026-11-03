<?php

/**
 * @package    Grav\Common\File
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\File;

use Grav\Framework\File\Formatter\YamlFormatter;
use RocketTheme\Toolbox\File\YamlFile;

/**
 * Class CompiledYamlFile
 * @package Grav\Common\File
 */
class CompiledYamlFile extends YamlFile
{
    use CompiledFile;

    /**
     * Check contents and make sure it is in correct format.
     *
     * The toolbox encoder writes a `<<` key quoted, but the decoders still read it as the
     * merge key, so the file would load differently from how it was saved.
     *
     * @param mixed $var
     * @return array
     */
    protected function check($var)
    {
        $var = parent::check($var);

        YamlFormatter::assertNoMergeKeys($var);

        return $var;
    }

    /**
     * Decode RAW string into contents.
     *
     * A file with a plain `y:`, `yes:`, `on:` or `off:` key (or `n:`, `no:`) is parsed by Symfony, because
     * libyaml reads those keys as booleans. Configuration, blueprints and languages then get the same keys with
     * or without the yaml extension. See YamlFormatter::hasBooleanLikeKey().
     *
     * @param string $var
     * @return array
     */
    protected function decode($var)
    {
        if (function_exists('yaml_parse') && $this->setting('native', true)
            && YamlFormatter::hasBooleanLikeKey((string)$var)) {
            $settings = $this->settings;
            $this->settings = ['native' => false] + $settings;
            try {
                return parent::decode($var);
            } finally {
                $this->settings = $settings;
            }
        }

        return parent::decode($var);
    }
}
