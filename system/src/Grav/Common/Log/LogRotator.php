<?php

/**
 * @package    Grav\Common\Log
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Log;

use Grav\Common\Grav;
use Grav\Common\Scheduler\Scheduler;
use Grav\Common\Utils;
use Throwable;

/**
 * Size-based rotation for the log files in log://.
 *
 * The live file keeps its name, so everything that reads `log://grav.log` keeps working:
 *
 *   grav.log -> grav.log.1 -> grav.log.2.gz -> ... -> grav.log.<max_files>.gz (then deleted)
 *
 * `.1` stays uncompressed because a request that opened the live file just before it was
 * renamed keeps appending to it until it finishes. Only `.2` onwards is gzipped.
 *
 * Rotation runs in two places: RotatingStreamHandler checks the size once per request on its
 * first write (most sites never set up cron), and the `log-rotate` scheduler job sweeps every
 * `*.log` in the folder, which covers logs plugins write with their own handlers.
 */
class LogRotator
{
    public const DEFAULT_MAX_SIZE = '10M';
    public const DEFAULT_MAX_FILES = 5;
    public const JOB_ID = 'log-rotate';

    /** Shared by every rotation in the folder; hidden so the `*.log` listings never pick it up. */
    private const LOCK_FILE = '.logrotate.lock';

    /**
     * Rotation settings from `system.log.rotate`, or null while the system config isn't
     * loaded yet (a log written during early bootstrap), so nothing rotates against
     * defaults the site may have turned off.
     *
     * @return array{enabled: bool, max_size: int, max_files: int, compress: bool}|null
     */
    public static function settings(): ?array
    {
        try {
            $grav = Grav::instance();
            if (!isset($grav['config'])) {
                return null;
            }
            $log = $grav['config']->get('system.log');
        } catch (Throwable) {
            return null;
        }

        if (!is_array($log)) {
            return null;
        }

        return static::normalize((array) ($log['rotate'] ?? []));
    }

    /**
     * @param array<string, mixed> $rotate
     * @return array{enabled: bool, max_size: int, max_files: int, compress: bool}
     */
    public static function normalize(array $rotate): array
    {
        $maxSize = Utils::parseSize($rotate['max_size'] ?? self::DEFAULT_MAX_SIZE);

        return [
            // A size of 0 means no limit, the same as turning rotation off.
            'enabled' => (bool) ($rotate['enabled'] ?? true) && $maxSize > 0,
            'max_size' => $maxSize,
            'max_files' => max(1, (int) ($rotate['max_files'] ?? self::DEFAULT_MAX_FILES)),
            'compress' => (bool) ($rotate['compress'] ?? true),
        ];
    }

    /**
     * @param string $path
     * @param int $maxSize bytes; 0 or less never rotates
     * @return bool
     */
    public static function needsRotation(string $path, int $maxSize): bool
    {
        if ($maxSize <= 0) {
            return false;
        }

        clearstatcache(true, $path);
        $size = @filesize($path);

        return $size !== false && $size >= $maxSize;
    }

    /**
     * Rotate one log file. Returns false without touching anything when another process is
     * rotating in the same folder, when the file is empty or missing, when `$ifLargerThan`
     * is given and the file is under it (someone else got there first), or when the live
     * file can't be renamed (on Windows, while another request has it open).
     *
     * @param string $path
     * @param int $maxFiles rotated files to keep
     * @param bool $compress gzip `.2` onwards
     * @param int|null $ifLargerThan re-check the size once the lock is held
     * @return bool
     */
    public static function rotate(string $path, int $maxFiles = self::DEFAULT_MAX_FILES, bool $compress = true, ?int $ifLargerThan = null): bool
    {
        $lock = @fopen(dirname($path) . '/' . self::LOCK_FILE, 'c');
        if ($lock === false) {
            return false;
        }

        try {
            if (!flock($lock, LOCK_EX | LOCK_NB)) {
                return false;
            }

            if ($ifLargerThan !== null && !static::needsRotation($path, $ifLargerThan)) {
                return false;
            }

            clearstatcache(true, $path);
            $size = @filesize($path);
            if (!$size) {
                return false;
            }

            $maxFiles = max(1, $maxFiles);
            $compress = $compress && function_exists('gzopen');

            // The oldest one falls off the end.
            @unlink($path . '.' . $maxFiles);
            @unlink($path . '.' . $maxFiles . '.gz');

            // Shift the rest up by one, newest last so every target is free.
            for ($i = $maxFiles - 1; $i >= 1; $i--) {
                $next = $path . '.' . ($i + 1);

                if (is_file($path . '.' . $i . '.gz')) {
                    @rename($path . '.' . $i . '.gz', $next . '.gz');
                }

                $plain = $path . '.' . $i;
                if (is_file($plain)) {
                    if ($compress && !is_file($next . '.gz') && static::gzip($plain, $next . '.gz')) {
                        @unlink($plain);
                    } else {
                        @rename($plain, $next);
                    }
                }
            }

            $perms = @fileperms($path);
            if (!@rename($path, $path . '.1')) {
                return false;
            }

            // Recreate the live file with the old one's permissions, so a web server and a
            // CLI user sharing a group can both keep writing to it.
            if (@touch($path) && $perms !== false) {
                @chmod($path, $perms & 0777);
            }

            return true;
        } catch (Throwable) {
            return false;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Stream-compress a file so a large log never has to fit in memory.
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    protected static function gzip(string $from, string $to): bool
    {
        $in = @fopen($from, 'rb');
        if ($in === false) {
            return false;
        }

        $out = @gzopen($to, 'wb6');
        if ($out === false) {
            fclose($in);

            return false;
        }

        $ok = true;
        while (!feof($in)) {
            $chunk = fread($in, 1048576);
            if ($chunk === false || ($chunk !== '' && gzwrite($out, $chunk) === false)) {
                $ok = false;
                break;
            }
        }

        fclose($in);
        $ok = gzclose($out) && $ok;
        if (!$ok) {
            @unlink($to);
        }

        return $ok;
    }

    /**
     * Rotate every `*.log` in a folder that has reached the size limit, or every non-empty
     * one with `$force`.
     *
     * @param string $dir
     * @param array{max_size: int, max_files: int, compress: bool} $settings
     * @param bool $force
     * @return array{rotated: string[], skipped: string[]} skipped = due but couldn't rotate
     */
    public static function rotateDirectory(string $dir, array $settings, bool $force = false): array
    {
        $result = ['rotated' => [], 'skipped' => []];

        foreach (glob(rtrim($dir, '/') . '/*.log') ?: [] as $path) {
            if (!is_file($path)) {
                continue;
            }

            if ($force) {
                clearstatcache(true, $path);
                if (!@filesize($path)) {
                    continue;
                }
            } elseif (!static::needsRotation($path, $settings['max_size'])) {
                continue;
            }

            $done = static::rotate($path, $settings['max_files'], $settings['compress'], $force ? null : $settings['max_size']);
            $result[$done ? 'rotated' : 'skipped'][] = basename($path);
        }

        return $result;
    }

    /**
     * Register the hourly `log-rotate` job while rotation is on.
     *
     * @param Scheduler $scheduler
     * @return void
     */
    public static function registerJob(Scheduler $scheduler): void
    {
        $settings = static::settings();
        if ($settings === null || !$settings['enabled']) {
            return;
        }

        $job = $scheduler->addFunction(static::class . '::job', [], self::JOB_ID);
        $job->at('0 * * * *');
        $job->output('log://' . self::JOB_ID . '.out');
        $job->backlink('/config/system#errors');
    }

    /**
     * Scheduler entry point; the scheduler keeps what it prints as the job's output.
     *
     * @return void
     */
    public static function job(): void
    {
        $settings = static::settings();
        if ($settings === null || !$settings['enabled']) {
            echo 'Log rotation is turned off';

            return;
        }

        $dir = Grav::instance()['locator']->findResource('log://', true, true);
        if (!$dir || !is_dir($dir)) {
            echo 'No log folder to rotate';

            return;
        }

        $result = static::rotateDirectory($dir, $settings);
        $msg = $result['rotated']
            ? 'Rotated ' . implode(', ', $result['rotated'])
            : 'No logs over ' . Utils::prettySize($settings['max_size']);
        if ($result['skipped']) {
            $msg .= "\nCould not rotate " . implode(', ', $result['skipped']) . ' (another process is rotating, or the file is in use)';
        }

        echo $msg;
    }
}
