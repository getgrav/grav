<?php

declare(strict_types=1);

/**
 * @package    Grav\Common\Flex
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Flex\Types\Users\Traits;

use Grav\Common\Page\Medium\ImageMedium;
use Grav\Common\Page\Medium\StaticImageMedium;
use function count;

/**
 * Trait UserObjectLegacyTrait
 * @package Grav\Common\Flex\Types\Users\Traits
 */
trait UserObjectLegacyTrait
{
    /**
     * Merge two configurations together.
     *
     * @param array $data
     * @return $this
     * @deprecated 1.6 Use `->update($data)` instead (same but with data validation & filtering, file upload support).
     */
    public function merge(array $data)
    {
        user_error(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated since Grav 1.6, use ->update($data) method instead', E_USER_DEPRECATED);

        $this->setElements($this->getBlueprint()->mergeData($this->toArray(), $data));

        return $this;
    }

    /**
     * Return media object for the User's avatar.
     *
     * @return ImageMedium|StaticImageMedium|null
     * @deprecated 1.6 Use ->getAvatarImage() method instead.
     */
    public function getAvatarMedia()
    {
        user_error(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated since Grav 1.6, use ->getAvatarImage() method instead', E_USER_DEPRECATED);

        return $this->getAvatarImage();
    }

    /**
     * Return the User's avatar URL
     *
     * @return string
     * @deprecated 1.6 Use ->getAvatarUrl() method instead.
     */
    public function avatarUrl()
    {
        user_error(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated since Grav 1.6, use ->getAvatarUrl() method instead', E_USER_DEPRECATED);

        return $this->getAvatarUrl();
    }

    /**
     * Checks user authorization to the action.
     * Ensures backwards compatibility
     *
     * @param string $action
     * @return bool
     * @deprecated 1.5 Use ->authorize() method instead.
     */
    public function authorise($action)
    {
        user_error(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated since Grav 1.5, use ->authorize() method instead', E_USER_DEPRECATED);

        return $this->authorize($action) ?? false;
    }

    /**
     * Implements Countable interface.
     *
     * @return int
     * @deprecated 1.6 Method makes no sense for user account.
     */
    #[\ReturnTypeWillChange]
    public function count()
    {
        user_error(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated since Grav 1.6', E_USER_DEPRECATED);

        return count($this->jsonSerialize());
    }
}
