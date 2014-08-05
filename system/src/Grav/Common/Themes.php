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
     * Return list of all theme data with their blueprints.
     *
     * @return array|Data\Data[]
     */
    static public function all()
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
     * @param string $type
     * @return Data\Data
     * @throws \RuntimeException
     */
    static public function get($type)
    {
        if (!$type) {
            throw new \RuntimeException('Theme name not provided.');
        }

        $blueprints = new Data\Blueprints(THEMES_DIR . $type);
        $blueprint = $blueprints->get('blueprints');
        $blueprint->name = $type;

        // Find thumbnail.
        $thumb = THEMES_DIR . "{$type}/thumbnail.jpg";
        if (file_exists($thumb)) {
            // TODO: use real URL with base path.
            $blueprint->set('thumbnail', "/user/themes/{$type}/thumbnail.jpg");
        }

        // Load default configuration.
        $file = File\Yaml::instance(THEMES_DIR . "{$type}/{$type}" . YAML_EXT);
        $obj = new Data\Data($file->content(), $blueprint);

        // Override with user configuration.
        $file = File\Yaml::instance(USER_DIR . "config/themes/{$type}" . YAML_EXT);
        $obj->merge($file->content());

        // Save configuration always to user/config.
        $obj->file($file);

        return $obj;
    }

    public function load($name = null)
    {
        if (!$name) {
            $config = Registry::get('Config');
            $name = $config->get('system.pages.theme');
        }

        $file = THEMES_DIR . "{$name}/{$name}.php";
        if (file_exists($file)) {
            $class = require_once $file;

            if (!is_object($class)) {
                $className = '\\Grav\\Theme\\' . ucfirst($name);

                if (class_exists($className)) {
                    $class = new $className;
                }
            }
        }

        if (empty($class)) {
            $class = new Theme;
        }

        return $class;
    }
}
