<?php
namespace Grav\Common\GPM\Remote;

use Grav\Common\GPM\Response;
use Grav\Common\GravTrait;
use Grav\Common\Iterator;
use \Doctrine\Common\Cache\Cache as DoctrineCache;
use \Doctrine\Common\Cache\FilesystemCache;

class Collection extends Iterator {
    use GravTrait;

    /**
     * The cached data previously fetched
     * @var string
     */
    protected $raw;

    /**
     * The lifetime to store the entry in seconds
     * @var integer
     */
    private $lifetime = 86400;
    private $repository;
    private $cache;

    public function __construct($repository = null)
    {
        if ($repository === null) {
            throw new \RuntimeException("A repository is required for storing the cache");
        }

        $cache_dir = self::getGrav()['locator']->findResource('cache://gpm', true, true);
        $this->cache = new FilesystemCache($cache_dir);

        $this->repository = $repository;
        $this->raw        = $this->cache->fetch(md5($this->repository));
    }

    public function toJson()
    {
        $items = [];

        foreach ($this->items as $name => $theme) {
            $items[$name] = $theme->toArray();
        }

        return json_encode($items);
    }

    public function toArray()
    {
        $items = [];

        foreach ($this->items as $name => $theme) {
            $items[$name] = $theme->toArray();
        }

        return $items;
    }

    public function fetch($refresh = false, $callback = null)
    {
        if (!$this->raw || $refresh) {
            $response  = Response::get($this->repository, [], $callback);
            $this->raw = $response;
            $this->cache->save(md5($this->repository), $this->raw, $this->lifetime);
        }

        return $this->raw;
    }
}
