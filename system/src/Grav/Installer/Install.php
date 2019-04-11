<?php

/**
 * @package    Grav\Installer
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Installer;

use Composer\Autoload\ClassLoader;
use Grav\Common\Cache;
use Grav\Common\GPM\Installer;
use Grav\Common\Grav;
use Grav\Common\Plugins;

/**
 * Grav installer.
 *
 * NOTE: This class can be initialized during upgrade from an older version of Grav. Make sure it runs there!
 */
class Install
{
    private $requires = [
        'php' => [
            'name' => 'PHP',
            'versions' => [
                '7.3' => '7.3.1',
                '7.2' => '7.2.0',
                '7.1' => '7.1.3',
                '' => '7.2.14'
            ]
        ],
        'grav' => [
            'name' => 'Grav',
            'versions' => [
                '1.5' => '1.5.0',
                '' => '1.5.7'
            ]
        ],
        'plugins' => [
            'admin' => [
                'name' => 'Admin',
                'optional' => true,
                'versions' => [
                    '1.8' => '1.8.0',
                    '' => '1.8.16'
                ]
            ],
            'email' => [
                'name' => 'Email',
                'optional' => true,
                'versions' => [
                    '2.7' => '2.7.0',
                    '' => '2.7.2'
                ]
            ],
            'form' => [
                'name' => 'Form',
                'optional' => true,
                'versions' => [
                    '2.16' => '2.16.0',
                    '' => '2.16.4'
                ]
            ],
            'login' => [
                'name' => 'Login',
                'optional' => true,
                'versions' => [
                    '2.8' => '2.8.0',
                    '' => '2.8.3'
                ]
            ],
        ]
    ];

    private $ignores = [
        'backup',
        'cache',
        'images',
        'logs',
        'tmp',
        'user',
        '.htaccess',
        'robots.txt'
    ];

    private $classMap = [
        // 'Grav\\Installer\\Test' => __DIR__ . '/Test.php',
    ];

    private $zip;
    private $location;

    private static $instance;

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

    public function setZip(string $zip)
    {
        $this->zip = $zip;

        return $this;
    }

    public function __invoke(?string $zip)
    {
        $this->zip = $zip;

        $failedRequirements = $this->checkRequirements();
        if ($failedRequirements) {
            $error = ['Following requirements have failed:'];

            foreach ($failedRequirements as $name => $req) {
                $error[] = "{$req['title']} >= <strong>v{$req['minimum']}</strong> required, you have <strong>v{$req['installed']}</strong>";
            }

            throw new \RuntimeException(implode("<br />\n", $error));
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

        $this->checkVersion($results, 'php','php', $this->requires['php'], PHP_VERSION);
        $this->checkVersion($results, 'grav', 'grav', $this->requires['grav'], GRAV_VERSION);
        $this->checkPlugins($results, $this->requires['plugins']);

        return $results;
    }

    /**
     * @throws \RuntimeException
     */
    public function prepare(): void
    {
        // Locate the new Grav update and the target site from the filesystem.
        $location = dirname(realpath(__DIR__), 4);
        $target = dirname(realpath(GRAV_ROOT . '/index.php'));
        if ($location === $target) {
            // We cannot copy files into themselves, abort!
            throw new \RuntimeException('Grav has already been installed here!', 400);
        }

        // Make sure that none of the Grav\Installer classes have been loaded, otherwise installation may fail!
        foreach ($this->classMap as $class_name => $path) {
            if (\class_exists($class_name, false)) {
                throw new \RuntimeException(sprintf('Cannot update Grav, class %s has already been loaded!', $class_name), 500);
            }
        }

        $grav = Grav::instance();

        /** @var ClassLoader $loader */
        $loader = $grav['loader'];

        // Override Grav\Installer classes by using this version of Grav.
        $loader->addClassMap($this->classMap);

        $this->location = $location;
    }

    /**
     * @throws \RuntimeException
     */
    public function install(): void
    {
        if (!$this->location) {
            throw new \RuntimeException('Oops, installer was run without prepare()!', 500);
        }

        try {
            Installer::install(
                $this->zip,
                GRAV_ROOT,
                ['sophisticated' => true, 'overwrite' => true, 'ignore_symlinks' => true, 'ignores' => $this->ignores],
                $this->location,
                !($this->zip && is_file($this->zip))
            );
        } catch (\Exception $e) {
            Installer::setError($e->getMessage());
        }

        $errorCode = Installer::lastErrorCode();

        $success = !(is_string($errorCode) || ($errorCode & (Installer::ZIP_OPEN_ERROR | Installer::ZIP_EXTRACT_ERROR)));

        if (!$success) {
            throw new \RuntimeException(Installer::lastErrorMsg());
        }
    }

    /**
     * @throws \RuntimeException
     */
    public function finalize(): void
    {
        Cache::clearCache();

        clearstatcache();
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    protected function checkVersion(array &$results, $type, $name, array $check, $version): void
    {
        if (null === $version && !empty($check['optional'])) {
            return;
        }

        $major = $minor = 0;
        $versions = $check['versions'] ?? [];
        foreach ($versions as $major => $minor) {
            if (!$major || version_compare($version, $major, '<')) {
                continue;
            }

            if (version_compare($version, $minor, '>=')) {
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

    protected function checkPlugins(array &$results, array $plugins): void
    {
        if (!\class_exists('Plugins')) {
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
}