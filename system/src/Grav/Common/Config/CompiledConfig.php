<?php

/**
 * @package    Grav\Common\Config
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Config;

use Grav\Common\File\CompiledYamlFile;
use function is_callable;

/**
 * Class CompiledConfig
 * @package Grav\Common\Config
 */
class CompiledConfig extends CompiledBase
{
    /** @var callable  Blueprints loader. */
    protected $callable;

    /** @var bool */
    protected $withDefaults = false;

    /**
     * CompiledConfig constructor.
     * @param string $cacheFolder
     * @param array $files
     * @param string $path
     */
    public function __construct($cacheFolder, array $files, $path)
    {
        parent::__construct($cacheFolder, $files, $path);

        $this->version = 1;
    }

    /**
     * Set blueprints for the configuration.
     *
     * @param callable $blueprints
     * @return $this
     */
    public function setBlueprints(callable $blueprints)
    {
        $this->callable = $blueprints;

        return $this;
    }

    /**
     * @param bool $withDefaults
     * @return mixed
     */
    public function load($withDefaults = false)
    {
        $this->withDefaults = $withDefaults;

        return parent::load();
    }

    /**
     * Create configuration object.
     *
     * @param  array  $data
     * @return void
     */
    protected function createObject(array $data = [])
    {
        if ($this->withDefaults && empty($data) && is_callable($this->callable)) {
            $blueprints = $this->callable;
            $data = $blueprints()->getDefaults();
        }

        $this->object = new Config($data, $this->callable);
    }

    /**
     * Finalize configuration object.
     *
     * @return void
     */
    protected function finalizeObject()
    {
        $this->object->checksum($this->checksum());
        $this->object->timestamp($this->timestamp());
    }

    /**
     * Function gets called when cached configuration is saved.
     *
     * @return void
     */
    public function modified()
    {
        $this->object->modified(true);
    }

    /**
     * Load single configuration file and append it to the correct position.
     *
     * @param  string  $name  Name of the position.
     * @param  string  $filename  File to be loaded.
     * @return void
     */
    protected function loadFile($name, $filename)
    {
        $file = CompiledYamlFile::instance($filename);
        $this->object->join($name, $file->content(), '/');
        $file->free();
    }
}
