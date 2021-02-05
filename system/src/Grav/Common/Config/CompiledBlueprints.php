<?php

/**
 * @package    Grav\Common\Config
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Config;

use Grav\Common\Data\Blueprint;
use Grav\Common\Data\BlueprintSchema;
use Grav\Common\Grav;

/**
 * Class CompiledBlueprints
 * @package Grav\Common\Config
 */
class CompiledBlueprints extends CompiledBase
{
    /**
     * CompiledBlueprints constructor.
     * @param string $cacheFolder
     * @param array $files
     * @param string $path
     */
    public function __construct($cacheFolder, array $files, $path)
    {
        parent::__construct($cacheFolder, $files, $path);

        $this->version = 2;
    }

    /**
     * Returns checksum from the configuration files.
     *
     * You can set $this->checksum = false to disable this check.
     *
     * @return bool|string
     */
    public function checksum()
    {
        if (null === $this->checksum) {
            $this->checksum = md5(json_encode($this->files) . json_encode($this->getTypes()) . $this->version);
        }

        return $this->checksum;
    }

    /**
     * Create configuration object.
     *
     * @param array $data
     */
    protected function createObject(array $data = [])
    {
        $this->object = (new BlueprintSchema($data))->setTypes($this->getTypes());
    }

    /**
     * Get list of form field types.
     *
     * @return array
     */
    protected function getTypes()
    {
        return Grav::instance()['plugins']->formFieldTypes ?: [];
    }

    /**
     * Finalize configuration object.
     *
     * @return void
     */
    protected function finalizeObject()
    {
    }

    /**
     * Load single configuration file and append it to the correct position.
     *
     * @param  string  $name  Name of the position.
     * @param  array   $files  Files to be loaded.
     * @return void
     */
    protected function loadFile($name, $files)
    {
        // Load blueprint file.
        $blueprint = new Blueprint($files);

        $this->object->embed($name, $blueprint->load()->toArray(), '/', true);
    }

    /**
     * Load and join all configuration files.
     *
     * @return bool
     * @internal
     */
    protected function loadFiles()
    {
        $this->createObject();

        // Convert file list into parent list.
        $list = [];
        /** @var array $files */
        foreach ($this->files as $files) {
            foreach ($files as $name => $item) {
                $list[$name][] = $this->path . $item['file'];
            }
        }

        // Load files.
        foreach ($list as $name => $files) {
            $this->loadFile($name, $files);
        }

        $this->finalizeObject();

        return true;
    }

    /**
     * @return array
     */
    protected function getState()
    {
        return $this->object->getState();
    }
}
