<?php
namespace Grav\Common\File;

use RocketTheme\Toolbox\File\PhpFile;

/**
 * Class CompiledFile
 * @package Grav\Common\File
 *
 * @property string $filename
 * @property string $extension
 * @property string $raw
 * @property array|string $content
 */
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
        // If nothing has been loaded, attempt to get pre-compiled version of the file first.
        if ($var === null && $this->raw === null && $this->content === null) {
            $key = md5($this->filename);
            $file = PhpFile::instance(CACHE_DIR . "/compiled/files/{$key}{$this->extension}.php");
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
                $file->lock(false);

                // Decode RAW file into compiled array.
                $data = $this->decode($this->raw());
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
                }
            }

            $this->content = $cache['data'];
        }

        return parent::content($var);
    }
}
