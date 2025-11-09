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
use Grav\Common\GPM\Installer;
use Grav\Common\Grav;
use Grav\Common\Plugins;
use RuntimeException;
// NOTE: SafeUpgradeService is NOT imported via 'use' to avoid autoloader loading OLD class
// When Install.php is included during upgrade, the autoloader is from the OLD installation
// and would load the OLD SafeUpgradeService. We manually require the NEW one when needed.
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

            if ($this->shouldUseSafeUpgrade()) {
                // CRITICAL: Check if SafeUpgradeService is already loaded from old installation
                // If it is, PHP will use the OLD version instead of the NEW one from this package
                $expectedPath = $this->location . '/system/src/Grav/Common/Upgrade/SafeUpgradeService.php';

                if (class_exists('Grav\\Common\\Upgrade\\SafeUpgradeService', false)) {
                    // Class is already loaded - check if it's from the correct location
                    $reflection = new \ReflectionClass('Grav\\Common\\Upgrade\\SafeUpgradeService');
                    $loadedFrom = $reflection->getFileName();

                    $loadedFromReal = realpath($loadedFrom) ?: $loadedFrom;
                    $expectedReal = realpath($expectedPath) ?: $expectedPath;

                    if ($loadedFromReal !== $expectedReal) {
                        // OLD SafeUpgradeService is already loaded - fall back to traditional upgrade
                        error_log(sprintf(
                            'WARNING: SafeUpgradeService was loaded from old installation (%s). ' .
                            'Falling back to traditional upgrade method.',
                            $loadedFromReal
                        ));

                        // Force traditional upgrade by disabling safe upgrade
                        Install::forceSafeUpgrade(false);

                        // Skip to traditional upgrade below
                        goto traditional_upgrade;
                    }
                }

                // SafeUpgradeService was loaded in shouldUseSafeUpgrade() from the NEW package
                $options = [];
                try {
                    $grav = Grav::instance();
                    if ($grav && isset($grav['config'])) {
                        $options['config'] = $grav['config'];
                    }
                } catch (\Throwable $e) {
                    // ignore
                }

                // Use fully qualified class name (no 'use' statement to avoid autoloader)
                $service = new \Grav\Common\Upgrade\SafeUpgradeService($options);

                // CRITICAL: Verify we're using the NEW SafeUpgradeService from this package, not the old one
                $this->verifySafeUpgradeServiceVersion($service);

                // Run preflight checks using the NEW SafeUpgradeService
                // This ensures preflight logic is from the package being installed, not the old installation
                try {
                    $targetVersion = $this->getVersion();
                    $preflight = $service->preflight($targetVersion);
                    $isMajorMinorUpgrade = $preflight['is_major_minor_upgrade'] ?? false;

                    // Check for pending plugin/theme updates
                    if (!empty($preflight['plugins_pending'])) {
                        $pending = $preflight['plugins_pending'];
                        $list = [];
                        foreach ($pending as $slug => $info) {
                            $current = $info['current'] ?? 'unknown';
                            $available = $info['available'] ?? 'unknown';
                            $list[] = sprintf('%s (v%s → v%s)', $slug, $current, $available);
                        }

                        if ($isMajorMinorUpgrade) {
                            // For major/minor upgrades, block until plugins are updated
                            // This allows the NEW package to define and enforce the upgrade policy
                            $currentVersion = GRAV_VERSION;
                            $targetVersion = $this->getVersion();
                            throw new RuntimeException(
                                sprintf(
                                    "Major version upgrade detected (v%s → v%s).\n\n" .
                                    "The following plugins/themes have updates available:\n  - %s\n\n" .
                                    "For major version upgrades, plugins and themes must be updated FIRST.\n" .
                                    "Please run 'bin/gpm update' to update these packages, then retry the upgrade.\n" .
                                    "This ensures plugins have necessary compatibility fixes for the new Grav version.",
                                    $currentVersion,
                                    $targetVersion,
                                    implode("\n  - ", $list)
                                )
                            );
                        } else {
                            // For patch upgrades, just log a warning but allow upgrade
                            error_log(
                                'WARNING: The following plugins/themes have updates available: ' .
                                implode(', ', $list) . '. ' .
                                'Consider updating them after upgrading Grav.'
                            );
                        }
                    }

                    // PSR log conflicts - log warning but don't block for now
                    if (!empty($preflight['psr_log_conflicts'])) {
                        $conflicts = $preflight['psr_log_conflicts'];
                        error_log(
                            'WARNING: PSR/log conflicts detected in plugins: ' .
                            implode(', ', array_keys($conflicts)) .
                            '. These may need updating after Grav upgrade.'
                        );
                    }

                    // Monolog conflicts - log warning but don't block for now
                    if (!empty($preflight['monolog_conflicts'])) {
                        $conflicts = $preflight['monolog_conflicts'];
                        error_log(
                            'WARNING: Monolog API conflicts detected in plugins: ' .
                            implode(', ', array_keys($conflicts)) .
                            '. These may need updating after Grav upgrade.'
                        );
                    }
                } catch (RuntimeException $e) {
                    // Preflight failed - abort upgrade with clear message
                    throw new RuntimeException(
                        'Upgrade preflight checks failed: ' . $e->getMessage(),
                        0,
                        $e
                    );
                }

                // Preflight passed - proceed with upgrade
                if ($this->progressCallback) {
                    $service->setProgressCallback(function (string $stage, string $message, ?int $percent = null, array $extra = []) {
                        $this->relayProgress($stage, $message, $percent);
                    });
                }
                $manifest = $service->promote($this->location, $this->getVersion(), $this->ignores);
                $this->lastManifest = $manifest;
                if (method_exists($service, 'getLastManifest')) {
                    // SafeUpgradeService in Grav < 1.7.50.1 does not expose getLastManifest().
                    $lastManifest = $service->getLastManifest();
                    if (null !== $lastManifest) {
                        $this->lastManifest = $lastManifest;
                    }
                }
                Installer::setError(Installer::OK);
            } else {
                traditional_upgrade:
                Installer::install(
                    $this->zip ?? '',
                    GRAV_ROOT,
                    ['sophisticated' => true, 'overwrite' => true, 'ignore_symlinks' => true, 'ignores' => $this->ignores],
                    $this->location,
                    !($this->zip && is_file($this->zip))
                );
            }
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
        // CRITICAL: Check if class exists WITHOUT triggering autoloader
        // If not loaded yet, manually load the NEW one from this package
        if (!class_exists('Grav\\Common\\Upgrade\\SafeUpgradeService', false)) {
            // Class not loaded yet - try to load from NEW package
            $serviceFile = $this->location . '/system/src/Grav/Common/Upgrade/SafeUpgradeService.php';
            if (!file_exists($serviceFile)) {
                return false; // SafeUpgradeService not available in this package
            }
            // Load the NEW SafeUpgradeService from this package
            require_once $serviceFile;
        }

        // Check static override first
        if (null !== self::$forceSafeUpgrade) {
            return self::$forceSafeUpgrade;
        }

        // Check environment variable set by SelfupgradeCommand (avoids early class loading)
        $envValue = getenv('GRAV_FORCE_SAFE_UPGRADE');
        if (false !== $envValue && '' !== $envValue) {
            return $envValue === '1';
        }

        // Check Grav config
        try {
            $grav = Grav::instance();
            if ($grav && isset($grav['config'])) {
                return (bool) $grav['config']->get('system.updates.safe_upgrade', true);
            }
        } catch (\Throwable $e) {
            // Grav container may not be initialised yet, default to safe upgrade.
        }

        return true;
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

    /**
     * Verify that we're using the NEW SafeUpgradeService from this package,
     * not the OLD one from the current installation.
     *
     * This is CRITICAL to ensure bugfixes in SafeUpgradeService are actually used.
     *
     * @param object $service The SafeUpgradeService instance (no type hint to avoid early loading)
     * @return void
     * @throws RuntimeException if the wrong version is loaded
     */
    protected function verifySafeUpgradeServiceVersion(object $service): void
    {
        // Get the file path where SafeUpgradeService was loaded from
        $reflection = new \ReflectionClass($service);
        $loadedFrom = $reflection->getFileName();

        // Expected: should be from THIS package in $this->location
        // e.g., /tmp/grav-update-abc123/grav-update/system/src/Grav/Common/Upgrade/SafeUpgradeService.php
        $expectedPath = $this->location . '/system/src/Grav/Common/Upgrade/SafeUpgradeService.php';

        // Normalize paths for comparison
        $loadedFromReal = realpath($loadedFrom) ?: $loadedFrom;
        $expectedReal = realpath($expectedPath) ?: $expectedPath;

        if ($loadedFromReal !== $expectedReal) {
            // CRITICAL ERROR: We're using the OLD SafeUpgradeService!
            // This means bugfixes in the new version won't be applied.
            error_log(sprintf(
                'CRITICAL: SafeUpgradeService loaded from WRONG location!' . "\n" .
                '  Expected (NEW): %s' . "\n" .
                '  Actual (OLD):   %s' . "\n" .
                'This indicates a class loading issue that will prevent bugfixes from being applied.',
                $expectedReal,
                $loadedFromReal
            ));

            throw new RuntimeException(
                'CRITICAL: SafeUpgradeService was loaded from the old installation instead of the new package. ' .
                'This is a known issue that has been fixed. Please upgrade using CLI: bin/gpm self-upgrade -f'
            );
        }

        // Additional check: verify IMPLEMENTATION_VERSION constant exists
        // (Added in this fix - old versions won't have it)
        if (!defined('Grav\\Common\\Upgrade\\SafeUpgradeService::IMPLEMENTATION_VERSION')) {
            error_log(
                'WARNING: SafeUpgradeService::IMPLEMENTATION_VERSION not defined. ' .
                'This suggests an old version is loaded.'
            );
        } else {
            $version = constant('Grav\\Common\\Upgrade\\SafeUpgradeService::IMPLEMENTATION_VERSION');
            error_log(sprintf(
                'SafeUpgradeService verification PASSED. Using version %s from: %s',
                $version,
                $loadedFromReal
            ));
        }
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
