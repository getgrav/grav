<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media;

use Exception;
use Grav\Framework\File\Formatter\JsonFormatter;
use Grav\Framework\File\JsonFile;

/**
 * Media Index class
 */
class MediaIndex
{
    /** @var array<string,MediaIndex> */
    protected static array $instances = [];

    protected string $filepath;
    protected ?array $indexes = null;
    protected int $modified = 0;
    protected ?JsonFile $file = null;

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

        try {
            $indexes = ['version' => 1];
            if ($file->exists()) {
                $indexes = $file->load();
                // TODO: Handle B/C
            }
        } catch (Exception $e) {
            // TODO: Broken data lost. Maybe log this?
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

             try {
                 $this->indexes = [];
                 $this->modified = 0;

                if ($file->exists()) {
                    $modified = $file->getModificationTime();
                    $indexes = $file->load();

                    $version = $indexes['version'] ?? null;
                    if ($version !== 1) {
                        // TODO: Handle B/C
                    }

                    unset($indexes['version']);
                    $this->indexes = $indexes;
                    $this->modified = $modified;
                }
            } catch (Exception $e) {
                // No need to catch the error, index will be regenerated.
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
