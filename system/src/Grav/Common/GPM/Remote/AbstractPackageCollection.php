<?php

/**
 * @package    Grav\Common\GPM
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\GPM\Remote;

use Grav\Common\Grav;
use Grav\Common\GPM\Common\AbstractPackageCollection as BaseCollection;
use Grav\Common\GPM\Response;
use \Doctrine\Common\Cache\FilesystemCache;
use RuntimeException;

/**
 * Class AbstractPackageCollection
 * @package Grav\Common\GPM\Remote
 */
class AbstractPackageCollection extends BaseCollection
{
    /** @var string The cached data previously fetched */
    protected $raw;
    /** @var string */
    protected $repository;
    /** @var FilesystemCache */
    protected $cache;

    /** @var int The lifetime to store the entry in seconds */
    private $lifetime = 86400;

    /**
     * AbstractPackageCollection constructor.
     *
     * @param string|null $repository
     * @param bool $refresh
     * @param callable|null $callback
     */
    public function __construct($repository = null, $refresh = false, $callback = null)
    {
        parent::__construct();
        if ($repository === null) {
            throw new RuntimeException('A repository is required to indicate the origin of the remote collection');
        }

        $channel = Grav::instance()['config']->get('system.gpm.releases', 'stable');
        $cache_dir = Grav::instance()['locator']->findResource('cache://gpm', true, true);
        $this->cache = new FilesystemCache($cache_dir);

        $this->repository = $repository . '?v=' . GRAV_VERSION . '&' . $channel . '=1';
        $this->raw        = $this->cache->fetch(md5($this->repository));

        $this->fetch($refresh, $callback);
        foreach (json_decode($this->raw, true) as $slug => $data) {
            // Temporarily fix for using multi-sites
            if (isset($data['install_path'])) {
                $path = preg_replace('~^user/~i', 'user://', $data['install_path']);
                $data['install_path'] = Grav::instance()['locator']->findResource($path, false, true);
            }
            $this->items[$slug] = new Package($data, $this->type);
        }
    }

    /**
     * @param bool $refresh
     * @param callable|null $callback
     * @return string
     */
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
