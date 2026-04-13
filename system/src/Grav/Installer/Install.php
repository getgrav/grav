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
use Grav\Common\GPM\GPM;
use Grav\Common\GPM\Installer;
use Grav\Common\Grav;
use Grav\Common\Plugins;
use RocketTheme\Toolbox\Compat\Yaml\Yaml;
use RuntimeException;
use Throwable;
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
     * Backward-compatibility: older versions (e.g. 1.7.50) with safe-upgrade call
     * forceSafeUpgrade() / getLastManifest() on the Install singleton loaded from
     * the update package.  These stubs prevent fatal errors when downgrading from
     * a safe-upgrade-aware release to one that removed it.
     */

    /** @var bool|null */
    private static $forceSafeUpgrade = null;

    /** @var bool */
    private static $allowPendingOverride = false;

    /** @var bool */
    private static $allowIncompatibleOverride = false;

    /** @var callable|null */
    private $progressCallback = null;

    /** @var array|null */
    private $pendingPreflight = null;

    /**
     * @param bool|null $state
     * @return void
     */
    public static function forceSafeUpgrade(?bool $state = true): void
    {
        self::$forceSafeUpgrade = $state;
    }

    /**
     * Allow an upgrade run to proceed even when GPM-tracked plugin/theme
     * updates are still pending. Toggled by SelfupgradeCommand after the
     * operator confirms the override interactively.
     */
    public static function allowPendingPackageOverride(?bool $state = true): void
    {
        self::$allowPendingOverride = $state === null ? false : (bool)$state;
    }

    /**
     * Allow an upgrade run to proceed with enabled plugins/themes that have
     * not been marked compatible with the target Grav version.
     */
    public static function allowIncompatibleOverride(?bool $state = true): void
    {
        self::$allowIncompatibleOverride = $state === null ? false : (bool)$state;
    }

    /**
     * @return array|null
     */
    public function getLastManifest(): ?array
    {
        return null;
    }

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

    // ---------------------------------------------------------------------
    // Preflight — invoked by the SelfupgradeCommand on the target package's
    // Install singleton before files are overlaid. Read-only detection:
    // this surface MUST NOT mutate state.
    // ---------------------------------------------------------------------

    private function ensureLocation(): void
    {
        if (null === $this->location) {
            $path = realpath(__DIR__);
            if ($path) {
                $this->location = dirname($path, 4);
            }
        }
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

    public function generatePreflightReport(): array
    {
        $this->ensureLocation();
        $version = $this->getVersion();

        $report = $this->runPreflightChecks($version ?: GRAV_VERSION);
        $this->pendingPreflight = $report;

        return $report;
    }

    public function getPreflightReport(): ?array
    {
        return $this->pendingPreflight;
    }

    private function runPreflightChecks(string $targetVersion): array
    {
        $start = microtime(true);
        $this->relayProgress('initializing', 'Running preflight checks...', null);

        $report = [
            'warnings' => [],
            'psr_log_conflicts' => [],
            'monolog_conflicts' => [],
            'plugins_pending' => [],
            'incompatible_packages' => [],
            'is_major_minor_upgrade' => $this->isMajorMinorUpgrade($targetVersion),
            'blocking' => [],
        ];

        $report['plugins_pending']   = $this->detectPendingPackageUpdates();
        $report['psr_log_conflicts'] = $this->detectPsrLogConflicts();
        $report['monolog_conflicts'] = $this->detectMonologConflicts();

        if ($report['plugins_pending']) {
            if (self::$allowPendingOverride) {
                $report['warnings'][] = 'Pending plugin/theme updates ignored for this upgrade run.';
            } elseif ($report['is_major_minor_upgrade']) {
                $report['blocking'][] = 'Pending plugin/theme updates detected. Because this is a major Grav upgrade, update them before continuing.';
            }
        }

        if ($report['is_major_minor_upgrade']) {
            $report['incompatible_packages'] = $this->detectIncompatiblePackages($targetVersion);

            if (!empty($report['incompatible_packages']['blocking']) && !self::$allowIncompatibleOverride) {
                $target = $report['incompatible_packages']['target'];
                $report['blocking'][] = 'Some enabled plugins/themes have not been marked as compatible with Grav ' . $target . '. Disable them before continuing.';
            }
        }

        $elapsed = microtime(true) - $start;
        $this->relayProgress('initializing', sprintf('Preflight checks complete in %.3fs.', $elapsed), null);

        return $report;
    }

    private function isMajorMinorUpgrade(string $targetVersion): bool
    {
        [$currentMajor, $currentMinor] = array_map('intval', array_pad(explode('.', GRAV_VERSION), 2, 0));
        [$targetMajor, $targetMinor]   = array_map('intval', array_pad(explode('.', $targetVersion), 2, 0));

        return $currentMajor !== $targetMajor || $currentMinor !== $targetMinor;
    }

    private function detectPendingPackageUpdates(): array
    {
        $pending = [];

        if (!class_exists(GPM::class)) {
            return $pending;
        }

        try {
            $gpm = new GPM();
        } catch (Throwable $e) {
            $this->relayProgress('warning', 'Unable to query GPM: ' . $e->getMessage(), null);

            return $pending;
        }

        $repoPlugins = $this->packagesToArray($gpm->getRepositoryPlugins());
        $repoThemes  = $this->packagesToArray($gpm->getRepositoryThemes());

        $scanRoot = GRAV_ROOT ?: getcwd();

        foreach ($this->scanLocalPackageVersions($scanRoot . '/user/plugins') as $slug => $version) {
            $remote = $repoPlugins[$slug] ?? null;
            if (!$this->isGpmPackagePublished($remote)) {
                continue;
            }
            $remoteVersion = $this->resolveRemotePackageVersion($remote);
            if (!$remoteVersion || !$version) {
                continue;
            }
            if (!$this->isPluginEnabled($slug)) {
                if (str_contains($version, 'dev-')) {
                    $this->relayProgress('warning', sprintf('Skipping dev plugin %s (%s).', $slug, $version), null);
                    continue;
                }
            }

            if (version_compare($remoteVersion, $version, '>')) {
                $pending[$slug] = ['type' => 'plugins', 'current' => $version, 'available' => $remoteVersion];
            }
        }

        foreach ($this->scanLocalPackageVersions($scanRoot . '/user/themes') as $slug => $version) {
            $remote = $repoThemes[$slug] ?? null;
            if (!$this->isGpmPackagePublished($remote)) {
                if (str_contains($version, 'dev-')) {
                    $this->relayProgress('warning', sprintf('Skipping dev theme %s (%s).', $slug, $version), null);
                    continue;
                }
            }
            $remoteVersion = $this->resolveRemotePackageVersion($remote);
            if (!$remoteVersion || !$version) {
                continue;
            }
            if (!$this->isThemeEnabled($slug)) {
                continue;
            }

            if (version_compare($remoteVersion, $version, '>')) {
                $pending[$slug] = ['type' => 'themes', 'current' => $version, 'available' => $remoteVersion];
            }
        }

        $this->relayProgress('initializing', sprintf('Detected %d updatable packages (including symlinks).', count($pending)), null);

        return $pending;
    }

    private function scanLocalPackageVersions(string $path): array
    {
        $versions = [];
        if (!is_dir($path)) {
            return $versions;
        }

        foreach (glob($path . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $slug = basename($dir);
            $version = $this->readBlueprintVersion($dir) ?? $this->readComposerVersion($dir);
            if ($version !== null) {
                $versions[$slug] = $version;
            }
        }

        return $versions;
    }

    private function readBlueprintVersion(string $dir): ?string
    {
        $file = $dir . '/blueprints.yaml';
        if (!is_file($file)) {
            return null;
        }

        try {
            $contents = @file_get_contents($file);
            if ($contents === false) {
                return null;
            }
            $data = Yaml::parse($contents);
            if (is_array($data) && isset($data['version'])) {
                return (string)$data['version'];
            }
        } catch (Throwable $e) {
            // ignore parse errors
        }

        return null;
    }

    private function readComposerVersion(string $dir): ?string
    {
        $file = $dir . '/composer.json';
        if (!is_file($file)) {
            return null;
        }

        $data = json_decode((string)@file_get_contents($file), true);
        if (is_array($data) && isset($data['version'])) {
            return (string)$data['version'];
        }

        return null;
    }

    private function packagesToArray($packages): array
    {
        if (!$packages) {
            return [];
        }
        if (is_array($packages)) {
            return $packages;
        }
        if ($packages instanceof \Traversable) {
            return iterator_to_array($packages, true);
        }

        return [];
    }

    private function resolveRemotePackageVersion($package): ?string
    {
        if (!$package) {
            return null;
        }
        if (is_array($package)) {
            return $package['version'] ?? null;
        }
        if (is_object($package)) {
            if (isset($package->version)) {
                return (string)$package->version;
            }
            if (method_exists($package, 'offsetGet')) {
                try {
                    return (string)$package->offsetGet('version');
                } catch (Throwable $e) {
                    return null;
                }
            }
        }

        return null;
    }

    private function isGpmPackagePublished($package): bool
    {
        if (is_object($package) && method_exists($package, 'getData')) {
            $data = $package->getData();
            if ($data instanceof \Grav\Common\Data\Data) {
                return $data->get('published') !== false;
            }
        }
        if (is_array($package)) {
            return array_key_exists('published', $package) ? $package['published'] !== false : true;
        }
        if (is_object($package) && property_exists($package, 'published')) {
            return $package->published !== false;
        }

        return true;
    }

    private function detectPsrLogConflicts(): array
    {
        $conflicts = [];
        foreach (glob(GRAV_ROOT . '/user/plugins/*', GLOB_ONLYDIR) ?: [] as $path) {
            $composerFile = $path . '/composer.json';
            if (!is_file($composerFile)) {
                continue;
            }
            $json = json_decode((string)@file_get_contents($composerFile), true);
            if (!is_array($json)) {
                continue;
            }
            $slug = basename($path);
            if (!$this->isPluginEnabled($slug)) {
                continue;
            }

            $rawConstraint = $json['require']['psr/log'] ?? ($json['require-dev']['psr/log'] ?? null);
            if (!$rawConstraint) {
                continue;
            }

            $constraint = strtolower((string)$rawConstraint);
            $compatible = $constraint === '*'
                || false !== strpos($constraint, '3')
                || false !== strpos($constraint, '4')
                || (false !== strpos($constraint, '>=') && preg_match('/>=\s*3/', $constraint));

            if ($compatible) {
                continue;
            }

            $conflicts[$slug] = ['composer' => $composerFile, 'requires' => $rawConstraint];
        }

        return $conflicts;
    }

    private function detectMonologConflicts(): array
    {
        $conflicts = [];
        $pattern = '/->add(?:Debug|Info|Notice|Warning|Error|Critical|Alert|Emergency)\s*\(/i';

        foreach (glob(GRAV_ROOT . '/user/plugins/*', GLOB_ONLYDIR) ?: [] as $path) {
            $slug = basename($path);
            if (!$this->isPluginEnabled($slug)) {
                continue;
            }

            $directory = new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS);
            $filter = new \RecursiveCallbackFilterIterator($directory, static function ($current, $key, $iterator) {
                if ($current->getFilename()[0] === '.') {
                    return false;
                }
                if ($iterator->hasChildren()) {
                    return !in_array($current->getFilename(), ['vendor', 'node_modules'], true);
                }

                return $current->getExtension() === 'php';
            });
            $iterator = new \RecursiveIteratorIterator($filter);

            foreach ($iterator as $file) {
                $contents = @file_get_contents($file->getPathname());
                if ($contents === false) {
                    continue;
                }
                if (preg_match($pattern, $contents, $match)) {
                    $relative = str_replace(GRAV_ROOT . '/', '', $file->getPathname());
                    $conflicts[$slug][] = ['file' => $relative, 'method' => trim($match[0])];
                }
            }
        }

        return $conflicts;
    }

    private function isPluginEnabled(string $slug): bool
    {
        $configPath = GRAV_ROOT . '/user/config/plugins/' . $slug . '.yaml';
        if (is_file($configPath)) {
            try {
                $contents = @file_get_contents($configPath);
                if ($contents !== false) {
                    $data = Yaml::parse($contents);
                    if (is_array($data) && array_key_exists('enabled', $data)) {
                        return (bool)$data['enabled'];
                    }
                }
            } catch (Throwable $e) {
                // ignore parse errors
            }
        }

        return true;
    }

    private function isThemeEnabled(string $slug): bool
    {
        $configPath = GRAV_ROOT . '/user/config/system.yaml';
        if (is_file($configPath)) {
            try {
                $contents = @file_get_contents($configPath);
                if ($contents !== false) {
                    $data = Yaml::parse($contents);
                    if (is_array($data)) {
                        $active = $data['pages']['theme'] ?? ($data['system']['pages']['theme'] ?? null);
                        if ($active !== null) {
                            return $active === $slug;
                        }
                    }
                }
            } catch (Throwable $e) {
                // ignore parse errors
            }
        }

        return true;
    }

    /**
     * @return array{blocking: array, warnings: array, target: string}
     */
    private function detectIncompatiblePackages(string $targetVersion): array
    {
        $parts = explode('.', $targetVersion);
        $targetMajorMinor = ($parts[0] ?? '1') . '.' . ($parts[1] ?? '7');

        $blocking = [];
        $warnings = [];
        $scanRoot = GRAV_ROOT ?: getcwd();

        foreach (glob($scanRoot . '/user/plugins/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $slug = basename($dir);
            $compat = $this->readBlueprintCompatibility($dir);
            if (in_array($targetMajorMinor, $compat['grav'], true)) {
                continue;
            }
            $version = $this->readBlueprintVersion($dir) ?? 'unknown';
            $enabled = $this->isPluginEnabled($slug);
            $entry = [
                'type' => 'plugin',
                'version' => $version,
                'compatibility' => $compat,
                'enabled' => $enabled,
            ];
            if ($enabled) {
                $blocking[$slug] = $entry;
            } else {
                $warnings[$slug] = $entry;
            }
        }

        foreach (glob($scanRoot . '/user/themes/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $slug = basename($dir);
            $compat = $this->readBlueprintCompatibility($dir);
            if (in_array($targetMajorMinor, $compat['grav'], true)) {
                continue;
            }
            $version = $this->readBlueprintVersion($dir) ?? 'unknown';
            $active = $this->isThemeEnabled($slug);
            $entry = [
                'type' => 'theme',
                'version' => $version,
                'compatibility' => $compat,
                'enabled' => $active,
            ];
            if ($active) {
                $blocking[$slug] = $entry;
            } else {
                $warnings[$slug] = $entry;
            }
        }

        return ['blocking' => $blocking, 'warnings' => $warnings, 'target' => $targetMajorMinor];
    }

    /**
     * @return array{grav: string[], api: string[]}
     */
    private function readBlueprintCompatibility(string $dir): array
    {
        $file = $dir . '/blueprints.yaml';
        if (!is_file($file)) {
            return ['grav' => [], 'api' => []];
        }

        try {
            $contents = @file_get_contents($file);
            if ($contents === false) {
                return ['grav' => [], 'api' => []];
            }
            $data = Yaml::parse($contents);
            if (!is_array($data)) {
                return ['grav' => [], 'api' => []];
            }

            if (isset($data['compatibility']['grav']) && is_array($data['compatibility']['grav'])) {
                return [
                    'grav' => array_map('strval', $data['compatibility']['grav']),
                    'api'  => isset($data['compatibility']['api']) && is_array($data['compatibility']['api'])
                        ? array_map('strval', $data['compatibility']['api'])
                        : [],
                ];
            }

            return $this->inferCompatibleVersions($data['dependencies'] ?? []);
        } catch (Throwable $e) {
            return ['grav' => [], 'api' => []];
        }
    }

    /**
     * @return array{grav: string[], api: string[]}
     */
    private function inferCompatibleVersions(array $dependencies): array
    {
        foreach ($dependencies as $dep) {
            if (!is_array($dep) || ($dep['name'] ?? '') !== 'grav') {
                continue;
            }
            $version = $dep['version'] ?? '';
            if (!preg_match('/(\d+\.\d+(?:\.\d+)?)/', $version, $m)) {
                continue;
            }

            if (version_compare($m[1], '2.0', '>=')) {
                return ['grav' => ['2.0'], 'api' => []];
            }
            if (version_compare($m[1], '1.8', '>=')) {
                return ['grav' => ['1.8'], 'api' => []];
            }

            return ['grav' => ['1.7'], 'api' => []];
        }

        return ['grav' => ['1.7'], 'api' => []];
    }
}
