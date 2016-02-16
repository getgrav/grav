<?php
namespace Grav\Common\Config;

use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use RocketTheme\Toolbox\Blueprints\Blueprints;

/**
 * The Compiled Blueprints class.
 */
class CompiledBlueprints extends CompiledBase
{
    /**
     * @var int Version number for the compiled file.
     */
    public $version = 2;

    /**
     * @var Blueprints  Blueprints object.
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
        $this->object = (new Blueprints($data))->setTypes($this->getTypes());
    }

    protected function getTypes()
    {
        return Grav::instance()['plugins']->formFieldTypes;
    }

    /**
     * Finalize configuration object.
     */
    protected function finalizeObject()
    {
        $this->object->init('static');
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
        $this->object->embed($name, $file->content(), '/');
        $file->free();
    }
}
