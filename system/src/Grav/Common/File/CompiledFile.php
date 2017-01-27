<?php
/**
 * @package    Grav.Common.File
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\File;

use RocketTheme\Toolbox\File\PhpFile;

trait CompiledFile
{
    /**
     * Get/set parsed file contents.
     *
     * @param mixed $var
     * @return string
     */
    public function content($var = null)
    {
        // Set some options
        $this->settings(['native' => true, 'compat' => true]);

        try {
            // If nothing has been loaded, attempt to get pre-compiled version of the file first.
            if ($var === null && $this->raw === null && $this->content === null) {
                $key = md5($this->filename);
                $file = PhpFile::instance(CACHE_DIR . DS . "compiled/files/{$key}{$this->extension}.php");

                $modified = $this->modified();

                if (!$modified) {
                    return $this->decode($this->raw());
                }

                $class = get_class($this);

                $cache = $file->exists() ? $file->content() : null;

                // Load real file if cache isn't up to date (or is invalid).
                if (
                    !isset($cache['@class'])
                    || $cache['@class'] != $class
                    || $cache['modified'] != $modified
                    || $cache['filename'] != $this->filename
                ) {
                    // Attempt to lock the file for writing.
                    try {
                        $file->lock(false);
                    } catch (\Exception $e) {
                        // Another process has locked the file; we will check this in a bit.
                    }

                    // Decode RAW file into compiled array.
                    $data = (array)$this->decode($this->raw());
                    $cache = [
                        '@class' => $class,
                        'filename' => $this->filename,
                        'modified' => $modified,
                        'data' => $data
                    ];

                    // If compiled file wasn't already locked by another process, save it.
                    if ($file->locked() !== false) {
                        $file->save($cache);
                        $file->unlock();

                        // Compile cached file into bytecode cache
                        if (function_exists('opcache_invalidate')) {
                            // Silence error if function exists, but is restricted.
                            @opcache_invalidate($file->filename(), true);
                        }
                    }
                }
                $file->free();

                $this->content = $cache['data'];
            }

        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf('Failed to read %s: %s', basename($this->filename), $e->getMessage()), 500, $e);
        }

        return parent::content($var);
    }
}
