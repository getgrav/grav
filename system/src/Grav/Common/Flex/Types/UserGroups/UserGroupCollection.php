<?php

declare(strict_types=1);

/**
 * @package    Grav\Common\Flex
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Flex\Types\UserGroups;

use Grav\Common\Flex\Traits\FlexCollectionTrait;
use Grav\Common\Flex\Traits\FlexGravTrait;
use Grav\Framework\Flex\FlexCollection;

/**
 * Class UserGroupCollection
 * @package Grav\Common\Flex\Types\UserGroups
 *
 * @extends FlexCollection<string,UserGroupObject>
 */
class UserGroupCollection extends FlexCollection
{
    use FlexGravTrait;
    use FlexCollectionTrait;

    /**
     * @return array
     */
    public static function getCachedMethods(): array
    {
        return [
            'authorize' => 'session',
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
        /** @var UserGroupObject $object */
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
