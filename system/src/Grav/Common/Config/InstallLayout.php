<?php

/**
 * @package    Grav\Common\Config
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Config;

/**
 * Where the parts of a Grav install live.
 *
 * By default everything sits in one folder, which is both GRAV_ROOT and GRAV_WEBROOT. In the
 * public webroot layout GRAV_ROOT is a private folder holding system/, vendor/, bin/, cache/,
 * logs/, tmp/, backup/ and .env, and only a subfolder of it (GRAV_WEBROOT, usually `public/`)
 * is served by the web server. That subfolder holds index.php, .htaccess, robots.txt, user/,
 * assets/ and images/.
 *
 * Runs from system/defines.php before most of Grav exists, so it only uses plain PHP.
 */
final class InstallLayout
{
    /** @var string[] Folders tried, in order, as the webroot when GRAV_ROOT has no index.php. */
    public const WEBROOT_CANDIDATES = ['public', 'public_html', 'web', 'www', 'htdocs'];

    /** @var string[] Top-level entries of a Grav package that belong in the webroot rather than in GRAV_ROOT. */
    public const WEBROOT_ENTRIES = ['index.php', '.htaccess', 'robots.txt', 'assets', 'images', 'user'];

    /**
     * Find the webroot of a Grav root that runs the public webroot layout.
     *
     * Returns null for the single-folder layout (GRAV_ROOT has its own index.php) and when no
     * candidate folder holds both an index.php and the user folder.
     *
     * @param string   $root       Absolute path of the Grav root.
     * @param string   $userPath   User folder, relative to the webroot.
     * @param string[] $candidates Folder names to try.
     * @return string|null Absolute path of the webroot.
     */
    public static function detectWebroot(string $root, string $userPath = 'user', array $candidates = self::WEBROOT_CANDIDATES): ?string
    {
        $root = rtrim($root, '/\\');
        if (is_file($root . '/index.php')) {
            return null;
        }

        foreach ($candidates as $name) {
            $dir = $root . '/' . $name;
            if (is_file($dir . '/index.php') && is_dir($dir . '/' . $userPath)) {
                return $dir;
            }
        }

        return null;
    }

    /**
     * True for an absolute path (`/x`, `C:/x` or `C:\x`).
     *
     * @param string $path
     * @return bool
     */
    public static function isAbsolute(string $path): bool
    {
        return (bool) preg_match('`^(/|[a-z]:[\\\/])`ui', $path);
    }

    /**
     * Resolve a path against a base folder. Absolute paths are returned as they are.
     *
     * @param string $base
     * @param string $path
     * @return string
     */
    public static function resolve(string $base, string $path): string
    {
        return static::isAbsolute($path) ? $path : rtrim($base, '/') . '/' . $path;
    }

    /**
     * True when the private Grav root and the public webroot are different folders.
     *
     * @return bool
     */
    public static function isSplit(): bool
    {
        return \defined('GRAV_ROOT') && \defined('GRAV_WEBROOT') && GRAV_ROOT !== GRAV_WEBROOT;
    }

    /**
     * Path of a folder that lives in GRAV_ROOT (system, cache, logs, tmp, backup), as the
     * resource locator needs it.
     *
     * The locator resolves relative paths against GRAV_WEBROOT. That is the same folder as
     * GRAV_ROOT in the single-folder layout, so the path is returned unchanged there. In the
     * public webroot layout a relative path is made absolute against GRAV_ROOT.
     *
     * @param string $path
     * @return string
     */
    public static function rootPath(string $path): string
    {
        return static::isSplit() ? static::resolve(GRAV_ROOT, $path) : $path;
    }
}
