<?php
namespace Grav\Common\GPM;

use Grav\Common\Iterator;

class GPM extends Iterator {
    private $installed, $repository;
    protected $cache;

    public function __construct() {
        $this->installed  = new Local\Packages();
        $this->repository = new Remote\Packages();
    }

    public function getInstalled() {
        return $this->installed;
    }

    public function getInstalledPlugins() {
        return $this->installed['plugins'];
    }

    public function isPluginInstalled($slug) {
        return isset($this->installed['plugins'][$slug]);
    }

    public function getInstalledThemes() {
        return $this->installed['themes'];
    }

    public function isThemeInstalled($slug) {
        return isset($this->installed['themes'][$slug]);
    }

    public function countUpdates() {
        $count = 0;

        $count += count($this->getUpdatablePlugins());
        $count += count($this->getUpdatableThemes());

        return $count;
    }

    public function getUpdatablePlugins() {
        $items      = [];
        $repository = $this->repository['plugins'];

        // local cache to speed things up
        if (isset($this->cache[__METHOD__])) {
            return $this->cache[__METHOD__];
        }

        foreach ($this->installed['plugins'] as $slug => $plugin) {
            if (!isset($repository[$slug])) {
                continue;
            }

            $local_version  = $plugin->version ? $plugin->version : 'unknown';
            $remote_version = $repository[$slug]->version;

            if (version_compare($local_version, $remote_version) < 0) {
                $repository[$slug]->current_version = $local_version;
                $items[]                            = $repository[$slug];
            }
        }

        $this->cache[__METHOD__] = $items;
        return $items;
    }

    public function isPluginUpdatable($plugin) {
        return array_key_exists($plugin, $this->getUpdatablePlugins());
    }

    public function getUpdatableThemes() {
        $items      = [];
        $repository = $this->repository['themes'];

        // local cache to speed things up
        if (isset($this->cache[__METHOD__])) {
            return $this->cache[__METHOD__];
        }

        foreach ($this->installed['themes'] as $slug => $plugin) {
            if (!isset($repository[$slug])) {
                continue;
            }

            $local_version  = $plugin->version ? $plugin->version : 'unknown';
            $remote_version = $repository[$slug]->version;

            if (version_compare($local_version, $remote_version) < 0) {
                $repository[$slug]->current_version = $local_version;
                $items[]                            = $repository[$slug];
            }
        }

        $this->cache[__METHOD__] = $items;
        return $items;
    }

    public function isThemeUpdatable($theme) {
        return array_key_exists($theme, $this->getUpdatableThemes());
    }

    public function getRepositoryPlugins() {
        return $this->repository['plugins'];
    }

    public function getRepositoryThemes() {
        return $this->repository['themes'];
    }

    public function getRepository() {
        return $this->repository;
    }
}
