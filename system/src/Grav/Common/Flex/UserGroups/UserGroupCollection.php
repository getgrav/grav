<?php

declare(strict_types=1);

/**
 * @package    Grav\Common\Flex
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Flex\UserGroups;

use Grav\Framework\Flex\FlexCollection;

class UserGroupCollection extends FlexCollection
{
    /**
     * @return array
     */
    public static function getCachedMethods(): array
    {
        return [
            'authorize' => true,
        ] + parent::getCachedMethods();
    }

    /**
     * Checks user authorization to the action.
     *
     * @param  string $action
     * @param  string|null $scope
     * @return bool|null
     */
    public function authorize(string $action, string $scope = null): ?bool
    {
        $authorized = null;
        foreach ($this as $object) {
            $auth = $object->authorize($action, $scope);
            if ($auth === true) {
                $authorized = true;
            } elseif ($auth === false) {
                return false;
            }
        }

        return $authorized;
    }
}
