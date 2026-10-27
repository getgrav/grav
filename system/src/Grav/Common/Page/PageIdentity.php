<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page;

use Grav\Common\Grav;
use RuntimeException;
use InvalidArgumentException;
use function count;
use function is_string;

/**
 * Stable identity for a page folder.
 *
 * A page folder can carry a hidden `.page-id` file holding a UUID v4. The file
 * travels with the folder however it is renamed or moved (Admin, reorder, git,
 * FTP), so anything keyed on the id survives what breaks a path or a route.
 * All translations in one folder share the id.
 *
 * Grav skips hidden files when it scans pages (`system.pages.ignore_hidden`)
 * and when it builds a page's media, and `Folder::copy()` leaves them behind,
 * so the file never shows on the site and a copy made through Grav starts out
 * without one.
 *
 * Page ids are opt-in. While `system.pages.uuid` is false (the default) Grav
 * never writes a `.page-id`, doesn't index them and `Pages::findByUuid()`
 * returns null; `$page->uuid()` still reads a file that is there. A site owner
 * can turn the option on in `system.yaml`, and a plugin that needs ids turns
 * it on for every request with one call made in `onPluginsInitialized`,
 * before the pages are initialized:
 *
 *     public function onPluginsInitialized(): void
 *     {
 *         PageIdentity::enable();
 *     }
 *
 * With ids on, saving or creating a page through Grav gives its folder an id
 * when it has none, and a copy gets a new one. A plugin may also call
 * ensure() itself, whatever the option says.
 *
 * Duplicates: a folder copied outside Grav (`cp -R`, a file manager) brings
 * its `.page-id` along. When two folders claim one id, the folder whose
 * `.page-id` file is older keeps it (a copy gets a fresh modification time,
 * the original keeps its own); on a tie the folder whose path sorts first
 * keeps it. A caller that records where it last saw an id can pass that path
 * to ensure() and resolve(); the folder at the recorded path then keeps the
 * id. ensure() on any other folder holding it gives that folder a new id.
 */
final class PageIdentity
{
    /** Name of the file holding a page folder's id. */
    public const FILE = '.page-id';

    /** @var array<string, string[]>|null Ids found by the last filesystem scan, kept up to date as ids are written. */
    private static $scanned;

    /** @var string|null The pages folders the last scan searched. */
    private static $scannedRoots;

    /** @var array<string, true> Lock files this process holds (see locked()). */
    private static $locks = [];

    /**
     * Whether page ids are on (`system.pages.uuid`).
     */
    public static function enabled(): bool
    {
        $grav = Grav::instance();

        return isset($grav['config']) && (bool) $grav['config']->get('system.pages.uuid', false);
    }

    /**
     * Turn page ids on for this request. Call it in `onPluginsInitialized`,
     * before the pages are initialized, so the pages index records the ids.
     */
    public static function enable(): void
    {
        Grav::instance()['config']->set('system.pages.uuid', true);
    }

    /**
     * A new random UUID v4.
     */
    public static function generate(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-'
            . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
    }

    /**
     * Whether a value is a UUID (any version, either case).
     *
     * @param mixed $uuid
     */
    public static function isValid($uuid): bool
    {
        return is_string($uuid)
            && (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/i', $uuid);
    }

    /**
     * The id stored in a folder, lower case, or null when it has none or the
     * file doesn't hold a UUID. A trailing newline is tolerated.
     */
    public static function read(string $folder): ?string
    {
        $file = self::normalize($folder) . '/' . self::FILE;
        if (!is_file($file)) {
            return null;
        }

        $uuid = strtolower(trim((string) @file_get_contents($file, false, null, 0, 128)));

        return self::isValid($uuid) ? $uuid : null;
    }

    /**
     * Write an id into a folder, replacing any it had.
     *
     * The file is written beside its final name and renamed into place, so a
     * reader never sees half a file and the folder's modification time
     * changes.
     *
     * Grav's own saves tell the pages cache about the change. A caller that
     * writes ids outside a Grav save (a CLI command, a migration, a plugin
     * task) must do the same once it is done, or the pages index keeps
     * answering findByUuid() without the new ids until the change check
     * notices: call `$grav['pages']->markChanged()` (a site that never checks
     * for changes, `system.cache.check.method: none`, otherwise keeps the old
     * index until the cache is cleared).
     */
    public static function write(string $folder, string $uuid): void
    {
        if (!self::isValid($uuid)) {
            throw new InvalidArgumentException('Invalid page id: ' . $uuid);
        }

        $folder = self::normalize($folder);
        if (!is_dir($folder)) {
            throw new RuntimeException('Cannot write a page id into a missing folder: ' . $folder);
        }

        $target = $folder . '/' . self::FILE;
        $temp = $folder . '/' . self::FILE . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($temp, strtolower($uuid), LOCK_EX) === false || !@rename($temp, $target)) {
            @unlink($temp);
            throw new RuntimeException('Unable to write ' . self::FILE . ' in ' . $folder);
        }

        self::rememberScanned($folder, strtolower($uuid));
    }

    /**
     * Give a folder a new id, replacing any it had.
     */
    public static function assign(string $folder): string
    {
        $uuid = self::generate();
        self::write($folder, $uuid);

        return $uuid;
    }

    /**
     * Remove a folder's id. Returns true when there was one.
     */
    public static function remove(string $folder): bool
    {
        $file = self::normalize($folder) . '/' . self::FILE;
        if (!is_file($file)) {
            return false;
        }

        $removed = @unlink($file);
        if ($removed) {
            self::rememberScanned(self::normalize($folder), null);
        }

        return $removed;
    }

    /**
     * The folder's id, assigning one when it has none or when another folder
     * has the better claim to the one it holds (see the class description).
     *
     * Works whatever `system.pages.uuid` says.
     *
     * @param string      $folder       Absolute path of the page folder.
     * @param string|null $recordedPath Where the caller last saw this id, as an
     *                                  absolute path or relative to the pages folder.
     */
    public static function ensure(string $folder, ?string $recordedPath = null): string
    {
        $folder = self::normalize($folder);

        // Two requests saving the same folder at once must not each give it
        // a different id: the second waits, then reads what the first wrote.
        return self::locked($folder, static function () use ($folder, $recordedPath): string {
            clearstatcache(true, $folder . '/' . self::FILE);
            $uuid = self::read($folder);

            if ($uuid === null || self::isDuplicate($folder, $uuid, $recordedPath)) {
                return self::assign($folder);
            }

            return $uuid;
        });
    }

    /**
     * Run $callback holding the lock for a folder's id. Locks are held per
     * process: a callback that ensures the same folder again doesn't wait on
     * itself.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function locked(string $folder, callable $callback)
    {
        $file = self::lockFile($folder);
        if (isset(self::$locks[$file])) {
            return $callback();
        }

        $handle = @fopen($file, 'c');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            // No lock to be had (a read-only temp folder): carry on unlocked,
            // as before locking existed.
            if ($handle !== false) {
                fclose($handle);
            }

            return $callback();
        }

        self::$locks[$file] = true;
        try {
            return $callback();
        } finally {
            unset(self::$locks[$file]);
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * The lock file guarding a folder's id. It lives in the system temp
     * folder, never in the page folder, and Grav's cache clearing never
     * removes it while it is held.
     *
     * @internal
     */
    public static function lockFile(string $folder): string
    {
        return rtrim(sys_get_temp_dir(), '/\\') . '/grav-page-id-' . md5(self::normalize($folder)) . '.lock';
    }

    /**
     * Copy the `.page-id` files of a folder tree onto the same subfolders of
     * another tree, leaving every other hidden file alone. For a copy that
     * stands in for a move (Folder::move() across filesystems), so the moved
     * pages keep their ids; a real copy must get new ones (onCopied()).
     *
     * @return int How many ids were copied.
     */
    public static function copyIds(string $source, string $target): int
    {
        $source = self::normalize($source);
        $target = self::normalize($target);
        if (!is_dir($source) || !is_dir($target)) {
            return 0;
        }

        $copied = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $file) {
            if ($file->getFilename() !== self::FILE || !$file->isFile()) {
                continue;
            }
            $folder = self::normalize($file->getPath());
            $relative = substr($folder, strlen($source));
            $uuid = self::read($folder);
            if ($uuid !== null && is_dir($target . $relative)) {
                try {
                    self::write($target . $relative, $uuid);
                } catch (\Throwable $e) {
                    self::log('Unable to carry the page id of ' . $folder . ' over: ' . $e->getMessage());
                    continue;
                }
                // Keep the file's age: the older file keeps a duplicated id.
                @touch($target . $relative . '/' . self::FILE, (int) $file->getMTime());
                $copied++;
            }
        }

        return $copied;
    }

    /**
     * Whether another folder has the better claim to the id this folder holds.
     *
     * Without a recorded path the pages index answers when the pages are
     * initialized with ids on. An index built with ids that doesn't know the
     * id is taken at its word (the folder got its id after the index was
     * built, so no indexed folder shares it) instead of searching every pages
     * folder on each request. Only without such an index (ids off, the pages
     * not initialized, the CLI) are the pages folders searched, once per
     * request: the result is kept and updated as ids are written.
     */
    public static function isDuplicate(string $folder, ?string $uuid = null, ?string $recordedPath = null): bool
    {
        $folder = self::normalize($folder);
        $uuid = $uuid ?? self::read($folder);
        if ($uuid === null) {
            return false;
        }

        if ($recordedPath !== null && $recordedPath !== '') {
            $recorded = self::absolute($recordedPath);
            if ($recorded === $folder) {
                return false;
            }

            return self::read($recorded) === $uuid;
        }

        $pages = self::indexedPages();
        if ($pages !== null) {
            $keeper = $pages->uuidPath($uuid);

            return $keeper !== null && self::normalize($keeper) !== $folder;
        }

        $holders = self::find($uuid);

        return count($holders) > 1 && self::keeper($holders) !== $folder;
    }

    /**
     * The folder holding an id right now, or null when none does.
     *
     * The recorded path is tried first, then the pages index, and when
     * neither holds the id (the folder was renamed or moved outside Grav
     * since) the pages folders are searched.
     *
     * @param string      $uuid
     * @param string|null $recordedPath Absolute, or relative to the pages folder.
     */
    public static function resolve(string $uuid, ?string $recordedPath = null): ?string
    {
        if (!self::isValid($uuid)) {
            return null;
        }
        $uuid = strtolower($uuid);

        if ($recordedPath !== null && $recordedPath !== '') {
            $recorded = self::absolute($recordedPath);
            if (self::read($recorded) === $uuid) {
                return $recorded;
            }
        }

        $indexed = self::indexedPath($uuid);
        if ($indexed !== null && self::read($indexed) === $uuid) {
            return $indexed;
        }

        $holders = self::find($uuid, true);

        return $holders ? self::keeper($holders) : null;
    }

    /**
     * Every folder in the pages folders that holds an id, by searching them.
     *
     * @return string[] Absolute folder paths.
     */
    public static function find(string $uuid, bool $refresh = false): array
    {
        return self::scan($refresh)[strtolower($uuid)] ?? [];
    }

    /**
     * Every id in the pages folders and the folders holding it, the folder
     * that keeps a duplicated id listed first.
     *
     * @return array<string, string[]>
     */
    public static function scan(bool $refresh = false): array
    {
        $roots = self::roots();
        $signature = implode('|', $roots);
        if (self::$scanned !== null && !$refresh && self::$scannedRoots === $signature) {
            return self::$scanned;
        }

        $map = [];
        foreach ($roots as $root) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->getFilename() !== self::FILE || !$file->isFile()) {
                    continue;
                }
                $folder = self::normalize($file->getPath());
                $uuid = self::read($folder);
                if ($uuid !== null) {
                    $map[$uuid][] = $folder;
                }
            }
        }

        foreach ($map as $uuid => $folders) {
            if (count($folders) > 1) {
                $keeper = self::keeper($folders);
                $others = array_values(array_diff($folders, [$keeper]));
                sort($others);
                $map[$uuid] = array_merge([$keeper], $others);
            }
        }

        self::$scannedRoots = $signature;

        return self::$scanned = $map;
    }

    /**
     * Give every page folder an id: folders without one get a new id, and of
     * folders sharing one, all but the keeper get a new id.
     *
     * Works whatever `system.pages.uuid` says. With $dryRun nothing is written
     * and the report says what would change.
     *
     * @return array{folders: int, existing: int, assigned: string[], duplicates: array<string, array{keeper: string, others: string[]}>}
     */
    public static function assignAll(bool $dryRun = false): array
    {
        $folders = self::pageFolders();
        $scan = self::scan(true);

        $duplicates = [];
        foreach ($scan as $uuid => $holders) {
            if (count($holders) > 1) {
                $duplicates[$uuid] = ['keeper' => $holders[0], 'others' => array_slice($holders, 1)];
            }
        }

        $assigned = [];
        $existing = 0;
        foreach ($folders as $folder) {
            $uuid = self::read($folder);
            if ($uuid !== null && !(isset($duplicates[$uuid]) && $duplicates[$uuid]['keeper'] !== $folder)) {
                $existing++;
                continue;
            }
            if ($uuid === null) {
                $assigned[] = $folder;
            }
            if (!$dryRun) {
                self::assign($folder);
            }
        }

        // Duplicated ids in folders that hold no page file still get fixed.
        if (!$dryRun) {
            foreach ($duplicates as $duplicate) {
                foreach ($duplicate['others'] as $other) {
                    if (!in_array($other, $folders, true)) {
                        self::assign($other);
                    }
                }
            }
        }

        return ['folders' => count($folders), 'existing' => $existing, 'assigned' => $assigned, 'duplicates' => $duplicates];
    }

    /**
     * Every folder in the pages folders that holds a page file (`.md`),
     * leaving out hidden folders and `system.pages.ignore_folders`.
     *
     * @return string[]
     */
    public static function pageFolders(): array
    {
        $ignore = array_flip((array) Grav::instance()['config']->get('system.pages.ignore_folders', []));
        $folders = [];
        $walk = static function (string $dir) use (&$walk, &$folders, $ignore): void {
            $hasPage = false;
            $subdirs = [];
            foreach (new \FilesystemIterator($dir, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS) as $entry) {
                $name = $entry->getFilename();
                if ($name === '' || $name[0] === '.') {
                    continue;
                }
                if ($entry->isDir()) {
                    if (!isset($ignore[$name])) {
                        $subdirs[] = $entry->getPathname();
                    }
                } elseif (!$hasPage && strtolower($entry->getExtension()) === 'md') {
                    $hasPage = true;
                }
            }
            if ($hasPage) {
                $folders[] = self::normalize($dir);
            }
            sort($subdirs);
            foreach ($subdirs as $subdir) {
                $walk($subdir);
            }
        };

        foreach (self::roots() as $root) {
            foreach (new \FilesystemIterator($root, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS) as $entry) {
                $name = $entry->getFilename();
                if ($entry->isDir() && $name[0] !== '.' && !isset($ignore[$name])) {
                    $walk($entry->getPathname());
                }
            }
        }

        sort($folders);

        return $folders;
    }

    /**
     * Of several folders claiming one id, the one that keeps it: the oldest
     * `.page-id` file, then the path that sorts first.
     *
     * @param string[] $folders
     */
    public static function keeper(array $folders): string
    {
        $folders = array_map([self::class, 'normalize'], $folders);
        usort($folders, static function (string $a, string $b): int {
            $ma = @filemtime($a . '/' . self::FILE);
            $mb = @filemtime($b . '/' . self::FILE);
            $ma = $ma === false ? PHP_INT_MAX : $ma;
            $mb = $mb === false ? PHP_INT_MAX : $mb;

            return $ma <=> $mb ?: strcmp($a, $b);
        });

        return $folders[0];
    }

    /**
     * Called by Grav after a page is saved or created: gives its folder an id
     * when ids are on. Returns the id, or null when ids are off.
     *
     * @internal
     */
    public static function onSaved(?string $folder): ?string
    {
        if (!$folder || !self::enabled() || !self::isPageFolder($folder)) {
            return null;
        }

        try {
            return self::ensure($folder);
        } catch (\Throwable $e) {
            self::log('Unable to give ' . $folder . ' a page id: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Called after a page folder was copied: the copy gets a new id when ids
     * are on, and never keeps the original's.
     *
     * @internal
     */
    public static function onCopied(?string $folder): ?string
    {
        if (!$folder || !is_dir($folder)) {
            return null;
        }

        try {
            if (self::enabled() && self::isPageFolder($folder)) {
                return self::assign($folder);
            }
            self::remove($folder);
        } catch (\Throwable $e) {
            self::log('Unable to reset the page id of ' . $folder . ': ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Absolute paths of the pages folders (`system.pages.dirs`).
     *
     * @return string[]
     */
    public static function roots(): array
    {
        $grav = Grav::instance();
        $locator = $grav['locator'];
        $roots = [];
        foreach ((array) $grav['config']->get('system.pages.dirs', ['page://']) as $dir) {
            $path = $locator->findResource($dir, true);
            if (is_string($path) && is_dir($path)) {
                $path = self::normalize($path);
                if (!in_array($path, $roots, true)) {
                    $roots[] = $path;
                }
            }
        }

        return $roots;
    }

    /**
     * A folder's path relative to the pages folder holding it ('' for the
     * pages folder itself). A path outside the pages folders comes back as is.
     */
    public static function relative(string $folder): string
    {
        $folder = self::normalize($folder);
        foreach (self::roots() as $root) {
            if ($folder === $root) {
                return '';
            }
            if (strpos($folder, $root . '/') === 0) {
                return substr($folder, strlen($root) + 1);
            }
        }

        return $folder;
    }

    /**
     * An absolute folder path for a path relative to the main pages folder;
     * an absolute path comes back normalized.
     */
    public static function absolute(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        if ($path !== '' && ($path[0] === '/' || preg_match('#^[a-zA-Z]:/#', $path))) {
            return self::normalize($path);
        }

        $relative = trim($path, '/');
        foreach (explode('/', $relative) as $segment) {
            if ($segment === '..' || $segment === '.') {
                throw new InvalidArgumentException('Invalid page path: ' . $path);
            }
        }

        $root = self::roots()[0] ?? null;
        if ($root === null) {
            throw new RuntimeException('No pages folder found');
        }

        return $relative === '' ? $root : $root . '/' . $relative;
    }

    /**
     * Whether a folder is inside a pages folder (and isn't one itself).
     */
    public static function isPageFolder(string $folder): bool
    {
        $folder = self::normalize($folder);
        if (!is_dir($folder)) {
            return false;
        }
        foreach (self::roots() as $root) {
            if (strpos($folder, $root . '/') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * The folder the pages index holds for an id, when the pages are
     * initialized with ids on.
     */
    private static function indexedPath(string $uuid): ?string
    {
        $pages = self::indexedPages();
        $path = $pages !== null ? $pages->uuidPath($uuid) : null;

        return $path !== null ? self::normalize($path) : null;
    }

    /**
     * The pages, when they are initialized with an index of ids.
     */
    private static function indexedPages(): ?Pages
    {
        $grav = Grav::instance();
        $pages = isset($grav['pages']) ? $grav['pages'] : null;

        return $pages instanceof Pages && $pages->hasUuidIndex() ? $pages : null;
    }

    /**
     * Keep the last scan in step with an id written or removed in this
     * request, instead of throwing it away and searching every pages folder
     * again on the next lookup.
     */
    private static function rememberScanned(string $folder, ?string $uuid): void
    {
        if (self::$scanned === null || self::$scannedRoots !== implode('|', self::roots())) {
            self::$scanned = null;

            return;
        }

        foreach (self::$scanned as $id => $folders) {
            $kept = array_values(array_diff($folders, [$folder]));
            if ($kept) {
                self::$scanned[$id] = $kept;
            } else {
                unset(self::$scanned[$id]);
            }
        }
        if ($uuid !== null && self::isPageFolder($folder)) {
            $holders = self::$scanned[$uuid] ?? [];
            $holders[] = $folder;
            if (count($holders) > 1) {
                $keeper = self::keeper($holders);
                $others = array_values(array_diff($holders, [$keeper]));
                sort($others);
                $holders = array_merge([$keeper], $others);
            }
            self::$scanned[$uuid] = $holders;
        }
    }

    private static function normalize(string $folder): string
    {
        return rtrim(str_replace('\\', '/', $folder), '/');
    }

    private static function log(string $message): void
    {
        $grav = Grav::instance();
        if (isset($grav['log'])) {
            $grav['log']->warning('[PageIdentity] ' . $message);
        }
    }
}
