<?php
/**
 * @package    Grav.Common.Config
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Config;

use RocketTheme\Toolbox\File\PhpFile;

abstract class CompiledBase
{
    /**
     * @var int Version number for the compiled file.
     */
    public $version = 1;

    /**
     * @var string  Filename (base name) of the compiled configuration.
     */
    public $name;

    /**
     * @var string|bool  Configuration checksum.
     */
    public $checksum;

    /**
     * @var string  Timestamp of compiled configuration
     */
    public $timestamp;

    /**
     * @var string Cache folder to be used.
     */
    protected $cacheFolder;

    /**
     * @var array  List of files to load.
     */
    protected $files;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var mixed  Configuration object.
     */
    protected $object;

    /**
     * @param  string $cacheFolder  Cache folder to be used.
     * @param  array  $files  List of files as returned from ConfigFileFinder class.
     * @param string $path  Base path for the file list.
     * @throws \BadMethodCallException
     */
    public function __construct($cacheFolder, array $files, $path)
    {
        if (!$cacheFolder) {
            throw new \BadMethodCallException('Cache folder not defined.');
        }

        $this->path = $path ? rtrim($path, '\\/') . '/' : '';
        $this->cacheFolder = $cacheFolder;
        $this->files = $files;
        $this->timestamp = 0;
    }

    /**
     * Get filename for the compiled PHP file.
     *
     * @param string $name
     * @return $this
     */
    public function name($name = null)
    {
        if (!$this->name) {
            $this->name = $name ?: md5(json_encode(array_keys($this->files)));
        }

        return $this;
    }

    /**
     * Function gets called when cached configuration is saved.
     */
    public function modified() {}

    /**
     * Get timestamp of compiled configuration
     *
     * @return int Timestamp of compiled configuration
     */
    public function timestamp()
    {
        return $this->timestamp ?: time();
    }

    /**
     * Load the configuration.
     *
     * @return mixed
     */
    public function load()
    {
        if ($this->object) {
            return $this->object;
        }

        $filename = $this->createFilename();
        if (!$this->loadCompiledFile($filename) && $this->loadFiles()) {
            $this->saveCompiledFile($filename);
        }

        return $this->object;
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
        if (!isset($this->checksum)) {
            $this->checksum = md5(json_encode($this->files) . $this->version);
        }

        return $this->checksum;
    }

    protected function createFilename()
    {
        return "{$this->cacheFolder}/{$this->name()->name}.php";
    }

    /**
     * Create configuration object.
     *
     * @param  array  $data
     */
    abstract protected function createObject(array $data = []);

    /**
     * Finalize configuration object.
     */
    abstract protected function finalizeObject();

    /**
     * Load single configuration file and append it to the correct position.
     *
     * @param  string  $name  Name of the position.
     * @param  string  $filename  File to be loaded.
     */
    abstract protected function loadFile($name, $filename);

    /**
     * Load and join all configuration files.
     *
     * @return bool
     * @internal
     */
    protected function loadFiles()
    {
        $this->createObject();

        $list = array_reverse($this->files);
        foreach ($list as $files) {
            foreach ($files as $name => $item) {
                $this->loadFile($name, $this->path . $item['file']);
            }
        }

        $this->finalizeObject();

        return true;
    }

    /**
     * Load compiled file.
     *
     * @param  string  $filename
     * @return bool
     * @internal
     */
    protected function loadCompiledFile($filename)
    {
        if (!file_exists($filename)) {
            return false;
        }

        $cache = include $filename;
        if (
            !is_array($cache)
            || !isset($cache['checksum'])
            || !isset($cache['data'])
            || !isset($cache['@class'])
            || $cache['@class'] != get_class($this)
        ) {
            return false;
        }

        // Load real file if cache isn't up to date (or is invalid).
        if ($cache['checksum'] !== $this->checksum()) {
            return false;
        }

        $this->createObject($cache['data']);
        $this->timestamp = isset($cache['timestamp']) ? $cache['timestamp'] : 0;

        $this->finalizeObject();

        return true;
    }

    /**
     * Save compiled file.
     *
     * @param  string  $filename
     * @throws \RuntimeException
     * @internal
     */
    protected function saveCompiledFile($filename)
    {
        $file = PhpFile::instance($filename);

        // Attempt to lock the file for writing.
        try {
            $file->lock(false);
        } catch (\Exception $e) {
            // Another process has locked the file; we will check this in a bit.
        }

        if ($file->locked() === false) {
            // File was already locked by another process.
            return;
        }

        $cache = [
            '@class' => get_class($this),
            'timestamp' => time(),
            'checksum' => $this->checksum(),
            'files' => $this->files,
            'data' => $this->getState()
        ];

        $file->save($cache);
        $file->unlock();
        $file->free();

        $this->modified();
    }

    protected function getState()
    {
        return $this->object->toArray();
    }
}
