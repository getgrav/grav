<?php

/**
 * @package    Grav\Common\User
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\User\Interfaces;

use Grav\Common\Data\Blueprint;
use Grav\Common\Data\DataInterface;
use Grav\Common\Media\Interfaces\MediaInterface;
use Grav\Common\Page\Medium\Medium;
use RocketTheme\Toolbox\ArrayTraits\ExportInterface;
use RuntimeException;

/**
 * Interface UserInterface
 * @package Grav\Common\User\Interfaces
 *
 * @property string $username
 * @property string $email
 * @property string $fullname
 * @property string $state
 * @property array $groups
 * @property array $access
 *
 * @property bool $authenticated
 * @property bool $authorized
 */
interface UserInterface extends AuthorizeInterface, DataInterface, MediaInterface, \ArrayAccess, \JsonSerializable, ExportInterface
{
    /**
     * @param array $items
     * @param Blueprint|callable $blueprints
     */
    //public function __construct(array $items = [], $blueprints = null);

    /**
     * Get value by using dot notation for nested arrays/objects.
     *
     * @example $value = $this->get('this.is.my.nested.variable');
     *
     * @param string  $name       Dot separated path to the requested value.
     * @param mixed   $default    Default value (or null).
     * @param string|null  $separator  Separator, defaults to '.'
     * @return mixed  Value.
     */
    public function get($name, $default = null, $separator = null);

    /**
     * Set value by using dot notation for nested arrays/objects.
     *
     * @example $data->set('this.is.my.nested.variable', $value);
     *
     * @param string  $name       Dot separated path to the requested value.
     * @param mixed   $value      New value.
     * @param string|null  $separator  Separator, defaults to '.'
     * @return $this
     */
    public function set($name, $value, $separator = null);

    /**
     * Unset value by using dot notation for nested arrays/objects.
     *
     * @example $data->undef('this.is.my.nested.variable');
     *
     * @param string  $name       Dot separated path to the requested value.
     * @param string|null  $separator  Separator, defaults to '.'
     * @return $this
     */
    public function undef($name, $separator = null);

    /**
     * Set default value by using dot notation for nested arrays/objects.
     *
     * @example $data->def('this.is.my.nested.variable', 'default');
     *
     * @param string  $name       Dot separated path to the requested value.
     * @param mixed   $default    Default value (or null).
     * @param string|null  $separator  Separator, defaults to '.'
     * @return $this
     */
    public function def($name, $default = null, $separator = null);

    /**
     * Join nested values together by using blueprints.
     *
     * @param string  $name       Dot separated path to the requested value.
     * @param mixed   $value      Value to be joined.
     * @param string  $separator  Separator, defaults to '.'
     * @return $this
     * @throws RuntimeException
     */
    public function join($name, $value, $separator = '.');

    /**
     * Get nested structure containing default values defined in the blueprints.
     *
     * Fields without default value are ignored in the list.

     * @return array
     */
    public function getDefaults();

    /**
     * Set default values by using blueprints.
     *
     * @param string  $name       Dot separated path to the requested value.
     * @param mixed   $value      Value to be joined.
     * @param string  $separator  Separator, defaults to '.'
     * @return $this
     */
    public function joinDefaults($name, $value, $separator = '.');

    /**
     * Get value from the configuration and join it with given data.
     *
     * @param string  $name       Dot separated path to the requested value.
     * @param array|object $value      Value to be joined.
     * @param string  $separator  Separator, defaults to '.'
     * @return array
     * @throws RuntimeException
     */
    public function getJoined($name, $value, $separator = '.');

    /**
     * Set default values to the configuration if variables were not set.
     *
     * @param array $data
     * @return $this
     */
    public function setDefaults(array $data);

    /**
     * Update object with data
     *
     * @param array $data
     * @param array $files
     * @return $this
     */
    public function update(array $data, array $files = []);

    /**
     * Returns whether the data already exists in the storage.
     *
     * NOTE: This method does not check if the data is current.
     *
     * @return bool
     */
    public function exists();

    /**
     * Return unmodified data as raw string.
     *
     * NOTE: This function only returns data which has been saved to the storage.
     *
     * @return string
     */
    public function raw();

    /**
     * Authenticate user.
     *
     * If user password needs to be updated, new information will be saved.
     *
     * @param string $password Plaintext password.
     * @return bool
     */
    public function authenticate(string $password): bool;

    /**
     * Return media object for the User's avatar.
     *
     * Note: if there's no local avatar image for the user, you should call getAvatarUrl() to get the external avatar URL.
     *
     * @return Medium|null
     */
    public function getAvatarImage(): ?Medium;

    /**
     * Return the User's avatar URL.
     *
     * @return string
     */
    public function getAvatarUrl(): string;
}
