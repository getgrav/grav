<?php
namespace Grav\Common\Config;

use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Data\Data;
use Grav\Common\Utils;
use RocketTheme\Toolbox\File\YamlFile;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * The Config class contains configuration information.
 *
 * @author RocketTheme
 * @license MIT
 */
class Setup extends Data
{
    protected $streams = [
        'system' => [
            'type' => 'ReadOnlyStream',
            'prefixes' => [
                '' => ['system'],
            ]
        ],
        'user' => [
            'type' => 'ReadOnlyStream',
            'prefixes' => [
                '' => ['user'],
            ]
        ],
        'environment' => [
            'type' => 'ReadOnlyStream'
            // If not defined, environment will be set up in the constructor.
        ],
        'asset' => [
            'type' => 'ReadOnlyStream',
            'prefixes' => [
                '' => ['assets'],
            ]
        ],
        'blueprints' => [
            'type' => 'ReadOnlyStream',
            'prefixes' => [
                '' => ['environment://blueprints', 'user://blueprints', 'system/blueprints'],
            ]
        ],
        'config' => [
            'type' => 'ReadOnlyStream',
            'prefixes' => [
                '' => ['environment://config', 'user://config', 'system/config'],
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
                '' => ['environment://languages', 'user://languages', 'system/languages'],
            ]
        ],
        'cache' => [
            'type' => 'Stream',
            'prefixes' => [
                '' => ['cache'],
                'images' => ['images']
            ]
        ],
        'log' => [
            'type' => 'Stream',
            'prefixes' => [
                '' => ['logs']
            ]
        ],
        'backup' => [
            'type' => 'Stream',
            'prefixes' => [
                '' => ['backup']
            ]
        ],
        'image' => [
            'type' => 'ReadOnlyStream',
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
        'account' => [
            'type' => 'ReadOnlyStream',
            'prefixes' => [
                '' => ['user://accounts']
            ]
        ],
    ];

    public function __construct($container)
    {
        $environment = $container['uri']->environment();
        if (!$environment) {
            $environment = 'localhost';
        }

        // Pre-load setup.php which contains our initial configuration.
        // Configuration may contain dynamic parts, which is why we need to always load it.
        $file = GRAV_ROOT . '/setup.php';
        $setup = is_file($file) ? (array) include $file : [];

        // Add default streams defined in beginning of the class.
        if (!isset($setup['streams']['schemes'])) {
            $setup['streams']['schemes'] = [];
        }
        $setup['streams']['schemes'] += $this->streams;

        // Initialize class.
        parent::__construct($setup);

        // Set up environment.
        $this->def('environment', $environment);
        $this->def('streams.schemes.environment.prefixes', ['' => ["user://{$this->environment}"]]);
    }

    /**
     * @return $this
     */
    public function init()
    {
        $locator = new UniformResourceLocator(GRAV_ROOT);
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
                $content = $file->content();
                if (!empty($content['schemes'])) {
                    $this->items['streams']['schemes'] = $content['schemes'] + $this->items['streams']['schemes'];
                }
            }
        } while (--$guard);

        if (!$guard) {
            throw new \RuntimeException('Setup: Configuration reload loop detected!');
        }

        // Make sure we have valid setup.
        $this->check($locator);

        return $this;
    }

    /**
     * Initialize resource locator by using the configuration.
     *
     * @param UniformResourceLocator $locator
     */
    public function initializeLocator(UniformResourceLocator $locator)
    {
        $locator->reset();

        $schemes = (array) $this->get('streams.schemes', []);

        foreach ($schemes as $scheme => $config) {
            if (isset($config['paths'])) {
                $locator->addPath($scheme, '', $config['paths']);
            }
            if (isset($config['prefixes'])) {
                foreach ($config['prefixes'] as $prefix => $paths) {
                    $locator->addPath($scheme, $prefix, $paths);
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
            $type = !empty($config['type']) ? $config['type'] : 'ReadOnlyStream';
            if ($type[0] != '\\') {
                $type = '\\RocketTheme\\Toolbox\\StreamWrapper\\' . $type;
            }

            $schemes[$scheme] = $type;
        }

        return $schemes;
    }

    /**
     * @param UniformResourceLocator $locator
     * @throws \InvalidArgumentException
     */
    protected function check(UniformResourceLocator $locator)
    {
        $streams = isset($this->items['streams']['schemes']) ? $this->items['streams']['schemes'] : null;
        if (!is_array($streams)) {
            throw new \InvalidArgumentException('Configuration is missing streams.schemes!');
        }
        $diff = array_keys(array_diff_key($this->streams, $streams));
        if ($diff) {
            throw new \InvalidArgumentException(
                sprintf('Configuration is missing keys %s from streams.schemes!', implode(', ', $diff))
            );
        }

        if (!$locator->findResource('environment://config', true)) {
            // If environment does not have its own directory, remove it from the lookup.
            $this->set('streams.schemes.environment.prefixes', ['config' => []]);
            $this->initializeLocator($locator);
        }

        // Create security.yaml if it doesn't exist.
        $filename = $locator->findResource('config://security.yaml', true, true);
        $file = YamlFile::instance($filename);
        if (!$file->exists()) {
            $file->save(['salt' => Utils::generateRandomString(14)]);
            $file->free();
        }
    }
}
