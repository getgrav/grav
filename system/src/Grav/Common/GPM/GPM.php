<?php
namespace Grav\Common\GPM;

use Grav\Common\Inflector;
use Grav\Common\Iterator;
use Grav\Common\Utils;

class GPM extends Iterator
{
    /**
     * Local installed Packages
     * @var Local\Packages
     */
    private $installed;

    /**
     * Remote available Packages
     * @var Remote\Packages
     */
    private $repository;

    /**
     * @var Remote\Grav
     */
    public $grav;

    /**
     * Internal cache
     * @var
     */
    protected $cache;

    protected $install_paths = ['plugins' => 'user/plugins/%name%', 'themes' => 'user/themes/%name%', 'skeletons' => 'user/'];

    /**
     * Creates a new GPM instance with Local and Remote packages available
     * @param boolean  $refresh  Applies to Remote Packages only and forces a refetch of data
     * @param callable $callback Either a function or callback in array notation
     */
    public function __construct($refresh = false, $callback = null)
    {
        $this->installed  = new Local\Packages();
        try {
            $this->repository = new Remote\Packages($refresh, $callback);
            $this->grav       = new Remote\Grav($refresh, $callback);
        } catch (\Exception $e) {
        }
    }

    /**
     * Returns the Locally installed packages
     * @return Iterator The installed packages
     */
    public function getInstalled()
    {
        return $this->installed;
    }

    /**
     * Returns the amount of locally installed packages
     * @return integer Amount of installed packages
     */
    public function countInstalled()
    {
        $installed = $this->getInstalled();

        return count($installed['plugins']) + count($installed['themes']);
    }

    /**
     * Return the instance of a specific Plugin
     * @param  string  $slug The slug of the Plugin
     * @return Local\Package The instance of the Plugin
     */
    public function getInstalledPlugin($slug)
    {
        return $this->installed['plugins'][$slug];
    }

    /**
     * Returns the Locally installed plugins
     * @return Iterator The installed plugins
     */
    public function getInstalledPlugins()
    {
        return $this->installed['plugins'];
    }

    /**
     * Checks if a Plugin is installed
     * @param  string  $slug The slug of the Plugin
     * @return boolean True if the Plugin has been installed. False otherwise
     */
    public function isPluginInstalled($slug)
    {
        return isset($this->installed['plugins'][$slug]);
    }

    /**
     * Return the instance of a specific Theme
     * @param  string  $slug The slug of the Theme
     * @return Local\Package The instance of the Theme
     */
    public function getInstalledTheme($slug)
    {
        return $this->installed['themes'][$slug];
    }

    /**
     * Returns the Locally installed themes
     * @return Iterator The installed themes
     */
    public function getInstalledThemes()
    {
        return $this->installed['themes'];
    }

    /**
     * Checks if a Theme is installed
     * @param  string  $slug The slug of the Theme
     * @return boolean True if the Theme has been installed. False otherwise
     */
    public function isThemeInstalled($slug)
    {
        return isset($this->installed['themes'][$slug]);
    }

    /**
     * Returns the amount of updates available
     * @return integer Amount of available updates
     */
    public function countUpdates()
    {
        $count = 0;

        $count += count($this->getUpdatablePlugins());
        $count += count($this->getUpdatableThemes());

        return $count;
    }

    /**
     * Returns an array of Plugins and Themes that can be updated.
     * Plugins and Themes are extended with the `available` property that relies to the remote version
     * @return array Array of updatable Plugins and Themes.
     *               Format: ['total' => int, 'plugins' => array, 'themes' => array]
     */
    public function getUpdatable()
    {
        $plugins = $this->getUpdatablePlugins();
        $themes  = $this->getUpdatableThemes();

        $items = [
            'total'   => count($plugins)+count($themes),
            'plugins' => $plugins,
            'themes'  => $themes
        ];

        return $items;
    }

    /**
     * Returns an array of Plugins that can be updated.
     * The Plugins are extended with the `available` property that relies to the remote version
     * @return Iterator Array of updatable Plugins
     */
    public function getUpdatablePlugins()
    {
        $items      = [];
        $repository = $this->repository['plugins'];

        // local cache to speed things up
        if (isset($this->cache[__METHOD__])) {
            return $this->cache[__METHOD__];
        }

        foreach ($this->installed['plugins'] as $slug => $plugin) {
            if (!isset($repository[$slug]) || $plugin->symlink) {
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

    /**
     * Check if a Plugin or Theme is updatable
     * @param  string  $slug The slug of the package
     * @return boolean True if updatable. False otherwise or if not found
     */
    public function isUpdatable($slug)
    {
        return $this->isPluginUpdatable($slug) || $this->isThemeUpdatable($slug);
    }

    /**
     * Checks if a Plugin is updatable
     * @param  string  $plugin The slug of the Plugin
     * @return boolean True if the Plugin is updatable. False otherwise
     */
    public function isPluginUpdatable($plugin)
    {
        return array_key_exists($plugin, (array) $this->getUpdatablePlugins());
    }

    /**
     * Returns an array of Themes that can be updated.
     * The Themes are extended with the `available` property that relies to the remote version
     * @return Iterator Array of updatable Themes
     */
    public function getUpdatableThemes()
    {
        $items      = [];
        $repository = $this->repository['themes'];

        // local cache to speed things up
        if (isset($this->cache[__METHOD__])) {
            return $this->cache[__METHOD__];
        }

        foreach ($this->installed['themes'] as $slug => $plugin) {
            if (!isset($repository[$slug]) || $plugin->symlink) {
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

    /**
     * Checks if a Theme is Updatable
     * @param  string  $theme The slug of the Theme
     * @return boolean True if the Theme is updatable. False otherwise
     */
    public function isThemeUpdatable($theme)
    {
        return array_key_exists($theme, (array) $this->getUpdatableThemes());
    }

    /**
     * Returns a Plugin from the repository
     * @param  string $slug The slug of the Plugin
     * @return mixed  Package if found, NULL if not
     */
    public function getRepositoryPlugin($slug)
    {
        return @$this->repository['plugins'][$slug];
    }

    /**
     * Returns the list of Plugins available in the repository
     * @return Iterator The Plugins remotely available
     */
    public function getRepositoryPlugins()
    {
        return $this->repository['plugins'];
    }

    /**
     * Returns a Theme from the repository
     * @param  string $slug The slug of the Theme
     * @return mixed  Package if found, NULL if not
     */
    public function getRepositoryTheme($slug)
    {
        return @$this->repository['themes'][$slug];
    }

    /**
     * Returns the list of Themes available in the repository
     * @return Iterator The Themes remotely available
     */
    public function getRepositoryThemes()
    {
        return $this->repository['themes'];
    }

    /**
     * Returns the list of Plugins and Themes available in the repository
     * @return array Array of available Plugins and Themes
     *               Format: ['plugins' => array, 'themes' => array]
     */
    public function getRepository()
    {
        return $this->repository;
    }

    /**
     * Searches for a Package in the repository
     * @param  string  $search Can be either the slug or the name
     * @return Remote\Package Package if found, FALSE if not
     */
    public function findPackage($search)
    {
        $search = strtolower($search);
        if ($found = $this->getRepositoryTheme($search)) {
            return $found;
        }

        if ($found = $this->getRepositoryPlugin($search)) {
            return $found;
        }

        $themes = $this->getRepositoryThemes();
        $plugins = $this->getRepositoryPlugins();

        if (!$themes && !$plugins) {
            if (!is_writable(ROOT_DIR . '/cache/gpm')) {
                throw new \RuntimeException("The cache/gpm folder is not writable. Please check the folder permissions.");
            }

            throw new \RuntimeException("GPM not reachable. Please check your internet connection or check the Grav site is reachable");
        }

        if ($themes) foreach ($themes as $slug => $theme) {
            if ($search == $slug || $search == $theme->name) {
                return $theme;
            }
        }

        if ($plugins) foreach ($plugins as $slug => $plugin) {
            if ($search == $slug || $search == $plugin->name) {
                return $plugin;
            }
        }

        return false;
    }

    /**
     * Returns the list of Plugins and Themes available in the repository
     * @return array Array of available Plugins and Themes
     *               Format: ['plugins' => array, 'themes' => array]
     */
    /**
     * Searches for a list of Packages in the repository
     * @param  array $searches An array of either slugs or names
     * @return array Array of found Packages
     *                        Format: ['total' => int, 'not_found' => array, <found-slugs>]
     */
    public function findPackages($searches = [])
    {
        $packages = ['total' => 0, 'not_found' => []];
        $inflector = new Inflector();

        foreach ($searches as $search) {
            $repository = '';
            // if this is an object, get the search data from the key
            if (is_object($search)) {
                $search = (array) $search;
                $key = key($search);
                $repository = $search[$key];
                $search = $key;
            }

            if ($found = $this->findPackage($search)) {
                // set override repository if provided
                if ($repository) {
                    $found->override_repository = $repository;
                }
                if (!isset($packages[$found->package_type])) {
                    $packages[$found->package_type] = [];
                }

                $packages[$found->package_type][$found->slug] = $found;
                $packages['total']++;
            } else {
                // make a best guess at the type based on the repo URL
                if (Utils::contains($repository, '-theme')) {
                    $type = 'themes';
                } else {
                    $type = 'plugins';
                }

                $not_found = new \stdClass();
                $not_found->name = $inflector->camelize($search);
                $not_found->slug = $search;
                $not_found->package_type = $type;
                $not_found->install_path = str_replace('%name%', $search, $this->install_paths[$type]);
                $not_found->override_repository = $repository;
                $packages['not_found'][$search] = $not_found;
            }
        }

        return $packages;
    }
}
