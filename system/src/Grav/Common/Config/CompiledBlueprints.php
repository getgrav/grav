<?php
/**
 * @package    Grav.Common.Config
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */
namespace Grav\Common\Config;

use Grav\Common\Data\Blueprint;
use Grav\Common\Data\BlueprintSchema;
use Grav\Common\Grav;

class CompiledBlueprints extends CompiledBase
{
    /**
     * @var int Version number for the compiled file.
     */
    public $version = 2;

    /**
     * @var BlueprintSchema  Blueprints object.
     */
    protected $object;

    /**
     * Returns checksum from the configuration files.
     *
     * You can set $this->checksum = false to disable this check.
     *
     * @return bool|string
     */
    public function checksum()
    {
        if (!isset($this->checksum)) {
            $this->checksum = md5(json_encode($this->files) . json_encode($this->getTypes()) . $this->version);
        }

        return $this->checksum;
    }

    /**
     * Create configuration object.
     *
     * @param array  $data
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
     */
    protected function finalizeObject()
    {
    }

    /**
     * Load single configuration file and append it to the correct position.
     *
     * @param  string  $name  Name of the position.
     * @param  array   $files  Files to be loaded.
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

    protected function getState()
    {
        return $this->object->getState();
    }
}
