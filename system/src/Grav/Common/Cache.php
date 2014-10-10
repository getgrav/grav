<?php
namespace Grav\Common;

use \Doctrine\Common\Cache\Cache as DoctrineCache;
use Grav\Common\Config\Config;

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

    protected $cache_dir;

    /**
     * Constructor
     *
     * @params Grav $grav
     */
    public function __construct(Grav $grav)
    {
        $this->init($grav);
    }

    /**
     * Initialization that sets a base key and the driver based on configuration settings
     *
     * @param  Grav $grav
     * @return void
     */
    public function init(Grav $grav)
    {
        /** @var Config $config */
        $this->config = $grav['config'];

        $this->cache_dir = $grav['locator']->findResource('cache://doctrine', true, true);

        /** @var Uri $uri */
        $uri = $grav['uri'];

        $prefix = $this->config->get('system.cache.prefix');

        $this->enabled = (bool) $this->config->get('system.cache.enabled');

        // Cache key allows us to invalidate all cache on configuration changes.
        $this->key = substr(md5(($prefix ? $prefix : 'g') . $uri->rootUrl(true) . $this->config->key() . GRAV_VERSION), 2, 8);

        $this->driver = $this->getCacheDriver();

        // Set the cache namespace to our unique key
        $this->driver->setNamespace($this->key);
    }

    /**
     * Automatically picks the cache mechanism to use.  If you pick one manually it will use that
     * If there is no config option for $driver in the config, or it's set to 'auto', it will
     * pick the best option based on which cache extensions are installed.
     *
     * @return DoctrineCacheDriver  The cache driver to use
     */
    public function getCacheDriver()
    {
        $setting = $this->config->get('system.cache.driver');
        $driver_name = 'file';

        if (!$setting || $setting == 'auto') {
            if (extension_loaded('apc')) {
                $driver_name = 'apc';
            } elseif (extension_loaded('wincache')) {
                $driver_name = 'wincache';
            } elseif (extension_loaded('xcache')) {
                $driver_name = 'xcache';
            }
        } else {
            $driver_name = $setting;
        }

        switch ($driver_name) {
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
                $memcache = new \Memcache();
                $memcache->connect($this->config->get('system.cache.memcache.server','localhost'),
                                   $this->config->get('system.cache.memcache.port', 11211));
                $driver = new \Doctrine\Common\Cache\MemcacheCache();
                $driver->setMemcache($memcache);
                break;

            default:
                $driver = new \Doctrine\Common\Cache\FilesystemCache($this->cache_dir);
                break;
        }

        return $driver;
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
