<?php

/**
 * @package    Grav\Common\Config
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Config;

use Grav\Common\File\CompiledYamlFile;

/**
 * Class CompiledLanguages
 * @package Grav\Common\Config
 */
class CompiledLanguages extends CompiledBase
{
    /**
     * CompiledLanguages constructor.
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
     * Create configuration object.
     *
     * @param  array  $data
     * @return void
     */
    protected function createObject(array $data = [])
    {
        $this->object = new Languages($data);
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
        if (preg_match('|languages\.yaml$|', $filename)) {
            $this->object->mergeRecursive((array) $file->content());
        } else {
            $this->object->mergeRecursive([$name => $file->content()]);
        }
        $file->free();
    }
}
