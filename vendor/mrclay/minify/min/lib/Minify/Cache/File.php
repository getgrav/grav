<?php
/**
 * Class Minify_Cache_File  
 * @package Minify
 */

class Minify_Cache_File {
    
    public function __construct($path = '', $fileLocking = false)
    {
        if (! $path) {
            $path = self::tmp();
        }
        $this->_locking = $fileLocking;
        $this->_path = $path;
    }

    /**
     * Write data to cache.
     *
     * @param string $id cache id (e.g. a filename)
     * 
     * @param string $data
     * 
     * @return bool success
     */
    public function store($id, $data)
    {
        $flag = $this->_locking
            ? LOCK_EX
            : null;
        $file = $this->_path . '/' . $id;
        if (! @file_put_contents($file, $data, $flag)) {
            $this->_log("Minify_Cache_File: Write failed to '$file'");
        }
        // write control
        if ($data !== $this->fetch($id)) {
            @unlink($file);
            $this->_log("Minify_Cache_File: Post-write read failed for '$file'");
            return false;
        }
        return true;
    }
    
    /**
     * Get the size of a cache entry
     *
     * @param string $id cache id (e.g. a filename)
     * 
     * @return int size in bytes
     */
    public function getSize($id)
    {
        return filesize($this->_path . '/' . $id);
    }
    
    /**
     * Does a valid cache entry exist?
     *
     * @param string $id cache id (e.g. a filename)
     * 
     * @param int $srcMtime mtime of the original source file(s)
     * 
     * @return bool exists
     */
    public function isValid($id, $srcMtime)
    {
        $file = $this->_path . '/' . $id;
        return (is_file($file) && (filemtime($file) >= $srcMtime));
    }
    
    /**
     * Send the cached content to output
     *
     * @param string $id cache id (e.g. a filename)
     */
    public function display($id)
    {
        if ($this->_locking) {
            $fp = fopen($this->_path . '/' . $id, 'rb');
            flock($fp, LOCK_SH);
            fpassthru($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
        } else {
            readfile($this->_path . '/' . $id);            
        }
    }
    
	/**
     * Fetch the cached content
     *
     * @param string $id cache id (e.g. a filename)
     * 
     * @return string
     */
    public function fetch($id)
    {
        if ($this->_locking) {
            $fp = fopen($this->_path . '/' . $id, 'rb');
            flock($fp, LOCK_SH);
            $ret = stream_get_contents($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
            return $ret;
        } else {
            return file_get_contents($this->_path . '/' . $id);
        }
    }
    
    /**
     * Fetch the cache path used
     *
     * @return string
     */
    public function getPath()
    {
        return $this->_path;
    }

    /**
     * Get a usable temp directory
     *
     * Adapted from Solar/Dir.php
     * @author Paul M. Jones <pmjones@solarphp.com>
     * @license http://opensource.org/licenses/bsd-license.php BSD
     * @link http://solarphp.com/trac/core/browser/trunk/Solar/Dir.php
     *
     * @return string
     */
    public static function tmp()
    {
        static $tmp = null;
        if (! $tmp) {
            $tmp = function_exists('sys_get_temp_dir')
                ? sys_get_temp_dir()
                : self::_tmp();
            $tmp = rtrim($tmp, DIRECTORY_SEPARATOR);
        }
        return $tmp;
    }

    /**
     * Returns the OS-specific directory for temporary files
     *
     * @author Paul M. Jones <pmjones@solarphp.com>
     * @license http://opensource.org/licenses/bsd-license.php BSD
     * @link http://solarphp.com/trac/core/browser/trunk/Solar/Dir.php
     *
     * @return string
     */
    protected static function _tmp()
    {
        // non-Windows system?
        if (strtolower(substr(PHP_OS, 0, 3)) != 'win') {
            $tmp = empty($_ENV['TMPDIR']) ? getenv('TMPDIR') : $_ENV['TMPDIR'];
            if ($tmp) {
                return $tmp;
            } else {
                return '/tmp';
            }
        }
        // Windows 'TEMP'
        $tmp = empty($_ENV['TEMP']) ? getenv('TEMP') : $_ENV['TEMP'];
        if ($tmp) {
            return $tmp;
        }
        // Windows 'TMP'
        $tmp = empty($_ENV['TMP']) ? getenv('TMP') : $_ENV['TMP'];
        if ($tmp) {
            return $tmp;
        }
        // Windows 'windir'
        $tmp = empty($_ENV['windir']) ? getenv('windir') : $_ENV['windir'];
        if ($tmp) {
            return $tmp;
        }
        // final fallback for Windows
        return getenv('SystemRoot') . '\\temp';
    }

    /**
     * Send message to the Minify logger
     * @param string $msg
     * @return null
     */
    protected function _log($msg)
    {
        Minify_Logger::log($msg);
    }
    
    private $_path = null;
    private $_locking = null;
}
