<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media;

use Grav\Framework\File\Formatter\JsonFormatter;
use Grav\Framework\File\JsonFile;

/**
 * Media Index class
 */
class MediaIndex
{
    /** @var array */
    protected static $instances = [];

    /** @var string */
    protected $filepath;
    /** @var array */
    protected $indexes;
    /** @var int */
    protected $modified = 0;
    /** @var JsonFile */
    protected $file;

    /**
     * @param string $filepath
     * @return MediaIndex
     */
    public static function getInstance(string $filepath): MediaIndex
    {
        if (!isset(static::$instances[$filepath])) {
            static::$instances[$filepath] = new static($filepath);
        }

        return static::$instances[$filepath];
    }

    /**
     * @param string $id
     * @param bool $reload
     * @return array
     */
    public function get(string $id, bool $reload = false): array
    {
        $indexes = $this->getIndexes($reload);

        $index = $indexes[$id] ?? [];

        return [$index, $this->modified];
    }

    /**
     * @param bool $block
     * @return void
     */
    public function lock(bool $block = true): void
    {
        $this->getFile()->lock($block);
    }

    /**
     * @param int|null $timestamp
     * @return void
     */
    public function touch(string $id, ?int $timestamp = null): void
    {
        $this->getFile()->touch($timestamp);
    }

    /**
     * @param string $id
     * @param array $index
     * @return void
     */
    public function save(string $id, array $index): void
    {
        $file = $this->getFile();
        $file->lock();

        $index = array_filter($index, static function($val) { return $val !== null; } );

        $indexes = $file->exists() ? $file->load() : ['version' => 1];
        $version = $indexes['version'] ?? null;
        if ($version !== 1) {
            $indexes = ['version' => 1];
        }
        $indexes[$id] = $index;

        $file->save($indexes);
        $file->unlock();

        $this->indexes = $indexes;
        $this->modified = $file->getModificationTime();
    }

    protected function getIndexes(bool $reload = true): array
    {
        if (!isset($this->indexes) || $reload) {
            // Read media index file.
            $file = $this->getFile();
            if ($file->exists()) {
                $this->indexes = $file->load();
                $version = $this->indexes['version'] ?? null;
                if ($version !== 1) {
                    $this->indexes = [];
                }
                $this->modified = $file->getModificationTime();
            } else {
                $this->indexes = [];
                $this->modified = 0;
            }
        }

        return $this->indexes;
    }

    /**
     * Get index file, which stores media file index.
     *
     * @return JsonFile
     */
    protected function getFile(): JsonFile
    {
        if (!isset($this->file)) {
            $this->file = new JsonFile($this->filepath, new JsonFormatter(['encode_options' => JSON_PRETTY_PRINT]));
        }

        return $this->file;
    }

    /**
     * @param string $filepath
     */
    private function __construct(string $filepath)
    {
        $this->filepath = $filepath;
    }
}
