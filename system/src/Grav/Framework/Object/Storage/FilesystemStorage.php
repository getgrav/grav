<?php
/**
 * @package    Grav\Framework\Object\Storage
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Object\Storage;

use Grav\Common\Grav;
use RocketTheme\Toolbox\File\FileInterface;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * FilesystemStorage
 * @package Grav\Framework\Object\Storage
 */
class FilesystemStorage implements StorageInterface
{
    /**
     * @var string
     */
    protected $path;

    /**
     * @var string
     */
    protected $type = 'Grav\\Common\\File\\CompiledJsonFile';

    /**
     * @var string
     */
    protected $extension = '.json';

    /**
     * @param string $path
     * @param string $extension
     * @param string $type
     */
    public function __construct($path, $type = null, $extension = null)
    {
        $this->path = $path;
        if ($type) {
            $this->type = $type;
        }
        if ($extension) {
            $this->extension = $extension;
        }
    }

    /**
     * @param string $key
     * @return bool
     */
    public function exists($key)
    {
        if ($key === null) {
            return false;
        }

        $file = $this->getFile($key);

        return $file->exists();
    }

    /**
     * @param string $key
     * @return array
     */
    public function load($key)
    {
        if ($key === null) {
            return [];
        }

        $file = $this->getFile($key);
        $content = (array)$file->content();
        $file->free();

        return $content;
    }

    /**
     * @param string $key
     * @param array $data
     * @return string
     */
    public function save($key, array $data)
    {
        $file = $this->getFile($key);
        $file->save($data);
        $file->free();

        return $key;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function delete($key)
    {
        $file = $this->getFile($key);
        $result = $file->delete();
        $file->free();

        return $result;
    }

    /**
     * @param string[] $list
     * @return array
     */
    public function loadList(array $list)
    {
        $results = [];
        foreach ($list as $id) {
            $results[$id] = $this->load($id);
        }

        return $results;
    }

    /**
     * @param string $key
     * @return FileInterface
     */
    protected function getFile($key)
    {
        if ($key === null) {
            throw new \RuntimeException('Storage key not defined');
        }

        $filename = "{$this->path}/{$key}{$this->extension}";

        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];

        /** @var FileInterface $type */
        $type = $this->type;

        return $type::instance($locator->findResource($filename, true) ?: $locator->findResource($filename, true, true));
    }
}
