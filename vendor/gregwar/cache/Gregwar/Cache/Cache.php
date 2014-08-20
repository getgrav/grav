<?php

namespace Gregwar\Cache;

/**
 * A cache system based on files
 *
 * @author Gregwar <g.passault@gmail.com>
 */
class Cache
{
    /**
     * Cache directory
     */
    protected $cacheDirectory;

    /**
     * Use a different directory as actual cache
     */
    protected $actualCacheDirectory = null;

    /**
     * Prefix directories size
     *
     * For instance, if the file is helloworld.txt and the prefix size is
     * 5, the cache file will be: h/e/l/l/o/helloworld.txt
     *
     * This is useful to avoid reaching a too large number of files into the 
     * cache system directories
     */
    protected $prefixSize = 5;

    /**
     * Constructs the cache system
     */
    public function __construct($cacheDirectory = 'cache')
    {
	$this->cacheDirectory = $cacheDirectory;
    }

    /**
     * Sets the cache directory
     *
     * @param $cacheDirectory the cache directory
     */
    public function setCacheDirectory($cacheDirectory)
    {
	$this->cacheDirectory = $cacheDirectory;

	return $this;
    }

    /**
     * Gets the cache directory
     *
     * @return string the cache directory
     */
    public function getCacheDirectory()
    {
	return $this->cacheDirectory;
    }

    /**
     * Sets the actual cache directory
     */
    public function setActualCacheDirectory($actualCacheDirectory = null)
    {
        $this->actualCacheDirectory = $actualCacheDirectory;

        return $this;
    }

    /**
     * Returns the actual cache directory
     */
    public function getActualCacheDirectory()
    {
        return $this->actualCacheDirectory ?: $this->cacheDirectory;
    }

    /**
     * Change the prefix size
     *
     * @param $prefixSize the size of the prefix directories
     */
    public function setPrefixSize($prefixSize)
    {
        $this->prefixSize = $prefixSize;

        return $this;
    }

    /**
     * Creates a directory
     *
     * @param $directory, the target directory
     */
    protected function mkdir($directory)
    {
        if (!is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }
    }

    /**
     * Gets the cache file name
     *
     * @param $filename, the name of the cache file
     * @param $actual get the actual file or the public file
     * @param $mkdir, a boolean to enable/disable the construction of the
     *        cache file directory
     */
    public function getCacheFile($filename, $actual = false, $mkdir = false)
    {
	$path = array();

	// Getting the length of the filename before the extension
	$parts = explode('.', $filename);
	$len = strlen($parts[0]);

	for ($i=0; $i<min($len, $this->prefixSize); $i++) {
	    $path[] = $filename[$i];

        }
	$path = implode('/', $path);

        $actualDir = $this->getActualCacheDirectory() . '/' . $path;
        if ($mkdir && !is_dir($actualDir)) {
	    mkdir($actualDir, 0755, true);
	}

	$path .= '/' . $filename;

        if ($actual) {
            return $this->getActualCacheDirectory() . '/' . $path;
        } else {
            return $this->getCacheDirectory() . '/' . $path;
        }
    }

    /**
     * Checks that the cache conditions are respected
     *
     * @param $cacheFile the cache file
     * @param $conditions an array of conditions to check
     */
    protected function checkConditions($cacheFile, array $conditions = array())
    {
        // Implicit condition: the cache file should exist
        if (!file_exists($cacheFile)) {
	    return false;
	}

	foreach ($conditions as $type => $value) {
	    switch ($type) {
	    case 'maxage':
            case 'max-age':
		// Return false if the file is older than $value
                $age = time() - filectime($cacheFile);
                if ($age > $value) {
                    return false;
                }
		break;
	    case 'younger-than':
            case 'youngerthan':
                // Return false if the file is older than the file $value, or the files $value
                $check = function($filename) use ($cacheFile) {
                    return !file_exists($filename) || filectime($cacheFile) < filectime($filename);
                };

                if (!is_array($value)) {
                    if (!$this->isRemote($value) && $check($value)) {
                        return false;
                    }
                } else {
                    foreach ($value as $file) {
                        if (!$this->isRemote($file) && $check($file)) {
                            return false;
                        }
                    }
                }
		break;
	    default:
		throw new \Exception('Cache condition '.$type.' not supported');
	    }
	}

	return true;
    }

    /**
     * Checks if the targt filename exists in the cache and if the conditions
     * are respected
     *
     * @param $filename the filename 
     * @param $conditions the conditions to respect
     */
    public function exists($filename, array $conditions = array())
    {
        $cacheFile = $this->getCacheFile($filename, true);

	return $this->checkConditions($cacheFile, $conditions);
    }

    /**
     * Alias for exists
     */
    public function check($filename, array $conditions = array())
    {
        return $this->exists($filename, $conditions);
    }

    /**
     * Write data in the cache
     */
    public function set($filename, $contents = '')
    {
	$cacheFile = $this->getCacheFile($filename, true, true);

        file_put_contents($cacheFile, $contents);

        return $this;
    }

    /**
     * Alias for set()
     */
    public function write($filename, $contents = '')
    {
        return $this->set($filename, $contents);
    }

    /**
     * Get data from the cache
     */
    public function get($filename, array $conditions = array())
    {
	if ($this->exists($filename, $conditions)) {
	    return file_get_contents($this->getCacheFile($filename, true));
	} else {
	    return null;
	}
    }

    /**
     * Is this URL remote?
     */
    protected function isRemote($file)
    {
        return preg_match('/^http(s{0,1}):\/\//', $file);
    }

    /**
     * Get or create the cache entry
     *
     * @param $filename the cache file name
     * @param $conditions an array of conditions about expiration
     * @param $function the closure to call if the file does not exists
     * @param $file returns the cache file or the file contents
     * @param $actual returns the actual cache file
     */
    public function getOrCreate($filename, array $conditions = array(), \Closure $function, $file = false, $actual = false)
    {
        $cacheFile = $this->getCacheFile($filename, true, true);
        $data = null;

        if ($this->check($filename, $conditions)) {
            $data = file_get_contents($cacheFile);
        } else {
            @unlink($cacheFile);
            $data = $function($cacheFile);

            // Test if the closure wrote the file or if it returned the data
            if (!file_exists($cacheFile)) {
                $this->set($filename, $data);
            } else {
                $data = file_get_contents($cacheFile);
            }
        }

        return $file ? $this->getCacheFile($filename, $actual) : $data;
    }

    /**
     * Alias to getOrCreate with $file = true
     */
    public function getOrCreateFile($filename, array $conditions = array(), \Closure $function, $actual = false)
    {
        return $this->getOrCreate($filename, $conditions, $function, true, $actual);
    }
}
