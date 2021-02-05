<?php

/**
 * @package    Grav\Common\User
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\User;

use function is_bool;

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
}
