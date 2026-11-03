<?php

/**
 * @package    Grav\Common\File
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\File;

/**
 * Writer lock and atomic replace for compiled cache files (cache/compiled), shared by
 * CompiledFile and CompiledBase.
 *
 * Compiled files are include()d by every request without a lock. Writers lock a separate
 * `<file>.lock` next to the compiled file, never the compiled file itself: on Windows a
 * flock() is a mandatory lock, so locking the compiled file would make every request that
 * includes it fail for as long as the rebuild takes. The lock is only a mutex that keeps
 * parallel requests from all doing the same work.
 *
 * @internal
 */
final class CompiledFileLock
{
    /**
     * Take the writer lock for a compiled file.
     *
     * Like the toolbox file lock, a filesystem without lock support counts as locked: only
     * another process holding the lock stops the write.
     *
     * @param string $filename Compiled file.
     * @param bool $block Wait for the lock instead of giving up when another process holds it.
     * @return resource|false|null The lock handle, false when another process holds the lock,
     *                             null when the lock file can't be opened (a write will most likely fail too).
     */
    public static function acquire(string $filename, bool $block = false)
    {
        $dir = \dirname($filename);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $handle = @fopen($filename . '.lock', 'c');
        if (!$handle) {
            return null;
        }

        $wouldBlock = 0;
        if (!flock($handle, $block ? LOCK_EX : LOCK_EX | LOCK_NB, $wouldBlock) && $wouldBlock) {
            fclose($handle);

            return false;
        }

        return $handle;
    }

    /**
     * Release a lock taken with acquire().
     *
     * @param resource|null $handle
     */
    public static function release($handle): void
    {
        if (\is_resource($handle)) {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * Tell whether another process holds the writer lock for a compiled file right now.
     *
     * On a filesystem without lock support this can't tell and answers no.
     */
    public static function isHeld(string $filename): bool
    {
        $handle = @fopen($filename . '.lock', 'r');
        if (!$handle) {
            return false;
        }

        try {
            $wouldBlock = 0;
            $acquired = flock($handle, LOCK_SH | LOCK_NB, $wouldBlock);
            if ($acquired) {
                flock($handle, LOCK_UN);
            }

            return !$acquired && $wouldBlock === 1;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Rename a fully written temporary file over the compiled file.
     *
     * Windows refuses to replace a file while another request has it open for include(),
     * which lasts a few milliseconds, so there the rename is tried again for up to 100 ms.
     */
    public static function replace(string $tmp, string $filename): bool
    {
        for ($attempt = 0; ; $attempt++) {
            if (@rename($tmp, $filename)) {
                return true;
            }
            if (\DIRECTORY_SEPARATOR === '/' || $attempt >= 20 || !is_file($tmp)) {
                return false;
            }
            usleep(5000);
        }
    }

    /**
     * Include a compiled file.
     *
     * Returns false when the file can't be opened. On Windows a file that is being replaced
     * can't be opened for a moment, so there the include is tried again for up to 50 ms.
     * Errors thrown by the file itself (a ParseError from a broken file) reach the caller.
     *
     * @return mixed What the file returns, or false.
     */
    public static function load(string $filename)
    {
        for ($attempt = 0; ; $attempt++) {
            $result = @include $filename;
            if ($result !== false || \DIRECTORY_SEPARATOR === '/' || $attempt >= 10 || !is_file($filename)) {
                return $result;
            }
            usleep(5000);
        }
    }
}
