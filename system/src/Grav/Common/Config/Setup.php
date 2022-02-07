<?php

/**
 * @package    Grav\Common\Config
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Config;

use BadMethodCallException;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Data\Data;
use Grav\Common\Utils;
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
     * @param Container|array $container
     */
    public function __construct($container)
    {
        // Configure main streams.
        $abs = str_starts_with(GRAV_SYSTEM_PATH, '/');
        $this->streams['system']['prefixes'][''] = $abs ? ['system', GRAV_SYSTEM_PATH] : ['system'];
        $this->streams['user']['prefixes'][''] = [GRAV_USER_PATH];
        $this->streams['cache']['prefixes'][''] = [GRAV_CACHE_PATH];
        $this->streams['log']['prefixes'][''] = [GRAV_LOG_PATH];
        $this->streams['tmp']['prefixes'][''] = [GRAV_TMP_PATH];
        $this->streams['backup']['prefixes'][''] = [GRAV_BACKUP_PATH];

        // If environment is not set, look for the environment variable and then the constant.
        $environment = static::$environment ??
            (defined('GRAV_ENVIRONMENT') ? GRAV_ENVIRONMENT : (getenv('GRAV_ENVIRONMENT') ?: null));

        // If no environment is set, make sure we get one (CLI or hostname).
        if (null === $environment) {
            if (defined('GRAV_CLI')) {
                $environment = 'cli';
            } else {
                /** @var ServerRequestInterface $request */
                $request = $container['request'];
                $host = $request->getUri()->getHost();

                $environment = Utils::substrToString($host, ':');
            }
        }

        // Resolve server aliases to the proper environment.
        static::$environment = static::$environments[$environment] ?? $environment;

        // Pre-load setup.php which contains our initial configuration.
        // Configuration may contain dynamic parts, which is why we need to always load it.
        // If GRAV_SETUP_PATH has been defined, use it, otherwise use defaults.
        $setupFile = defined('GRAV_SETUP_PATH') ? GRAV_SETUP_PATH : (getenv('GRAV_SETUP_PATH') ?: null);
        if (null !== $setupFile) {
            // Make sure that the custom setup file exists. Terminates the script if not.
            if (!str_starts_with($setupFile, '/')) {
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

        // Add default streams defined in beginning of the class.
        if (!isset($setup['streams']['schemes'])) {
            $setup['streams']['schemes'] = [];
        }
        $setup['streams']['schemes'] += $this->streams;

        // Initialize class.
        parent::__construct($setup);

        $this->def('environment', static::$environment);

        // Figure out path for the current environment.
        $envPath = defined('GRAV_ENVIRONMENT_PATH') ? GRAV_ENVIRONMENT_PATH : (getenv('GRAV_ENVIRONMENT_PATH') ?: null);
        if (null === $envPath) {
            // Find common path for all environments and append current environment into it.
            $envPath = defined('GRAV_ENVIRONMENTS_PATH') ? GRAV_ENVIRONMENTS_PATH : (getenv('GRAV_ENVIRONMENTS_PATH') ?: null);
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
        $this->def('streams.schemes.environment.prefixes', ['' => [$envPath]]);
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
            // If environment is found, remove all missing override locations (B/C compatibility).
            if ($locator->findResource('environment://', true)) {
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
            }

            if (!$locator->findResource('environment://config', true)) {
                // If environment does not have its own directory, remove it from the lookup.
                $prefixes = $this->get('streams.schemes.environment.prefixes');
                $prefixes['config'] = [];

                $this->set('streams.schemes.environment.prefixes', $prefixes);
                $this->initializeLocator($locator);
            }

            // Create security.yaml salt if it doesn't exist into existing configuration environment if possible.
            $securityFile = Utils::basename(static::$securityFile);
            $securityFolder = substr(static::$securityFile, 0, -\strlen($securityFile));
            $securityFolder = $locator->findResource($securityFolder, true) ?: $locator->findResource($securityFolder, true, true);
            $filename = "{$securityFolder}/{$securityFile}";

            $security_file = CompiledYamlFile::instance($filename);
            $security_content = (array)$security_file->content();

            if (!isset($security_content['salt'])) {
                $security_content = array_merge($security_content, ['salt' => Utils::generateRandomString(14)]);
                $security_file->content($security_content);
                $security_file->save();
                $security_file->free();
            }
        } catch (RuntimeException $e) {
            throw new RuntimeException(sprintf('Grav failed to initialize: %s', $e->getMessage()), 500, $e);
        }
    }
}
