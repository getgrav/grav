<?php
namespace Grav\Common\Config;

use Grav\Common\File\CompiledYamlFile;
use RocketTheme\Toolbox\Blueprints\Blueprints;

/**
 * The Compiled Blueprints class.
 */
class CompiledBlueprints extends CompiledBase
{
    /**
     * @var int Version number for the compiled file.
     */
    public $version = 1;

    /**
     * @var Blueprints  Blueprints object.
     */
    protected $object;

    /**
     * Create configuration object.
     *
     * @param array  $data
     */
    protected function createObject(array $data = [])
    {
        $this->object = new Blueprints($data);
    }

    /**
     * Finalize configuration object.
     */
    protected function finalizeObject() {}

    /**
     * Load single configuration file and append it to the correct position.
     *
     * @param  string  $name  Name of the position.
     * @param  string  $filename  File to be loaded.
     */
    protected function loadFile($name, $filename)
    {
        $file = CompiledYamlFile::instance($filename);
        $this->object->embed($name, $file->content(), '/');
        $file->free();
    }
}
