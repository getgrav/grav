<?php

/**
 * @package    Grav\Common\Config
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Config;

use BadMethodCallException;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Data\Data;
use InvalidArgumentException;
use Pimple\Container;
use Psr\Http\Message\ServerRequestInterface;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RuntimeException;
use function defined;
use function is_array;

/**
 * Class Setup
 * @package Grav\Common\Config
 */
class Setup extends Data
{
    /**
     * @var array Environment aliases normalized to lower case.
     */
    public static $environments = [
        '' => 'unknown',
        '127.0.0.1' => 'localhost',
        '::1' => 'localhost'
    ];

    /**
     * @var string|null Current environment normalized to lower case.
     */
    public static $environment;

    /** @var string */
    public static $securityFile = 'config://security.yaml';

    /** @var bool True if the environment name is just the host name sent by the client. */
    private $hostEnvironment = false;

    /** @var array */
    protected $streams = [
        'user' => [
            'type' => 'ReadOnlyStream',
            'force' => true,
            'prefixes' => [
                '' => [] // Set in constructor
            ]
        ],
        'cache' => [
            'type' => 'Stream',
            'force' => true,
            'prefixes' => [
                '' => [], // Set in constructor
                'images' => ['images']
            ]
        ],
        'log' => [
            'type' => 'Stream',
            'force' => true,
            'prefixes' => [
                '' => [] // Set in constructor
            ]
        ],
        'tmp' => [
            'type' => 'Stream',
            'force' => true,
            'prefixes' => [
                '' => [] // Set in constructor
            ]
        ],
        'backup' => [
            'type' => 'Stream',
            'force' => true,
            'prefixes' => [
                '' => [] // Set in constructor
            ]
        ],
        'environment' => [
            'type' => 'ReadOnlyStream'
            // If not defined, environment will be set up in the constructor.
        ],
        'system' => [
            'type' => 'ReadOnlyStream',
            'prefixes' => [
                '' => ['system'],
            ]
        ],
        'asset' => [
            'type' => 'Stream',
            'prefixes' => [
                '' => ['assets'],
            ]
        ],
        'blueprints' => [
            'type' => 'ReadOnlyStream',
            'prefixes' => [
                '' => ['environment://blueprints', 'user://blueprints', 'system://blueprints'],
            ]
        ],
        'config' => [
            'type' => 'ReadOnlyStream',
            'prefixes' => [
                '' => ['environment://config', 'user://config', 'system://config'],
            ]
        ],
        'plugins' => [
            'type' => 'ReadOnlyStream',
            'prefixes' => [
                '' => ['user://plugins'],
             ]
        ],
        'plugin' => [
            'type' => 'ReadOnlyStream',
            'prefixes' => [
                '' => ['user://plugins'],
            ]
        ],
        'themes' => [
            'type' => 'ReadOnlyStream',
            'prefixes' => [
                '' => ['user://themes'],
            ]
        ],
        'languages' => [
            'type' => 'ReadOnlyStream',
            'prefixes' => [
                '' => ['environment://languages', 'user://languages', 'system://languages'],
            ]
        ],
        'image' => [
            'type' => 'Stream',
            'prefixes' => [
                '' => ['user://images', 'system://images']
            ]
        ],
        'media' => [
            'type' => 'Stream',
            'prefixes' => [
                '' => ['environment://media', 'user://media']
            ]
        ],
        'page' => [
            'type' => 'ReadOnlyStream',
            'prefixes' => [
                '' => ['user://pages']
            ]
        ],
        'user-data' => [
            'type' => 'Stream',
            'force' => true,
            'prefixes' => [
                '' => ['user://data']
            ]
        ],
        'account' => [
            'type' => 'ReadOnlyStream',
            'prefixes' => [
                '' => ['user://accounts']
            ]
        ],
    ];

    /**
     * Read a bootstrap variable from wherever the SAPI put it.
     *
     * $_SERVER is the only source PHP guarantees for server-set variables, but
     * a present-but-empty entry (an unset nginx variable used in a
     * `fastcgi_param`, an empty `SetEnv`) must not shadow a working getenv(),
     * or the fix breaks the hosts where the old code worked. Mirrors Env.php
     * and Uri::ip(). (#4279)
     *
     * @param string $name
     * @return string|null
     */
    private static function envVar(string $name): ?string
    {
        foreach ([$_SERVER[$name] ?? null, $_ENV[$name] ?? null, getenv($name)] as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param Container|array $container
     */
    public function __construct($container)
    {
        // Configure main streams. The locator resolves relative paths against GRAV_WEBROOT, while
        // system, cache, logs, tmp and backup live in GRAV_ROOT. Those are the same folder in the
        // single-folder layout; in the public webroot layout rootPath() makes them absolute.
        $system = InstallLayout::rootPath(GRAV_SYSTEM_PATH);
        $abs = InstallLayout::isAbsolute($system);
        $this->streams['system']['prefixes'][''] = $abs ? ['system', $system] : ['system'];
        $this->streams['user']['prefixes'][''] = [GRAV_USER_PATH];
        $this->streams['cache']['prefixes'][''] = [InstallLayout::rootPath(GRAV_CACHE_PATH)];
        $this->streams['log']['prefixes'][''] = [InstallLayout::rootPath(GRAV_LOG_PATH)];
        $this->streams['tmp']['prefixes'][''] = [InstallLayout::rootPath(GRAV_TMP_PATH)];
        $this->streams['backup']['prefixes'][''] = [InstallLayout::rootPath(GRAV_BACKUP_PATH)];

        // If environment is not set, look for the environment variable and then the constant.
        $environment = static::$environment ??
            (defined('GRAV_ENVIRONMENT') ? GRAV_ENVIRONMENT : static::envVar('GRAV_ENVIRONMENT'));

        // If no environment is set, make sure we get one (CLI or hostname).
        if (null === $environment) {
            if (defined('GRAV_CLI')) {
                $request = null;
                $uri = null;
                $environment = 'cli';
            } else {
                /** @var ServerRequestInterface $request */
                $request = $container['request'];
                $uri = $request->getUri();
                $environment = $uri->getHost();
                $this->hostEnvironment = true;
            }
        }

        // Resolve server aliases to the proper environment.
        static::$environment = static::$environments[$environment] ?? $environment;

        // Pre-load setup.php which contains our initial configuration.
        // Configuration may contain dynamic parts, which is why we need to always load it.
        // If GRAV_SETUP_PATH has been defined, use it, otherwise use defaults.
        $setupFile = defined('GRAV_SETUP_PATH') ? GRAV_SETUP_PATH : static::envVar('GRAV_SETUP_PATH');
        if (null !== $setupFile) {
            // Make sure that the custom setup file exists. Terminates the script if not.
            if (!str_starts_with((string) $setupFile, '/')) {
                $setupFile = GRAV_WEBROOT . '/' . $setupFile;
            }
            if (!is_file($setupFile)) {
                echo 'GRAV_SETUP_PATH is defined but does not point to existing setup file.';
                exit(1);
            }
        } else {
            $setupFile = GRAV_WEBROOT . '/setup.php';
            if (!is_file($setupFile)) {
                $setupFile = GRAV_WEBROOT . '/' . GRAV_USER_PATH . '/setup.php';
            }
            if (!is_file($setupFile)) {
                $setupFile = null;
            }
        }
        $setup = $setupFile ? (array) include $setupFile : [];
        if (isset($setup['environment'])) {
            $this->hostEnvironment = false;
        }

        // Add default streams defined in beginning of the class.
        if (!isset($setup['streams']['schemes'])) {
            $setup['streams']['schemes'] = [];
        }
        $setup['streams']['schemes'] += $this->streams;

        // Initialize class.
        parent::__construct($setup);

        $this->def('environment', static::$environment);

        // Figure out path for the current environment.
        $validName = true;
        $envPath = defined('GRAV_ENVIRONMENT_PATH') ? GRAV_ENVIRONMENT_PATH : static::envVar('GRAV_ENVIRONMENT_PATH');
        if (null === $envPath) {
            // The name usually comes from the `Host` header and is joined into a
            // path below, so it has to be a single, non-hidden folder name.
            $name = (string) $this->get('environment');
            $validName = $name !== '' && $name[0] !== '.' && strpbrk($name, '/\\') === false;

            // Find common path for all environments and append current environment into it.
            $envPath = defined('GRAV_ENVIRONMENTS_PATH') ? GRAV_ENVIRONMENTS_PATH : static::envVar('GRAV_ENVIRONMENTS_PATH');
            if (null !== $envPath) {
                $envPath .= '/';
            } else {
                // Use default location. Start with Grav 1.7 default.
                $envPath = GRAV_WEBROOT. '/' . GRAV_USER_PATH . '/env';
                if (is_dir($envPath)) {
                    $envPath = 'user://env/';
                } else {
                    // Fallback to Grav 1.6 default.
                    $envPath = 'user://';
                }
            }
            $envPath .= $this->get('environment');
        }

        // Set up environment.
        $this->def('environment', static::$environment);

        // Refuse to register a non-existent per-host env folder as a writable
        // stream target. A multi-site config save that reaches the server with
        // a hostname variant the operator never set up (e.g. bare host vs.
        // `www.`, mismatched proxy headers) would otherwise materialize a
        // brand-new `user/env/<host>/` directory and divert writes there.
        // The dir must already exist on disk to be a valid env target — same
        // invariant the Grav 1.7 admin enforced via `findResource()`.
        //
        // Existing is not enough. In the Grav 1.6 layout the env folders sit
        // next to `user/pages`, `user/data`, `user/media`... and the name comes
        // from the `Host` header, so `Host: pages` would otherwise turn the
        // content folder into the environment and load whatever a publisher
        // put under `user/pages/config/` on top of the site configuration.
        $envAbsolute = $this->resolveEnvAbsolutePath($envPath);
        if (!$validName || ($envAbsolute !== null && (!is_dir($envAbsolute) || $this->isStreamFolder($envAbsolute)))) {
            $this->def('streams.schemes.environment.prefixes', ['' => []]);
        } else {
            $this->def('streams.schemes.environment.prefixes', ['' => [$envPath]]);
        }
    }

    /**
     * Whether an existing env folder is, or lies inside, a folder that another
     * stream already serves from `user://` (pages, data, accounts, config,
     * plugins, themes, media, images... and whatever setup.php adds). Such a
     * folder has a job of its own and must never double as an environment.
     */
    private function isStreamFolder(string $folder): bool
    {
        $realFolder = realpath($folder);
        if ($realFolder === false) {
            return false;
        }
        $realFolder .= DIRECTORY_SEPARATOR;

        foreach ((array) $this->get('streams.schemes', []) as $config) {
            $prefixes = (array) ($config['prefixes'] ?? []);
            $prefixes[] = $config['paths'] ?? [];
            foreach ($prefixes as $paths) {
                foreach ((array) $paths as $path) {
                    // `user://` alone is the parent of every env folder in the Grav 1.6 layout.
                    if (!is_string($path) || !str_starts_with($path, 'user://') || trim(substr($path, 7), '/') === '') {
                        continue;
                    }
                    $realRoot = realpath((string) $this->resolveEnvAbsolutePath($path));
                    if ($realRoot === false) {
                        continue;
                    }
                    // The trailing separator matters: `user/pages` must not match `user/pages.example.com`.
                    $realRoot .= DIRECTORY_SEPARATOR;
                    if (strncasecmp($realFolder, $realRoot, strlen($realRoot)) === 0) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Resolve a configured env path to an absolute filesystem path for an
     * is_dir() check. Returns null when the path uses a stream other than
     * `user://` (custom GRAV_ENVIRONMENT_PATH) — those are trusted as-is and
     * cleaned up later by check() if missing.
     */
    private function resolveEnvAbsolutePath(string $envPath): ?string
    {
        if (str_starts_with($envPath, 'user://')) {
            return GRAV_WEBROOT . '/' . GRAV_USER_PATH . '/' . substr($envPath, 7);
        }
        if (str_starts_with($envPath, '/')) {
            return $envPath;
        }
        return null;
    }

    /**
     * @return $this
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function init()
    {
        $locator = new UniformResourceLocator(GRAV_WEBROOT);
        $files = [];

        $guard = 5;
        do {
            $check = $files;
            $this->initializeLocator($locator);
            $files = $locator->findResources('config://streams.yaml');

            if ($check === $files) {
                break;
            }

            // Update streams.
            foreach (array_reverse($files) as $path) {
                $file = CompiledYamlFile::instance($path);
                $content = (array)$file->content();
                if (!empty($content['schemes'])) {
                    $this->items['streams']['schemes'] = $content['schemes'] + $this->items['streams']['schemes'];
                }
            }
        } while (--$guard);

        if (!$guard) {
            throw new RuntimeException('Setup: Configuration reload loop detected!');
        }

        // Make sure we have valid setup.
        $this->check($locator);

        return $this;
    }

    /**
     * Initialize resource locator by using the configuration.
     *
     * @param UniformResourceLocator $locator
     * @return void
     * @throws BadMethodCallException
     */
    public function initializeLocator(UniformResourceLocator $locator)
    {
        $locator->reset();

        $schemes = (array) $this->get('streams.schemes', []);

        foreach ($schemes as $scheme => $config) {
            if (isset($config['paths'])) {
                $locator->addPath($scheme, '', $config['paths']);
            }

            $override = $config['override'] ?? false;
            $force = $config['force'] ?? false;

            if (isset($config['prefixes'])) {
                foreach ((array)$config['prefixes'] as $prefix => $paths) {
                    $locator->addPath($scheme, $prefix, $paths, $override, $force);
                }
            }
        }
    }

    /**
     * Get available streams and their types from the configuration.
     *
     * @return array
     */
    public function getStreams()
    {
        $schemes = [];
        foreach ((array) $this->get('streams.schemes') as $scheme => $config) {
            $type = $config['type'] ?? 'ReadOnlyStream';
            if ($type[0] !== '\\') {
                $type = '\\RocketTheme\\Toolbox\\StreamWrapper\\' . $type;
            }

            $schemes[$scheme] = $type;
        }

        return $schemes;
    }

    /**
     * @param UniformResourceLocator $locator
     * @return void
     * @throws InvalidArgumentException
     * @throws BadMethodCallException
     * @throws RuntimeException
     */
    protected function check(UniformResourceLocator $locator)
    {
        $streams = $this->items['streams']['schemes'] ?? null;
        if (!is_array($streams)) {
            throw new InvalidArgumentException('Configuration is missing streams.schemes!');
        }
        $diff = array_keys(array_diff_key($this->streams, $streams));
        if ($diff) {
            throw new InvalidArgumentException(
                sprintf('Configuration is missing keys %s from streams.schemes!', implode(', ', $diff))
            );
        }

        try {
            // Strip missing override locations from environment://. Runs even when the env
            // dir itself does not exist on disk, otherwise the stale prefix lingers and a
            // later write (e.g. config save under a hostname variant) materializes the dir.
            $force = $this->get('streams.schemes.environment.force', false);
            if (!$force) {
                $prefixes = $this->get('streams.schemes.environment.prefixes.');
                $update = false;
                foreach ($prefixes as $i => $prefix) {
                    if ($locator->isStream($prefix)) {
                        if ($locator->findResource($prefix, true)) {
                            break;
                        }
                    } elseif (file_exists($prefix)) {
                        break;
                    }

                    unset($prefixes[$i]);
                    $update = true;
                }

                if ($update) {
                    $this->set('streams.schemes.environment.prefixes', ['' => array_values($prefixes)]);
                    $this->initializeLocator($locator);
                }
            }

            // Folderless hosts share compiled caches for the same stream configuration.
            // Keep the original host in Setup::$environment for environment folder lookups.
            if ($this->hostEnvironment && !$this->get('streams.schemes.environment.prefixes.')) {
                $this->set('environment', '@' . substr(md5(json_encode($this->get('streams.schemes'))), 0, 8));
            }

            if (!$locator->findResource('environment://config', true)) {
                // If environment does not have its own directory, remove it from the lookup.
                $prefixes = $this->get('streams.schemes.environment.prefixes');
                $prefixes['config'] = [];

                $this->set('streams.schemes.environment.prefixes', $prefixes);
                $this->initializeLocator($locator);
            }

            // Guarantee the core `log` stream always resolves to a writable path.
            // It is consumed during early bootstrap by Monolog's StreamHandler,
            // before Grav can trap and report errors. A user override that layers
            // `log://` solely on top of `environment://` (which is registered with
            // an empty prefix list whenever the per-host env folder is absent, see
            // the constructor) leaves `log://` resolving to nothing; handing that
            // `false` to StreamHandler throws and takes the whole request down
            // before it can even boot. Restore the built-in default location as a
            // forced fallback so logging degrades gracefully to `logs/` instead.
            // This never materializes the env folder, so the #4086 protection is
            // untouched: `environment://` stays empty and env-layered writes still
            // fall through to the shared user paths.
            if (!$locator->findResource('log://grav.log', true, true)) {
                $prefixes = (array) $this->get('streams.schemes.log.prefixes.', []);
                $prefixes[''] = array_merge($prefixes[''] ?? [], [InstallLayout::rootPath(GRAV_LOG_PATH)]);

                $this->set('streams.schemes.log.prefixes', $prefixes);
                // Force the fallback so it survives even when `logs/` does not yet
                // exist on disk (addPath() otherwise filters missing plain paths).
                // StreamHandler creates the file and its parent dir on first write.
                $this->set('streams.schemes.log.force', true);
                $this->initializeLocator($locator);
            }

            // Legacy `security.salt` auto-gen was removed in v2.0 (GHSA-3f29-pqwf-v4j4);
            // Security::getNonceKey() now manages the equivalent value in a private
            // PHP file outside the Config tree so sandboxed Twig cannot read it.
        } catch (RuntimeException $e) {
            throw new RuntimeException(sprintf('Grav failed to initialize: %s', $e->getMessage()), 500, $e);
        }
    }
}
