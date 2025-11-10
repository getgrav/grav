<?php

/**
 * @package    Grav\Installer
 *
 * @copyright  Copyright (c) 2015 - 2025 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Installer;

use Composer\Autoload\ClassLoader;
use Exception;
use Grav\Common\Cache;
use Grav\Common\Filesystem\Folder;
use Grav\Common\GPM\Installer;
use Grav\Common\Grav;
use Grav\Common\Plugins;
use RuntimeException;
use function class_exists;
use function date;
use function dirname;
use function floor;
use function function_exists;
use function is_dir;
use function is_file;
use function is_link;
use function is_string;
use function is_writable;
use function json_encode;
use function readlink;
use function sort;
use function sprintf;
use function symlink;
use function time;
use function uniqid;
use function unlink;
use const GRAV_ROOT;
use const JSON_PRETTY_PRINT;

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
                '1.7' => '1.7.50',
                '' => '1.7.50'
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

    /** @var array|null */
    private $lastManifest = null;

    /** @var static */
    private static $instance;
    /** @var bool|null */
    private static $forceSafeUpgrade = null;
    /** @var callable|null */
    private $progressCallback = null;

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

    /**
     * Force safe-upgrade mode independently of system configuration.
     *
     * @param bool|null $state
     * @return void
     */
    public static function forceSafeUpgrade(?bool $state = true): void
    {
        self::$forceSafeUpgrade = $state;
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
Please install Grav 1.7.50 first by running following commands:

wget -q https://getgrav.org/download/core/grav-update/1.7.50 -O tmp/grav-update-v1.7.50.zip
bin/gpm direct-install -y tmp/grav-update-v1.7.50.zip
rm tmp/grav-update-v1.7.50.zip
ERR;
            }

            throw new RuntimeException($errors);
        }

        $this->prepare();
        $this->install();
        $this->finalize();
    }

    public function setProgressCallback(?callable $callback): self
    {
        $this->progressCallback = $callback;

        return $this;
    }

    private function relayProgress(string $stage, string $message, ?int $percent = null): void
    {
        if ($this->progressCallback) {
            ($this->progressCallback)($stage, $message, $percent);
        }
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

        $this->lastManifest = null;

        try {
            if (null === $this->updater) {
                $versions = Versions::instance(USER_DIR . 'config/versions.yaml');
                $this->updater = new VersionUpdater('core/grav', __DIR__ . '/updates', $this->getVersion(), $versions);
            }

            // Update user/config/version.yaml before copying the files to avoid frontend from setting the version schema.
            $this->updater->install();

            $safeUpgradeRequested = $this->shouldUseSafeUpgrade();
            $targetVersion = $this->getVersion();
            $snapshotManifest = null;
            if ($safeUpgradeRequested) {
                $snapshotManifest = $this->captureCoreSnapshot($targetVersion);
                if ($snapshotManifest) {
                    $this->relayProgress('snapshot', sprintf('Snapshot %s captured.', $snapshotManifest['id']), 100);
                } else {
                    $this->relayProgress('snapshot', 'Snapshot capture unavailable; continuing without it.', null);
                }
            }
            $progressMessage = $safeUpgradeRequested
                ? 'Running Grav standard installer (safe mode)...'
                : 'Running Grav standard installer...';
            $this->relayProgress('installing', $progressMessage, null);

            Installer::install(
                $this->zip ?? '',
                GRAV_ROOT,
                ['sophisticated' => true, 'overwrite' => true, 'ignore_symlinks' => true, 'ignores' => $this->ignores],
                $this->location,
                !($this->zip && is_file($this->zip))
            );

            $this->relayProgress('complete', 'Grav standard installer finished.', 100);
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
     * @return bool
     */
    private function shouldUseSafeUpgrade(): bool
    {
        if (null !== self::$forceSafeUpgrade) {
            return self::$forceSafeUpgrade;
        }

        $envValue = getenv('GRAV_FORCE_SAFE_UPGRADE');
        if (false !== $envValue && '' !== $envValue) {
            return $envValue === '1';
        }

        try {
            $grav = Grav::instance();
            if ($grav && isset($grav['config'])) {
                $configValue = $grav['config']->get('system.updates.safe_upgrade');
                if ($configValue !== null) {
                    return (bool) $configValue;
                }
            }
        } catch (\Throwable $e) {
            // ignore bootstrap failures
        }

        return false;
    }

    private function captureCoreSnapshot(string $targetVersion): ?array
    {
        $entries = $this->collectSnapshotEntries();
        if (!$entries) {
            return null;
        }

        $snapshotRoot = $this->resolveSnapshotStore();
        if (!$snapshotRoot) {
            return null;
        }

        $snapshotId = 'snapshot-' . date('YmdHis');
        $snapshotPath = $snapshotRoot . '/' . $snapshotId;
        try {
            Folder::create($snapshotPath);
        } catch (\Throwable $e) {
            error_log('[Grav Upgrade] Unable to create snapshot directory: ' . $e->getMessage());

            return null;
        }

        $total = count($entries);
        foreach ($entries as $index => $entry) {
            $percent = $total > 0 ? (int)floor((($index + 1) / $total) * 100) : null;
            $this->relayProgress('snapshot', sprintf('Snapshotting %s (%d/%d)', $entry, $index + 1, $total), $percent);

            $source = GRAV_ROOT . '/' . $entry;
            $destination = $snapshotPath . '/' . $entry;

            try {
                $this->snapshotCopyEntry($source, $destination);
            } catch (\Throwable $e) {
                error_log('[Grav Upgrade] Snapshot copy failed for ' . $entry . ': ' . $e->getMessage());

                return null;
            }
        }

        $manifest = [
            'id' => $snapshotId,
            'created_at' => time(),
            'source_version' => GRAV_VERSION,
            'target_version' => $targetVersion,
            'php_version' => PHP_VERSION,
            'entries' => $entries,
            'package_path' => null,
            'backup_path' => $snapshotPath,
            'operation' => 'upgrade',
            'mode' => 'pre-upgrade',
        ];

        $this->persistSnapshotManifest($manifest);
        $this->lastManifest = $manifest;

        return $manifest;
    }

    private function collectSnapshotEntries(): array
    {
        $ignores = array_fill_keys($this->ignores, true);
        $ignores['user'] = true;

        $entries = [];
        try {
            $iterator = new \DirectoryIterator(GRAV_ROOT);
            foreach ($iterator as $item) {
                if ($item->isDot()) {
                    continue;
                }

                $name = $item->getFilename();
                if (isset($ignores[$name])) {
                    continue;
                }

                $entries[] = $name;
            }
        } catch (\Throwable $e) {
            error_log('[Grav Upgrade] Unable to enumerate snapshot entries: ' . $e->getMessage());

            return [];
        }

        sort($entries);

        return $entries;
    }

    private function snapshotCopyEntry(string $source, string $destination): void
    {
        if (is_link($source)) {
            $linkTarget = readlink($source);
            Folder::create(dirname($destination));
            if (is_link($destination) || is_file($destination)) {
                @unlink($destination);
            }
            if ($linkTarget !== false) {
                @symlink($linkTarget, $destination);
            }

            return;
        }

        if (is_dir($source)) {
            Folder::rcopy($source, $destination);

            return;
        }

        Folder::create(dirname($destination));
        if (!@copy($source, $destination)) {
            throw new RuntimeException(sprintf('Failed to copy file %s during snapshot.', $source));
        }
    }

    private function resolveSnapshotStore(): ?string
    {
        $candidates = [];
        try {
            $grav = Grav::instance();
            if ($grav && isset($grav['locator'])) {
                $path = $grav['locator']->findResource('tmp://grav-snapshots', true, true);
                if ($path) {
                    $candidates[] = $path;
                }
            }
        } catch (\Throwable $e) {
            // ignore locator issues
        }
        $candidates[] = GRAV_ROOT . '/tmp/grav-snapshots';

        foreach ($candidates as $candidate) {
            if (!$candidate) {
                continue;
            }

            try {
                Folder::create($candidate);
            } catch (\Throwable $e) {
                continue;
            }

            if (is_dir($candidate) && is_writable($candidate)) {
                return rtrim($candidate, '\\/');
            }
        }

        error_log('[Grav Upgrade] Unable to locate writable snapshot directory; skipping snapshot.');

        return null;
    }

    private function persistSnapshotManifest(array $manifest): void
    {
        $store = GRAV_ROOT . '/user/data/upgrades';

        try {
            Folder::create($store);
            $path = $store . '/' . $manifest['id'] . '.json';
            @file_put_contents($path, json_encode($manifest, JSON_PRETTY_PRINT));
        } catch (\Throwable $e) {
            error_log('[Grav Upgrade] Unable to write snapshot manifest: ' . $e->getMessage());
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

        $this->ensureExecutablePermissions();

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

    private function ensureExecutablePermissions(): void
    {
        $executables = [
            'bin/grav',
            'bin/plugin',
            'bin/gpm',
            'bin/restore',
            'bin/composer.phar'
        ];

        foreach ($executables as $relative) {
            $path = GRAV_ROOT . '/' . $relative;
            if (!is_file($path) || is_link($path)) {
                continue;
            }

            $mode = @fileperms($path);
            $current = $mode !== false ? ($mode & 0777) : 0644;
            if (($current & 0111) === 0111) {
                continue;
            }

            @chmod($path, $current | 0111);
        }
    }

    /**
     * @return array|null
     */
    public function getLastManifest(): ?array
    {
        return $this->lastManifest;
    }
}
