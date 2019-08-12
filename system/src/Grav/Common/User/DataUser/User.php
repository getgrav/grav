<?php

/**
 * @package    Grav\Common\User
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\User\DataUser;

use Grav\Common\Data\Blueprint;
use Grav\Common\Data\Blueprints;
use Grav\Common\Data\Data;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use Grav\Common\Page\Media;
use Grav\Common\Page\Medium\ImageMedium;
use Grav\Common\Page\Medium\MediumFactory;
use Grav\Common\User\Authentication;
use Grav\Common\User\Interfaces\UserInterface;
use Grav\Common\User\Traits\UserTrait;

class User extends Data implements UserInterface
{
    use UserTrait;

    protected $_media;

    /**
     * User constructor.
     * @param array $items
     * @param Blueprint $blueprints
     */
    public function __construct(array $items = [], $blueprints = null)
    {
        // User can only be authenticated via login.
        unset($items['authenticated'], $items['authorized']);

        // Always set blueprints.
        if (null === $blueprints) {
            $blueprints = (new Blueprints)->get('user/account');
        }

        parent::__construct($items, $blueprints);
    }

    /**
     * @param string $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        $value = parent::offsetExists($offset);

        // Handle special case where user was logged in before 'authorized' was added to the user object.
        if (false === $value && $offset === 'authorized') {
            $value = $this->offsetExists('authenticated');
        }

        return $value;
    }

    /**
     * @param string $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        $value = parent::offsetGet($offset);

        // Handle special case where user was logged in before 'authorized' was added to the user object.
        if (null === $value && $offset === 'authorized') {
            $value = $this->offsetGet('authenticated');
            $this->offsetSet($offset, $value);
        }

        return $value;
    }

    public function isValid(): bool
    {
        return $this->items !== null;
    }

    /**
     * Update object with data
     *
     * @param array $data
     * @param array $files
     * @return $this
     */
    public function update(array $data, array $files = [])
    {
        // Note: $this->merge() would cause infinite loop as it calls this method.
        parent::merge($data);

        return $this;
    }

    /**
     * Save user without the username
     */
    public function save()
    {
        /** @var CompiledYamlFile $file */
        $file = $this->file();
        if (!$file || !$file->filename()) {
            user_error(__CLASS__ . ': calling \$user = new ' . __CLASS__ . "() is deprecated since Grav 1.6, use \$grav['accounts']->load(\$username) or \$grav['accounts']->load('') instead", E_USER_DEPRECATED);
        }

        if ($file) {
            $username = $this->get('username');

            if (!$file->filename()) {
                $locator = Grav::instance()['locator'];
                $file->filename($locator->findResource('account://' . mb_strtolower($username) . YAML_EXT, true, true));
            }

            // if plain text password, hash it and remove plain text
            $password = $this->get('password');
            if ($password) {
                $this->set('hashed_password', Authentication::create($password));
                $this->undef('password');
            }

            $data = $this->items;
            unset($data['username'], $data['authenticated'], $data['authorized']);

            $file->save($data);
        }
    }

    public function getMedia()
    {
        if (null === $this->_media) {
            // Media object should only contain avatar, nothing else.
            $media = new Media($this->getMediaFolder(), $this->getMediaOrder(), false);

            $path = $this->getAvatarFile();
            if ($path && is_file($path)) {
                $medium = MediumFactory::fromFile($path);
                if ($medium) {
                    $media->add(basename($path), $medium);
                }
            }

            $this->_media = $media;
        }

        return $this->_media;
    }

    public function getMediaFolder()
    {
        return $this->blueprints()->fields()['avatar']['destination'] ?? 'user://accounts/avatars';
    }

    public function getMediaOrder()
    {
        return [];
    }

    /**
     * Serialize user.
     */
    public function __sleep()
    {
        return [
            'items',
            'storage'
        ];
    }

    /**
     * Unserialize user.
     */
    public function __wakeup()
    {
        $this->gettersVariable = 'items';
        $this->nestedSeparator = '.';

        if (null === $this->items) {
            $this->items = [];
        }

        // Always set blueprints.
        if (null === $this->blueprints) {
            $this->blueprints = (new Blueprints)->get('user/account');
        }
    }

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

        return $this->update($data);
    }

    /**
     * Return media object for the User's avatar.
     *
     * @return ImageMedium|null
     * @deprecated 1.6 Use ->getAvatarImage() method instead.
     */
    public function getAvatarMedia()
    {
        user_error(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated since Grav 1.6, use getAvatarImage() method instead', E_USER_DEPRECATED);

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
        user_error(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated since Grav 1.6, use getAvatarUrl() method instead', E_USER_DEPRECATED);

        return $this->getAvatarUrl();
    }

    /**
     * Checks user authorization to the action.
     * Ensures backwards compatibility
     *
     * @param  string $action
     *
     * @return bool
     * @deprecated 1.5 Use ->authorize() method instead.
     */
    public function authorise($action)
    {
        user_error(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated since Grav 1.5, use authorize() method instead', E_USER_DEPRECATED);

        return $this->authorize($action);
    }

    /**
     * Implements Countable interface.
     *
     * @return int
     * @deprecated 1.6 Method makes no sense for user account.
     */
    public function count()
    {
        user_error(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated since Grav 1.6', E_USER_DEPRECATED);

        return parent::count();
    }

    protected function getAvatarFile(): ?string
    {
        $avatars = $this->get('avatar');
        if (\is_array($avatars) && $avatars) {
            $avatar = array_shift($avatars);
            return $avatar['path'] ?? null;
        }

        return null;
    }
}
