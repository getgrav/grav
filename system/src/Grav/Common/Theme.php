<?php

/**
 * @package    Grav\Common
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

use Grav\Common\Config\Config;
use RocketTheme\Toolbox\File\YamlFile;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Class Theme
 * @package Grav\Common
 */
class Theme extends Plugin
{
    /**
     * Constructor.
     *
     * @param Grav   $grav
     * @param Config $config
     * @param string $name
     */
    public function __construct(Grav $grav, Config $config, $name)
    {
        parent::__construct($name, $grav, $config);
    }

    /**
     * Get configuration of the plugin.
     *
     * @return array
     */
    public function config()
    {
        return $this->config["themes.{$this->name}"] ?? [];
    }

    /**
     * Persists to disk the theme parameters currently stored in the Grav Config object
     *
     * @param string $name The name of the theme whose config it should store.
     * @return bool
     */
    public static function saveConfig($name)
    {
        if (!$name) {
            return false;
        }

        $grav = Grav::instance();

        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];

        $filename = 'config://themes/' . $name . '.yaml';
        $file = YamlFile::instance((string)$locator->findResource($filename, true, true));
        $content = $grav['config']->get('themes.' . $name);
        $file->save($content);
        $file->free();
        unset($file);

        return true;
    }

    /**
     * Load blueprints.
     *
     * @return void
     */
    protected function loadBlueprint()
    {
        if (!$this->blueprint) {
            $grav = Grav::instance();
            /** @var Themes $themes */
            $themes = $grav['themes'];
            $data = $themes->get($this->name);
            \assert($data !== null);
            $this->blueprint = $data->blueprints();
        }
    }
}
