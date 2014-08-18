<?php
/**
 * Class Minify_Cache_XCache
 *
 * @link http://xcache.lighttpd.net/
 * @package Minify
 */

/**
 * XCache-based cache class for Minify
 * {@see http://xcache.lighttpd.net/wiki/XcacheApi XCache API}
 *
 * <code>
 * Minify::setCache(new Minify_Cache_XCache());
 * </code>
 *
 * @package Minify
 * @author Elan Ruusamäe <glen@delfi.ee>
 **/
class Minify_Cache_XCache {

    /**
     * Create a Minify_Cache_XCache object, to be passed to
     * Minify::setCache().
     *
     * @param int $expire seconds until expiration (default = 0
     * meaning the item will not get an expiration date)
     */
    public function __construct($expire = 0)
    {
        $this->_exp = $expire;
    }

    /**
     * Write data to cache.
     *
     * @param string $id cache id
     * @param string $data
     * @return bool success
     */
    public function store($id, $data)
    {
        return xcache_set($id, "{$_SERVER['REQUEST_TIME']}|{$data}", $this->_exp);
    }

    /**
     * Get the size of a cache entry
     *
     * @param string $id cache id
     * @return int size in bytes
     */
    public function getSize($id)
    {
        if (! $this->_fetch($id)) {
            return false;
        }
        return (function_exists('mb_strlen') && ((int)ini_get('mbstring.func_overload') & 2))
            ? mb_strlen($this->_data, '8bit')
            : strlen($this->_data);
    }

    /**
     * Does a valid cache entry exist?
     *
     * @param string $id cache id
     * @param int $srcMtime mtime of the original source file(s)
     * @return bool exists
     */
    public function isValid($id, $srcMtime)
    {
        return ($this->_fetch($id) && ($this->_lm >= $srcMtime));
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
     * Fetch data and timestamp from xcache, store in instance
     *
     * @param string $id
     * @return bool success
     */
    private function _fetch($id)
    {
        if ($this->_id === $id) {
            return true;
        }
        $ret = xcache_get($id);
        if (false === $ret) {
            $this->_id = null;
            return false;
        }
        list($this->_lm, $this->_data) = explode('|', $ret, 2);
        $this->_id = $id;
        return true;
    }
}
