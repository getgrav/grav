<?php
namespace Grav\Common\User;

use Grav\Common\Data\Blueprints;
use Grav\Common\Data\Data;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use Grav\Common\Utils;

/**
 * Group object
 *
 * @author  RocketTheme
 * @license MIT
 */
class Group extends Data
{
    /**
     * Get the groups list
     *
     * @return array
     */
    private static function groups()
    {
        $groups = Grav::instance()['config']->get('groups');

        return $groups;
    }

    /**
     * Checks if a group exists
     *
     * @param string $groupname
     *
     * @return object
     */
    public static function groupExists($groupname)
    {
        return isset(self::groups()[$groupname]);
    }

    /**
     * Get a group by name
     *
     * @param string $groupname
     *
     * @return object
     */
    public static function load($groupname)
    {
        if (self::groupExists($groupname)) {
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
     * Save a group
     */
    public function save()
    {
        $blueprints = new Blueprints('blueprints://');
        $blueprint = $blueprints->get('user/group');

        $fields = $blueprint->fields();

        Grav::instance()['config']->set("groups.$this->groupname", []);

        foreach ($fields as $field) {
            if ($field['type'] == 'text') {
                $value = $field['name'];
                if (isset($this->items[$value])) {
                    Grav::instance()['config']->set("groups.$this->groupname.$value", $this->items[$value]);
                }
            }
            if ($field['type'] == 'array') {
                $value = $field['name'];
                $arrayValues = Utils::resolve($this->items, $field['name']);

                if ($arrayValues) {
                    foreach ($arrayValues as $arrayIndex => $arrayValue) {
                        Grav::instance()['config']->set("groups.$this->groupname.$value.$arrayIndex", $arrayValue);
                    }
                }
            }
        }

        $type = 'groups';
        $blueprints = $this->blueprints("config/{$type}");
        $obj = new Data(Grav::instance()['config']->get($type), $blueprints);
        $file = CompiledYamlFile::instance(Grav::instance()['locator']->findResource("config://{$type}.yaml"));
        $obj->file($file);
        $obj->save();
    }

    /**
     * Remove a group
     *
     * @param string $groupname
     *
     * @return bool True if the action was performed
     */
    public static function remove($groupname)
    {
        $blueprints = new Blueprints('blueprints://');
        $blueprint = $blueprints->get('user/group');

        $groups = Grav::instance()['config']->get("groups");
        unset($groups[$groupname]);
        Grav::instance()['config']->set("groups", $groups);

        $type = 'groups';
        $obj = new Data(Grav::instance()['config']->get($type), $blueprint);
        $file = CompiledYamlFile::instance(Grav::instance()['locator']->findResource("config://{$type}.yaml"));
        $obj->file($file);
        $obj->save();

        return true;
    }
}
