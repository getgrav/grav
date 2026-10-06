<?php

/**
 * @package    Grav\Common\Config
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Config;

use Symfony\Component\Dotenv\Dotenv;
use function is_dir;
use function is_file;

/**
 * Native dotenv support.
 *
 * Loads environment variables from `.env` file(s) at the Grav root very early in
 * the bootstrap (from system/defines.php), before any GRAV_* constant is defined.
 * Once populated they feed every existing Grav environment mechanism: the path
 * constants in defines.php, the environment/multisite selection in Setup.php, and
 * the GRAV_CONFIG__* config overrides in InitializeProcessor.
 *
 * The layering follows Symfony's documented precedence, with one deliberate
 * difference: it never forces a default environment. Grav derives the environment
 * from the request hostname when GRAV_ENVIRONMENT is not set, so forcing a default
 * (as Symfony's Dotenv::loadEnv() does) would silently break multisite setups.
 *
 * Files are loaded in this order (each layer overrides values set by earlier
 * dotenv layers, but real server-set environment variables always win):
 *
 *   1. .env  (or .env.dist when .env is absent)
 *   2. .env.local
 *   3. .env.$GRAV_ENVIRONMENT          (only when an environment is set)
 *   4. .env.$GRAV_ENVIRONMENT.local    (only when an environment is set)
 *
 * By default the files are read from the Grav root. Set the real (server-set)
 * `GRAV_ENV_PATH` environment variable to a directory (or to a specific base
 * file) to load them from outside the web root instead, which keeps secrets
 * such as API keys out of a publicly served docroot.
 */
final class Env
{
    /** @var string Environment variable that selects the per-environment layer. */
    public const ENV_KEY = 'GRAV_ENVIRONMENT';

    /**
     * @var string Optional environment variable pointing at the `.env` location,
     *             either a directory holding it or a specific base file path, so
     *             the file can live outside the web root.
     */
    public const ENV_PATH_KEY = 'GRAV_ENV_PATH';

    /**
     * Read a variable from wherever the SAPI or the dotenv loader put it.
     *
     * Checks $_SERVER, then $_ENV, then getenv(), and returns the first value
     * that is a non-empty string, or null. An empty entry is skipped rather than
     * returned, so a present-but-empty $_SERVER value (an unset nginx variable
     * used in a `fastcgi_param`, an empty `SetEnv`) cannot shadow a working
     * getenv(). Same rule as Setup::envVar(). system/defines.php carries its own
     * copy of this lookup because it runs before, and must not depend on, any
     * class. (#4344)
     *
     * @param string $name
     * @return string|null
     */
    public static function get(string $name): ?string
    {
        foreach ([$_SERVER[$name] ?? null, $_ENV[$name] ?? null, getenv($name)] as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Load .env file(s) from the given root directory into the environment.
     *
     * A no-op when no env files are present, so sites that don't use a .env pay
     * nothing but a couple of stat() calls. Any parse error is swallowed and
     * logged rather than thrown, because this runs before Grav's error handling
     * is in place and a malformed file must not white-screen the site.
     *
     * @param string $root Absolute path to the Grav root (no trailing slash).
     */
    public static function load(string $root): void
    {
        if (!class_exists(Dotenv::class)) {
            return;
        }

        $base = self::resolveBase($root);

        // Fast bail-out: nothing to do when no env files exist.
        $env = self::get(self::ENV_KEY);
        if (!is_file($base) && !is_file($base . '.dist') && !is_file($base . '.local')
            && !($env !== null && is_file($base . '.' . $env))) {
            return;
        }

        try {
            // putenv() keeps getenv() in step for code outside Grav, but hosts that
            // list it in disable_functions (Cloudways' web SAPI) would make Dotenv
            // throw halfway through the first file, after $_ENV/$_SERVER are filled
            // and before any later layer loads. Grav's own bootstrap reads go
            // through get(), which checks $_SERVER and $_ENV first, so skipping
            // putenv() there loses nothing. (#4344)
            $dotenv = (new Dotenv(self::ENV_KEY))->usePutenv(function_exists('putenv'));

            // 1. Base file: .env, falling back to .env.dist.
            if (is_file($base)) {
                $dotenv->load($base);
            } elseif (is_file($base . '.dist')) {
                $dotenv->load($base . '.dist');
            }

            $env = self::get(self::ENV_KEY);

            // 2. Machine-specific overrides. Skipped under the test environment to
            //    keep test runs reproducible (matches Symfony's convention).
            if ($env !== 'test' && is_file($base . '.local')) {
                $dotenv->load($base . '.local');
                $env = self::get(self::ENV_KEY) ?? $env;
            }

            // 3 & 4. Per-environment layers, only when an environment is set.
            if ($env !== null && $env !== '' && $env !== 'local') {
                if (is_file($base . '.' . $env)) {
                    $dotenv->load($base . '.' . $env);
                }
                if (is_file($base . '.' . $env . '.local')) {
                    $dotenv->load($base . '.' . $env . '.local');
                }
            }
        } catch (\Throwable $e) {
            error_log('Grav .env loading failed: ' . $e->getMessage());
        }
    }

    /**
     * Resolve the base `.env` path whose layered variants are loaded.
     *
     * Defaults to `<root>/.env`. A real (server-set) `GRAV_ENV_PATH` overrides
     * it: a directory is treated as the folder holding the `.env` (and its
     * `.local`/per-environment layers), while any other value is used verbatim
     * as the base file path. Lets the file live outside the web root.
     *
     * @param string $root Absolute path to the Grav root (no trailing slash).
     * @return string Absolute base path (the `.env` file or its base name).
     */
    private static function resolveBase(string $root): string
    {
        $override = self::get(self::ENV_PATH_KEY);
        if ($override !== null) {
            $override = rtrim(str_replace('\\', '/', $override), '/');

            return is_dir($override) ? $override . '/.env' : $override;
        }

        return $root . '/.env';
    }
}
