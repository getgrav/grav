<?php

/**
 * @package    Grav\Common\Upgrade
 *
 * @copyright  Copyright (c) 2015 - 2025 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Upgrade;

use DirectoryIterator;
use Grav\Common\Data\Data;
use Grav\Common\Filesystem\Folder;
use Grav\Common\GPM\GPM;
use Grav\Common\Grav;
use Grav\Common\Yaml;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;
use function array_key_exists;
use function basename;
use function chmod;
use function copy;
use function count;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function in_array;
use function is_dir;
use function is_file;
use function json_decode;
use function json_encode;
use function preg_match;
use function preg_replace;
use function property_exists;
use function rename;
use function rsort;
use function sort;
use function rtrim;
use function strpos;
use function time;
use function trim;
use function uniqid;
use function unlink;
use function ltrim;
use const GRAV_ROOT;
use const GLOB_ONLYDIR;
use const JSON_PRETTY_PRINT;

/**
 * Safe upgrade orchestration for Grav core.
 */
class SafeUpgradeService
{
    /**
     * Version identifier for this SafeUpgradeService implementation.
     * This is used to verify that the correct version is loaded during upgrades.
     *
     * IMPORTANT: Increment this with each release that changes SafeUpgradeService.
     * Format: YYYYMMDD (release date)
     *
     * @var string
     */
    public const IMPLEMENTATION_VERSION = '20251118'; // 2025-11-18 - Fixed snapshot creation (copy vs move) and implemented pruning

    /** @var string */
    private $rootPath;
    /** @var string */
    private $stagingRoot;
    /** @var string */
    private $manifestStore;
    /** @var \Grav\Common\Config\ConfigInterface|null */
    private $config;
    /** @var array|null */
    private $lastManifest = null;

    /** @var array */
    private $ignoredDirs = [
        'backup',
        'images',
        'logs',
        'tmp',
        'cache',
        'user',
        '.github',
        '.phan',
    ];
    /** @var callable|null */
    private $progressCallback = null;

    /**
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $root = $options['root'] ?? GRAV_ROOT;
        $this->rootPath = rtrim($root, DIRECTORY_SEPARATOR);
        $this->config = $options['config'] ?? null;

        $locator = null;
        try {
            $locator = Grav::instance()['locator'] ?? null;
        } catch (Throwable $e) {
            $locator = null;
        }

        $primary = null;
        if ($locator && method_exists($locator, 'findResource')) {
            try {
                $primary = $locator->findResource('tmp://grav-snapshots', true, true);
            } catch (Throwable $e) {
                $primary = null;
            }
        }

        if (!$primary) {
            $primary = $this->rootPath . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'grav-snapshots';
        }

        $this->stagingRoot = $this->resolveStagingPath($primary);

        if (null === $this->stagingRoot) {
            throw new RuntimeException('Unable to locate writable staging directory. Ensure tmp://grav-snapshots is writable.');
        }
        $this->manifestStore = $options['manifest_store'] ?? ($this->rootPath . DIRECTORY_SEPARATOR . 'user' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'upgrades');
        if (isset($options['ignored_dirs']) && is_array($options['ignored_dirs'])) {
            $this->ignoredDirs = $options['ignored_dirs'];
        }
    }

    /**
     * Run preflight validations before attempting an upgrade.
     *
     * @param string|null $targetVersion The target Grav version being upgraded to (e.g., '1.8.0')
     * @return array{plugins_pending: array<string, array>, psr_log_conflicts: array<string, array>, warnings: string[], is_major_minor_upgrade: bool}
     */
    public function preflight(?string $targetVersion = null): array
    {
        $warnings = [];
        $isMajorMinorUpgrade = false;

        // Determine if this is a major/minor version upgrade (e.g., 1.7.x -> 1.8.y)
        if ($targetVersion !== null) {
            $currentVersion = GRAV_VERSION;
            $currentParts = explode('.', $currentVersion);
            $targetParts = explode('.', $targetVersion);

            $currentMajor = (int)($currentParts[0] ?? 0);
            $currentMinor = (int)($currentParts[1] ?? 0);
            $targetMajor = (int)($targetParts[0] ?? 0);
            $targetMinor = (int)($targetParts[1] ?? 0);

            $isMajorMinorUpgrade = ($currentMajor !== $targetMajor) || ($currentMinor !== $targetMinor);
        }

        try {
            $pending = $this->detectPendingPluginUpdates();
        } catch (RuntimeException $e) {
            $pending = [];
            $warnings[] = $e->getMessage();
        }

        $psrLogConflicts = $this->detectPsrLogConflicts();
        $monologConflicts = $this->detectMonologConflicts();

        if ($pending) {
            if ($isMajorMinorUpgrade) {
                $warnings[] = 'Because this is a major Grav upgrade, update pending plugins and themes before continuing.';
            } else {
                $warnings[] = 'Pending plugin/theme updates detected. Update them before running Grav upgrade.';
            }
        }
        if ($psrLogConflicts) {
            $warnings[] = 'Potential psr/log signature conflicts detected.';
        }
        if ($monologConflicts) {
            $warnings[] = 'Potential Monolog logger API incompatibilities detected.';
        }

        return [
            'plugins_pending' => $pending,
            'psr_log_conflicts' => $psrLogConflicts,
            'monolog_conflicts' => $monologConflicts,
            'warnings' => $warnings,
            'is_major_minor_upgrade' => $isMajorMinorUpgrade,
        ];
    }

    /**
     * Stage and promote a Grav update from an extracted folder.
     *
     * @param string $extractedPath Path to the extracted update package.
     * @param string $targetVersion Target Grav version.
     * @param array<string> $ignores
     * @return array Manifest data.
     */
    public function promote(string $extractedPath, string $targetVersion, array $ignores): array
    {
        if (!is_dir($extractedPath)) {
            throw new InvalidArgumentException(sprintf('Extracted package path "%s" is not a directory.', $extractedPath));
        }

        $stageId = uniqid('stage-', false);
        $stagePath = $this->stagingRoot . DIRECTORY_SEPARATOR . $stageId;
        $packagePath = $stagePath . DIRECTORY_SEPARATOR . 'package';
        $backupPath = $this->stagingRoot . DIRECTORY_SEPARATOR . 'snapshot-' . $stageId;

        Folder::create(dirname($packagePath));

        $this->reportProgress('installing', 'Preparing staged package...', null);
        $stagingMode = $this->stageExtractedPackage($extractedPath, $packagePath);
        $this->reportProgress('installing', 'Preparing staged package...', null, ['mode' => $stagingMode]);

        $packageEntries = $this->collectPackageEntries($packagePath);

        // CRITICAL SAFETY CHECK: Verify 'user' is never in package entries before proceeding
        if (in_array('user', $packageEntries, true)) {
            throw new RuntimeException(
                'SAFETY VIOLATION: user directory found in package entries. ' .
                'This should never happen and could result in data loss. Aborting upgrade.'
            );
        }

        $this->carryOverRootDotfiles($packagePath);

        // Ensure ignored directories are replaced with live copies.
        $this->hydrateIgnoredDirectories($packagePath, $ignores);
        $this->carryOverRootFiles($packagePath, $ignores);

        // IMPORTANT: Snapshot should ONLY include files from the original package, NOT custom directories
        // that were carried over. This prevents snapshotting huge custom folders like /media-test, /downloads, etc.
        // The carryOverRootFiles() method preserves custom files during upgrade, but they should not be snapshotted
        // because they are not being replaced and don't need to be rolled back.
        if (!$packageEntries) {
            throw new RuntimeException('Staged package does not contain any files to promote.');
        }

        // FINAL SAFETY CHECK: Verify 'user' is not in the entries that will be deployed
        if (in_array('user', $packageEntries, true)) {
            throw new RuntimeException(
                'SAFETY VIOLATION: user directory found in deployment entries. ' .
                'Aborting upgrade to protect user data.'
            );
        }

        $this->reportProgress('snapshot', 'Creating backup snapshot...', null);
        $this->createBackupSnapshot($packageEntries, $backupPath);

        $manifest = $this->buildManifest($stageId, $targetVersion, $packagePath, $backupPath, $packageEntries);
        $manifestPath = $stagePath . DIRECTORY_SEPARATOR . 'manifest.json';
        Folder::create(dirname($manifestPath));
        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT));

        $this->reportProgress('installing', 'Copying update files...', null);

        try {
            $this->copyEntries($packageEntries, $packagePath, $this->rootPath, 'installing', 'Deploying', true);
        } catch (Throwable $e) {
            // Rollback: restore from snapshot
            $this->reportProgress('rollback', 'Upgrade failed, restoring from snapshot...', null);
            $this->copyEntries($packageEntries, $backupPath, $this->rootPath, 'rollback', 'Restoring', false);
            throw new RuntimeException('Failed to promote staged Grav release.', 0, $e);
        }

        $this->reportProgress('finalizing', 'Finalizing upgrade...', null);
        $this->persistManifest($manifest);
        $this->lastManifest = $manifest;
        $this->pruneOldSnapshots();

        // Clean up staging directory
        // Wrap in try-catch because autoloader may have stale paths after file copy
        try {
            Folder::delete($stagePath);
        } catch (\Throwable $e) {
            // Staging cleanup failed, but upgrade succeeded
            // Directory will be cleaned up on next request
            error_log('Warning: Failed to delete staging directory: ' . $e->getMessage());
        }

        return $manifest;
    }

    /**
     * Create a manual snapshot of the current Grav installation.
     *
     * @param string|null $label
     * @return array
     */
    public function createSnapshot(?string $label = null): array
    {
        $entries = $this->collectPackageEntries($this->rootPath);
        if (!$entries) {
            throw new RuntimeException('Unable to locate files to snapshot.');
        }

        $stageId = uniqid('snapshot-', false);
        $backupPath = $this->stagingRoot . DIRECTORY_SEPARATOR . 'snapshot-' . $stageId;

        $this->reportProgress('snapshot', 'Creating manual snapshot...', null, [
            'operation' => 'snapshot',
            'label' => $label,
            'mode' => 'manual',
        ]);

        $this->createBackupSnapshot($entries, $backupPath);

        $manifest = $this->buildManifest($stageId, GRAV_VERSION, $this->rootPath, $backupPath, $entries);
        $manifest['package_path'] = null;
        if ($label !== null && $label !== '') {
            $manifest['label'] = $label;
        }
        $manifest['operation'] = 'snapshot';
        $manifest['mode'] = 'manual';

        $this->persistManifest($manifest);
        $this->lastManifest = $manifest;
        $this->pruneOldSnapshots();

        $this->reportProgress('complete', sprintf('Snapshot %s created.', $stageId), 100, [
            'operation' => 'snapshot',
            'snapshot' => $stageId,
            'version' => $manifest['target_version'] ?? null,
            'mode' => 'manual',
        ]);

        return $manifest;
    }

    /**
     * Create a snapshot specifically for automated upgrades.
     *
     * @param string $targetVersion
     * @param string|null $label
     * @return array
     */
    public function createUpgradeSnapshot(string $targetVersion, ?string $label = null): array
    {
        $entries = $this->collectPackageEntries($this->rootPath);
        if (!$entries) {
            throw new RuntimeException('Unable to locate files to snapshot.');
        }

        $stageId = uniqid('upgrade-', false);
        $backupPath = $this->stagingRoot . DIRECTORY_SEPARATOR . 'snapshot-' . $stageId;

        $this->reportProgress('snapshot', sprintf('Capturing snapshot before upgrading to %s...', $targetVersion), null, [
            'operation' => 'upgrade',
            'target_version' => $targetVersion,
        ]);

        $this->createBackupSnapshot($entries, $backupPath);

        $manifest = $this->buildManifest($stageId, $targetVersion, $this->rootPath, $backupPath, $entries);
        $manifest['package_path'] = null;
        if ($label !== null && $label !== '') {
            $manifest['label'] = $label;
        }
        $manifest['operation'] = 'upgrade';
        $manifest['mode'] = 'pre-upgrade';

        $this->persistManifest($manifest);
        $this->lastManifest = $manifest;
        $this->pruneOldSnapshots();

        $this->reportProgress('snapshot', sprintf('Snapshot %s captured.', $stageId), 100, [
            'operation' => 'upgrade',
            'snapshot' => $stageId,
            'target_version' => $targetVersion,
        ]);

        return $manifest;
    }

    private function collectPackageEntries(string $packagePath): array
    {
        $entries = [];
        $iterator = new DirectoryIterator($packagePath);
        foreach ($iterator as $fileinfo) {
            if ($fileinfo->isDot()) {
                continue;
            }

            $name = $fileinfo->getFilename();
            if (in_array($name, $this->ignoredDirs, true)) {
                continue;
            }

            // CRITICAL SAFETY CHECK: Never allow 'user' directory to be collected
            // This prevents any scenario where user/ could be overwritten during upgrade
            if ($name === 'user') {
                continue;
            }

            $entries[] = $name;
        }

        sort($entries);

        return $entries;
    }

    private function stageExtractedPackage(string $sourcePath, string $packagePath): string
    {
        if (is_dir($packagePath)) {
            Folder::delete($packagePath);
        }

        if (@rename($sourcePath, $packagePath)) {
            return 'move';
        }

        Folder::create($packagePath);
        $entries = $this->collectPackageEntries($sourcePath);
        $this->copyEntries($entries, $sourcePath, $packagePath, 'installing', 'Staging', true);
        Folder::delete($sourcePath);

        return 'copy';
    }

    private function createBackupSnapshot(array $entries, string $backupPath): void
    {
        Folder::create($backupPath);
        $this->copyEntries($entries, $this->rootPath, $backupPath, 'snapshot', 'Snapshotting', false);
    }

    private function copyEntries(array $entries, string $sourceBase, string $targetBase, ?string $progressStage = null, ?string $progressPrefix = null, bool $useMove = false): void
    {
        $total = count($entries);
        foreach ($entries as $index => $entry) {
            // CRITICAL SAFETY CHECK: Absolutely prevent any operations on 'user' directory
            // This is a fail-safe to ensure user data is never touched during upgrades
            if ($entry === 'user' || strpos($entry, 'user' . DIRECTORY_SEPARATOR) === 0) {
                throw new RuntimeException(
                    'SAFETY VIOLATION: Attempted to copy user directory during upgrade. ' .
                    'This should never happen. Aborting upgrade to protect user data.'
                );
            }

            $source = $sourceBase . DIRECTORY_SEPARATOR . $entry;
            if (!is_file($source) && !is_dir($source) && !is_link($source)) {
                continue;
            }

            if ($progressStage) {
                $message = sprintf(
                    '%s %s (%d/%d)',
                    $progressPrefix ?? 'Processing',
                    $entry,
                    $index + 1,
                    max($total, 1)
                );
                $percent = $total > 0 ? (int)floor((($index + 1) / $total) * 100) : null;
                $this->reportProgress($progressStage, $message, $percent ?: null, [
                    'entry' => $entry,
                    'index' => $index + 1,
                    'total' => $total,
                ]);
            }

            $destination = $targetBase . DIRECTORY_SEPARATOR . $entry;

            // Use the same simple approach as traditional upgrade:
            // Delete old, copy new, let filesystem handle ownership
            $this->removeEntry($destination);

            if (is_link($source)) {
                Folder::create(dirname($destination));
                if (!@symlink(readlink($source), $destination)) {
                    throw new RuntimeException(sprintf('Failed to replicate symlink "%s".', $source));
                }
            } elseif (is_dir($source)) {
                Folder::create(dirname($destination));
                
                if ($useMove) {
                    // Use move() like traditional upgrade - faster than copy
                    Folder::move($source, $destination);
                } else {
                    Folder::rcopy($source, $destination, true);
                }

                // Set bin/ permissions like traditional upgrade does
                if ($entry === 'bin') {
                    $binFiles = glob($destination . DIRECTORY_SEPARATOR . '*') ?: [];
                    foreach ($binFiles as $binFile) {
                        @chmod($binFile, 0755);
                    }
                }
            } else {
                Folder::create(dirname($destination));
                if ($useMove) {
                    if (!@rename($source, $destination)) {
                        if (!@copy($source, $destination)) {
                            throw new RuntimeException(sprintf('Failed to move file "%s" to "%s".', $source, $destination));
                        }
                        @unlink($source);
                    }
                } else {
                    if (!@copy($source, $destination)) {
                        throw new RuntimeException(sprintf('Failed to copy file "%s" to "%s".', $source, $destination));
                    }
                    $perms = @fileperms($source);
                    if ($perms !== false) {
                        @chmod($destination, $perms & 0777);
                    }
                }
            }
        }
    }

    private function removeEntry(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);
        } elseif (is_dir($path)) {
            Folder::delete($path);
        }
    }

    public function setProgressCallback(?callable $callback): self
    {
        $this->progressCallback = $callback;

        return $this;
    }

    private function reportProgress(string $stage, string $message, ?int $percent = null, array $extra = []): void
    {
        if ($this->progressCallback) {
            ($this->progressCallback)($stage, $message, $percent, $extra);
        }
    }

    /**
     * Roll back to the most recent snapshot.
     *
     * @param string|null $id
     * @return array|null
     */
    public function rollback(?string $id = null): ?array
    {
        $manifest = $this->resolveManifest($id);
        if (!$manifest) {
            return null;
        }

        $backupPath = $manifest['backup_path'] ?? null;
        if (!$backupPath || !is_dir($backupPath)) {
            throw new RuntimeException('Rollback snapshot is no longer available.');
        }

        $entries = $manifest['entries'] ?? [];
        if (!$entries) {
            $entries = $this->collectPackageEntries($backupPath);
        }
        if (!$entries) {
            throw new RuntimeException('Rollback snapshot entries are missing from the manifest.');
        }

        $this->reportProgress('rollback', 'Restoring snapshot...', null);
        $this->copyEntries($entries, $backupPath, $this->rootPath, 'rollback', 'Restoring', false);
        $this->markRollback($manifest['id']);
        $this->lastManifest = $manifest;

        return $manifest;
    }

    /**
     * @return void
     */
    public function clearRecoveryFlag(): void
    {
        $flag = $this->rootPath . '/user/data/recovery.flag';
        if (is_file($flag)) {
            @unlink($flag);
        }
    }

    /**
     * @return array|null
     */
    public function getLastManifest(): ?array
    {
        return $this->lastManifest;
    }

    /**
     * @return array<string, array>
     */
    protected function detectPendingPluginUpdates(): array
    {
        try {
            $gpm = new GPM();
        } catch (Throwable $e) {
            throw new RuntimeException('Unable to query GPM: ' . $e->getMessage(), 0, $e);
        }
        $updates = $gpm->getUpdatable(['plugins' => true, 'themes' => true]);
        $pending = [];
        foreach ($updates as $type => $packages) {
            if (!is_array($packages)) {
                continue;
            }
            foreach ($packages as $slug => $package) {
                if (!$this->isGpmPackagePublished($package)) {
                    continue;
                }

                if ($type === 'plugins' && !$this->isPluginEnabled($slug)) {
                    continue;
                }

                if ($type === 'themes' && !$this->isThemeEnabled($slug)) {
                    continue;
                }

                $pending[$slug] = [
                    'type' => $type,
                    'current' => $package->version ?? null,
                    'available' => $package->available ?? null,
                ];
            }
        }

        return $pending;
    }

    /**
     * Determine if the provided GPM package metadata is marked as published.
     *
     * By default the GPM repository omits the `published` flag, so we only treat the package as unpublished
     * when the value exists and evaluates to `false`.
     *
     * @param mixed $package
     * @return bool
     */
    protected function isGpmPackagePublished($package): bool
    {
        if (is_object($package) && method_exists($package, 'getData')) {
            $data = $package->getData();
            if ($data instanceof Data) {
                $published = $data->get('published');
                return $published !== false;
            }
        }

        if (is_array($package)) {
            if (array_key_exists('published', $package)) {
                return $package['published'] !== false;
            }

            return true;
        }

        $value = null;
        if (is_object($package) && property_exists($package, 'published')) {
            $value = $package->published;
        }

        return $value !== false;
    }

    /**
     * Check plugins for psr/log requirements that conflict with Grav 1.8 vendor stack.
     *
     * @return array<string, array>
     */
    protected function detectPsrLogConflicts(): array
    {
        $conflicts = [];
        $pluginRoots = glob($this->rootPath . '/user/plugins/*', GLOB_ONLYDIR) ?: [];
        foreach ($pluginRoots as $path) {
            $composerFile = $path . '/composer.json';
            if (!is_file($composerFile)) {
                continue;
            }

            $json = json_decode(file_get_contents($composerFile), true);
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

            $conflicts[$slug] = [
                'composer' => $composerFile,
                'requires' => $rawConstraint,
            ];
        }

        return $conflicts;
    }

    protected function isPluginEnabled(string $slug): bool
    {
        if ($this->config) {
            try {
                $value = $this->config->get("plugins.{$slug}.enabled");
                if ($value !== null) {
                    return (bool)$value;
                }
            } catch (Throwable $e) {
                // ignore and fall back to file checks
            }
        }

        $configPath = $this->rootPath . '/user/config/plugins/' . $slug . '.yaml';
        if (is_file($configPath)) {
            try {
                $data = Yaml::parseFile($configPath);
                if (is_array($data) && array_key_exists('enabled', $data)) {
                    return (bool)$data['enabled'];
                }
            } catch (Throwable $e) {
                // ignore parse errors and treat as enabled
            }
        }

        return true;
    }

    protected function isThemeEnabled(string $slug): bool
    {
        if ($this->config) {
            try {
                $active = $this->config->get('system.pages.theme');
                if ($active !== null) {
                    return $active === $slug;
                }
            } catch (Throwable $e) {
                // ignore
            }
        }

        $configPath = $this->rootPath . '/user/config/system.yaml';
        if (is_file($configPath)) {
            try {
                $data = Yaml::parseFile($configPath);
                if (is_array($data)) {
                    $active = $data['pages']['theme'] ?? ($data['system']['pages']['theme'] ?? null);
                    if ($active !== null) {
                        return $active === $slug;
                    }
                }
            } catch (Throwable $e) {
                // ignore parse errors and assume current theme
            }
        }

        return true;
    }

    /**
     * Detect usage of deprecated Monolog `add*` methods removed in newer releases.
     *
     * @return array<string, array>
     */
    protected function detectMonologConflicts(): array
    {
        $conflicts = [];
        $pluginRoots = glob($this->rootPath . '/user/plugins/*', GLOB_ONLYDIR) ?: [];
        $pattern = '/->add(?:Debug|Info|Notice|Warning|Error|Critical|Alert|Emergency)\s*\(/i';

        foreach ($pluginRoots as $path) {
            $slug = basename($path);
            if (!$this->isPluginEnabled($slug)) {
                continue;
            }

            $directory = new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS);
            $filter = new RecursiveCallbackFilterIterator($directory, static function ($current, $key, $iterator) {
                // Skip hidden files/dirs (starting with .)
                if ($current->getFilename()[0] === '.') {
                    return false;
                }
                if ($iterator->hasChildren()) {
                    // Exclude vendor and node_modules directories
                    return !in_array($current->getFilename(), ['vendor', 'node_modules'], true);
                }
                // Only include PHP files
                return $current->getExtension() === 'php';
            });

            $iterator = new RecursiveIteratorIterator($filter);

            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                $contents = @file_get_contents($file->getPathname());
                if ($contents === false) {
                    continue;
                }

                if (preg_match($pattern, $contents, $match)) {
                    $relative = str_replace($this->rootPath . '/', '', $file->getPathname());
                    $conflicts[$slug][] = [
                        'file' => $relative,
                        'method' => trim($match[0]),
                    ];
                }
            }
        }

        return $conflicts;
    }

    /**
     * Ensure directories flagged for ignoring get hydrated from the current installation.
     *
     * @param string $packagePath
     * @param array<string> $ignores
     * @return void
     */
    private function hydrateIgnoredDirectories(string $packagePath, array $ignores): void
    {
        $strategic = $ignores ?: $this->ignoredDirs;

        foreach ($strategic as $relative) {
            $relative = trim($relative, '/');
            if ($relative === '') {
                continue;
            }

            // CRITICAL: Ensure 'user' is always in the ignored directories list
            if (!in_array('user', $strategic, true)) {
                throw new RuntimeException(
                    'SAFETY VIOLATION: user directory is not in the ignored directories list. ' .
                    'This is a critical configuration error that could result in data loss.'
                );
            }

            $live = $this->rootPath . '/' . $relative;
            $stage = $packagePath . '/' . $relative;

            Folder::delete($stage);

            if (!is_dir($live)) {
                continue;
            }

            // Use empty placeholders to preserve directory structure without duplicating data.
            Folder::create($stage);
        }
    }

    /**
     * Preserve critical root-level dotfiles that may not ship in update packages.
     *
     * @param string $packagePath
     * @return void
     */
    private function carryOverRootDotfiles(string $packagePath): void
    {
        $skip = [
            '.git',
            '.DS_Store',
        ];

        $iterator = new DirectoryIterator($this->rootPath);
        foreach ($iterator as $entry) {
            if ($entry->isDot()) {
                continue;
            }

            $name = $entry->getFilename();
            if ($name === '' || $name[0] !== '.') {
                continue;
            }

            if (in_array($name, $skip, true)) {
                continue;
            }

            $target = $packagePath . DIRECTORY_SEPARATOR . $name;
            if (file_exists($target)) {
                continue;
            }

            $source = $entry->getPathname();
            if ($entry->isDir()) {
                Folder::rcopy($source, $target, true);
            } elseif ($entry->isFile()) {
                Folder::create(dirname($target));
                copy($source, $target);
            }
        }
    }

    /**
     * Carry over non-dot root files that are absent from the staged package.
     *
     * @param string $packagePath
     * @param array<string> $ignores
     * @return void
     */
    private function carryOverRootFiles(string $packagePath, array $ignores): void
    {
        $strategic = $ignores ?: $this->ignoredDirs;
        $skip = array_map(static function ($value) {
            return trim((string)$value, '/');
        }, $strategic);
        $skip = array_filter($skip, static function ($value) {
            return $value !== '';
        });
        $skip = array_values(array_unique($skip));

        // CRITICAL: Ensure 'user' is always in the skip list
        if (!in_array('user', $skip, true)) {
            $skip[] = 'user';
        }

        $iterator = new DirectoryIterator($this->rootPath);
        foreach ($iterator as $entry) {
            if ($entry->isDot()) {
                continue;
            }

            $name = $entry->getFilename();
            if ($name === '' || $name[0] === '.') {
                continue;
            }

            // CRITICAL SAFETY CHECK: Never copy 'user' directory
            if ($name === 'user') {
                continue;
            }

            if (in_array($name, $skip, true)) {
                continue;
            }

            if (!$entry->isDir() || $entry->isLink()) {
                continue;
            }

            $target = $packagePath . DIRECTORY_SEPARATOR . $name;
            if (file_exists($target)) {
                continue;
            }

            $source = $entry->getPathname();
            Folder::create(dirname($target));

            Folder::rcopy($source, $target, true);
        }
    }

    /**
     * Build manifest metadata for a staged upgrade.
     *
     * @param string $stageId
     * @param string $targetVersion
     * @param string $packagePath
     * @param string $backupPath
     * @return array
     */
    private function buildManifest(string $stageId, string $targetVersion, string $packagePath, string $backupPath, array $entries): array
    {
        $plugins = [];
        $pluginRoots = glob($this->rootPath . '/user/plugins/*', GLOB_ONLYDIR) ?: [];
        foreach ($pluginRoots as $path) {
            $slug = basename($path);
            $blueprint = $path . '/blueprints.yaml';
            $details = [
                'version' => null,
                'name' => $slug,
            ];

            if (is_file($blueprint)) {
                try {
                    $yaml = Yaml::parse(file_get_contents($blueprint));
                    if (isset($yaml['version'])) {
                        $details['version'] = $yaml['version'];
                    }
                    if (isset($yaml['name'])) {
                        $details['name'] = $yaml['name'];
                    }
                } catch (\RuntimeException $e) {
                    // ignore parse errors, keep defaults
                }
            }

            $plugins[$slug] = $details;
        }

        return [
            'id' => $stageId,
            'created_at' => time(),
            'source_version' => GRAV_VERSION,
            'target_version' => $targetVersion,
            'php_version' => PHP_VERSION,
            'package_path' => $packagePath,
            'backup_path' => $backupPath,
            'entries' => array_values($entries),
            'plugins' => $plugins,
        ];
    }

    /**
     * Ensure Git metadata is retained after stage promotion.
     *
     * @param string $source
     * @param string $destination
     * @return void
     */
    /**
     * Persist manifest into Grav data directory.
     *
     * @param array $manifest
     * @return void
     */
    private function persistManifest(array $manifest): void
    {
        Folder::create($this->manifestStore);
        $target = $this->manifestStore . DIRECTORY_SEPARATOR . $manifest['id'] . '.json';
        file_put_contents($target, json_encode($manifest, JSON_PRETTY_PRINT));
    }

    /**
     * Ensure directory exists and is writable.
     *
     * @param string $path
     * @return bool
     */
    private function resolveStagingPath(?string $path): ?string
    {
        if (null === $path || $path === '') {
            return null;
        }

        $expanded = $path;
        if (0 === strpos($expanded, '~')) {
            $home = getenv('HOME');
            if ($home) {
                $expanded = rtrim($home, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($expanded, '~\/');
            } else {
                return null;
            }
        }
        if (!$this->isAbsolutePath($expanded)) {
            $expanded = $this->rootPath . DIRECTORY_SEPARATOR . ltrim($expanded, DIRECTORY_SEPARATOR);
        }

        $expanded = $this->normalizePath($expanded);

        try {
            Folder::create($expanded);
        } catch (\RuntimeException $e) {
            return null;
        }

        return is_writable($expanded) ? $expanded : null;
    }

    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }

        return (bool)preg_match('#^[A-Za-z]:[\\/]#', $path);
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path);
        $path = rtrim($path, '/');

        return str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    /**
     * @param string|null $id
     * @return array|null
     */
    private function resolveManifest(?string $id): ?array
    {
        $path = null;

        if ($id) {
            $candidate = $this->manifestStore . DIRECTORY_SEPARATOR . $id . '.json';
            if (!is_file($candidate)) {
                return null;
            }
            $path = $candidate;
        } else {
            $files = glob($this->manifestStore . DIRECTORY_SEPARATOR . '*.json') ?: [];
            if (!$files) {
                return null;
            }
            rsort($files);
            $path = $files[0];
        }

        $decoded = json_decode(file_get_contents($path), true);

        return $decoded ?: null;
    }

    /**
     * Record rollback event in manifest store.
     *
     * @param string $id
     * @return void
     */
    private function markRollback(string $id): void
    {
        $target = $this->manifestStore . DIRECTORY_SEPARATOR . $id . '.json';
        if (!is_file($target)) {
            return;
        }

        $manifest = json_decode(file_get_contents($target), true);
        if (!is_array($manifest)) {
            return;
        }

        $manifest['rolled_back_at'] = time();
        file_put_contents($target, json_encode($manifest, JSON_PRETTY_PRINT));
    }

    /**
     * Keep only the newest snapshots based on configuration.
     *
     * @return void
     */
    private function pruneOldSnapshots(): void
    {
        $limit = 5;

        if ($this->config) {
            $limit = (int)$this->config->get('system.updates.safe_upgrade_snapshot_limit', 5);
        } else {
            try {
                $grav = Grav::instance();
                if (isset($grav['config'])) {
                    $limit = (int)$grav['config']->get('system.updates.safe_upgrade_snapshot_limit', 5);
                }
            } catch (Throwable $e) {
                // Fallback to default
            }
        }

        if ($limit <= 0) {
            return;
        }

        $files = glob($this->manifestStore . DIRECTORY_SEPARATOR . '*.json') ?: [];
        if (count($files) <= $limit) {
            return;
        }

        $manifests = [];
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }
            $data = json_decode($content, true);
            if (is_array($data) && isset($data['created_at'])) {
                $manifests[] = [
                    'path' => $file,
                    'created_at' => $data['created_at'],
                    'backup_path' => $data['backup_path'] ?? null
                ];
            }
        }

        // Sort by created_at descending
        usort($manifests, static function ($a, $b) {
            $result = $b['created_at'] <=> $a['created_at'];
            if ($result === 0) {
                return strcmp($b['path'], $a['path']);
            }

            return $result;
        });

        $toDelete = array_slice($manifests, $limit);

        foreach ($toDelete as $item) {
            // Delete manifest
            @unlink($item['path']);

            // Delete backup directory if it exists
            if ($item['backup_path'] && is_dir($item['backup_path'])) {
                // Ensure we are deleting a directory inside staging root to be safe
                if (strpos($item['backup_path'], $this->stagingRoot) === 0) {
                    Folder::delete($item['backup_path']);
                }
            }
        }
    }
}
