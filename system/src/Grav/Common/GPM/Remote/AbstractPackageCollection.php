<?php
namespace Grav\Common\GPM\Remote;

use Grav\Common\GPM\Common\AbstractPackageCollection as BaseCollection;
use Grav\Common\GPM\Response;

use \Doctrine\Common\Cache\FilesystemCache;

class AbstractPackageCollection extends BaseCollection
{
    /**
     * The cached data previously fetched
     * @var array
     */
    protected $raw;

    /**
     * The lifetime to store the entry in seconds
     * @var integer
     */
    private $lifetime = 86400;

    /**
     * The URL(s) to a repository
     * @var string|array
     */
    protected $repository;

    /**
     * Cache
     * @var \Doctrine\Common\Cache\FilesystemCache
     */
    protected $cache;

    public function __construct($repository = null, $refresh = false, $callback = null)
    {
        if ($repository === null) {
            throw new \RuntimeException("A repository is required to indicate the origin of the remote collection");
        }

        $cache_dir = self::getGrav()['locator']->findResource('cache://gpm', true, true);
        $this->cache = new FilesystemCache($cache_dir);

        // Allow custom repositories - for security reasons load built-in ones always at last
        $repositories = self::getGrav()['config']->get("repositories.{$this->type}", []);
        $repositories = array_merge($repositories, (array) $this->repository);

        $this->repository = $repositories;
        $this->raw        = $this->cache->fetch(md5(serialize($this->repository)));

        $this->fetch($refresh, $callback);
        foreach ($this->raw as $raw) {
            foreach (json_decode($raw, true) as $slug => $data) {
                $this->items[$slug] = new Package($data, $this->type);
            }
        }
    }

    public function fetch($refresh = false, $callback = null)
    {
        if (!$this->raw || $refresh) {
            // Download repository databases
            foreach ((array) $this->repository as $repository) {
                $response  = Response::get($repository, [], $callback);
                $this->raw[] = $response;
            }

            // Save results
            $this->cache->save(md5(serialize($this->repository)), $this->raw, $this->lifetime);
        }

        return $this->raw;
    }
}
