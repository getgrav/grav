<?php
namespace Grav\Common;

use Grav\Common\Filesystem\File;

/**
 * The Themes object holds an array of all the theme objects that Grav knows about.
 *
 * @author RocketTheme
 * @license MIT
 */
class Themes
{
    /**
     * @var Grav
     */
    protected $grav;

    public function __construct(Grav $grav)
    {
        $this->grav = $grav;
    }

    /**
     * Return list of all theme data with their blueprints.
     *
     * @return array|Data\Data[]
     */
    public function all()
    {
        $list = array();
        $iterator = new \DirectoryIterator(THEMES_DIR);

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
     * @return Data\Data
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
        $thumb = THEMES_DIR . "{$name}/thumbnail.jpg";
        if (file_exists($thumb)) {
            // TODO: use real URL with base path.
            $blueprint->set('thumbnail', "/user/themes/{$name}/thumbnail.jpg");
        }

        // Load default configuration.
        $file = File\Yaml::instance("theme://{$name}.yaml");
        $obj = new Data\Data($file->content(), $blueprint);

        // Override with user configuration.
        $file = File\Yaml::instance("user://config/themes/{$name}.yaml");
        $obj->merge($file->content());

        // Save configuration always to user/config.
        $obj->file($file);

        return $obj;
    }

    public function load($name = null)
    {
        /** @var Config $config */
        $config = $this->grav['config'];

        if (!$name) {
            $name = $config->get('system.pages.theme');
        }

        $file = THEMES_DIR . "{$name}/{$name}.php";
        if (file_exists($file)) {
            $class = require_once $file;

            if (!is_object($class)) {
                $className = '\\Grav\\Theme\\' . ucfirst($name);

                if (class_exists($className)) {
                    $class = new $className($this->grav, $config, $name);
                }
            }
        }

        if (empty($class)) {
            $class = new Theme($this->grav, $config, $name);
        }

        return $class;
    }
}
