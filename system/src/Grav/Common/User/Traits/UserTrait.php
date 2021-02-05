<?php

/**
 * @package    Grav\Common\User
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\User\Traits;

use Grav\Common\Grav;
use Grav\Common\Page\Medium\ImageMedium;
use Grav\Common\Page\Medium\Medium;
use Grav\Common\Page\Medium\StaticImageMedium;
use Grav\Common\User\Authentication;
use Grav\Common\Utils;
use function is_array;
use function is_string;

/**
 * Trait UserTrait
 * @package Grav\Common\User\Traits
 */
trait UserTrait
{
    /**
     * Authenticate user.
     *
     * If user password needs to be updated, new information will be saved.
     *
     * @param string $password Plaintext password.
     * @return bool
     */
    public function authenticate(string $password): bool
    {
        $hash = $this->get('hashed_password');

        $isHashed = null !== $hash;
        if (!$isHashed) {
            // If there is no hashed password, fake verify with default hash.
            $hash = Grav::instance()['config']->get('system.security.default_hash');
        }

        // Always execute verify() to protect us from timing attacks, but make the test to fail if hashed password wasn't set.
        $result = Authentication::verify($password, $hash) && $isHashed;

        $plaintext_password = $this->get('password');
        if (null !== $plaintext_password) {
            // Plain-text password is still stored, check if it matches.
            if ($password !== $plaintext_password) {
                return false;
            }

            // Force hash update to get rid of plaintext password.
            $result = 2;
        }

        if ($result === 2) {
            // Password needs to be updated, save the user.
            $this->set('password', $password);
            $this->undef('hashed_password');
            $this->save();
        }

        return (bool)$result;
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
        // User needs to be enabled.
        if ($this->get('state', 'enabled') !== 'enabled') {
            return false;
        }

        // User needs to be logged in.
        if (!$this->get('authenticated')) {
            return false;
        }

        // User needs to be authorized (2FA).
        if (strpos($action, 'login') === false && !$this->get('authorized', true)) {
            return false;
        }

        if (null !== $scope) {
            $action = $scope . '.' . $action;
        }

        $config = Grav::instance()['config'];
        $authorized = false;

        //Check group access level
        $groups = (array)$this->get('groups');
        foreach ($groups as $group) {
            $permission = $config->get("groups.{$group}.access.{$action}");
            $authorized = Utils::isPositive($permission);
            if ($authorized === true) {
                break;
            }
        }

        //Check user access level
        $access = $this->get('access');
        if ($access && Utils::getDotNotation($access, $action) !== null) {
            $permission = $this->get("access.{$action}");
            $authorized = Utils::isPositive($permission);
        }

        return $authorized;
    }

    /**
     * Return media object for the User's avatar.
     *
     * Note: if there's no local avatar image for the user, you should call getAvatarUrl() to get the external avatar URL.
     *
     * @return ImageMedium|StaticImageMedium|null
     */
    public function getAvatarImage(): ?Medium
    {
        $avatars = $this->get('avatar');
        if (is_array($avatars) && $avatars) {
            $avatar = array_shift($avatars);

            $media = $this->getMedia();
            $name = $avatar['name'] ?? null;

            $image = $name ? $media[$name] : null;
            if ($image instanceof ImageMedium ||
                $image instanceof StaticImageMedium) {
                return $image;
            }
        }

        return null;
    }

    /**
     * Return the User's avatar URL
     *
     * @return string
     */
    public function getAvatarUrl(): string
    {
        // Try to locate avatar image.
        $avatar = $this->getAvatarImage();
        if ($avatar) {
            return $avatar->url();
        }

        // Try if avatar is a sting (URL).
        $avatar = $this->get('avatar');
        if (is_string($avatar)) {
            return $avatar;
        }

        // Try looking for provider.
        $provider = $this->get('provider');
        $provider_options = $this->get($provider);
        if (is_array($provider_options)) {
            if (isset($provider_options['avatar_url']) && is_string($provider_options['avatar_url'])) {
                return $provider_options['avatar_url'];
            }
            if (isset($provider_options['avatar']) && is_string($provider_options['avatar'])) {
                return $provider_options['avatar'];
            }
        }

        $email = $this->get('email');

        // By default fall back to gravatar image.
        return $email ? 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($email))) : '';
    }

    abstract public function get($name, $default = null, $separator = null);
    abstract public function set($name, $value, $separator = null);
    abstract public function undef($name, $separator = null);
    abstract public function save();
}
