<?php

/**
 * @package    Grav\Common\Upgrade
 *
 * @copyright  Copyright (c) 2015 - 2025 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Upgrade;

use Grav\Common\Filesystem\Folder;
use Grav\Common\GPM\GPM;
use Grav\Common\Yaml;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use function basename;
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
use function rename;
use function rsort;
use function sort;
use function time;
use function uniqid;
use function trim;
use function strpos;
use function unlink;
use const GRAV_ROOT;
use const GLOB_ONLYDIR;
use const JSON_PRETTY_PRINT;

/**
 * Safe upgrade orchestration for Grav core.
 */
class SafeUpgradeService
{
    /** @var string */
    private $rootPath;
    /** @var string */
    private $parentDir;
    /** @var string */
    private $stagingRoot;
    /** @var string */
    private $manifestStore;

    /** @var array */
    private $ignoredDirs = [
        'backup',
        'images',
        'logs',
        'tmp',
        'cache',
        'user',
    ];

    /**
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $root = $options['root'] ?? GRAV_ROOT;
        $this->rootPath = rtrim($root, DIRECTORY_SEPARATOR);
        $this->parentDir = $options['parent_dir'] ?? dirname($this->rootPath);
        $this->stagingRoot = $options['staging_root'] ?? ($this->parentDir . DIRECTORY_SEPARATOR . 'grav-upgrades');
        $this->manifestStore = $options['manifest_store'] ?? ($this->rootPath . DIRECTORY_SEPARATOR . 'user' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'upgrades');
        if (isset($options['ignored_dirs']) && is_array($options['ignored_dirs'])) {
            $this->ignoredDirs = $options['ignored_dirs'];
        }
    }

    /**
     * Run preflight validations before attempting an upgrade.
     *
     * @return array{plugins_pending: array<string, array>, psr_log_conflicts: array<string, array>, warnings: string[]}
     */
    public function preflight(): array
    {
        $warnings = [];
        try {
            $pending = $this->detectPendingPluginUpdates();
        } catch (RuntimeException $e) {
            $pending = [];
            $warnings[] = $e->getMessage();
        }

        $psrLogConflicts = $this->detectPsrLogConflicts();
        if ($pending) {
            $warnings[] = 'One or more plugins/themes are not up to date.';
        }
        if ($psrLogConflicts) {
            $warnings[] = 'Potential psr/log signature conflicts detected.';
        }

        return [
            'plugins_pending' => $pending,
            'psr_log_conflicts' => $psrLogConflicts,
            'warnings' => $warnings,
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
        $backupPath = $this->stagingRoot . DIRECTORY_SEPARATOR . 'rollback-' . $stageId;

        Folder::create($packagePath);

        // Copy extracted package into staging area.
        Folder::rcopy($extractedPath, $packagePath);

        // Ensure ignored directories are replaced with live copies.
        $this->hydrateIgnoredDirectories($packagePath, $ignores);

        $manifest = $this->buildManifest($stageId, $targetVersion, $packagePath, $backupPath);
        $manifestPath = $stagePath . DIRECTORY_SEPARATOR . 'manifest.json';
        Folder::create(dirname($manifestPath));
        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT));

        // Promote staged package into place.
        $this->promoteStagedTree($packagePath, $backupPath);
        $this->persistManifest($manifest);
        $this->pruneOldSnapshots();
        Folder::delete($stagePath);

        return $manifest;
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

        // Put the current tree aside before flip.
        $rotated = $this->rotateCurrentTree();

        $this->promoteBackup($backupPath);
        $this->markRollback($manifest['id']);
        if ($rotated && is_dir($rotated)) {
            Folder::delete($rotated);
        }

        return $manifest;
    }

    /**
     * @return void
     */
    public function clearRecoveryFlag(): void
    {
        $flag = $this->rootPath . '/system/recovery.flag';
        if (is_file($flag)) {
            @unlink($flag);
        }
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

            $live = $this->rootPath . '/' . $relative;
            $stage = $packagePath . '/' . $relative;

            Folder::delete($stage);

            if (!is_dir($live)) {
                continue;
            }

            // Skip caches to avoid stale data.
            if (in_array($relative, ['cache', 'tmp'], true)) {
                Folder::create($stage);
                continue;
            }

            Folder::create(dirname($stage));
            Folder::rcopy($live, $stage);
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
    private function buildManifest(string $stageId, string $targetVersion, string $packagePath, string $backupPath): array
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
            'plugins' => $plugins,
        ];
    }

    /**
     * Promote staged package by swapping directory names.
     *
     * @param string $packagePath
     * @param string $backupPath
     * @return void
     */
    private function promoteStagedTree(string $packagePath, string $backupPath): void
    {
        $liveRoot = $this->rootPath;
        Folder::create(dirname($backupPath));

        if (!rename($liveRoot, $backupPath)) {
            throw new RuntimeException('Failed to move current Grav directory into backup.');
        }

        if (!rename($packagePath, $liveRoot)) {
            // Attempt to restore live tree.
            rename($backupPath, $liveRoot);
            throw new RuntimeException('Failed to promote staged Grav release.');
        }
    }

    /**
     * Move existing tree aside to allow rollback swap.
     *
     * @return void
     */
    private function rotateCurrentTree(): string
    {
        $liveRoot = $this->rootPath;
        $target = $this->stagingRoot . DIRECTORY_SEPARATOR . 'rotated-' . time();
        Folder::create($this->stagingRoot);
        if (!rename($liveRoot, $target)) {
            throw new RuntimeException('Unable to rotate live tree during rollback.');
        }

        return $target;
    }

    /**
     * Promote a backup tree into the live position.
     *
     * @param string $backupPath
     * @return void
     */
    private function promoteBackup(string $backupPath): void
    {
        if (!rename($backupPath, $this->rootPath)) {
            throw new RuntimeException('Rollback failed: unable to move backup into live position.');
        }
    }

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
     * Keep only the three newest snapshots.
     *
     * @return void
     */
    private function pruneOldSnapshots(): void
    {
        $files = glob($this->manifestStore . DIRECTORY_SEPARATOR . '*.json') ?: [];
        if (count($files) <= 3) {
            return;
        }

        sort($files);
        $excess = array_slice($files, 0, count($files) - 3);
        foreach ($excess as $file) {
            $data = json_decode(file_get_contents($file), true);
            if (isset($data['backup_path']) && is_dir($data['backup_path'])) {
                Folder::delete($data['backup_path']);
            }
            @unlink($file);
        }
    }
}
