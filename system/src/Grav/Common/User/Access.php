<?php

/**
 * @package    Grav\Common\User
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\User;

use Grav\Common\Grav;
use Grav\Common\User\Interfaces\UserInterface;
use Grav\Common\Utils;
use function is_array;
use function is_bool;
use function is_string;

/**
 * Class Access
 * @package Grav\Common\User
 */
class Access extends \Grav\Framework\Acl\Access
{
    /** @var array[] */
    private $aliases = [
        'admin.configuration.system' => ['admin.configuration_system'],
        'admin.configuration.site' => ['admin.configuration_site', 'admin.settings'],
        'admin.configuration.media' => ['admin.configuration_media'],
        'admin.configuration.info' => ['admin.configuration_info'],
    ];

    /**
     * @param string $action
     * @return bool|null
     */
    public function get(string $action)
    {
        $result = parent::get($action);
        if (is_bool($result)) {
            return $result;
        }

        // Get access value.
        if (isset($this->aliases[$action])) {
            $aliases = $this->aliases[$action];
            foreach ($aliases as $alias) {
                $result = parent::get($alias);
                if (is_bool($result)) {
                    return $result;
                }
            }
        }

        return null;
    }

    /**
     * Whether the action is granted to the user by its own name, in the access map of the user or of one of its
     * enabled groups. Unlike authorize(), a rule granted to a parent (`api: true`) does not count for the action
     * (`api.super`). For the actions which must have been given on purpose, such as super-admin access.
     *
     * @param UserInterface $user
     * @param string $action
     * @return bool
     */
    public static function isGrantedExplicitly(UserInterface $user, string $action): bool
    {
        $maps = [$user->get('access')];

        $config = Grav::instance()['config'];
        foreach ((array)$user->get('groups') as $group) {
            if (is_string($group) && $config->get("groups.{$group}.enabled", true)) {
                $maps[] = $config->get("groups.{$group}.access");
            }
        }

        foreach ($maps as $map) {
            if (is_array($map) && Utils::isPositive(Utils::arrayFlattenDotNotation($map)[$action] ?? null)) {
                return true;
            }
        }

        return false;
    }
}
