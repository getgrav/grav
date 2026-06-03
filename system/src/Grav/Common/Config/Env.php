<?php

/**
 * @package    Grav\Common\Config
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Config;

use Symfony\Component\Dotenv\Dotenv;
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
 */
final class Env
{
    /** @var string Environment variable that selects the per-environment layer. */
    public const ENV_KEY = 'GRAV_ENVIRONMENT';

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

        $base = $root . '/.env';

        // Fast bail-out: nothing to do when no env files exist.
        $env = $_SERVER[self::ENV_KEY] ?? $_ENV[self::ENV_KEY] ?? (getenv(self::ENV_KEY) ?: null);
        if (!is_file($base) && !is_file($base . '.dist') && !is_file($base . '.local')
            && !($env !== null && is_file($base . '.' . $env))) {
            return;
        }

        try {
            // usePutenv(true) is required: Grav reads its bootstrap config via
            // getenv() (defines.php, Setup.php), which Symfony Dotenv does not
            // populate by default.
            $dotenv = (new Dotenv(self::ENV_KEY))->usePutenv(true);

            // 1. Base file: .env, falling back to .env.dist.
            if (is_file($base)) {
                $dotenv->load($base);
            } elseif (is_file($base . '.dist')) {
                $dotenv->load($base . '.dist');
            }

            $env = $_SERVER[self::ENV_KEY] ?? $_ENV[self::ENV_KEY] ?? (getenv(self::ENV_KEY) ?: null);

            // 2. Machine-specific overrides. Skipped under the test environment to
            //    keep test runs reproducible (matches Symfony's convention).
            if ($env !== 'test' && is_file($base . '.local')) {
                $dotenv->load($base . '.local');
                $env = $_SERVER[self::ENV_KEY] ?? $_ENV[self::ENV_KEY] ?? $env;
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
}
