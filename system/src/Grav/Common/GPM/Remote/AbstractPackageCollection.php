<?php

/**
 * @package    Grav\Common\GPM
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\GPM\Remote;

use Grav\Common\Grav;
use Grav\Common\GPM\Common\AbstractPackageCollection as BaseCollection;
use Grav\Common\GPM\Response;
use \Doctrine\Common\Cache\FilesystemCache;

class AbstractPackageCollection extends BaseCollection
{
    /**
     * The cached data previously fetched
     * @var string
     */
    protected $raw;

    /**
     * The lifetime to store the entry in seconds
     * @var int
     */
    private $lifetime = 86400;

    protected $repository;

    protected $cache;

    /**
     * AbstractPackageCollection constructor.
     *
     * @param null $repository
     * @param bool $refresh
     * @param null $callback
     */
    public function __construct($repository = null, $refresh = false, $callback = null)
    {
        parent::__construct();
        if ($repository === null) {
            throw new \RuntimeException('A repository is required to indicate the origin of the remote collection');
        }

        $channel = Grav::instance()['config']->get('system.gpm.releases', 'stable');
        $cache_dir = Grav::instance()['locator']->findResource('cache://gpm', true, true);
        $this->cache = new FilesystemCache($cache_dir);

        $this->repository = $repository . '?v=' . GRAV_VERSION . '&' . $channel . '=1';
        $this->raw        = $this->cache->fetch(md5($this->repository));

        $this->fetch($refresh, $callback);
        foreach (json_decode($this->raw, true) as $slug => $data) {
            // Temporarily fix for using multisites
            if (isset($data['install_path'])) {
                $path = preg_replace('~^user/~i', 'user://', $data['install_path']);
                $data['install_path'] = Grav::instance()['locator']->findResource($path, false, true);
            }
            $this->items[$slug] = new Package($data, $this->type);
        }
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
