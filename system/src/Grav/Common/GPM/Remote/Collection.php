<?php
namespace Grav\Common\GPM\Remote;

use Grav\Common\GPM\Response;
use Grav\Common\GravTrait;
use Grav\Common\Iterator;

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

    private $plugins, $themes;

    public function __construct($repository = null) {
        if ($repository == null) {
            throw new \RuntimeException("A repository is required for storing the cache");
        }

        $this->repository = $repository;
        $this->raw        = self::$grav['cache']->fetch(md5($this->repository));
    }

    public function toJson() {
        $items = [];

        foreach ($this->items as $name => $theme) {
            $items[$name] = $theme->toArray();
        }

        return json_encode($items);
    }

    public function toArray() {
        $items = [];

        foreach ($this->items as $name => $theme) {
            $items[$name] = $theme->toArray();
        }

        return $items;
    }

    public function fetch($callback = null, $force = false) {
        if (!$this->raw || $force) {
            $response  = Response::get($this->repository, [], $callback);
            $this->raw = $response;
            self::$grav['cache']->save(md5($this->repository), $this->raw, $this->lifetime);
        }

        return $this->raw;
    }
}
