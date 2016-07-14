<?php
/**
 * @package    Grav.Common.User
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\User;

use Grav\Common\Data\Blueprints;
use Grav\Common\Data\Data;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use Grav\Common\Utils;

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

        $blueprints = new Blueprints;
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
        $grav = Grav::instance();
        $config = $grav['config'];

        $blueprints = new Blueprints;
        $blueprint = $blueprints->get('user/group');

        $fields = $blueprint->fields();

        $config->set("groups.$this->groupname", []);

        foreach ($fields as $field) {
            if ($field['type'] == 'text') {
                $value = $field['name'];
                if (isset($this->items[$value])) {
                    $config->set("groups.$this->groupname.$value", $this->items[$value]);
                }
            }
            if ($field['type'] == 'array') {
                $value = $field['name'];
                $arrayValues = Utils::getDotNotation($this->items, $field['name']);

                if ($arrayValues) {
                    foreach ($arrayValues as $arrayIndex => $arrayValue) {
                        $config->set("groups.$this->groupname.$value.$arrayIndex", $arrayValue);
                    }
                }
            }
        }

        $type = 'groups';
        $blueprints = $this->blueprints("config/{$type}");
        $obj = new Data($config->get($type), $blueprints);
        $file = CompiledYamlFile::instance($grav['locator']->findResource("config://{$type}.yaml"));
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
        $grav = Grav::instance();
        $config = $grav['config'];
        $blueprints = new Blueprints;
        $blueprint = $blueprints->get('user/group');

        $groups = $config->get("groups");
        unset($groups[$groupname]);
        $config->set("groups", $groups);

        $type = 'groups';
        $obj = new Data($config->get($type), $blueprint);
        $file = CompiledYamlFile::instance($grav['locator']->findResource("config://{$type}.yaml"));
        $obj->file($file);
        $obj->save();

        return true;
    }
}
