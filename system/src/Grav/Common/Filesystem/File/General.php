<?php
namespace Grav\Common\Filesystem\File;

use Grav\Common\Filesystem\FileInterface;

/**
 * General file handling class.
 *
 * @author RocketTheme
 * @license MIT
 */
class General implements FileInterface
{
    /**
     * @var string
     */
    protected $filename;

    /**
     * @var resource
     */
    protected $handle;

    /**
     * @var bool|null
     */
    protected $locked;

    /**
     * @var string
     */
    protected $extension;

    /**
     * @var string  Raw file contents.
     */
    protected $raw;

    /**
     * @var array  Parsed file contents.
     */
    protected $content;

    /**
     * @var array|General[]
     */
    static protected $instances = array();

    /**
     * Get file instance.
     *
     * @param  string  $filename
     * @return FileInterface
     */
    public static function instance($filename)
    {
        if (!isset(static::$instances[$filename])) {
            static::$instances[$filename] = new static;
            static::$instances[$filename]->init($filename);
        }
        return static::$instances[$filename];
    }

    /**
     * Prevent constructor from being used.
     *
     * @internal
     */
    protected function __construct()
    {
    }

    /**
     * Prevent cloning.
     *
     * @internal
     */
    protected function __clone()
    {
        //Me not like clones! Me smash clones!
    }

    /**
     * Set filename.
     *
     * @param $filename
     */
    protected function init($filename)
    {
        $this->filename = $filename;
    }

    /**
     * Get/set the file location.
     *
     * @param  string $var
     * @return string
     */
    public function filename($var = null)
    {
        if ($var !== null) {
            $this->filename = $var;
        }
        return $this->filename;
    }

    /**
     * Return basename of the file.
     *
     * @return string
     */
    public function basename()
    {
        return basename($this->filename, $this->extension);
    }

    /**
     * Check if file exits.
     *
     * @return bool
     */
    public function exists()
    {
        return is_file($this->filename);
    }

    /**
     * Return file modification time.
     *
     * @return int|bool Timestamp or false if file doesn't exist.
     */
    public function modified()
    {
        return is_file($this->filename) ? filemtime($this->filename) : false;
    }

    /**
     * Lock file for writing. You need to manually unlock().
     *
     * @param bool $block  For non-blocking lock, set the parameter to false.
     * @return bool
     */
    public function lock($block = true)
    {
        if (!$this->handle) {
            if (!$this->mkdir(dirname($this->filename))) {
                throw new \RuntimeException('Creating directory failed for ' . $this->filename);
            }
            $this->handle = fopen($this->filename, 'wb+');
        }
        $lock = $block ? LOCK_EX : LOCK_EX | LOCK_NB;
        return $this->locked = flock($this->handle, $lock);
    }

    /**
     * Returns true if file has been locked for writing.
     *
     * @return bool|null True = locked, false = failed, null = not locked.
     */
    public function locked()
    {
        return $this->locked;
    }

    /**
     * Unlock file.
     *
     * @return bool
     */
    public function unlock()
    {
        if (!$this->handle) {
            return;
        }
        if ($this->locked) {
            flock($this->handle, LOCK_UN);
            $this->locked = null;
        }
        fclose($this->handle);
    }

    /**
     * Check if file can be written.
     *
     * @return bool
     */
    public function writable()
    {
        return is_writable($this->filename) || $this->writableDir(dirname($this->filename));
    }

    /**
     * (Re)Load a file and return RAW file contents.
     *
     * @return string
     */
    public function load()
    {
        $this->raw = $this->exists() ? (string) file_get_contents($this->filename) : '';
        $this->content = null;

        return $this->raw;
    }

    /**
     * Get/set raw file contents.
     *
     * @param string $var
     * @return string
     */
    public function raw($var = null)
    {
        if ($var !== null) {
            $this->raw = (string) $var;
            $this->content = null;
        }

        if (!is_string($this->raw)) {
            $this->raw = $this->load();
        }

        return $this->raw;
    }

    /**
     * Get/set parsed file contents.
     *
     * @param mixed $var
     * @return string
     */
    public function content($var = null)
    {
        if ($var !== null) {
            $this->content = $this->check($var);

            // Update RAW, too.
            $this->raw = $this->encode($this->content);

        } elseif ($this->content === null) {
            // Decode RAW file.
            $this->content = $this->decode($this->raw());
        }

        return $this->content;
    }

    /**
     * Save file.
     *
     * @param  mixed  $data  Optional data to be saved, usually array.
     * @throws \RuntimeException
     */
    public function save($data = null)
    {
        if ($data !== null) {
            $this->content($data);
        }

        if (!$this->locked) {
            // Obtain blocking lock or fail.
            if (!$this->lock()) {
                throw new \RuntimeException('Obtaining write lock failed on file: ' . $this->filename);
            }
            $lock = true;
        }

        if (@fwrite($this->handle, $this->raw()) === false) {
            $this->unlock();
            throw new \RuntimeException('Saving file failed: ' . $this->filename);
        }

        if (isset($lock)) {
            $this->unlock();
        }

        // Touch the directory as well, thus marking it modified.
        @touch(dirname($this->filename));
    }

    /**
     * Delete file from filesystem.
     *
     * @return bool
     */
    public function delete()
    {
        return unlink($this->filename);
    }

    /**
     * Check contents and make sure it is in correct format.
     *
     * Override in derived class.
     *
     * @param string $var
     * @return string
     */
    protected function check($var)
    {
        return (string) $var;
    }

    /**
     * Encode contents into RAW string.
     *
     * Override in derived class.
     *
     * @param string $var
     * @return string
     */
    protected function encode($var)
    {
        return (string) $var;
    }

    /**
     * Decode RAW string into contents.
     *
     * Override in derived class.
     *
     * @param string $var
     * @return string mixed
     */
    protected function decode($var)
    {
        return (string) $var;
    }

    /**
     * @param  string  $dir
     * @return bool
     * @internal
     */
    protected function mkdir($dir)
    {
        return is_dir($dir) || mkdir($dir, 0777, true);
    }

    /**
     * @param  string  $dir
     * @return bool
     * @internal
     */
    protected function writableDir($dir)
    {
        if ($dir && !file_exists($dir)) {
            return $this->writableDir(dirname($dir));
        }

        return $dir && is_dir($dir) && is_writable($dir);
    }
}
