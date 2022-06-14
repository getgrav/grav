<?php

/**
 * @package    Grav\Common\GPM
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\GPM;

use Exception;
use Grav\Common\Grav;
use Grav\Common\Filesystem\Folder;
use Grav\Common\HTTP\Response;
use Grav\Common\Inflector;
use Grav\Common\Iterator;
use Grav\Common\Utils;
use RocketTheme\Toolbox\File\YamlFile;
use RuntimeException;
use stdClass;
use function array_key_exists;
use function count;
use function in_array;
use function is_array;
use function is_object;

/**
 * Class GPM
 * @package Grav\Common\GPM
 */
class GPM extends Iterator
{
    /** @var Local\Packages Local installed Packages */
    private $installed;
    /** @var Remote\Packages|null Remote available Packages */
    private $repository;
    /** @var Remote\GravCore|null Remove Grav Packages */
    private $grav;
    /** @var bool */
    private $refresh;
    /** @var callable|null */
    private $callback;

    /** @var array Internal cache */
    protected $cache;
    /** @var array */
    protected $install_paths = [
        'plugins' => 'user/plugins/%name%',
        'themes' => 'user/themes/%name%',
        'skeletons' => 'user/'
    ];

    /**
     * Creates a new GPM instance with Local and Remote packages available
     *
     * @param bool $refresh Applies to Remote Packages only and forces a refetch of data
     * @param callable|null $callback Either a function or callback in array notation
     */
    public function __construct($refresh = false, $callback = null)
    {
        parent::__construct();

        Folder::create(CACHE_DIR . '/gpm');

        $this->cache = [];
        $this->installed = new Local\Packages();
        $this->refresh = $refresh;
        $this->callback = $callback;
    }

    /**
     * Magic getter method
     *
     * @param string $offset Asset name value
     * @return mixed Asset value
     */
    #[\ReturnTypeWillChange]
    public function __get($offset)
    {
        switch ($offset) {
            case 'grav':
                return $this->getGrav();
        }

        return parent::__get($offset);
    }

    /**
     * Magic method to determine if the attribute is set
     *
     * @param string $offset Asset name value
     * @return bool True if the value is set
     */
    #[\ReturnTypeWillChange]
    public function __isset($offset)
    {
        switch ($offset) {
            case 'grav':
                return $this->getGrav() !== null;
        }

        return parent::__isset($offset);
    }

    /**
     * Return the locally installed packages
     *
     * @return Local\Packages
     */
    public function getInstalled()
    {
        return $this->installed;
    }

    /**
     * Returns the Locally installable packages
     *
     * @param array $list_type_installed
     * @return array The installed packages
     */
    public function getInstallable($list_type_installed = ['plugins' => true, 'themes' => true])
    {
        $items = ['total' => 0];
        foreach ($list_type_installed as $type => $type_installed) {
            if ($type_installed === false) {
                continue;
            }
            $methodInstallableType = 'getInstalled' . ucfirst($type);
            $to_install = $this->$methodInstallableType();
            $items[$type] = $to_install;
            $items['total'] += count($to_install);
        }

        return $items;
    }

    /**
     * Returns the amount of locally installed packages
     *
     * @return int Amount of installed packages
     */
    public function countInstalled()
    {
        $installed = $this->getInstalled();

        return count($installed['plugins']) + count($installed['themes']);
    }

    /**
     * Return the instance of a specific Package
     *
     * @param  string $slug The slug of the Package
     * @return Local\Package|null The instance of the Package
     */
    public function getInstalledPackage($slug)
    {
        return $this->getInstalledPlugin($slug) ?? $this->getInstalledTheme($slug);
    }

    /**
     * Return the instance of a specific Plugin
     *
     * @param  string $slug The slug of the Plugin
     * @return Local\Package|null The instance of the Plugin
     */
    public function getInstalledPlugin($slug)
    {
        return $this->installed['plugins'][$slug] ?? null;
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
     * Returns the plugin's enabled state
     *
     * @param  string $slug
     * @return bool True if the Plugin is Enabled. False if manually set to enable:false. Null otherwise.
     */
    public function isPluginEnabled($slug): bool
    {
        $grav = Grav::instance();

        return ($grav['config']['plugins'][$slug]['enabled'] ?? false) === true;
    }

    /**
     * Checks if a Plugin is installed
     *
     * @param  string $slug The slug of the Plugin
     * @return bool True if the Plugin has been installed. False otherwise
     */
    public function isPluginInstalled($slug): bool
    {
        return isset($this->installed['plugins'][$slug]);
    }

    /**
     * @param string $slug
     * @return bool
     */
    public function isPluginInstalledAsSymlink($slug)
    {
        $plugin = $this->getInstalledPlugin($slug);

        return (bool)($plugin->symlink ?? false);
    }

    /**
     * Return the instance of a specific Theme
     *
     * @param  string $slug The slug of the Theme
     * @return Local\Package|null The instance of the Theme
     */
    public function getInstalledTheme($slug)
    {
        return $this->installed['themes'][$slug] ?? null;
    }

    /**
     * Returns the Locally installed themes
     *
     * @return Iterator The installed themes
     */
    public function getInstalledThemes()
    {
        return $this->installed['themes'];
    }

    /**
     * Checks if a Theme is enabled
     *
     * @param  string $slug The slug of the Theme
     * @return bool True if the Theme has been set to the default theme. False if installed, but not enabled. Null otherwise.
     */
    public function isThemeEnabled($slug): bool
    {
        $grav = Grav::instance();

        $current_theme = $grav['config']['system']['pages']['theme'] ?? null;

        return $current_theme === $slug;
    }

    /**
     * Checks if a Theme is installed
     *
     * @param  string $slug The slug of the Theme
     * @return bool True if the Theme has been installed. False otherwise
     */
    public function isThemeInstalled($slug): bool
    {
        return isset($this->installed['themes'][$slug]);
    }

    /**
     * Returns the amount of updates available
     *
     * @return int Amount of available updates
     */
    public function countUpdates()
    {
        return count($this->getUpdatablePlugins()) + count($this->getUpdatableThemes());
    }

    /**
     * Returns an array of Plugins and Themes that can be updated.
     * Plugins and Themes are extended with the `available` property that relies to the remote version
     *
     * @param array $list_type_update specifies what type of package to update
     * @return array Array of updatable Plugins and Themes.
     *               Format: ['total' => int, 'plugins' => array, 'themes' => array]
     */
    public function getUpdatable($list_type_update = ['plugins' => true, 'themes' => true])
    {
        $items = ['total' => 0];
        foreach ($list_type_update as $type => $type_updatable) {
            if ($type_updatable === false) {
                continue;
            }
            $methodUpdatableType = 'getUpdatable' . ucfirst($type);
            $to_update = $this->$methodUpdatableType();
            $items[$type] = $to_update;
            $items['total'] += count($to_update);
        }

        return $items;
    }

    /**
     * Returns an array of Plugins that can be updated.
     * The Plugins are extended with the `available` property that relies to the remote version
     *
     * @return array Array of updatable Plugins
     */
    public function getUpdatablePlugins()
    {
        $items = [];

        $repository = $this->getRepository();
        if (null === $repository) {
            return $items;
        }

        $plugins = $repository['plugins'];

        // local cache to speed things up
        if (isset($this->cache[__METHOD__])) {
            return $this->cache[__METHOD__];
        }

        foreach ($this->installed['plugins'] as $slug => $plugin) {
            if (!isset($plugins[$slug]) || $plugin->symlink || !$plugin->version || $plugin->gpm === false) {
                continue;
            }

            $local_version = $plugin->version ?? 'Unknown';
            $remote_version = $plugins[$slug]->version;

            if (version_compare($local_version, $remote_version) < 0) {
                $plugins[$slug]->available = $remote_version;
                $plugins[$slug]->version = $local_version;
                $plugins[$slug]->type = $plugins[$slug]->release_type;
                $items[$slug] = $plugins[$slug];
            }
        }

        $this->cache[__METHOD__] = $items;

        return $items;
    }

    /**
     * Get the latest release of a package from the GPM
     *
     * @param string $package_name
     * @return string|null
     */
    public function getLatestVersionOfPackage($package_name)
    {
        $repository = $this->getRepository();
        if (null === $repository) {
            return null;
        }

        $plugins = $repository['plugins'];
        if (isset($plugins[$package_name])) {
            return $plugins[$package_name]->available ?: $plugins[$package_name]->version;
        }

        //Not a plugin, it's a theme?
        $themes = $repository['themes'];
        if (isset($themes[$package_name])) {
            return $themes[$package_name]->available ?: $themes[$package_name]->version;
        }

        return null;
    }

    /**
     * Check if a Plugin or Theme is updatable
     *
     * @param  string $slug The slug of the package
     * @return bool True if updatable. False otherwise or if not found
     */
    public function isUpdatable($slug)
    {
        return $this->isPluginUpdatable($slug) || $this->isThemeUpdatable($slug);
    }

    /**
     * Checks if a Plugin is updatable
     *
     * @param  string $plugin The slug of the Plugin
     * @return bool True if the Plugin is updatable. False otherwise
     */
    public function isPluginUpdatable($plugin)
    {
        return array_key_exists($plugin, (array)$this->getUpdatablePlugins());
    }

    /**
     * Returns an array of Themes that can be updated.
     * The Themes are extended with the `available` property that relies to the remote version
     *
     * @return array Array of updatable Themes
     */
    public function getUpdatableThemes()
    {
        $items = [];

        $repository = $this->getRepository();
        if (null === $repository) {
            return $items;
        }

        $themes = $repository['themes'];

        // local cache to speed things up
        if (isset($this->cache[__METHOD__])) {
            return $this->cache[__METHOD__];
        }

        foreach ($this->installed['themes'] as $slug => $plugin) {
            if (!isset($themes[$slug]) || $plugin->symlink || !$plugin->version || $plugin->gpm === false) {
                continue;
            }

            $local_version = $plugin->version ?? 'Unknown';
            $remote_version = $themes[$slug]->version;

            if (version_compare($local_version, $remote_version) < 0) {
                $themes[$slug]->available = $remote_version;
                $themes[$slug]->version = $local_version;
                $themes[$slug]->type = $themes[$slug]->release_type;
                $items[$slug] = $themes[$slug];
            }
        }

        $this->cache[__METHOD__] = $items;

        return $items;
    }

    /**
     * Checks if a Theme is Updatable
     *
     * @param  string $theme The slug of the Theme
     * @return bool True if the Theme is updatable. False otherwise
     */
    public function isThemeUpdatable($theme)
    {
        return array_key_exists($theme, (array)$this->getUpdatableThemes());
    }

    /**
     * Get the release type of a package (stable / testing)
     *
     * @param string $package_name
     * @return string|null
     */
    public function getReleaseType($package_name)
    {
        $repository = $this->getRepository();
        if (null === $repository) {
            return null;
        }

        $plugins = $repository['plugins'];
        if (isset($plugins[$package_name])) {
            return $plugins[$package_name]->release_type;
        }

        //Not a plugin, it's a theme?
        $themes = $repository['themes'];
        if (isset($themes[$package_name])) {
            return $themes[$package_name]->release_type;
        }

        return null;
    }

    /**
     * Returns true if the package latest release is stable
     *
     * @param string $package_name
     * @return bool
     */
    public function isStableRelease($package_name)
    {
        return $this->getReleaseType($package_name) === 'stable';
    }

    /**
     * Returns true if the package latest release is testing
     *
     * @param string $package_name
     * @return bool
     */
    public function isTestingRelease($package_name)
    {
        $package = $this->getInstalledPackage($package_name);
        $testing = $package->testing ?? false;

        return $this->getReleaseType($package_name) === 'testing' || $testing;
    }

    /**
     * Returns a Plugin from the repository
     *
     * @param  string $slug The slug of the Plugin
     * @return Remote\Package|null  Package if found, NULL if not
     */
    public function getRepositoryPlugin($slug)
    {
        $packages = $this->getRepositoryPlugins();

        return $packages ? ($packages[$slug] ?? null) : null;
    }

    /**
     * Returns the list of Plugins available in the repository
     *
     * @return Iterator|null The Plugins remotely available
     */
    public function getRepositoryPlugins()
    {
        return $this->getRepository()['plugins'] ?? null;
    }

    /**
     * Returns a Theme from the repository
     *
     * @param  string $slug The slug of the Theme
     * @return Remote\Package|null  Package if found, NULL if not
     */
    public function getRepositoryTheme($slug)
    {
        $packages = $this->getRepositoryThemes();

        return $packages ? ($packages[$slug] ?? null) : null;
    }

    /**
     * Returns the list of Themes available in the repository
     *
     * @return Iterator|null The Themes remotely available
     */
    public function getRepositoryThemes()
    {
        return $this->getRepository()['themes'] ?? null;
    }

    /**
     * Returns the list of Plugins and Themes available in the repository
     *
     * @return Remote\Packages|null Available Plugins and Themes
     *               Format: ['plugins' => array, 'themes' => array]
     */
    public function getRepository()
    {
        if (null === $this->repository) {
            try {
                $this->repository = new Remote\Packages($this->refresh, $this->callback);
            } catch (Exception $e) {}
        }

        return $this->repository;
    }

    /**
     * Returns Grav version available in the repository
     *
     * @return Remote\GravCore|null
     */
    public function getGrav()
    {
        if (null === $this->grav) {
            try {
                $this->grav = new Remote\GravCore($this->refresh, $this->callback);
            } catch (Exception $e) {}
        }

        return $this->grav;
    }

    /**
     * Searches for a Package in the repository
     *
     * @param  string $search Can be either the slug or the name
     * @param  bool $ignore_exception True if should not fire an exception (for use in Twig)
     * @return Remote\Package|false Package if found, FALSE if not
     */
    public function findPackage($search, $ignore_exception = false)
    {
        $search = strtolower($search);

        $found = $this->getRepositoryPlugin($search) ?? $this->getRepositoryTheme($search);
        if ($found) {
            return $found;
        }

        $themes = $this->getRepositoryThemes();
        $plugins = $this->getRepositoryPlugins();

        if (null === $themes || null === $plugins) {
            if (!is_writable(GRAV_ROOT . '/cache/gpm')) {
                throw new RuntimeException('The cache/gpm folder is not writable. Please check the folder permissions.');
            }

            if ($ignore_exception) {
                return false;
            }

            throw new RuntimeException('GPM not reachable. Please check your internet connection or check the Grav site is reachable');
        }

        foreach ($themes as $slug => $theme) {
            if ($search === $slug || $search === $theme->name) {
                return $theme;
            }
        }

        foreach ($plugins as $slug => $plugin) {
            if ($search === $slug || $search === $plugin->name) {
                return $plugin;
            }
        }

        return false;
    }

    /**
     * Download the zip package via the URL
     *
     * @param string $package_file
     * @param string $tmp
     * @return string|null
     */
    public static function downloadPackage($package_file, $tmp)
    {
        $package = parse_url($package_file);
        if (!is_array($package)) {
            throw new \RuntimeException("Malformed GPM URL: {$package_file}");
        }

        $filename = Utils::basename($package['path'] ?? '');

        if (Grav::instance()['config']->get('system.gpm.official_gpm_only') && ($package['host'] ?? null) !== 'getgrav.org') {
            throw new RuntimeException('Only official GPM URLs are allowed. You can modify this behavior in the System configuration.');
        }

        $output = Response::get($package_file, []);

        if ($output) {
            Folder::create($tmp);
            file_put_contents($tmp . DS . $filename, $output);
            return $tmp . DS . $filename;
        }

        return null;
    }

    /**
     * Copy the local zip package to tmp
     *
     * @param string $package_file
     * @param string $tmp
     * @return string|null
     */
    public static function copyPackage($package_file, $tmp)
    {
        $package_file = realpath($package_file);

        if ($package_file && file_exists($package_file)) {
            $filename = Utils::basename($package_file);
            Folder::create($tmp);
            copy($package_file, $tmp . DS . $filename);
            return $tmp . DS . $filename;
        }

        return null;
    }

    /**
     * Try to guess the package type from the source files
     *
     * @param string $source
     * @return string|false
     */
    public static function getPackageType($source)
    {
        $plugin_regex = '/^class\\s{1,}[a-zA-Z0-9]{1,}\\s{1,}extends.+Plugin/m';
        $theme_regex = '/^class\\s{1,}[a-zA-Z0-9]{1,}\\s{1,}extends.+Theme/m';

        if (file_exists($source . 'system/defines.php') &&
            file_exists($source . 'system/config/system.yaml')
        ) {
            return 'grav';
        }

        // must have a blueprint
        if (!file_exists($source . 'blueprints.yaml')) {
            return false;
        }

        // either theme or plugin
        $name = Utils::basename($source);
        if (Utils::contains($name, 'theme')) {
            return 'theme';
        }
        if (Utils::contains($name, 'plugin')) {
            return 'plugin';
        }

        $glob = glob($source . '*.php') ?: [];
        foreach ($glob as $filename) {
            $contents = file_get_contents($filename);
            if (!$contents) {
                continue;
            }
            if (preg_match($theme_regex, $contents)) {
                return 'theme';
            }
            if (preg_match($plugin_regex, $contents)) {
                return 'plugin';
            }
        }

        // Assume it's a theme
        return 'theme';
    }

    /**
     * Try to guess the package name from the source files
     *
     * @param string $source
     * @return string|false
     */
    public static function getPackageName($source)
    {
        $ignore_yaml_files = ['blueprints', 'languages'];

        $glob = glob($source . '*.yaml') ?: [];
        foreach ($glob as $filename) {
            $name = strtolower(Utils::basename($filename, '.yaml'));
            if (in_array($name, $ignore_yaml_files)) {
                continue;
            }

            return $name;
        }

        return false;
    }

    /**
     * Find/Parse the blueprint file
     *
     * @param string $source
     * @return array|false
     */
    public static function getBlueprints($source)
    {
        $blueprint_file = $source . 'blueprints.yaml';
        if (!file_exists($blueprint_file)) {
            return false;
        }

        $file = YamlFile::instance($blueprint_file);
        $blueprint = (array)$file->content();
        $file->free();

        return $blueprint;
    }

    /**
     * Get the install path for a name and a particular type of package
     *
     * @param string $type
     * @param string $name
     * @return string
     */
    public static function getInstallPath($type, $name)
    {
        $locator = Grav::instance()['locator'];

        if ($type === 'theme') {
            $install_path = $locator->findResource('themes://', false) . DS . $name;
        } else {
            $install_path = $locator->findResource('plugins://', false) . DS . $name;
        }

        return $install_path;
    }

    /**
     * Searches for a list of Packages in the repository
     *
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
                $search = (array)$search;
                $key = key($search);
                $repository = $search[$key];
                $search = $key;
            }

            $found = $this->findPackage($search);
            if ($found) {
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

                $not_found = new stdClass();
                $not_found->name = $inflector::camelize($search);
                $not_found->slug = $search;
                $not_found->package_type = $type;
                $not_found->install_path = str_replace('%name%', $search, $this->install_paths[$type]);
                $not_found->override_repository = $repository;
                $packages['not_found'][$search] = $not_found;
            }
        }

        return $packages;
    }

    /**
     * Return the list of packages that have the passed one as dependency
     *
     * @param string $slug The slug name of the package
     * @return array
     */
    public function getPackagesThatDependOnPackage($slug)
    {
        $plugins = $this->getInstalledPlugins();
        $themes = $this->getInstalledThemes();
        $packages = array_merge($plugins->toArray(), $themes->toArray());

        $list = [];
        foreach ($packages as $package_name => $package) {
            $dependencies = $package['dependencies'] ?? [];
            foreach ($dependencies as $dependency) {
                if (is_array($dependency) && isset($dependency['name'])) {
                    $dependency = $dependency['name'];
                }

                if ($dependency === $slug) {
                    $list[] = $package_name;
                }
            }
        }

        return $list;
    }


    /**
     * Get the required version of a dependency of a package
     *
     * @param string $package_slug
     * @param string $dependency_slug
     * @return mixed|null
     */
    public function getVersionOfDependencyRequiredByPackage($package_slug, $dependency_slug)
    {
        $dependencies = $this->getInstalledPackage($package_slug)->dependencies ?? [];
        foreach ($dependencies as $dependency) {
            if (isset($dependency[$dependency_slug])) {
                return $dependency[$dependency_slug];
            }
        }

        return null;
    }

    /**
     * Check the package identified by $slug can be updated to the version passed as argument.
     * Thrown an exception if it cannot be updated because another package installed requires it to be at an older version.
     *
     * @param string $slug
     * @param string $version_with_operator
     * @param array $ignore_packages_list
     * @return bool
     * @throws RuntimeException
     */
    public function checkNoOtherPackageNeedsThisDependencyInALowerVersion($slug, $version_with_operator, $ignore_packages_list)
    {
        // check if any of the currently installed package need this in a lower version than the one we need. In case, abort and tell which package
        $dependent_packages = $this->getPackagesThatDependOnPackage($slug);
        $version = $this->calculateVersionNumberFromDependencyVersion($version_with_operator);

        if (count($dependent_packages)) {
            foreach ($dependent_packages as $dependent_package) {
                $other_dependency_version_with_operator = $this->getVersionOfDependencyRequiredByPackage($dependent_package, $slug);
                $other_dependency_version = $this->calculateVersionNumberFromDependencyVersion($other_dependency_version_with_operator);

                // check version is compatible with the one needed by the current package
                if ($this->versionFormatIsNextSignificantRelease($other_dependency_version_with_operator)) {
                    $compatible = $this->checkNextSignificantReleasesAreCompatible($version, $other_dependency_version);
                    if (!$compatible && !in_array($dependent_package, $ignore_packages_list, true)) {
                        throw new RuntimeException(
                            "Package <cyan>$slug</cyan> is required in an older version by package <cyan>$dependent_package</cyan>. This package needs a newer version, and because of this it cannot be installed. The <cyan>$dependent_package</cyan> package must be updated to use a newer release of <cyan>$slug</cyan>.",
                            2
                        );
                    }
                }
            }
        }

        return true;
    }

    /**
     * Check the passed packages list can be updated
     *
     * @param array $packages_names_list
     * @return void
     * @throws Exception
     */
    public function checkPackagesCanBeInstalled($packages_names_list)
    {
        foreach ($packages_names_list as $package_name) {
            $latest = $this->getLatestVersionOfPackage($package_name);
            $this->checkNoOtherPackageNeedsThisDependencyInALowerVersion($package_name, $latest, $packages_names_list);
        }
    }

    /**
     * Fetch the dependencies, check the installed packages and return an array with
     * the list of packages with associated an information on what to do: install, update or ignore.
     *
     * `ignore` means the package is already installed and can be safely left as-is.
     * `install` means the package is not installed and must be installed.
     * `update` means the package is already installed and must be updated as a dependency needs a higher version.
     *
     * @param array $packages
     * @return array
     * @throws RuntimeException
     */
    public function getDependencies($packages)
    {
        $dependencies = $this->calculateMergedDependenciesOfPackages($packages);
        foreach ($dependencies as $dependency_slug => $dependencyVersionWithOperator) {
            $dependency_slug = (string)$dependency_slug;
            if (in_array($dependency_slug, $packages, true)) {
                unset($dependencies[$dependency_slug]);
                continue;
            }

            // Check PHP version
            if ($dependency_slug === 'php') {
                $testVersion = $this->calculateVersionNumberFromDependencyVersion($dependencyVersionWithOperator);
                if (version_compare($testVersion, PHP_VERSION) === 1) {
                    //Needs a Grav update first
                    throw new RuntimeException("<red>One of the packages require PHP {$dependencies['php']}. Please update PHP to resolve this");
                }

                unset($dependencies[$dependency_slug]);
                continue;
            }

            //First, check for Grav dependency. If a dependency requires Grav > the current version, abort and tell.
            if ($dependency_slug === 'grav') {
                $testVersion = $this->calculateVersionNumberFromDependencyVersion($dependencyVersionWithOperator);
                if (version_compare($testVersion, GRAV_VERSION) === 1) {
                    //Needs a Grav update first
                    throw new RuntimeException("<red>One of the packages require Grav {$dependencies['grav']}. Please update Grav to the latest release.");
                }

                unset($dependencies[$dependency_slug]);
                continue;
            }

            if ($this->isPluginInstalled($dependency_slug)) {
                if ($this->isPluginInstalledAsSymlink($dependency_slug)) {
                    unset($dependencies[$dependency_slug]);
                    continue;
                }

                $dependencyVersion = $this->calculateVersionNumberFromDependencyVersion($dependencyVersionWithOperator);

                // get currently installed version
                $locator = Grav::instance()['locator'];
                $blueprints_path = $locator->findResource('plugins://' . $dependency_slug . DS . 'blueprints.yaml');
                $file = YamlFile::instance($blueprints_path);
                $package_yaml = $file->content();
                $file->free();
                $currentlyInstalledVersion = $package_yaml['version'];

                // if requirement is next significant release, check is compatible with currently installed version, might not be
                if ($this->versionFormatIsNextSignificantRelease($dependencyVersionWithOperator)
                    && $this->firstVersionIsLower($dependencyVersion, $currentlyInstalledVersion)) {
                    $compatible = $this->checkNextSignificantReleasesAreCompatible($dependencyVersion, $currentlyInstalledVersion);

                    if (!$compatible) {
                        throw new RuntimeException(
                            'Dependency <cyan>' . $dependency_slug . '</cyan> is required in an older version than the one installed. This package must be updated. Please get in touch with its developer.',
                            2
                        );
                    }
                }

                //if I already have the latest release, remove the dependency
                $latestRelease = $this->getLatestVersionOfPackage($dependency_slug);

                if ($this->firstVersionIsLower($latestRelease, $dependencyVersion)) {
                    //throw an exception if a required version cannot be found in the GPM yet
                    throw new RuntimeException(
                        'Dependency <cyan>' . $package_yaml['name'] . '</cyan> is required in version <cyan>' . $dependencyVersion . '</cyan> which is higher than the latest release, <cyan>' . $latestRelease . '</cyan>. Try running `bin/gpm -f index` to force a refresh of the GPM cache',
                        1
                    );
                }

                if ($this->firstVersionIsLower($currentlyInstalledVersion, $dependencyVersion)) {
                    $dependencies[$dependency_slug] = 'update';
                } elseif ($currentlyInstalledVersion === $latestRelease) {
                    unset($dependencies[$dependency_slug]);
                } else {
                    // an update is not strictly required mark as 'ignore'
                    $dependencies[$dependency_slug] = 'ignore';
                }
            } else {
                $dependencyVersion = $this->calculateVersionNumberFromDependencyVersion($dependencyVersionWithOperator);

                // if requirement is next significant release, check is compatible with latest available version, might not be
                if ($this->versionFormatIsNextSignificantRelease($dependencyVersionWithOperator)) {
                    $latestVersionOfPackage = $this->getLatestVersionOfPackage($dependency_slug);
                    if ($this->firstVersionIsLower($dependencyVersion, $latestVersionOfPackage)) {
                        $compatible = $this->checkNextSignificantReleasesAreCompatible(
                            $dependencyVersion,
                            $latestVersionOfPackage
                        );

                        if (!$compatible) {
                            throw new RuntimeException(
                                'Dependency <cyan>' . $dependency_slug . '</cyan> is required in an older version than the latest release available, and it cannot be installed. This package must be updated. Please get in touch with its developer.',
                                2
                            );
                        }
                    }
                }

                $dependencies[$dependency_slug] = 'install';
            }
        }

        $dependencies_slugs = array_keys($dependencies);
        $this->checkNoOtherPackageNeedsTheseDependenciesInALowerVersion(array_merge($packages, $dependencies_slugs));

        return $dependencies;
    }

    /**
     * @param array $dependencies_slugs
     * @return void
     */
    public function checkNoOtherPackageNeedsTheseDependenciesInALowerVersion($dependencies_slugs)
    {
        foreach ($dependencies_slugs as $dependency_slug) {
            $this->checkNoOtherPackageNeedsThisDependencyInALowerVersion(
                $dependency_slug,
                $this->getLatestVersionOfPackage($dependency_slug),
                $dependencies_slugs
            );
        }
    }

    /**
     * @param string $firstVersion
     * @param string $secondVersion
     * @return bool
     */
    private function firstVersionIsLower($firstVersion, $secondVersion)
    {
        return version_compare($firstVersion, $secondVersion) === -1;
    }

    /**
     * Calculates and merges the dependencies of a package
     *
     * @param string $packageName The package information
     * @param array $dependencies The dependencies array
     * @return array
     */
    private function calculateMergedDependenciesOfPackage($packageName, $dependencies)
    {
        $packageData = $this->findPackage($packageName);

        if (empty($packageData->dependencies)) {
            return $dependencies;
        }

        foreach ($packageData->dependencies as $dependency) {
            $dependencyName = $dependency['name'] ?? null;
            if (!$dependencyName) {
                continue;
            }

            $dependencyVersion = $dependency['version'] ?? '*';

            if (!isset($dependencies[$dependencyName])) {
                // Dependency added for the first time
                $dependencies[$dependencyName] = $dependencyVersion;

                //Factor in the package dependencies too
                $dependencies = $this->calculateMergedDependenciesOfPackage($dependencyName, $dependencies);
            } elseif ($dependencyVersion !== '*') {
                // Dependency already added by another package
                // If this package requires a version higher than the currently stored one, store this requirement instead
                $currentDependencyVersion = $dependencies[$dependencyName];
                $currently_stored_version_number = $this->calculateVersionNumberFromDependencyVersion($currentDependencyVersion);

                $currently_stored_version_is_in_next_significant_release_format = false;
                if ($this->versionFormatIsNextSignificantRelease($currentDependencyVersion)) {
                    $currently_stored_version_is_in_next_significant_release_format = true;
                }

                if (!$currently_stored_version_number) {
                    $currently_stored_version_number = '*';
                }

                $current_package_version_number = $this->calculateVersionNumberFromDependencyVersion($dependencyVersion);
                if (!$current_package_version_number) {
                    throw new RuntimeException("Bad format for version of dependency {$dependencyName} for package {$packageName}", 1);
                }

                $current_package_version_is_in_next_significant_release_format = false;
                if ($this->versionFormatIsNextSignificantRelease($dependencyVersion)) {
                    $current_package_version_is_in_next_significant_release_format = true;
                }

                //If I had stored '*', change right away with the more specific version required
                if ($currently_stored_version_number === '*') {
                    $dependencies[$dependencyName] = $dependencyVersion;
                } elseif (!$currently_stored_version_is_in_next_significant_release_format && !$current_package_version_is_in_next_significant_release_format) {
                    //Comparing versions equals or higher, a simple version_compare is enough
                    if (version_compare($currently_stored_version_number, $current_package_version_number) === -1) {
                        //Current package version is higher
                        $dependencies[$dependencyName] = $dependencyVersion;
                    }
                } else {
                    $compatible = $this->checkNextSignificantReleasesAreCompatible($currently_stored_version_number, $current_package_version_number);
                    if (!$compatible) {
                        throw new RuntimeException("Dependency {$dependencyName} is required in two incompatible versions", 2);
                    }
                }
            }
        }

        return $dependencies;
    }

    /**
     * Calculates and merges the dependencies of the passed packages
     *
     * @param array $packages
     * @return array
     */
    public function calculateMergedDependenciesOfPackages($packages)
    {
        $dependencies = [];

        foreach ($packages as $package) {
            $dependencies = $this->calculateMergedDependenciesOfPackage($package, $dependencies);
        }

        return $dependencies;
    }

    /**
     * Returns the actual version from a dependency version string.
     * Examples:
     *      $versionInformation == '~2.0' => returns '2.0'
     *      $versionInformation == '>=2.0.2' => returns '2.0.2'
     *      $versionInformation == '2.0.2' => returns '2.0.2'
     *      $versionInformation == '*' => returns null
     *      $versionInformation == '' => returns null
     *
     * @param string $version
     * @return string|null
     */
    public function calculateVersionNumberFromDependencyVersion($version)
    {
        if ($version === '*') {
            return null;
        }
        if ($version === '') {
            return null;
        }
        if ($this->versionFormatIsNextSignificantRelease($version)) {
            return trim(substr($version, 1));
        }
        if ($this->versionFormatIsEqualOrHigher($version)) {
            return trim(substr($version, 2));
        }

        return $version;
    }

    /**
     * Check if the passed version information contains next significant release (tilde) operator
     *
     * Example: returns true for $version: '~2.0'
     *
     * @param string $version
     * @return bool
     */
    public function versionFormatIsNextSignificantRelease($version): bool
    {
        return strpos($version, '~') === 0;
    }

    /**
     * Check if the passed version information contains equal or higher operator
     *
     * Example: returns true for $version: '>=2.0'
     *
     * @param string $version
     * @return bool
     */
    public function versionFormatIsEqualOrHigher($version): bool
    {
        return strpos($version, '>=') === 0;
    }

    /**
     * Check if two releases are compatible by next significant release
     *
     * ~1.2 is equivalent to >=1.2 <2.0.0
     * ~1.2.3 is equivalent to >=1.2.3 <1.3.0
     *
     * In short, allows the last digit specified to go up
     *
     * @param string $version1 the version string (e.g. '2.0.0' or '1.0')
     * @param string $version2 the version string (e.g. '2.0.0' or '1.0')
     * @return bool
     */
    public function checkNextSignificantReleasesAreCompatible($version1, $version2): bool
    {
        $version1array = explode('.', $version1);
        $version2array = explode('.', $version2);

        if (count($version1array) > count($version2array)) {
            [$version1array, $version2array] = [$version2array, $version1array];
        }

        $i = 0;
        while ($i < count($version1array) - 1) {
            if ($version1array[$i] !== $version2array[$i]) {
                return false;
            }
            $i++;
        }

        return true;
    }
}
