<?php

/**
 * @package    Grav\Common\Config
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Config;

use BadMethodCallException;
use Exception;
use Grav\Common\Grav;
use RocketTheme\Toolbox\File\PhpFile;
use RuntimeException;
use Throwable;
use function filter_var;
use function function_exists;
use function get_class;
use function ini_get;
use function is_array;

/**
 * Class CompiledBase
 * @package Grav\Common\Config
 */
abstract class CompiledBase
{
    /** @var int Version number for the compiled file. */
    public $version = 1;

    /** @var string  Filename (base name) of the compiled configuration. */
    public $name;

    /** @var string|bool  Configuration checksum. */
    public $checksum;

    /** @var int  Timestamp of compiled configuration */
    public $timestamp = 0;

    /** @var string Cache folder to be used. */
    protected $cacheFolder;

    /** @var array  List of files to load. */
    protected $files;

    /** @var string */
    protected $path;

    /** @var mixed  Configuration object. */
    protected $object;

    /**
     * @param  string $cacheFolder  Cache folder to be used.
     * @param  array  $files  List of files as returned from ConfigFileFinder class.
     * @param string $path  Base path for the file list.
     * @throws BadMethodCallException
     */
    public function __construct($cacheFolder, array $files, $path)
    {
        if (!$cacheFolder) {
            throw new BadMethodCallException('Cache folder not defined.');
        }

        $this->path = $path ? rtrim($path, '\\/') . '/' : '';
        $this->cacheFolder = $cacheFolder;
        $this->files = $files;
    }

    /**
     * Get filename for the compiled PHP file.
     *
     * @param string|null $name
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
     *
     * @return void
     */
    public function modified()
    {
    }

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
        if (null === $this->checksum) {
            $this->checksum = md5(json_encode($this->files) . $this->version);
        }

        return $this->checksum;
    }

    /**
     * @return string
     */
    protected function createFilename()
    {
        return "{$this->cacheFolder}/{$this->name()->name}.php";
    }

    /**
     * Create configuration object.
     *
     * @param  array  $data
     * @return void
     */
    abstract protected function createObject(array $data = []);

    /**
     * Finalize configuration object.
     *
     * @return void
     */
    abstract protected function finalizeObject();

    /**
     * Load single configuration file and append it to the correct position.
     *
     * @param  string  $name  Name of the position.
     * @param  string|string[]  $filename  File(s) to be loaded.
     * @return void
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
        if (!is_array($cache)
            || !isset($cache['checksum'], $cache['data'], $cache['@class'])
            || $cache['@class'] !== static::class
        ) {
            return false;
        }

        // Load real file if cache isn't up to date (or is invalid).
        if ($cache['checksum'] !== $this->checksum()) {
            return false;
        }

        $this->createObject($cache['data']);
        $this->timestamp = $cache['timestamp'] ?? 0;

        $this->finalizeObject();

        return true;
    }

    /**
     * Save compiled file.
     *
     * @param  string  $filename
     * @return void
     * @internal
     */
    protected function saveCompiledFile($filename)
    {
        $file = PhpFile::instance($filename);

        // Attempt to lock the file for writing.
        try {
            $file->lock(false);
        } catch (Exception) {
            // Another process has locked the file; we will check this in a bit.
        }

        if ($file->locked() === false) {
            // File was already locked by another process.
            return;
        }

        $cache = [
            '@class' => static::class,
            'timestamp' => time(),
            'checksum' => $this->checksum(),
            'files' => $this->files,
            'data' => $this->getState()
        ];

        // The compiled file is a cache and can always be rebuilt from the source
        // YAML. If it cannot be written we serve the request from the freshly
        // parsed files instead of taking the whole site down: this runs during
        // config init, before the logger, the error handler and the Problems
        // plugin exist, so an exception here 500s every route including /admin
        // and leaves no in-browser way back. (#4260)
        try {
            $file->save($cache);
            $file->unlock();

            $this->preloadOpcodeCache($file);

            $file->free();

            $this->modified();
        } catch (Throwable $e) {
            static::logCacheWriteFailure($filename, $e->getMessage());

            $file->unlock();
            $file->free();
        }
    }

    /**
     * Record that a compiled cache file could not be written and that the request
     * is being served uncached.
     *
     * Degrading is the right behaviour, but doing it silently hides what is
     * almost always a directory permission problem, so name the directory and say
     * what is wrong with it. The logger is resolved defensively and the whole
     * call is guarded, so reporting a degraded cache can never itself become the
     * fatal we are recovering from.
     *
     * @param string $filename Cache file that could not be written.
     * @param string $reason   Failure reported by the writer.
     * @return void
     */
    public static function logCacheWriteFailure(string $filename, string $reason): void
    {
        $dir = dirname($filename);
        if (!is_dir($dir)) {
            $hint = sprintf('the directory %s does not exist', $dir);
        } elseif (!is_writable($dir)) {
            $hint = sprintf('the directory %s is not writable by the web server user', $dir);
        } else {
            $hint = sprintf('the directory %s is writable, so the file itself may be owned by another user', $dir);
        }

        $message = sprintf(
            'Could not write compiled cache %s (%s) - %s. Serving this request uncached.',
            $filename,
            $reason,
            $hint
        );

        try {
            $log = Grav::instance()['log'] ?? null;
            if ($log) {
                $log->warning($message);

                return;
            }
        } catch (Throwable) {
            // Logging is best-effort: never let it mask the recovery it reports.
        }

        error_log('Grav: ' . $message);
    }

    /**
     * @return array
     */
    protected function getState()
    {
        return $this->object->toArray();
    }

    /**
     * Ensure compiled cache file is primed into OPcache when available.
     */
    protected function preloadOpcodeCache(PhpFile $file): void
    {
        if (!function_exists('opcache_invalidate') || !$this->isOpcacheEnabled()) {
            return;
        }

        $filename = $file->filename();
        if (!$filename) {
            return;
        }

        // Silence errors for restricted functions while keeping best effort behavior.
        @opcache_invalidate($filename, true);

        if (function_exists('opcache_compile_file')) {
            @opcache_compile_file($filename);
        }
    }

    /**
     * Detect if OPcache is active for current SAPI.
     */
    protected function isOpcacheEnabled(): bool
    {
        $enabled = filter_var(ini_get('opcache.enable'), \FILTER_VALIDATE_BOOLEAN);

        if (PHP_SAPI === 'cli') {
            $enabled = $enabled || filter_var(ini_get('opcache.enable_cli'), \FILTER_VALIDATE_BOOLEAN);
        }

        return $enabled;
    }
}
