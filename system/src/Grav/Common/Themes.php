<?php
namespace Grav\Common;

use Grav\Common\Filesystem\File;
use Grav\Component\Filesystem\ResourceLocator;

/**
 * The Themes object holds an array of all the theme objects that Grav knows about.
 *
 * @author RocketTheme
 * @license MIT
 */
class Themes extends Iterator
{
    protected $grav;
    protected $config;

    public function __construct(Grav $grav) {
        $this->grav = $grav;
        $this->config = $grav['config'];
    }

    /**
     * Return list of all theme data with their blueprints.
     *
     * @return array|Data[]
     */
    public function all()
    {
        $list = array();
        $iterator = new \DirectoryIterator('theme:///');

        /** @var \DirectoryIterator $directory */
        foreach ($iterator as $directory) {
            if (!$directory->isDir() || $directory->isDot()) {
                continue;
            }

            $type = $directory->getBasename();
            $list[$type] = self::get($type);
        }

        ksort($list);

        return $list;
    }

    /**
     * Get theme or throw exception if it cannot be found.
     *
     * @param string $name
     * @return Data
     * @throws \RuntimeException
     */
    public function get($name)
    {
        if (!$name) {
            throw new \RuntimeException('Theme name not provided.');
        }

        $blueprints = new Data\Blueprints("theme://{$name}");
        $blueprint = $blueprints->get('blueprints');
        $blueprint->name = $name;

        // Find thumbnail.
        $thumb = "theme:///{$name}/thumbnail.jpg";

        if (file_exists($thumb)) {
            $blueprint->set('thumbnail', $this->config->get('system.base_url_relative') . "/user/themes/{$name}/thumbnail.jpg");
        }

        // Load default configuration.
        $file = File\Yaml::instance("theme://{$name}/{$name}" . YAML_EXT);
        $obj = new Data\Data($file->content(), $blueprint);

        // Override with user configuration.
        $file = File\Yaml::instance("user://config/themes/{$name}" . YAML_EXT);
        $obj->merge($file->content());

        // Save configuration always to user/config.
        $obj->file($file);

        return $obj;
    }

    public function current($name = null)
    {

        if (!$name) {
            $name = $this->config->get('system.pages.theme');
        }

        return $name;
    }

    function load($name = null)
    {
        $name = $this->current($name);
        $grav = $this->grav;

        /** @var ResourceLocator $locator */
        $locator = $grav['locator'];

        $file = $locator("theme://theme.php") ?: $locator("theme://{$name}.php");
        if ($file) {
            // Local variables available in the file: $grav, $config, $name, $path, $file
            $class = include $file;

            if (!is_object($class)) {
                $className = '\\Grav\\Theme\\' . ucfirst($name);

                if (class_exists($className)) {
                    $class = new $className($grav, $this->config, $name);
                }
            }
        }

        if (empty($class)) {
            $class = new Theme($grav, $this->config, $name);
        }

        return $class;
    }

    public function configure($name = null) {
        $name = $this->current($name);

        /** @var Config $config */
        $config = $this->config;

        $themeConfig = File\Yaml::instance("theme://{$name}/{$name}" . YAML_EXT)->content();

        $config->merge(['themes' => [$name => $themeConfig]]);

        /** @var ResourceLocator $locator */
        $locator = $this->grav['locator'];

        // TODO: move
        $registered = stream_get_wrappers();
        $schemes = $config->get(
            "themes.{$name}.streams.schemes",
            ['theme' => ['paths' => ["user/themes/{$name}"]]]
        );

        foreach ($schemes as $scheme => $config) {
            if (isset($config['paths'])) {
                $locator->addPath($scheme, '', $config['paths']);
            }
            if (isset($config['prefixes'])) {
                foreach ($config['prefixes'] as $prefix => $paths) {
                    $locator->addPath($scheme, $prefix, $paths);
                }
            }

            if (in_array($scheme, $registered)) {
                stream_wrapper_unregister($scheme);
            }
            $type = !empty($config['type']) ? $config['type'] : 'ReadOnlyStream';
            if ($type[0] != '\\') {
                $type = '\\Grav\\Component\\Filesystem\\StreamWrapper\\' . $type;
            }

            if (!stream_wrapper_register($scheme, $type)) {
                throw new \InvalidArgumentException("Stream '{$type}' could not be initialized.");
            }

        }
    }
}
