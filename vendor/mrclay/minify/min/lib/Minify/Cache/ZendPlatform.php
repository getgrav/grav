<?php
/**
 * Class Minify_Cache_ZendPlatform
 * @package Minify
 */


/**
 * ZendPlatform-based cache class for Minify
 *
 * Based on Minify_Cache_APC, uses output_cache_get/put (currently deprecated)
 * 
 * <code>
 * Minify::setCache(new Minify_Cache_ZendPlatform());
 * </code>
 *
 * @package Minify
 * @author Patrick van Dissel
 */
class Minify_Cache_ZendPlatform {


    /**
     * Create a Minify_Cache_ZendPlatform object, to be passed to
     * Minify::setCache().
     *
     * @param int $expire seconds until expiration (default = 0
     * meaning the item will not get an expiration date)
     *
     * @return null
     */
    public function __construct($expire = 0)
    {
        $this->_exp = $expire;
    }


    /**
     * Write data to cache.
     *
     * @param string $id cache id
     *
     * @param string $data
     *
     * @return bool success
     */
    public function store($id, $data)
    {
        return output_cache_put($id, "{$_SERVER['REQUEST_TIME']}|{$data}");
    }


    /**
     * Get the size of a cache entry
     *
     * @param string $id cache id
     *
     * @return int size in bytes
     */
    public function getSize($id)
    {
        return $this->_fetch($id)
            ? strlen($this->_data)
            : false;
    }


    /**
     * Does a valid cache entry exist?
     *
     * @param string $id cache id
     *
     * @param int $srcMtime mtime of the original source file(s)
     *
     * @return bool exists
     */
    public function isValid($id, $srcMtime)
    {
        $ret = ($this->_fetch($id) && ($this->_lm >= $srcMtime));
        return $ret;
    }


    /**
     * Send the cached content to output
     *
     * @param string $id cache id
     */
    public function display($id)
    {
        echo $this->_fetch($id)
            ? $this->_data
            : '';
    }


    /**
     * Fetch the cached content
     *
     * @param string $id cache id
     *
     * @return string
     */
    public function fetch($id)
    {
        return $this->_fetch($id)
            ? $this->_data
            : '';
    }


    private $_exp = null;


    // cache of most recently fetched id
    private $_lm = null;
    private $_data = null;
    private $_id = null;


    /**
     * Fetch data and timestamp from ZendPlatform, store in instance
     *
     * @param string $id
     *
     * @return bool success
     */
    private function _fetch($id)
    {
        if ($this->_id === $id) {
            return true;
        }
        $ret = output_cache_get($id, $this->_exp);
        if (false === $ret) {
            $this->_id = null;
            return false;
        }
        list($this->_lm, $this->_data) = explode('|', $ret, 2);
        $this->_id = $id;
        return true;
    }
}
