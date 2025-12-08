<?php

/**
 * @package    Grav\Common\File
 *
 * @copyright  Copyright (c) 2015 - 2025 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\File;

use Exception;
use Grav\Common\Debugger;
use Grav\Common\Grav;
use Grav\Common\Utils;
use RocketTheme\Toolbox\File\PhpFile;
use RuntimeException;
use Throwable;
use function function_exists;
use function get_class;

/**
 * Trait CompiledFile
 * @package Grav\Common\File
 */
trait CompiledFile
{
    /**
     * Get/set parsed file contents.
     *
     * @param mixed $var
     * @return array
     */
    public function content(mixed $var = null)
    {
        try {
            $filename = $this->filename;
            // If nothing has been loaded, attempt to get pre-compiled version of the file first.
            if ($var === null && $this->raw === null && $this->content === null) {
                $key = md5($filename);
                $file = PhpFile::instance(CACHE_DIR . "compiled/files/{$key}{$this->extension}.php");
                $cacheFilename = $file->filename();

                // Improved support for opcache
                // Do not check timestamp if opcache.validate_timestamps = 0
                // https://www.php.net/manual/en/opcache.configuration.php#ini.opcache.validate-timestamps
                $modified = false;

                if (!filter_var(ini_get('opcache.enable'), \FILTER_VALIDATE_BOOLEAN) ||
                    filter_var(ini_get('opcache.validate_timestamps'), \FILTER_VALIDATE_BOOLEAN)) {
                    $modified = $this->modified();
                }

                // Improved support for opcache
                //
                // If not modified and file exists in opcache
                // This requires opcache.enable_file_override = 1
                // https://www.php.net/manual/en/opcache.configuration.php#ini.opcache.enable-file-override
                if (!$modified && is_file($cacheFilename)) {
                    try {
                        // Include the file directly to trigger loading from opcache
                        $var = (array) include $cacheFilename;

                        if (is_array($var) && isset($var['data'])) {
                            $var = $var['data'];
                        } else {
                            $var = null;
                        }

                        if (!is_array($var)) {
                            $var = $this->decode($this->raw());
                        }

                        return $var;
                    } catch (Throwable) {
                        // If the compiled file is broken, we can safely ignore the error and continue.
                    }
                }

                $class = get_class($this);

                // Check if the source file exists before getting its size
                if (!is_file($filename)) {
                    return parent::content($var);
                }

                $size = filesize($filename);
                $cache = $file->exists() ? $file->content() : null;

                // Load real file if cache isn't up to date (or is invalid).
                if (!isset($cache['@class'])
                    || $cache['@class'] !== $class
                    || $cache['modified'] !== $modified
                    || ($cache['size'] ?? null) !== $size
                    || $cache['filename'] !== $filename
                ) {
                    // Attempt to lock the file for writing.
                    try {
                        $locked = $file->lock(false);
                    } catch (Exception $e) {
                        $locked = false;

                        /** @var Debugger $debugger */
                        $debugger = Grav::instance()['debugger'];
                        $debugger->addMessage(sprintf('%s(): Cannot obtain a lock for compiling cache file for %s: %s', __METHOD__, $this->filename, $e->getMessage()), 'warning');
                    }

                    // Decode RAW file into compiled array.
                    $data = (array)$this->decode($this->raw());
                    $cache = [
                        '@class' => $class,
                        'filename' => $filename,
                        'modified' => $modified,
                        'size' => $size,
                        'data' => $data
                    ];

                    // If compiled file wasn't already locked by another process, save it.
                    if ($locked) {
                        $file->save($cache);
                        $file->unlock();

                        // Compile cached file into bytecode cache
                        if (function_exists('opcache_invalidate') && filter_var(ini_get('opcache.enable'), \FILTER_VALIDATE_BOOLEAN)) {
                            // Silence error if function exists, but is restricted.
                            @opcache_invalidate($cacheFilename, true);
                            @opcache_compile_file($cacheFilename);
                        }
                    }
                }
                $file->free();

                $this->content = $cache['data'];
            }
        } catch (Exception $e) {
            throw new RuntimeException(sprintf('Failed to read %s: %s', Utils::basename($filename), $e->getMessage()), 500, $e);
        }

        return parent::content($var);
    }

    /**
     * Save file.
     *
     * @param  mixed  $data  Optional data to be saved, usually array.
     * @return void
     * @throws RuntimeException
     */
    public function save(mixed $data = null)
    {
        // Make sure that the cache file is always up to date!
        $key = md5($this->filename);
        $file = PhpFile::instance(CACHE_DIR . "compiled/files/{$key}{$this->extension}.php");
        try {
            $locked = $file->lock();
        } catch (Exception $e) {
            $locked = false;

            /** @var Debugger $debugger */
            $debugger = Grav::instance()['debugger'];
            $debugger->addMessage(sprintf('%s(): Cannot obtain a lock for compiling cache file for %s: %s', __METHOD__, $this->filename, $e->getMessage()), 'warning');
        }

        parent::save($data);

        if ($locked) {
            $modified = $this->modified();
            $filename = $this->filename;
            $class = get_class($this);
            $size = filesize($filename);

            // windows doesn't play nicely with this as it can't read when locked
            if (!Utils::isWindows()) {
                // Reload data from the filesystem. This ensures that we always cache the correct data (see issue #2282).
                $this->raw = $this->content = null;
                $data = (array)$this->decode($this->raw());
            }

            // Decode data into compiled array.
            $cache = [
                '@class' => $class,
                'filename' => $filename,
                'modified' => $modified,
                'size' => $size,
                'data' => $data
            ];

            $file->save($cache);
            $file->unlock();

            // Compile cached file into bytecode cache
            if (function_exists('opcache_invalidate') && filter_var(ini_get('opcache.enable'), \FILTER_VALIDATE_BOOLEAN)) {
                $cacheFilename = $file->filename();
                // Silence error if function exists, but is restricted.
                @opcache_invalidate($cacheFilename, true);
                @opcache_compile_file($cacheFilename);
            }
        }
    }

    /**
     * Serialize file.
     *
     * @return array
     */
    public function __sleep()
    {
        return [
            'filename',
            'extension',
            'raw',
            'content',
            'settings'
        ];
    }

    /**
     * Unserialize file.
     */
    public function __wakeup()
    {
        if (!isset(static::$instances[$this->filename])) {
            static::$instances[$this->filename] = $this;
        }
    }
}
