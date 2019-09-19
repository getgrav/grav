<?php

/**
 * @package    Grav\Common\User
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\User\FlexUser;

use Grav\Common\Data\Blueprint;
use Grav\Common\Grav;
use Grav\Common\Media\Interfaces\MediaCollectionInterface;
use Grav\Common\Page\Media;
use Grav\Common\Page\Medium\ImageMedium;
use Grav\Common\Page\Medium\Medium;
use Grav\Common\User\Authentication;
use Grav\Common\User\Interfaces\UserInterface;
use Grav\Common\User\Traits\UserTrait;
use Grav\Framework\File\Formatter\JsonFormatter;
use Grav\Framework\File\Formatter\YamlFormatter;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Flex\FlexObject;
use Grav\Framework\Flex\Storage\FileStorage;
use Grav\Framework\Flex\Traits\FlexAuthorizeTrait;
use Grav\Framework\Flex\Traits\FlexMediaTrait;
use Grav\Framework\Form\FormFlashFile;
use Grav\Framework\Media\Interfaces\MediaManipulationInterface;
use Psr\Http\Message\UploadedFileInterface;
use RocketTheme\Toolbox\File\FileInterface;

/**
 * Flex User
 *
 * Flex User is mostly compatible with the older User class, except on few key areas:
 *
 * - Constructor parameters have been changed. Old code creating a new user does not work.
 * - Serializer has been changed -- existing sessions will be killed.
 *
 * @package Grav\Common\User
 *
 * @property string $username
 * @property string $email
 * @property string $fullname
 * @property string $state
 * @property array $groups
 * @property array $access
 * @property bool $authenticated
 * @property bool $authorized
 */
class User extends FlexObject implements UserInterface, MediaManipulationInterface, \Countable
{
    use FlexMediaTrait;
    use FlexAuthorizeTrait;
    use UserTrait;

    protected $_uploads_original;

    /**
     * @var FileInterface|null
     */
    protected $_storage;

    /**
     * @return array
     */
    public static function getCachedMethods(): array
    {
        return [
            'load' => false,
            'find' => false,
            'remove' => false,
            'get' => true,
            'set' => false,
            'undef' => false,
            'def' => false,
        ] + parent::getCachedMethods();
    }

    public function __construct(array $elements, $key, FlexDirectory $directory, bool $validate = false)
    {
        // User can only be authenticated via login.
        unset($elements['authenticated'], $elements['authorized']);

        parent::__construct($elements, $key, $directory, $validate);

        // Define username and state if they aren't set.
        $this->defProperty('username', $key);
        $this->defProperty('state', 'enabled');
    }

    /**
     * Get value by using dot notation for nested arrays/objects.
     *
     * @example $value = $this->get('this.is.my.nested.variable');
     *
     * @param string  $name       Dot separated path to the requested value.
     * @param mixed   $default    Default value (or null).
     * @param string  $separator  Separator, defaults to '.'
     * @return mixed  Value.
     */
    public function get($name, $default = null, $separator = null)
    {
        return $this->getNestedProperty($name, $default, $separator);
    }

    /**
     * Set value by using dot notation for nested arrays/objects.
     *
     * @example $data->set('this.is.my.nested.variable', $value);
     *
     * @param string  $name       Dot separated path to the requested value.
     * @param mixed   $value      New value.
     * @param string  $separator  Separator, defaults to '.'
     * @return $this
     */
    public function set($name, $value, $separator = null)
    {
        $this->setNestedProperty($name, $value, $separator);

        return $this;
    }

    /**
     * Unset value by using dot notation for nested arrays/objects.
     *
     * @example $data->undef('this.is.my.nested.variable');
     *
     * @param string  $name       Dot separated path to the requested value.
     * @param string  $separator  Separator, defaults to '.'
     * @return $this
     */
    public function undef($name, $separator = null)
    {
        $this->unsetNestedProperty($name, $separator);

        return $this;
    }

    /**
     * Set default value by using dot notation for nested arrays/objects.
     *
     * @example $data->def('this.is.my.nested.variable', 'default');
     *
     * @param string  $name       Dot separated path to the requested value.
     * @param mixed   $default    Default value (or null).
     * @param string  $separator  Separator, defaults to '.'
     * @return $this
     */
    public function def($name, $default = null, $separator = null)
    {
        $this->defNestedProperty($name, $default, $separator);

        return $this;
    }

    /**
     * Get value from a page variable (used mostly for creating edit forms).
     *
     * @param string $name Variable name.
     * @param mixed $default
     * @param string|null $separator
     * @return mixed
     */
    public function getFormValue(string $name, $default = null, string $separator = null)
    {
        $value = parent::getFormValue($name, null, $separator);

        if ($name === 'avatar') {
            return $this->parseFileProperty($value);
        }

        if (null === $value) {
            if ($name === 'media_order') {
                return implode(',', $this->getMediaOrder());
            }
        }

        return $value ?? $default;
    }

    /**
     * @param string $property
     * @param mixed $default
     * @return mixed
     */
    public function getProperty($property, $default = null)
    {
        $value = parent::getProperty($property, $default);

        if ($property === 'avatar') {
            $value = $this->parseFileProperty($value);
        }

        return $value;
    }

    /**
     * Convert object into an array.
     *
     * @return array
     */
    public function toArray()
    {
        $array = $this->jsonSerialize();
        $array['avatar'] = $this->parseFileProperty($array['avatar'] ?? null);

        return $array;
    }

    /**
     * Convert object into YAML string.
     *
     * @param  int $inline  The level where you switch to inline YAML.
     * @param  int $indent  The amount of spaces to use for indentation of nested nodes.
     *
     * @return string A YAML string representing the object.
     */
    public function toYaml($inline = 5, $indent = 2)
    {
        $yaml = new YamlFormatter(['inline' => $inline, 'indent' => $indent]);

        return $yaml->encode($this->toArray());
    }

    /**
     * Convert object into JSON string.
     *
     * @return string
     */
    public function toJson()
    {
        $json = new JsonFormatter();

        return $json->encode($this->toArray());
    }

    /**
     * Join nested values together by using blueprints.
     *
     * @param string  $name       Dot separated path to the requested value.
     * @param mixed   $value      Value to be joined.
     * @param string  $separator  Separator, defaults to '.'
     * @return $this
     * @throws \RuntimeException
     */
    public function join($name, $value, $separator = null)
    {
        $separator = $separator ?? '.';
        $old = $this->get($name, null, $separator);
        if ($old !== null) {
            if (!\is_array($old)) {
                throw new \RuntimeException('Value ' . $old);
            }

            if (\is_object($value)) {
                $value = (array) $value;
            } elseif (!\is_array($value)) {
                throw new \RuntimeException('Value ' . $value);
            }

            $value = $this->getBlueprint()->mergeData($old, $value, $name, $separator);
        }

        $this->set($name, $value, $separator);

        return $this;
    }

    /**
     * Get nested structure containing default values defined in the blueprints.
     *
     * Fields without default value are ignored in the list.

     * @return array
     */
    public function getDefaults()
    {
        return $this->getBlueprint()->getDefaults();
    }

    /**
     * Set default values by using blueprints.
     *
     * @param string  $name       Dot separated path to the requested value.
     * @param mixed   $value      Value to be joined.
     * @param string  $separator  Separator, defaults to '.'
     * @return $this
     */
    public function joinDefaults($name, $value, $separator = null)
    {
        if (\is_object($value)) {
            $value = (array) $value;
        }

        $old = $this->get($name, null, $separator);
        if ($old !== null) {
            $value = $this->getBlueprint()->mergeData($value, $old, $name, $separator);
        }

        $this->setNestedProperty($name, $value, $separator);

        return $this;
    }

    /**
     * Get value from the configuration and join it with given data.
     *
     * @param string  $name       Dot separated path to the requested value.
     * @param array|object $value      Value to be joined.
     * @param string  $separator  Separator, defaults to '.'
     * @return array
     * @throws \RuntimeException
     */
    public function getJoined($name, $value, $separator = null)
    {
        if (\is_object($value)) {
            $value = (array) $value;
        } elseif (!\is_array($value)) {
            throw new \RuntimeException('Value ' . $value);
        }

        $old = $this->get($name, null, $separator);

        if ($old === null) {
            // No value set; no need to join data.
            return $value;
        }

        if (!\is_array($old)) {
            throw new \RuntimeException('Value ' . $old);
        }

        // Return joined data.
        return $this->getBlueprint()->mergeData($old, $value, $name, $separator);
    }

    /**
     * Set default values to the configuration if variables were not set.
     *
     * @param array $data
     * @return $this
     */
    public function setDefaults(array $data)
    {
        $this->setElements($this->getBlueprint()->mergeData($data, $this->toArray()));

        return $this;
    }

    /**
     * Validate by blueprints.
     *
     * @return $this
     * @throws \Exception
     */
    public function validate()
    {
        $this->getBlueprint()->validate($this->toArray());

        return $this;
    }

    /**
     * Filter all items by using blueprints.
     * @return $this
     */
    public function filter()
    {
        $this->setElements($this->getBlueprint()->filter($this->toArray()));

        return $this;
    }

    /**
     * Get extra items which haven't been defined in blueprints.
     *
     * @return array
     */
    public function extra()
    {
        return $this->getBlueprint()->extra($this->toArray());
    }

    /**
     * @param string $name
     * @return Blueprint
     */
    public function getBlueprint(string $name = '')
    {
        $blueprint = clone parent::getBlueprint($name);

        $blueprint->addDynamicHandler('flex', function (array &$field, $property, array &$call) {
            $params = (array)$call['params'];
            $method = array_shift($params);

            if (method_exists($this, $method)) {
                $value = $this->{$method}(...$params);
                if (\is_array($value) && isset($field[$property]) && \is_array($field[$property])) {
                    $field[$property] = array_merge_recursive($field[$property], $value);
                } else {
                    $field[$property] = $value;
                }
            }
        });

        return $blueprint->init();
    }

    /**
     * Return unmodified data as raw string.
     *
     * NOTE: This function only returns data which has been saved to the storage.
     *
     * @return string
     */
    public function raw()
    {
        $file = $this->file();

        return $file ? $file->raw() : '';
    }

    /**
     * Set or get the data storage.
     *
     * @param FileInterface $storage Optionally enter a new storage.
     * @return FileInterface
     */
    public function file(FileInterface $storage = null)
    {
        if (null !== $storage) {
            $this->_storage = $storage;
        }

        return $this->_storage;
    }

    public function isValid(): bool
    {
        return $this->getProperty('state') !== null;
    }

    /**
     * Save user without the username
     */
    public function save()
    {
        // TODO: We may want to handle this in the storage layer in the future.
        $key = $this->getStorageKey();
        if (!$key || strpos($key, '@@')) {
            $storage = $this->getFlexDirectory()->getStorage();
            if ($storage instanceof FileStorage) {
                $this->setStorageKey($this->getKey());
            }
        }

        $password = $this->getProperty('password');
        if (null !== $password) {
            $this->unsetProperty('password');
            $this->unsetProperty('password1');
            $this->unsetProperty('password2');
            $this->setProperty('hashed_password', Authentication::create($password));
        }

        return parent::save();
    }

    public function isAuthorized(string $action, string $scope = null, UserInterface $user = null): bool
    {
        if (null === $user) {
            /** @var UserInterface $user */
            $user = Grav::instance()['user'] ?? null;
        }

        if ($user instanceof User && $user->getStorageKey() === $this->getStorageKey()) {
            return true;
        }

        return parent::isAuthorized($action, $scope, $user);
    }

    /**
     * @return array
     */
    public function prepareStorage(): array
    {
        $elements = parent::prepareStorage();

        // Do not save authorization information.
        unset($elements['authenticated'], $elements['authorized']);

        return $elements;
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

        $this->setElements($this->getBlueprint()->mergeData($this->toArray(), $data));

        return $this;
    }

    /**
     * Return media object for the User's avatar.
     *
     * @return ImageMedium|null
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

        return \count($this->jsonSerialize());
    }

    /**
     * Gets the associated media collection (original images).
     *
     * @return MediaCollectionInterface  Representation of associated media.
     */
    protected function getOriginalMedia()
    {
        return (new Media($this->getMediaFolder() . '/original', $this->getMediaOrder()))->setTimestamps();
    }

    /**
     * @param array $files
     */
    protected function setUpdatedMedia(array $files): void
    {
        $list = [];
        $list_original = [];
        foreach ($files as $field => $group) {
            foreach ($group as $filename => $file) {
                if (strpos($field, '/original')) {
                    // Special handling for original images.
                    $list_original[$filename] = $file;
                    continue;
                }

                $list[$filename] = $file;

                if ($file) {
                    /** @var FormFlashFile $file */
                    $data = $file->jsonSerialize();
                    $path = $file->getClientFilename();
                    unset($data['tmp_name'], $data['path']);

                    $this->setNestedProperty("{$field}\n{$path}", $data, "\n");
                } else {
                    $this->unsetNestedProperty("{$field}\n{$filename}", "\n");
                }
            }
        }

        $this->_uploads = $list;
        $this->_uploads_original = $list_original;
    }

    protected function saveUpdatedMedia(): void
    {
        // Upload/delete original sized images.
        /** @var FormFlashFile $file */
        foreach ($this->_uploads_original ?? [] as $name => $file) {
            $name = 'original/' . $name;
            if ($file) {
                $this->uploadMediaFile($file, $name);
            } else {
                $this->deleteMediaFile($name);
            }
        }

        /**
         * @var string $filename
         * @var UploadedFileInterface $file
         */
        foreach ($this->getUpdatedMedia() as $filename => $file) {
            if ($file) {
                $this->uploadMediaFile($file, $filename);
            } else {
                $this->deleteMediaFile($filename);
            }
        }

        $this->setUpdatedMedia([]);
    }

    /**
     * @param array $value
     * @return array
     */
    protected function parseFileProperty($value)
    {
        if (!\is_array($value)) {
            return $value;
        }

        $originalMedia = $this->getOriginalMedia();
        $resizedMedia = $this->getMedia();

        $list = [];
        foreach ($value as $filename => $info) {
            if (!\is_array($info)) {
                continue;
            }

            /** @var Medium $thumbFile */
            $thumbFile = $resizedMedia[$filename];
            /** @var Medium $imageFile */
            $imageFile = $originalMedia[$filename] ?? $thumbFile;
            if ($thumbFile) {
                $list[$filename] = [
                    'name' => $filename,
                    'type' => $info['type'],
                    'size' => $info['size'],
                    'image_url' => $imageFile->url(),
                    'thumb_url' =>  $thumbFile->url(),
                    'cropData' => (object)($imageFile->metadata()['upload']['crop'] ?? [])
                ];
            }
        }

        return $list;
    }

    /**
     * @return array
     */
    protected function doSerialize(): array
    {
        return [
            'type' => 'accounts',
            'key' => $this->getKey(),
            'elements' => $this->jsonSerialize(),
            'storage' => $this->getStorage()
        ];
    }

    /**
     * @param array $serialized
     */
    protected function doUnserialize(array $serialized): void
    {
        $grav = Grav::instance();

        /** @var UserCollection $accounts */
        $accounts = $grav['accounts'];

        $directory = $accounts->getFlexDirectory();
        if (!$directory) {
            throw new \InvalidArgumentException('Internal error');
        }

        $this->setFlexDirectory($directory);
        $this->setStorage($serialized['storage']);
        $this->setKey($serialized['key']);
        $this->setElements($serialized['elements']);
    }
}
