<?php
namespace Grav\Common\GPM;

use Grav\Common\Iterator;

class GPM extends Iterator {
    private $installed, $repository;
    protected $cache;

    public function __construct($refresh = false, $callback = null) {
        $this->installed  = new Local\Packages();
        $this->repository = new Remote\Packages($refresh, $callback);
    }

    public function getInstalled() {
        return $this->installed;
    }

    public function getInstalledPlugin($slug) {
        return $this->installed['plugins'][$slug];
    }

    public function getInstalledPlugins() {
        return $this->installed['plugins'];
    }

    public function isPluginInstalled($slug) {
        return isset($this->installed['plugins'][$slug]);
    }

    public function getInstalledTheme($slug) {
        return $this->installed['themes'][$slug];
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

    public function getUpdatable() {
        $items = [
            'plugins' => $this->getUpdatablePlugins(),
            'themes'  => $this->getUpdatableThemes()
        ];

        return $items;
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

            $local_version  = $plugin->version ? $plugin->version : 'Unknown';
            $remote_version = $repository[$slug]->version;

            if (version_compare($local_version, $remote_version) < 0) {
                $repository[$slug]->available = $remote_version;
                $repository[$slug]->version   = $local_version;
                $items[$slug]                 = $repository[$slug];
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

            $local_version  = $plugin->version ? $plugin->version : 'Unknown';
            $remote_version = $repository[$slug]->version;

            if (version_compare($local_version, $remote_version) < 0) {
                $repository[$slug]->available = $remote_version;
                $repository[$slug]->version   = $local_version;
                $items[$slug]                 = $repository[$slug];
            }
        }

        $this->cache[__METHOD__] = $items;
        return $items;
    }

    public function isThemeUpdatable($theme) {
        return array_key_exists($theme, $this->getUpdatableThemes());
    }

    public function getRepositoryPlugin($slug) {
        return $this->repository['plugins'][$slug];
    }

    public function getRepositoryPlugins() {
        return $this->repository['plugins'];
    }

    public function getRepositoryTheme($slug) {
        return $this->repository['plugins'][$slug];
    }

    public function getRepositoryThemes() {
        return $this->repository['themes'];
    }

    public function getRepository() {
        return $this->repository;
    }

    public function findPackage($search) {
        $search = strtolower($search);
        if ($found = $this->getRepositoryTheme($search)) {
            return $found;
        }

        if ($found = $this->getRepositoryPlugin($search)) {
            return $found;
        }

        foreach ($this->getRepositoryThemes() as $slug => $theme) {
            if ($search == $slug || $search == $theme->name) {
                return $theme;
            }
        }

        foreach ($this->getRepositoryPlugins() as $slug => $plugin) {
            if ($search == $slug || $search == $plugin->name) {
                return $plugin;
            }
        }

        return false;
    }
}
