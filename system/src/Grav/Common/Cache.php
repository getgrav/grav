<?php

/**
 * @package    Grav\Common
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

use \Doctrine\Common\Cache as DoctrineCache;
use Grav\Common\Config\Config;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Scheduler\Scheduler;
use Psr\SimpleCache\CacheInterface;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\Event\EventDispatcher;

/**
 * The GravCache object is used throughout Grav to store and retrieve cached data.
 * It uses DoctrineCache library and supports a variety of caching mechanisms. Those include:
 *
 * APCu
 * RedisCache
 * MemCache
 * MemCacheD
 * FileSystem
 */
class Cache extends Getters
{
    /**
     * @var string Cache key.
     */
    protected $key;

    protected $lifetime;
    protected $now;

    /** @var Config $config */
    protected $config;

    /**
     * @var DoctrineCache\CacheProvider
     */
    protected $driver;

    /**
     * @var CacheInterface
     */
    protected $simpleCache;

    protected $driver_name;

    protected $driver_setting;

    /**
     * @var bool
     */
    protected $enabled;

    protected $cache_dir;

    protected static $standard_remove = [
        'cache://twig/',
        'cache://doctrine/',
        'cache://compiled/',
        'cache://validated-',
        'cache://images',
        'asset://',
    ];

    protected static $standard_remove_no_images = [
        'cache://twig/',
        'cache://doctrine/',
        'cache://compiled/',
        'cache://validated-',
        'asset://',
    ];

    protected static $all_remove = [
        'cache://',
        'cache://images',
        'asset://',
        'tmp://'
    ];

    protected static $assets_remove = [
        'asset://'
    ];

    protected static $images_remove = [
        'cache://images'
    ];

    protected static $cache_remove = [
        'cache://'
    ];

    protected static $tmp_remove = [
        'tmp://'
    ];

    /**
     * Constructor
     *
     * @param Grav $grav
     */
    public function __construct(Grav $grav)
    {
        $this->init($grav);
    }

    /**
     * Initialization that sets a base key and the driver based on configuration settings
     *
     * @param  Grav $grav
     *
     * @return void
     */
    public function init(Grav $grav)
    {
        /** @var Config $config */
        $this->config = $grav['config'];
        $this->now = time();

        if (null === $this->enabled) {
            $this->enabled = (bool)$this->config->get('system.cache.enabled');
        }

        /** @var Uri $uri */
        $uri = $grav['uri'];

        $prefix = $this->config->get('system.cache.prefix');
        $uniqueness = substr(md5($uri->rootUrl(true) . $this->config->key() . GRAV_VERSION), 2, 8);

        // Cache key allows us to invalidate all cache on configuration changes.
        $this->key = ($prefix ? $prefix : 'g') . '-' . $uniqueness;
        $this->cache_dir = $grav['locator']->findResource('cache://doctrine/' . $uniqueness, true, true);
        $this->driver_setting = $this->config->get('system.cache.driver');
        $this->driver = $this->getCacheDriver();
        $this->driver->setNamespace($this->key);

        /** @var EventDispatcher $dispatcher */
        $dispatcher = Grav::instance()['events'];
        $dispatcher->addListener('onSchedulerInitialized', [$this, 'onSchedulerInitialized']);
    }

    /**
     * @return CacheInterface
     */
    public function getSimpleCache()
    {
        if (null === $this->simpleCache) {
            $cache = new \Grav\Framework\Cache\Adapter\DoctrineCache($this->driver, '', $this->getLifetime());

            // Disable cache key validation.
            $cache->setValidation(false);

            $this->simpleCache = $cache;
        }

        return $this->simpleCache;
    }

    /**
     * Deletes the old out of date file-based caches
     *
     * @return int
     */
    public function purgeOldCache()
    {
        $cache_dir = dirname($this->cache_dir);
        $current = basename($this->cache_dir);
        $count = 0;

        foreach (new \DirectoryIterator($cache_dir) as $file) {
            $dir = $file->getBasename();
            if ($dir === $current || $file->isDot() || $file->isFile()) {
                continue;
            }

            Folder::delete($file->getPathname());
            $count++;
        }

        return $count;
    }

    /**
     * Public accessor to set the enabled state of the cache
     *
     * @param bool|int $enabled
     */
    public function setEnabled($enabled)
    {
        $this->enabled = (bool)$enabled;
    }

    /**
     * Returns the current enabled state
     *
     * @return bool
     */
    public function getEnabled()
    {
        return $this->enabled;
    }

    /**
     * Get cache state
     *
     * @return string
     */
    public function getCacheStatus()
    {
        return 'Cache: [' . ($this->enabled ? 'true' : 'false') . '] Setting: [' . $this->driver_setting . '] Driver: [' . $this->driver_name . ']';
    }

    /**
     * Automatically picks the cache mechanism to use.  If you pick one manually it will use that
     * If there is no config option for $driver in the config, or it's set to 'auto', it will
     * pick the best option based on which cache extensions are installed.
     *
     * @return DoctrineCache\CacheProvider  The cache driver to use
     */
    public function getCacheDriver()
    {
        $setting = $this->driver_setting;
        $driver_name = 'file';

        // CLI compatibility requires a non-volatile cache driver
        if ($this->config->get('system.cache.cli_compatibility') && (
            $setting === 'auto' || $this->isVolatileDriver($setting))) {
            $setting = $driver_name;
        }

        if (!$setting || $setting === 'auto') {
            if (extension_loaded('apcu')) {
                $driver_name = 'apcu';
            } elseif (extension_loaded('wincache')) {
                $driver_name = 'wincache';
            }
        } else {
            $driver_name = $setting;
        }

        $this->driver_name = $driver_name;

        switch ($driver_name) {
            case 'apc':
            case 'apcu':
                $driver = new DoctrineCache\ApcuCache();
                break;

            case 'wincache':
                $driver = new DoctrineCache\WinCacheCache();
                break;

            case 'memcache':
                if (extension_loaded('memcache')) {
                    $memcache = new \Memcache();
                    $memcache->connect($this->config->get('system.cache.memcache.server', 'localhost'),
                        $this->config->get('system.cache.memcache.port', 11211));
                    $driver = new DoctrineCache\MemcacheCache();
                    $driver->setMemcache($memcache);
                } else {
                    throw new \LogicException('Memcache PHP extension has not been installed');
                }
                break;

            case 'memcached':
                if (extension_loaded('memcached')) {
                    $memcached = new \Memcached();
                    $memcached->addServer($this->config->get('system.cache.memcached.server', 'localhost'),
                        $this->config->get('system.cache.memcached.port', 11211));
                    $driver = new DoctrineCache\MemcachedCache();
                    $driver->setMemcached($memcached);
                } else {
                    throw new \LogicException('Memcached PHP extension has not been installed');
                }
                break;

            case 'redis':
                if (extension_loaded('redis')) {
                    $redis = new \Redis();
                    $socket = $this->config->get('system.cache.redis.socket', false);
                    $password = $this->config->get('system.cache.redis.password', false);

                    if ($socket) {
                        $redis->connect($socket);
                    } else {
                        $redis->connect($this->config->get('system.cache.redis.server', 'localhost'),
                            $this->config->get('system.cache.redis.port', 6379));
                    }

                    // Authenticate with password if set
                    if ($password && !$redis->auth($password)) {
                        throw new \RedisException('Redis authentication failed');
                    }

                    $driver = new DoctrineCache\RedisCache();
                    $driver->setRedis($redis);
                } else {
                    throw new \LogicException('Redis PHP extension has not been installed');
                }
                break;

            default:
                $driver = new DoctrineCache\FilesystemCache($this->cache_dir);
                break;
        }

        return $driver;
    }

    /**
     * Gets a cached entry if it exists based on an id. If it does not exist, it returns false
     *
     * @param  string $id the id of the cached entry
     *
     * @return object|bool     returns the cached entry, can be any type, or false if doesn't exist
     */
    public function fetch($id)
    {
        if ($this->enabled) {
            return $this->driver->fetch($id);
        }

        return false;
    }

    /**
     * Stores a new cached entry.
     *
     * @param  string       $id       the id of the cached entry
     * @param  array|object $data     the data for the cached entry to store
     * @param  int          $lifetime the lifetime to store the entry in seconds
     */
    public function save($id, $data, $lifetime = null)
    {
        if ($this->enabled) {
            if ($lifetime === null) {
                $lifetime = $this->getLifetime();
            }
            $this->driver->save($id, $data, $lifetime);
        }
    }

    /**
     * Deletes an item in the cache based on the id
     *
     * @param string $id    the id of the cached data entry
     * @return bool         true if the item was deleted successfully
     */
    public function delete($id)
    {
        if ($this->enabled) {
            return $this->driver->delete($id);
        }

        return false;
    }

    /**
     * Deletes all cache
     *
     * @return bool
     */
    public function deleteAll()
    {
        if ($this->enabled) {
            return $this->driver->deleteAll();
        }

        return false;
    }

    /**
     * Returns a boolean state of whether or not the item exists in the cache based on id key
     *
     * @param string $id    the id of the cached data entry
     * @return bool         true if the cached items exists
     */
    public function contains($id)
    {
        if ($this->enabled) {
            return $this->driver->contains(($id));
        }

        return false;
    }

    /**
     * Getter method to get the cache key
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * Setter method to set key (Advanced)
     */
    public function setKey($key)
    {
        $this->key = $key;
        $this->driver->setNamespace($this->key);
    }

    /**
     * Helper method to clear all Grav caches
     *
     * @param string $remove standard|all|assets-only|images-only|cache-only
     *
     * @return array
     */
    public static function clearCache($remove = 'standard')
    {
        $locator = Grav::instance()['locator'];
        $output = [];
        $user_config = USER_DIR . 'config/system.yaml';

        switch ($remove) {
            case 'all':
                $remove_paths = self::$all_remove;
                break;
            case 'assets-only':
                $remove_paths = self::$assets_remove;
                break;
            case 'images-only':
                $remove_paths = self::$images_remove;
                break;
            case 'cache-only':
                $remove_paths = self::$cache_remove;
                break;
            case 'tmp-only':
                $remove_paths = self::$tmp_remove;
                break;
            case 'invalidate':
                $remove_paths = [];
                break;
            default:
                if (Grav::instance()['config']->get('system.cache.clear_images_by_default')) {
                    $remove_paths = self::$standard_remove;
                } else {
                    $remove_paths = self::$standard_remove_no_images;
                }

        }

        // Delete entries in the doctrine cache if required
        if (in_array($remove, ['all', 'standard'])) {
            $cache = Grav::instance()['cache'];
            $cache->driver->deleteAll();
        }

        // Clearing cache event to add paths to clear
        Grav::instance()->fireEvent('onBeforeCacheClear', new Event(['remove' => $remove, 'paths' => &$remove_paths]));

        foreach ($remove_paths as $stream) {

            // Convert stream to a real path
            try {
                $path = $locator->findResource($stream, true, true);
                if($path === false) continue;

                $anything = false;
                $files = glob($path . '/*');

                if (is_array($files)) {
                    foreach ($files as $file) {
                        if (is_link($file)) {
                            $output[] = '<yellow>Skipping symlink:  </yellow>' . $file;
                        } elseif (is_file($file)) {
                            if (@unlink($file)) {
                                $anything = true;
                            }
                        } elseif (is_dir($file)) {
                            if (Folder::delete($file)) {
                                $anything = true;
                            }
                        }
                    }
                }

                if ($anything) {
                    $output[] = '<red>Cleared:  </red>' . $path . '/*';
                }
            } catch (\Exception $e) {
                // stream not found or another error while deleting files.
                $output[] = '<red>ERROR: </red>' . $e->getMessage();
            }
        }

        $output[] = '';

        if (($remove === 'all' || $remove === 'standard') && file_exists($user_config)) {
            touch($user_config);

            $output[] = '<red>Touched: </red>' . $user_config;
            $output[] = '';
        }

        // Clear stat cache
        @clearstatcache();

        // Clear opcache
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        return $output;
    }

    public static function invalidateCache()
    {
        $user_config = USER_DIR . 'config/system.yaml';

        if (file_exists($user_config)) {
            touch($user_config);
        }

        // Clear stat cache
        @clearstatcache();

        // Clear opcache
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

    }

    /**
     * Set the cache lifetime programmatically
     *
     * @param int $future timestamp
     */
    public function setLifetime($future)
    {
        if (!$future) {
            return;
        }

        $interval = (int)($future - $this->now);
        if ($interval > 0 && $interval < $this->getLifetime()) {
            $this->lifetime = $interval;
        }
    }


    /**
     * Retrieve the cache lifetime (in seconds)
     *
     * @return mixed
     */
    public function getLifetime()
    {
        if ($this->lifetime === null) {
            $this->lifetime = (int)($this->config->get('system.cache.lifetime') ?: 604800); // 1 week default
        }

        return $this->lifetime;
    }

    /**
     * Returns the current driver name
     *
     * @return mixed
     */
    public function getDriverName()
    {
        return $this->driver_name;
    }

    /**
     * Returns the current driver setting
     *
     * @return mixed
     */
    public function getDriverSetting()
    {
        return $this->driver_setting;
    }

    /**
     * is this driver a volatile driver in that it resides in PHP process memory
     *
     * @param string $setting
     * @return bool
     */
    public function isVolatileDriver($setting)
    {
        if (in_array($setting, ['apc', 'apcu', 'xcache', 'wincache'])) {
            return true;
        }

        return false;
    }

    /**
     * Static function to call as a scheduled Job to purge old Doctrine files
     */
    public static function purgeJob()
    {
        /** @var Cache $cache */
        $cache = Grav::instance()['cache'];
        $deleted_folders = $cache->purgeOldCache();

        echo 'Purged ' . $deleted_folders . ' old cache folders...';
    }

    /**
     * Static function to call as a scheduled Job to clear Grav cache
     *
     * @param string $type
     */
    public static function clearJob($type)
    {
        $result = static::clearCache($type);
        static::invalidateCache();

        echo strip_tags(implode("\n", $result));
    }

    public function onSchedulerInitialized(Event $event)
    {
        /** @var Scheduler $scheduler */
        $scheduler = $event['scheduler'];
        $config = Grav::instance()['config'];

        // File Cache Purge
        $at = $config->get('system.cache.purge_at');
        $name = 'cache-purge';
        $logs = 'logs/' . $name . '.out';

        $job = $scheduler->addFunction('Grav\Common\Cache::purgeJob', [], $name );
        $job->at($at);
        $job->output($logs);
        $job->backlink('/config/system#caching');

        // Cache Clear
        $at = $config->get('system.cache.clear_at');
        $clear_type = $config->get('system.cache.clear_job_type');
        $name = 'cache-clear';
        $logs = 'logs/' . $name . '.out';

        $job = $scheduler->addFunction('Grav\Common\Cache::clearJob', [$clear_type], $name );
        $job->at($at);
        $job->output($logs);
        $job->backlink('/config/system#caching');

    }


}
