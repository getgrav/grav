<?php
namespace Grav\Common;

use \Doctrine\Common\Cache\Cache as DoctrineCache;

/**
 * The GravCache object is used throughout Grav to store and retrieve cached data.
 * It uses DoctrineCache library and supports a variety of caching mechanisms. Those include:
 *
 * APC
 * XCache
 * RedisCache
 * MemCache
 * MemCacheD
 * FileSystem
 *
 * @author RocketTheme
 * @license MIT
 */
class Cache extends Getters
{
    /**
     * @var string Cache key.
     */
    protected $key;

    /**
     * @var DoctrineCache
     */
    protected $driver;

    /**
     * @var bool
     */
    protected $enabled;

    /**
     * Constructor
     *
     * @params Grav $c
     */
    public function __construct(Grav $c)
    {
        $this->init($c);
    }

    /**
     * Initialization that sets a base key and the driver based on configuration settings
     *
     * @param  Grav $c
     * @return void
     */
    public function init(Grav $c)
    {
        /** @var Config $config */
        $config = $c['config'];

        /** @var Uri $uri */
        $uri = $c['uri'];

        $prefix = $config->get('system.cache.prefix');

        $this->enabled = (bool) $config->get('system.cache.enabled');

        // Cache key allows us to invalidate all cache on configuration changes.
        $this->key = substr(md5(($prefix ? $prefix : 'g') . $uri->rootUrl(true) . $config->key . GRAV_VERSION), 2, 8);

        switch ($this->getCacheDriverName($config->get('system.cache.driver'))) {
            case 'apc':
                $driver = new \Doctrine\Common\Cache\ApcCache();
                break;

            case 'wincache':
                $driver = new \Doctrine\Common\Cache\WinCacheCache();
                break;

            case 'xcache':
                $driver = new \Doctrine\Common\Cache\XcacheCache();
                break;

            case 'memcache':
                $driver = new \Doctrine\Common\Cache\MemcacheCache();
                break;

            case 'memcached':
                $driver = new \Doctrine\Common\Cache\MemcachedCache();
                break;

            default:
                $driver = new \Doctrine\Common\Cache\FilesystemCache(CACHE_DIR);
                break;
        }
        $this->driver = $driver;
    }

    /**
     * Automatically picks the cache mechanism to use.  If you pick one manually it will use that
     * If there is no config option for $driver in the config, or it's set to 'auto', it will
     * pick the best option based on which cache extensions are installed.
     *
     * @param  string  $setting
     * @return string  The name of the best cache driver to use
     */
    protected function getCacheDriverName($setting = null)
    {

        if (!$setting || $setting == 'auto') {
            if (extension_loaded('apc') && ini_get('apc.enabled')) {
                return 'apc';
            } elseif (extension_loaded('wincache')) {
                return 'wincache';
            } elseif (extension_loaded('xcache') && ini_get('xcache.size') && ini_get('xcache.cacher')) {
                return 'xcache';
            } else {
                return 'file';
            }
        } else {
            return $setting;
        }
    }

    /**
     * Gets a cached entry if it exists based on an id. If it does not exist, it returns false
     *
     * @param  string $id the id of the cached entry
     * @return object     returns the cached entry, can be any type, or false if doesn't exist
     */
    public function fetch($id)
    {
        if ($this->enabled) {
            $id = $this->key . $id;
            return $this->driver->fetch($id);
        } else {
            return false;
        }
    }

    /**
     * Stores a new cached entry.
     *
     * @param  string $id       the id of the cached entry
     * @param  array|object $data     the data for the cached entry to store
     * @param  int $lifetime    the lifetime to store the entry in seconds
     */
    public function save($id, $data, $lifetime = null)
    {
        if ($this->enabled) {
            $id = $this->key . $id;
            $this->driver->save($id, $data, $lifetime);
        }
    }

    /**
     * Getter method to get the cache key
     */
    public function getKey()
    {
        return $this->key;
    }
}
