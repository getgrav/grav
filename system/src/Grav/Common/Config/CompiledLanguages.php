<?php
/**
 * @package    Grav.Common.Config
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Config;

use Grav\Common\File\CompiledYamlFile;

class CompiledLanguages extends CompiledBase
{
    /**
     * @var int Version number for the compiled file.
     */
    public $version = 1;

    /**
     * @var Languages  Configuration object.
     */
    protected $object;

    /**
     * Create configuration object.
     *
     * @param  array  $data
     */
    protected function createObject(array $data = [])
    {
        $this->object = new Languages($data);
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
        if (preg_match('|languages\.yaml$|', $filename)) {
            $this->object->mergeRecursive((array) $file->content());
        } else {
            $this->object->mergeRecursive([$name => $file->content()]);
        }
        $file->free();
    }
}
