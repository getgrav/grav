<?php

/**
 * @package    Grav\Common\User
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\User;

use Grav\Common\Config\Config;
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
        return Grav::instance()['config']->get('groups', []);
    }

    /**
     * Get the groups list
     *
     * @return array
     */
    public static function groupNames()
    {
        $groups = [];

        foreach(static::groups() as $groupname => $group) {
            $groups[$groupname] = $group['readableName'] ?? $groupname;
        }

        return $groups;
    }

    /**
     * Checks if a group exists
     *
     * @param string $groupname
     *
     * @return bool
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
        $groups = self::groups();

        $content = $groups[$groupname] ?? [];
        $content += ['groupname' => $groupname];

        $blueprints = new Blueprints();
        $blueprint = $blueprints->get('user/group');

        return new Group($content, $blueprint);
    }

    /**
     * Save a group
     */
    public function save()
    {
        $grav = Grav::instance();

        /** @var Config $config */
        $config = $grav['config'];

        $blueprints = new Blueprints();
        $blueprint = $blueprints->get('user/group');

        $config->set("groups.{$this->get('groupname')}", []);

        $fields = $blueprint->fields();
        foreach ($fields as $field) {
            if ($field['type'] === 'text') {
                $value = $field['name'];
                if (isset($this->items['data'][$value])) {
                    $config->set("groups.{$this->get('groupname')}.{$value}", $this->items['data'][$value]);
                }
            }
            if ($field['type'] === 'array' || $field['type'] === 'permissions') {
                $value = $field['name'];
                $arrayValues = Utils::getDotNotation($this->items['data'], $field['name']);

                if ($arrayValues) {
                    foreach ($arrayValues as $arrayIndex => $arrayValue) {
                        $config->set("groups.{$this->get('groupname')}.{$value}.{$arrayIndex}", $arrayValue);
                    }
                }
            }
        }

        $type = 'groups';
        $blueprints = $this->blueprints();

        $filename = CompiledYamlFile::instance($grav['locator']->findResource("config://{$type}.yaml"));

        $obj = new Data($config->get($type), $blueprints);
        $obj->file($filename);
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

        /** @var Config $config */
        $config = $grav['config'];

        $blueprints = new Blueprints();
        $blueprint = $blueprints->get('user/group');

        $type = 'groups';

        $groups = $config->get($type);
        unset($groups[$groupname]);
        $config->set($type, $groups);

        $filename = CompiledYamlFile::instance($grav['locator']->findResource("config://{$type}.yaml"));

        $obj = new Data($groups, $blueprint);
        $obj->file($filename);
        $obj->save();

        return true;
    }
}
