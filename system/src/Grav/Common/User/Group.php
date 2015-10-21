<?php
namespace Grav\Common\User;

use Grav\Common\Data\Blueprints;
use Grav\Common\Data\Data;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\GravTrait;

/**
 * Group object
 *
 * @property mixed authenticated
 * @property mixed password
 * @property bool|string hashed_password
 * @author RocketTheme
 * @license MIT
 */
class Group extends Data
{
    use GravTrait;

    /**
     * Get the groups list
     *
     * @return array
     */
    private static function groups()
    {
        $groups = self::getGrav()['config']->get('site.groups');
        return $groups;
    }

    /**
     * Checks if a group exists
     *
     * @return object
     */
    public static function group_exists($groupname)
    {
        return isset(self::groups()[$groupname]);
    }

    /**
     * Get a group by name
     *
     * @return object
     */
    public static function load($groupname)
    {
        if (self::group_exists($groupname)) {
            $content = self::groups()[$groupname];
        } else {
            $content = [];
        }

        $blueprints = new Blueprints('blueprints://');
        $blueprint = $blueprints->get('user/group');
        if (!isset($content['groupname'])) {
            $content['groupname'] = $groupname;
        }
        $group = new Group($content, $blueprint);

        return $group;
    }

    /**
     * Get value of an array using dot notation
     */
    private function resolve(array $array, $path, $default = null)
    {
        $current = $array;
        $p = strtok($path, '.');

        while ($p !== false) {
            if (!isset($current[$p])) {
                return $default;
            }
            $current = $current[$p];
            $p = strtok('.');
        }

        return $current;
    }

    /**
     * Save a group
     */
    public function save()
    {
        $blueprints = new Blueprints('blueprints://');
        $blueprint = $blueprints->get('user/group');

        $fields = $blueprint->fields();

        self::getGrav()['config']->set("site.groups.$this->groupname", []);

        foreach($fields as $field) {
            if ($field['type'] == 'text') {
                $value = $field['name'];
                if (isset($this->items[$value])) {
                    self::getGrav()['config']->set("site.groups.$this->groupname.$value", $this->items[$value]);
                }
            }
            if ($field['type'] == 'array') {
                $value = $field['name'];
                $arrayValues = $this->resolve($this->items, $field['name']);

                if ($arrayValues) foreach($arrayValues as $arrayIndex => $arrayValue) {
                    self::getGrav()['config']->set("site.groups.$this->groupname.$value.$arrayIndex", $arrayValue);
                }
            }
        }

        $type = 'site';
        $blueprints = $this->blueprints("config/{$type}");
        $obj = new Data(self::getGrav()['config']->get('site'), $blueprints);
        $file = CompiledYamlFile::instance(self::getGrav()['locator']->findResource("config://{$type}.yaml"));
        $obj->file($file);
        $obj->save();
    }
}
