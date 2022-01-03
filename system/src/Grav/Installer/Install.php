<?php

/**
 * @package    Grav\Installer
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Installer;

use Composer\Autoload\ClassLoader;
use Exception;
use Grav\Common\Cache;
use Grav\Common\GPM\Installer;
use Grav\Common\Grav;
use Grav\Common\Plugins;
use RuntimeException;
use function class_exists;
use function dirname;
use function function_exists;
use function is_string;

/**
 * Grav installer.
 *
 * NOTE: This class can be initialized during upgrade from an older version of Grav. Make sure it runs there!
 */
final class Install
{
    /** @var int Installer version. */
    public $version = 1;

    /** @var array */
    public $requires = [
        'php' => [
            'name' => 'PHP',
            'versions' => [
                '8.1' => '8.1.0',
                '8.0' => '8.0.0',
                '7.4' => '7.4.1',
                '7.3' => '7.3.6',
                '' => '8.0.13'
            ]
        ],
        'grav' => [
            'name' => 'Grav',
            'versions' => [
                '1.6' => '1.6.0',
                '' => '1.6.28'
            ]
        ],
        'plugins' => [
            'admin' => [
                'name' => 'Admin',
                'optional' => true,
                'versions' => [
                    '1.9' => '1.9.0',
                    '' => '1.9.13'
                ]
            ],
            'email' => [
                'name' => 'Email',
                'optional' => true,
                'versions' => [
                    '3.0' => '3.0.0',
                    '' => '3.0.10'
                ]
            ],
            'form' => [
                'name' => 'Form',
                'optional' => true,
                'versions' => [
                    '4.1' => '4.1.0',
                    '4.0' => '4.0.0',
                    '3.0' => '3.0.0',
                    '' => '4.1.2'
                ]
            ],
            'login' => [
                'name' => 'Login',
                'optional' => true,
                'versions' => [
                    '3.3' => '3.3.0',
                    '3.0' => '3.0.0',
                    '' => '3.3.6'
                ]
            ],
        ]
    ];

    /** @var array */
    public $ignores = [
        'backup',
        'cache',
        'images',
        'logs',
        'tmp',
        'user',
        '.htaccess',
        'robots.txt'
    ];

    /** @var array */
    private $classMap = [
        InstallException::class => __DIR__ . '/InstallException.php',
        Versions::class => __DIR__ . '/Versions.php',
        VersionUpdate::class => __DIR__ . '/VersionUpdate.php',
        VersionUpdater::class => __DIR__ . '/VersionUpdater.php',
        YamlUpdater::class => __DIR__ . '/YamlUpdater.php',
    ];

    /** @var string|null */
    private $zip;

    /** @var string|null */
    private $location;

    /** @var VersionUpdater|null */
    private $updater;

    /** @var static */
    private static $instance;

    /**
     * @return static
     */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    private function __construct()
    {
    }

    /**
     * @param string|null $zip
     * @return $this
     */
    public function setZip(?string $zip)
    {
        $this->zip = $zip;

        return $this;
    }

    /**
     * @param string|null $zip
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function __invoke(?string $zip)
    {
        $this->zip = $zip;

        $failedRequirements = $this->checkRequirements();
        if ($failedRequirements) {
            $error = ['Following requirements have failed:'];

            foreach ($failedRequirements as $name => $req) {
                $error[] = "{$req['title']} >= <strong>v{$req['minimum']}</strong> required, you have <strong>v{$req['installed']}</strong>";
            }

            $errors = implode("<br />\n", $error);
            if (\defined('GRAV_CLI') && GRAV_CLI) {
                $errors = "\n\n" . strip_tags($errors) . "\n\n";
                $errors .= <<<ERR
Please install Grav 1.6.31 first by running following commands:

wget -q https://getgrav.org/download/core/grav-update/1.6.31 -O tmp/grav-update-v1.6.31.zip
bin/gpm direct-install -y tmp/grav-update-v1.6.31.zip
rm tmp/grav-update.zip
ERR;
            }

            throw new RuntimeException($errors);
        }

        $this->prepare();
        $this->install();
        $this->finalize();
    }

    /**
     * NOTE: This method can only be called after $grav['plugins']->init().
     *
     * @return array List of failed requirements. If the list is empty, installation can go on.
     */
    public function checkRequirements(): array
    {
        $results = [];

        $this->checkVersion($results, 'php', 'php', $this->requires['php'], PHP_VERSION);
        $this->checkVersion($results, 'grav', 'grav', $this->requires['grav'], GRAV_VERSION);
        $this->checkPlugins($results, $this->requires['plugins']);

        return $results;
    }

    /**
     * @return void
     * @throws RuntimeException
     */
    public function prepare(): void
    {
        // Locate the new Grav update and the target site from the filesystem.
        $location = realpath(__DIR__);
        $target = realpath(GRAV_ROOT . '/index.php');

        if (!$location) {
            throw new RuntimeException('Internal Error', 500);
        }

        if ($target && dirname($location, 4) === dirname($target)) {
            // We cannot copy files into themselves, abort!
            throw new RuntimeException('Grav has already been installed here!', 400);
        }

        // Load the installer classes.
        foreach ($this->classMap as $class_name => $path) {
            // Make sure that none of the Grav\Installer classes have been loaded, otherwise installation may fail!
            if (class_exists($class_name, false)) {
                throw new RuntimeException(sprintf('Cannot update Grav, class %s has already been loaded!', $class_name), 500);
            }

            require $path;
        }

        $this->legacySupport();

        $this->location = dirname($location, 4);

        $versions = Versions::instance(USER_DIR . 'config/versions.yaml');
        $this->updater = new VersionUpdater('core/grav', __DIR__ . '/updates', $this->getVersion(), $versions);

        $this->updater->preflight();
    }

    /**
     * @return void
     * @throws RuntimeException
     */
    public function install(): void
    {
        if (!$this->location) {
            throw new RuntimeException('Oops, installer was run without prepare()!', 500);
        }

        try {
            if (null === $this->updater) {
                $versions = Versions::instance(USER_DIR . 'config/versions.yaml');
                $this->updater = new VersionUpdater('core/grav', __DIR__ . '/updates', $this->getVersion(), $versions);
            }

            // Update user/config/version.yaml before copying the files to avoid frontend from setting the version schema.
            $this->updater->install();

            Installer::install(
                $this->zip ?? '',
                GRAV_ROOT,
                ['sophisticated' => true, 'overwrite' => true, 'ignore_symlinks' => true, 'ignores' => $this->ignores],
                $this->location,
                !($this->zip && is_file($this->zip))
            );
        } catch (Exception $e) {
            Installer::setError($e->getMessage());
        }

        $errorCode = Installer::lastErrorCode();

        $success = !(is_string($errorCode) || ($errorCode & (Installer::ZIP_OPEN_ERROR | Installer::ZIP_EXTRACT_ERROR)));

        if (!$success) {
            throw new RuntimeException(Installer::lastErrorMsg());
        }
    }

    /**
     * @return void
     * @throws RuntimeException
     */
    public function finalize(): void
    {
        // Finalize can be run without installing Grav first.
        if (null === $this->updater) {
            $versions = Versions::instance(USER_DIR . 'config/versions.yaml');
            $this->updater = new VersionUpdater('core/grav', __DIR__ . '/updates', GRAV_VERSION, $versions);
            $this->updater->install();
        }

        $this->updater->postflight();

        Cache::clearCache('all');

        clearstatcache();
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    /**
     * @param array $results
     * @param string $type
     * @param string $name
     * @param array $check
     * @param string|null $version
     * @return void
     */
    protected function checkVersion(array &$results, $type, $name, array $check, $version): void
    {
        if (null === $version && !empty($check['optional'])) {
            return;
        }

        $major = $minor = 0;
        $versions = $check['versions'] ?? [];
        foreach ($versions as $major => $minor) {
            if (!$major || version_compare($version ?? '0', $major, '<')) {
                continue;
            }

            if (version_compare($version ?? '0', $minor, '>=')) {
                return;
            }

            break;
        }

        if (!$major) {
            $minor = reset($versions);
        }

        $recommended = end($versions);

        if (version_compare($recommended, $minor, '<=')) {
            $recommended = null;
        }

        $results[$name] = [
            'type' => $type,
            'name' => $name,
            'title' => $check['name'] ?? $name,
            'installed' => $version,
            'minimum' => $minor,
            'recommended' => $recommended
        ];
    }

    /**
     * @param array $results
     * @param array $plugins
     * @return void
     */
    protected function checkPlugins(array &$results, array $plugins): void
    {
        if (!class_exists('Plugins')) {
            return;
        }

        foreach ($plugins as $name => $check) {
            $plugin = Plugins::get($name);
            if (!$plugin) {
                $this->checkVersion($results, 'plugin', $name, $check, null);
                continue;
            }

            $blueprint = $plugin->blueprints();
            $version = (string)$blueprint->get('version');
            $check['name'] = ($blueprint->get('name') ?? $check['name'] ?? $name) . ' Plugin';
            $this->checkVersion($results, 'plugin', $name, $check, $version);
        }
    }

    /**
     * @return string
     */
    protected function getVersion(): string
    {
        $definesFile = "{$this->location}/system/defines.php";
        $content = file_get_contents($definesFile);
        if (false === $content) {
            return '';
        }

        preg_match("/define\('GRAV_VERSION', '([^']+)'\);/mu", $content, $matches);

        return $matches[1] ?? '';
    }

    protected function legacySupport(): void
    {
        // Support install for Grav 1.6.0 - 1.6.20 by loading the original class from the older version of Grav.
        class_exists(\Grav\Console\Cli\CacheCommand::class, true);
    }
}
