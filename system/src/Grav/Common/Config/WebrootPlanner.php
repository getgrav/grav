<?php

/**
 * @package    Grav\Common\Config
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Config;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Works out the plan for turning a single-folder site into the public webroot layout
 * (split) and back (join). Only looks at the filesystem; WebrootPlan::apply() does the work.
 */
final class WebrootPlanner
{
    /**
     * @var string[] Top-level entries that belong in the private folder. A split leaves them where
     * they are and never moves them into the webroot, even when asked to.
     */
    public const PRIVATE_ENTRIES = [
        'system', 'vendor', 'bin', 'cache', 'logs', 'tmp', 'backup', 'webserver-configs',
        'composer.json', 'composer.lock', '.env', '.env.example', '.dependencies',
        'CHANGELOG.md', 'README.md', 'LICENSE.txt', 'CODE_OF_CONDUCT.md', 'CONTRIBUTING.md', 'SECURITY.md', 'now.json',
    ];

    /** @var string Maintenance flag, never moved or listed. */
    private const FLAG = '.upgrading';

    /**
     * Plan the move from the single-folder layout to the public webroot layout.
     *
     * @param string   $root     The Grav folder, currently also the webroot.
     * @param string   $dir      Name of the webroot folder to create. Must be one of
     *                           InstallLayout::WEBROOT_CANDIDATES, or the CLI can't find it afterwards.
     * @param string[] $extra    More top-level names to move into the webroot.
     * @param string   $userPath The user folder (GRAV_USER_PATH), when it is a plain top-level name.
     * @return WebrootPlan
     */
    public static function split(string $root, string $dir = 'public', array $extra = [], string $userPath = 'user'): WebrootPlan
    {
        $root = self::canonical($root);
        $webroot = $root . '/' . $dir;
        $plan = new WebrootPlan(WebrootPlan::SPLIT, $root, $webroot);

        if (!\in_array($dir, InstallLayout::WEBROOT_CANDIDATES, true)) {
            $plan->errors[] = sprintf('--dir must be one of %s, the folder names Grav looks for. Any other name would need GRAV_WEBROOT set in .env.', implode(', ', InstallLayout::WEBROOT_CANDIDATES));
        }
        if (!is_file($root . '/index.php')) {
            $plan->errors[] = sprintf('%s has no index.php, so it is not a single-folder site. Is it already split? Run bin/grav webroot:status.', $root);
        }
        if (file_exists($webroot) || is_link($webroot)) {
            if (!is_dir($webroot) || is_link($webroot)) {
                $plan->errors[] = sprintf('%s already exists and is not a folder.', $webroot);
            } elseif (self::entries($webroot)) {
                $plan->errors[] = sprintf('%s already exists and is not empty.', $webroot);
            }
        }

        $names = InstallLayout::WEBROOT_ENTRIES;
        if ($userPath !== 'user' && $userPath !== '' && strpbrk($userPath, '/\\') === false) {
            $names[] = $userPath;
        }
        foreach ($extra as $name) {
            $name = trim((string) $name, '/');
            if ($name === '' || $name === '.' || $name === '..' || strpbrk($name, '/\\') !== false) {
                $plan->errors[] = sprintf('--move takes a top-level name of the site folder, not "%s".', $name);
            } elseif (\in_array($name, self::PRIVATE_ENTRIES, true) || $name === $dir || $name === self::FLAG) {
                $plan->errors[] = sprintf('%s belongs in the private folder, so --move won\'t make it public.', $name);
            } elseif (!file_exists($root . '/' . $name) && !is_link($root . '/' . $name)) {
                $plan->errors[] = sprintf('--move: %s does not exist in %s.', $name, $root);
            } else {
                $names[] = $name;
            }
        }

        $names = array_values(array_unique($names));
        foreach (self::entries($root) as $name) {
            if (\in_array($name, $names, true)) {
                $plan->moves[$root . '/' . $name] = $webroot . '/' . $name;
            } elseif ($name !== $dir && !self::isPrivate($name)) {
                $plan->unknown[] = $name;
            }
        }
        $plan->moves = self::indexLast($plan->moves);

        if (!isset($plan->moves[$root . '/' . $userPath])) {
            $plan->errors[] = sprintf('%s has no %s/ folder to move.', $root, $userPath);
        }

        $plan->links = self::linkRewrites($plan->moves);
        $plan->publisher = new WebrootPublisher($root . '/system', $webroot . '/system');

        return $plan;
    }

    /**
     * Plan the move from the public webroot layout back to the single-folder layout.
     *
     * @param string $root    The private Grav folder.
     * @param string $webroot The webroot to empty into it.
     * @return WebrootPlan
     */
    public static function join(string $root, string $webroot): WebrootPlan
    {
        $root = self::canonical($root);
        $webroot = self::canonical($webroot);
        $plan = new WebrootPlan(WebrootPlan::JOIN, $root, $webroot);

        if ($root === $webroot) {
            $plan->errors[] = 'This site already uses the single-folder layout.';

            return $plan;
        }
        if (!is_dir($webroot)) {
            $plan->errors[] = sprintf('Webroot %s not found.', $webroot);

            return $plan;
        }

        $publisher = new WebrootPublisher($root . '/system', $webroot . '/system');
        foreach (self::entries($webroot) as $name) {
            $from = $webroot . '/' . $name;
            if ($name === self::FLAG) {
                continue;
            }
            if ($name === 'system') {
                // The published copy is removed, not moved. Anything else in it is the user's.
                $extra = is_dir($from) && !is_link($from) ? $publisher->plan()['extra'] : ['(not a folder)'];
                if ($extra) {
                    $plan->errors[] = sprintf('%s holds files that webroot:sync did not publish (%s). Move them out first.', $from, implode(', ', \array_slice($extra, 0, 5)) . (\count($extra) > 5 ? ', ...' : ''));
                }
                $plan->publisher = $publisher;
                continue;
            }
            if (file_exists($root . '/' . $name) || is_link($root . '/' . $name)) {
                $plan->errors[] = sprintf('%s is in both %s and %s. Remove or rename one of them first.', $name, $root, $webroot);
                continue;
            }
            $plan->moves[$from] = $root . '/' . $name;
        }
        $plan->moves = self::indexLast($plan->moves);

        $plan->links = self::linkRewrites($plan->moves);

        return $plan;
    }

    /**
     * Relative symlinks inside the moved entries that would point somewhere else after the move.
     *
     * Each link's target is worked out from where the link is now. When that target moves too
     * (it is inside one of the moved entries) it is followed to its new place. The rewritten
     * link stays relative and points at the same file or folder as before.
     *
     * @param array<string,string> $moves Absolute path => new absolute path.
     * @return array<string,array{from:string,to:string}> By the link's path after the move.
     */
    public static function linkRewrites(array $moves): array
    {
        $rewrites = [];
        foreach ($moves as $from => $to) {
            foreach (self::linksUnder($from) as $link) {
                $target = readlink($link);
                if ($target === false || $target === '' || InstallLayout::isAbsolute($target)) {
                    continue;
                }

                $resolved = self::normalize(\dirname($link) . '/' . $target);
                $newLink = $to . substr($link, \strlen($from));
                $newTarget = self::relative(\dirname($newLink), self::mapPath($resolved, $moves));
                if ($newTarget !== $target) {
                    $rewrites[$newLink] = ['from' => $target, 'to' => $newTarget];
                }
            }
        }

        return $rewrites;
    }

    /**
     * Relative symlinks under a folder whose target is outside it, with whether they work.
     *
     * @param string $dir
     * @return array<string,array{target:string,broken:bool}>
     */
    public static function outsideLinks(string $dir): array
    {
        $dir = self::canonical($dir);
        $links = [];
        foreach (self::linksUnder($dir) as $link) {
            $target = readlink($link);
            if ($target === false || InstallLayout::isAbsolute($target)) {
                continue;
            }
            $resolved = self::normalize(\dirname($link) . '/' . $target);
            if ($resolved !== $dir && !str_starts_with($resolved, $dir . '/')) {
                $links[$link] = ['target' => $target, 'broken' => !file_exists($link)];
            }
        }

        return $links;
    }

    /**
     * Relative path from a folder to a path. Both absolute and normalized.
     */
    public static function relative(string $fromDir, string $to): string
    {
        $from = array_values(array_filter(explode('/', $fromDir), 'strlen'));
        $target = array_values(array_filter(explode('/', $to), 'strlen'));

        $common = 0;
        $max = min(\count($from), \count($target));
        while ($common < $max && $from[$common] === $target[$common]) {
            $common++;
        }

        $parts = array_merge(array_fill(0, \count($from) - $common, '..'), \array_slice($target, $common));

        return $parts ? implode('/', $parts) : '.';
    }

    /**
     * Resolve `.` and `..` in an absolute path without touching the filesystem.
     */
    public static function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $prefix = '';
        if (preg_match('#^([a-z]:)#i', $path, $match)) {
            $prefix = $match[1];
            $path = substr($path, 2);
        }

        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
            } else {
                $parts[] = $part;
            }
        }

        return $prefix . '/' . implode('/', $parts);
    }

    /**
     * True for a top-level entry that stays in the private folder without being listed.
     */
    public static function isPrivate(string $name): bool
    {
        if (\in_array($name, self::PRIVATE_ENTRIES, true) || $name === self::FLAG || $name === '.DS_Store') {
            return true;
        }

        // Environment files, git metadata and similar.
        return (bool) preg_match('#^\.(env|git|reeve)#', $name);
    }

    private static function mapPath(string $path, array $moves): string
    {
        foreach ($moves as $from => $to) {
            if ($path === $from || str_starts_with($path, $from . '/')) {
                return $to . substr($path, \strlen($from));
            }
        }

        return $path;
    }

    /**
     * Symlinks at or below a path. Symlinked folders are listed, never followed.
     *
     * @return string[]
     */
    private static function linksUnder(string $path): array
    {
        if (is_link($path)) {
            return [$path];
        }
        if (!is_dir($path)) {
            return [];
        }

        $links = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS),
            RecursiveIteratorIterator::SELF_FIRST,
            RecursiveIteratorIterator::CATCH_GET_CHILD
        );
        foreach ($iterator as $file) {
            if ($file->isLink()) {
                $links[] = str_replace('\\', '/', $file->getPathname());
            }
        }
        sort($links);

        return $links;
    }

    /**
     * @return string[] Names in a folder, sorted.
     */
    private static function entries(string $dir): array
    {
        $names = array_values(array_diff(scandir($dir) ?: [], ['.', '..']));
        sort($names);

        return $names;
    }

    /**
     * index.php last, so the old docroot keeps showing the maintenance page until the end.
     */
    private static function indexLast(array $moves): array
    {
        foreach ($moves as $from => $to) {
            if (basename($from) === 'index.php') {
                unset($moves[$from]);
                $moves[$from] = $to;
            }
        }

        return $moves;
    }

    private static function canonical(string $path): string
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        $real = realpath($path);

        return $real !== false ? str_replace('\\', '/', $real) : $path;
    }
}
