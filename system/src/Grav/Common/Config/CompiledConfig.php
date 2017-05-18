<?php
/**
 * @package    Grav.Common.Config
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Config;

use Grav\Common\File\CompiledYamlFile;

class CompiledConfig extends CompiledBase
{
    /**
     * @var int Version number for the compiled file.
     */
    public $version = 1;

    /**
     * @var Config  Configuration object.
     */
    protected $object;

    /**
     * @var callable  Blueprints loader.
     */
    protected $callable;

    /**
     * @var bool
     */
    protected $withDefaults;

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
     */
    protected function finalizeObject()
    {
        $this->object->checksum($this->checksum());
        $this->object->timestamp($this->timestamp());
    }

    /**
     * Function gets called when cached configuration is saved.
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
     */
    protected function loadFile($name, $filename)
    {
        $file = CompiledYamlFile::instance($filename);
        $this->object->join($name, $file->content(), '/');
        $file->free();
    }
}
