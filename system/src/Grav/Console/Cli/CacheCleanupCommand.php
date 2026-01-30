<?php

/**
 * @package    Grav\Console\Cli
 *
 * @copyright  Copyright (c) 2015 - 2025 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\Cli;

use DirectoryIterator;
use Exception;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Console\GravCommand;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class CacheCleanupCommand
 * @package Grav\Console\Cli
 */
class CacheCleanupCommand extends GravCommand
{
    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('cache-cleanup')
            ->setAliases(['cleanup'])
            ->setDescription('Removes orphaned cache directories that are no longer in use')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Actually delete orphaned caches (dry run without this)')
            ->addOption('max-age', 'd', InputOption::VALUE_REQUIRED, 'Delete orphaned caches older than N days', '1')
            ->addOption('max-age-weeks', 'w', InputOption::VALUE_REQUIRED, 'Delete orphaned caches older than N weeks')
            ->addOption('max-age-months', 'm', InputOption::VALUE_REQUIRED, 'Delete orphaned caches older than N months')
            ->setHelp(<<<'EOF'
The <info>cache-cleanup</info> command removes orphaned cache directories that are no longer in use.
Only keeps the current cache key directory.

<comment>Dry run (shows what would be deleted):</comment>
  <info>bin/grav cache-cleanup</info>

<comment>Actually delete orphaned caches:</comment>
  <info>bin/grav cache-cleanup --force</info>

<comment>Delete orphaned caches older than 7 days:</comment>
  <info>bin/grav cache-cleanup --force --max-age=7</info>

<comment>Delete orphaned caches older than 2 weeks:</comment>
  <info>bin/grav cache-cleanup --force --max-age-weeks=2</info>

<comment>Delete orphaned caches older than 1 month:</comment>
  <info>bin/grav cache-cleanup --force --max-age-months=1</info>

<comment>Cron example (run daily at 3am):</comment>
  <info>0 3 * * * /path/to/grav/bin/grav cache-cleanup --force >> /var/log/grav-cache-cleanup.log 2>&1</info>
EOF
            );
    }

    /**
     * @return int
     */
    protected function serve(): int
    {
        $this->initializeGrav();

        $input = $this->getInput();
        $io = $this->getIO();

        $force = $input->getOption('force');
        $maxAge = $this->calculateMaxAgeDays();
        $maxAgeSeconds = $maxAge * 86400;

        $grav = Grav::instance();
        $cache = $grav['cache'];
        $currentKey = $cache->getKey();

        // Extract just the uniqueness part (after the prefix and dash)
        $currentUniqueness = substr($currentKey, strpos($currentKey, '-') + 1);

        $io->title('Grav Cache Cleanup');
        $io->writeln("Current cache key: <info>{$currentKey}</info>");
        $io->writeln("Current uniqueness: <info>{$currentUniqueness}</info>");
        $io->writeln("Max age for orphaned caches: <info>{$maxAge} day(s)</info>");
        $io->writeln('Mode: ' . ($force ? '<red>FORCE (will delete)</red>' : '<yellow>DRY RUN (use --force to delete)</yellow>'));
        $io->newLine();

        $cacheDir = GRAV_ROOT . '/cache';

        if (!is_dir($cacheDir)) {
            $io->error("Cache directory not found: {$cacheDir}");
            return 1;
        }

        $now = time();
        $totalDeleted = 0;
        $totalSize = 0;
        $keptCount = 0;
        $skippedCount = 0;

        // Directories that contain cache key subdirectories (8-char hex)
        $cacheKeyDirs = [
            $cacheDir . '/doctrine',
            $cacheDir . '/grav',
        ];

        foreach ($cacheKeyDirs as $scanDir) {
            if (!is_dir($scanDir)) {
                if ($io->isVerbose()) {
                    $io->writeln("Skipping (not found): {$scanDir}");
                }
                continue;
            }

            $io->writeln("Scanning: <cyan>{$scanDir}</cyan>");
            $iterator = new DirectoryIterator($scanDir);

            foreach ($iterator as $file) {
                if ($file->isDot() || !$file->isDir()) {
                    continue;
                }

                $dirName = $file->getBasename();
                $dirPath = $file->getPathname();

                // Only process directories that look like cache keys (8-char hex)
                if (!preg_match('/^[a-f0-9]{8}$/', $dirName)) {
                    if ($io->isVerbose()) {
                        $io->writeln("[SKIP] {$dirName} (not a cache key directory)");
                    }
                    continue;
                }

                $dirAge = $now - $file->getMTime();
                $dirAgeDays = round($dirAge / 86400, 1);

                // Get directory size
                $size = $this->getDirectorySize($dirPath);
                $sizeFormatted = $this->formatBytes($size);

                if ($dirName === $currentUniqueness) {
                    $io->writeln("<green>[KEEP]</green> {$dirName} (CURRENT - {$sizeFormatted})");
                    $keptCount++;
                    continue;
                }

                // Check if old enough to delete
                if ($dirAge < $maxAgeSeconds) {
                    $io->writeln("<yellow>[SKIP]</yellow> {$dirName} (only {$dirAgeDays} days old, waiting for {$maxAge} days - {$sizeFormatted})");
                    $skippedCount++;
                    continue;
                }

                $io->writeln("<red>[DELETE]</red> {$dirName} ({$dirAgeDays} days old - {$sizeFormatted})");

                if ($force) {
                    try {
                        Folder::delete($dirPath);
                        $totalDeleted++;
                        $totalSize += $size;
                        if ($io->isVerbose()) {
                            $io->writeln('  -> Deleted successfully');
                        }
                    } catch (Exception $e) {
                        $io->writeln('  -> <red>ERROR:</red> ' . $e->getMessage());
                    }
                } else {
                    $totalDeleted++;
                    $totalSize += $size;
                }
            }
        }

        $io->newLine();
        $io->section('Summary');
        $io->writeln("Current cache kept: <green>{$keptCount}</green>");
        $io->writeln("Orphaned caches skipped (too new): <yellow>{$skippedCount}</yellow>");

        if ($force) {
            $io->writeln("Orphaned caches deleted: <red>{$totalDeleted}</red>");
            $io->writeln('Space freed: <info>' . $this->formatBytes($totalSize) . '</info>');
        } else {
            $io->writeln("Orphaned caches to delete: <red>{$totalDeleted}</red>");
            $io->writeln('Space to free: <info>' . $this->formatBytes($totalSize) . '</info>');
            if ($totalDeleted > 0) {
                $io->newLine();
                $io->note('Run with --force to actually delete these directories.');
            }
        }

        return 0;
    }

    /**
     * Calculate max age in days from the various options
     *
     * @return int
     */
    private function calculateMaxAgeDays(): int
    {
        $input = $this->getInput();

        // Check for months first (highest priority)
        $months = $input->getOption('max-age-months');
        if ($months !== null) {
            return (int)$months * 30;
        }

        // Check for weeks
        $weeks = $input->getOption('max-age-weeks');
        if ($weeks !== null) {
            return (int)$weeks * 7;
        }

        // Default to days
        return (int)$input->getOption('max-age');
    }

    /**
     * Get directory size recursively
     *
     * @param string $path
     * @return int
     */
    private function getDirectorySize(string $path): int
    {
        $size = 0;

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        } catch (Exception $e) {
            // Ignore errors, return what we have
        }

        return $size;
    }

    /**
     * Format bytes to human readable
     *
     * @param int $bytes
     * @param int $precision
     * @return string
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
