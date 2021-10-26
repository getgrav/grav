<?php

declare(strict_types=1);

/**
 * @package    Grav\Common\Flex
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Flex\Types\Users;

use Closure;
use Countable;
use Grav\Common\Config\Config;
use Grav\Common\Data\Blueprint;
use Grav\Common\Flex\FlexObject;
use Grav\Common\Flex\Traits\FlexGravTrait;
use Grav\Common\Flex\Traits\FlexObjectTrait;
use Grav\Common\Flex\Types\Users\Traits\UserObjectLegacyTrait;
use Grav\Common\Grav;
use Grav\Common\Media\Interfaces\MediaCollectionInterface;
use Grav\Common\Media\Interfaces\MediaUploadInterface;
use Grav\Common\Page\Media;
use Grav\Common\Page\Medium\MediumFactory;
use Grav\Common\User\Access;
use Grav\Common\User\Authentication;
use Grav\Common\Flex\Types\UserGroups\UserGroupCollection;
use Grav\Common\Flex\Types\UserGroups\UserGroupIndex;
use Grav\Common\User\Interfaces\UserInterface;
use Grav\Common\User\Traits\UserTrait;
use Grav\Framework\File\Formatter\JsonFormatter;
use Grav\Framework\File\Formatter\YamlFormatter;
use Grav\Framework\Filesystem\Filesystem;
use Grav\Framework\Flex\Flex;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Flex\Storage\FileStorage;
use Grav\Framework\Flex\Traits\FlexMediaTrait;
use Grav\Framework\Form\FormFlashFile;
use Psr\Http\Message\UploadedFileInterface;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\File\FileInterface;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RuntimeException;
use function is_array;
use function is_bool;
use function is_object;

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
class UserObject extends FlexObject implements UserInterface, Countable
{
    use FlexGravTrait;
    use FlexObjectTrait;
    use FlexMediaTrait {
        getMedia as private getFlexMedia;
        getMediaFolder as private getFlexMediaFolder;
    }
    use UserTrait;
    use UserObjectLegacyTrait;

    /** @var Closure|null */
    static public $authorizeCallable;

    /** @var array|null */
    protected $_uploads_original;
    /** @var FileInterface|null */
    protected $_storage;
    /** @var UserGroupIndex */
    protected $_groups;
    /** @var Access */
    protected $_access;
    /** @var array|null */
    protected $access;

    /**
     * @return array
     */
    public static function getCachedMethods(): array
    {
        return [
            'authorize' => 'session',
            'load' => false,
            'find' => false,
            'remove' => false,
            'get' => true,
            'set' => false,
            'undef' => false,
            'def' => false,
        ] + parent::getCachedMethods();
    }

    /**
     * UserObject constructor.
     * @param array $elements
     * @param string $key
     * @param FlexDirectory $directory
     * @param bool $validate
     */
    public function __construct(array $elements, $key, FlexDirectory $directory, bool $validate = false)
    {
        // User can only be authenticated via login.
        unset($elements['authenticated'], $elements['authorized']);

        // Define username if it's not set.
        if (!isset($elements['username'])) {
            $storageKey = $elements['__META']['storage_key'] ?? null;
            if (null !== $storageKey && $key === $directory->getStorage()->normalizeKey($storageKey)) {
                $elements['username'] = $storageKey;
            } else {
                $elements['username'] = $key;
            }
        }

        // Define state if it isn't set.
        if (!isset($elements['state'])) {
            $elements['state'] = 'enabled';
        }

        parent::__construct($elements, $key, $directory, $validate);
    }

    /**
     * @return void
     */
    public function onPrepareRegistration(): void
    {
        if (!$this->getProperty('access')) {
            /** @var Config $config */
            $config = Grav::instance()['config'];

            $groups = $config->get('plugins.login.user_registration.groups', '');
            $access = $config->get('plugins.login.user_registration.access', ['site' => ['login' => true]]);

            $this->setProperty('groups', $groups);
            $this->setProperty('access', $access);
        }
    }

    /**
     * Helper to get content editor will fall back if not set
     *
     * @return string
     */
    public function getContentEditor(): string
    {
        return $this->getProperty('content_editor', 'default');
    }

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
     * @param string|null  $separator  Separator, defaults to '.'
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
     * @param string|null  $separator  Separator, defaults to '.'
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
     * @param string|null  $separator  Separator, defaults to '.'
     * @return $this
     */
    public function def($name, $default = null, $separator = null)
    {
        $this->defNestedProperty($name, $default, $separator);

        return $this;
    }

    /**
     * @return bool
     */
    public function isMyself(): bool
    {
        $me = $this->getActiveUser();

        return $me && $me->authenticated && $this->username === $me->username;
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
        if ($scope === 'test') {
            // Special scope to test user permissions.
            $scope = null;
        } else {
            // User needs to be enabled.
            if ($this->getProperty('state') !== 'enabled') {
                return false;
            }

            // User needs to be logged in.
            if (!$this->getProperty('authenticated')) {
                return false;
            }

            if (strpos($action, 'login') === false && !$this->getProperty('authorized')) {
                // User needs to be authorized (2FA).
                return false;
            }

            // Workaround bug in Login::isUserAuthorizedForPage() <= Login v3.0.4
            if ((string)(int)$action === $action) {
                return false;
            }
        }

        // Check custom application access.
        $authorizeCallable = static::$authorizeCallable;
        if ($authorizeCallable instanceof Closure) {
            $authorizeCallable->bindTo($this);
            $authorized = $authorizeCallable($action, $scope);
            if (is_bool($authorized)) {
                return $authorized;
            }
        }

        // Check user access.
        $access = $this->getAccess();
        $authorized = $access->authorize($action, $scope);
        if (is_bool($authorized)) {
            return $authorized;
        }

        // Check group access.
        $authorized = $this->getGroups()->authorize($action, $scope);
        if (is_bool($authorized)) {
            return $authorized;
        }

        // If any specific rule isn't hit, check if user is a superuser.
        return $access->authorize('admin.super') === true;
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
            $settings = $this->getMediaFieldSettings($property);
            $value = $this->parseFileProperty($value, $settings);
        }

        return $value;
    }

    /**
     * @return UserGroupIndex
     */
    public function getRoles(): UserGroupIndex
    {
        return $this->getGroups();
    }

    /**
     * Convert object into an array.
     *
     * @return array
     */
    public function toArray()
    {
        $array = $this->jsonSerialize();

        $settings = $this->getMediaFieldSettings('avatar');
        $array['avatar'] = $this->parseFileProperty($array['avatar'] ?? null, $settings);

        return $array;
    }

    /**
     * Convert object into YAML string.
     *
     * @param  int $inline  The level where you switch to inline YAML.
     * @param  int $indent  The amount of spaces to use for indentation of nested nodes.
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
     * @param string|null  $separator  Separator, defaults to '.'
     * @return $this
     * @throws RuntimeException
     */
    public function join($name, $value, $separator = null)
    {
        $separator = $separator ?? '.';
        $old = $this->get($name, null, $separator);
        if ($old !== null) {
            if (!is_array($old)) {
                throw new RuntimeException('Value ' . $old);
            }

            if (is_object($value)) {
                $value = (array) $value;
            } elseif (!is_array($value)) {
                throw new RuntimeException('Value ' . $value);
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
     * @param string|null  $separator  Separator, defaults to '.'
     * @return $this
     */
    public function joinDefaults($name, $value, $separator = null)
    {
        if (is_object($value)) {
            $value = (array) $value;
        }

        $old = $this->get($name, null, $separator);
        if ($old !== null) {
            $value = $this->getBlueprint()->mergeData($value, $old, $name, $separator ?? '.');
        }

        $this->setNestedProperty($name, $value, $separator);

        return $this;
    }

    /**
     * Get value from the configuration and join it with given data.
     *
     * @param string  $name       Dot separated path to the requested value.
     * @param array|object $value Value to be joined.
     * @param string  $separator  Separator, defaults to '.'
     * @return array
     * @throws RuntimeException
     */
    public function getJoined($name, $value, $separator = null)
    {
        if (is_object($value)) {
            $value = (array) $value;
        } elseif (!is_array($value)) {
            throw new RuntimeException('Value ' . $value);
        }

        $old = $this->get($name, null, $separator);

        if ($old === null) {
            // No value set; no need to join data.
            return $value;
        }

        if (!is_array($old)) {
            throw new RuntimeException('Value ' . $old);
        }

        // Return joined data.
        return $this->getBlueprint()->mergeData($old, $value, $name, $separator ?? '.');
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
     * @param FileInterface|null $storage Optionally enter a new storage.
     * @return FileInterface|null
     */
    public function file(FileInterface $storage = null)
    {
        if (null !== $storage) {
            $this->_storage = $storage;
        }

        return $this->_storage;
    }

    /**
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->getProperty('state') !== null;
    }

    /**
     * Save user
     *
     * @return static
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

        $password = $this->getProperty('password') ?? $this->getProperty('password1');
        if (null !== $password && '' !== $password) {
            $password2 = $this->getProperty('password2');
            if (!\is_string($password) || ($password2 && $password !== $password2)) {
                throw new \RuntimeException('Passwords did not match.');
            }

            $this->setProperty('hashed_password', Authentication::create($password));
        }
        $this->unsetProperty('password');
        $this->unsetProperty('password1');
        $this->unsetProperty('password2');

        // Backwards compatibility with older plugins.
        $fireEvents = $this->isAdminSite() && $this->getFlexDirectory()->getConfig('object.compat.events', true);
        $grav = $this->getContainer();
        if ($fireEvents) {
            $self = $this;
            $grav->fireEvent('onAdminSave', new Event(['type' => 'flex', 'directory' => $this->getFlexDirectory(), 'object' => &$self]));
            if ($self !== $this) {
                throw new RuntimeException('Switching Flex User object during onAdminSave event is not supported! Please update plugin.');
            }
        }

        $instance = parent::save();

        // Backwards compatibility with older plugins.
        if ($fireEvents) {
            $grav->fireEvent('onAdminAfterSave', new Event(['type' => 'flex', 'directory' => $this->getFlexDirectory(), 'object' => $this]));
        }

        return $instance;
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
     * @return MediaCollectionInterface
     */
    public function getMedia()
    {
        /** @var Media $media */
        $media = $this->getFlexMedia();

        // Deal with shared avatar folder.
        $path = $this->getAvatarFile();
        if ($path && !$media[$path] && is_file($path)) {
            $medium = MediumFactory::fromFile($path);
            if ($medium) {
                $media->add($path, $medium);
                $name = basename($path);
                if ($name !== $path) {
                    $media->add($name, $medium);
                }
            }
        }

        return $media;
    }

    /**
     * @return string|null
     */
    public function getMediaFolder(): ?string
    {
        $folder = $this->getFlexMediaFolder();

        // Check for shared media
        if (!$folder && !$this->getFlexDirectory()->getMediaFolder()) {
            $this->_loadMedia = false;
            $folder = $this->getBlueprint()->fields()['avatar']['destination'] ?? 'user://accounts/avatars';
        }

        return $folder;
    }

    /**
     * @param string $name
     * @return Blueprint
     */
    protected function doGetBlueprint(string $name = ''): Blueprint
    {
        $blueprint = $this->getFlexDirectory()->getBlueprint($name ? '.' . $name : $name);

        // HACK: With folder storage we need to ignore the avatar destination.
        if ($this->getFlexDirectory()->getMediaFolder()) {
            $field = $blueprint->get('form/fields/avatar');
            if ($field) {
                unset($field['destination']);
                $blueprint->set('form/fields/avatar', $field);
            }
        }

        return $blueprint;
    }

    /**
     * @param UserInterface $user
     * @param string $action
     * @param string $scope
     * @param bool $isMe
     * @return bool|null
     */
    protected function isAuthorizedOverride(UserInterface $user, string $action, string $scope, bool $isMe = false): ?bool
    {
        if ($user instanceof self && $user->getStorageKey() === $this->getStorageKey()) {
            // User cannot delete his own account, otherwise he has full access.
            return $action !== 'delete';
        }

        return parent::isAuthorizedOverride($user, $action, $scope, $isMe);
    }

    /**
     * @return string|null
     */
    protected function getAvatarFile(): ?string
    {
        $avatars = $this->getElement('avatar');
        if (is_array($avatars) && $avatars) {
            $avatar = array_shift($avatars);

            return $avatar['path'] ?? null;
        }

        return null;
    }

    /**
     * Gets the associated media collection (original images).
     *
     * @return MediaCollectionInterface  Representation of associated media.
     */
    protected function getOriginalMedia()
    {
        $folder = $this->getMediaFolder();
        if ($folder) {
            $folder .= '/original';
        }

        return (new Media($folder ?? '', $this->getMediaOrder()))->setTimestamps();
    }

    /**
     * @param array $files
     * @return void
     */
    protected function setUpdatedMedia(array $files): void
    {
        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];

        $media = $this->getMedia();
        if (!$media instanceof MediaUploadInterface) {
            return;
        }

        $filesystem = Filesystem::getInstance(false);

        $list = [];
        $list_original = [];
        foreach ($files as $field => $group) {
            // Ignore files without a field.
            if ($field === '') {
                continue;
            }
            $field = (string)$field;

            // Load settings for the field.
            $settings = $this->getMediaFieldSettings($field);
            foreach ($group as $filename => $file) {
                if ($file) {
                    // File upload.
                    $filename = $file->getClientFilename();

                    /** @var FormFlashFile $file */
                    $data = $file->jsonSerialize();
                    unset($data['tmp_name'], $data['path']);
                } else {
                    // File delete.
                    $data = null;
                }

                if ($file) {
                    // Check file upload against media limits (except for max size).
                    $filename = $media->checkUploadedFile($file, $filename, ['filesize' => 0] + $settings);
                }

                $self = $settings['self'];
                if ($this->_loadMedia && $self) {
                    $filepath = $filename;
                } else {
                    $filepath = "{$settings['destination']}/{$filename}";

                    // For backwards compatibility we are always using relative path from the installation root.
                    if ($locator->isStream($filepath)) {
                        $filepath = $locator->findResource($filepath, false, true);
                    }
                }

                // Special handling for original images.
                if (strpos($field, '/original')) {
                    if ($this->_loadMedia && $self) {
                        $list_original[$filename] = [$file, $settings];
                    }
                    continue;
                }

                // Calculate path without the retina scaling factor.
                $realpath = $filesystem->pathname($filepath) . str_replace(['@3x', '@2x'], '', basename($filepath));

                $list[$filename] = [$file, $settings];

                $path = str_replace('.', "\n", $field);
                if (null !== $data) {
                    $data['name'] = $filename;
                    $data['path'] = $filepath;

                    $this->setNestedProperty("{$path}\n{$realpath}", $data, "\n");
                } else {
                    $this->unsetNestedProperty("{$path}\n{$realpath}", "\n");
                }
            }
        }

        $this->clearMediaCache();

        $this->_uploads = $list;
        $this->_uploads_original = $list_original;
    }

    protected function saveUpdatedMedia(): void
    {
        $media = $this->getMedia();
        if (!$media instanceof MediaUploadInterface) {
            throw new RuntimeException('Internal error UO101');
        }

        // Upload/delete original sized images.
        /**
         * @var string $filename
         * @var UploadedFileInterface|array|null $file
         */
        foreach ($this->_uploads_original ?? [] as $filename => $file) {
            $filename = 'original/' . $filename;
            if (is_array($file)) {
                [$file, $settings] = $file;
            } else {
                $settings = null;
            }
            if ($file instanceof UploadedFileInterface) {
                $media->copyUploadedFile($file, $filename, $settings);
            } else {
                $media->deleteFile($filename, $settings);
            }
        }

        // Upload/delete altered files.
        /**
         * @var string $filename
         * @var UploadedFileInterface|array|null $file
         */
        foreach ($this->getUpdatedMedia() as $filename => $file) {
            if (is_array($file)) {
                [$file, $settings] = $file;
            } else {
                $settings = null;
            }
            if ($file instanceof UploadedFileInterface) {
                $media->copyUploadedFile($file, $filename, $settings);
            } else {
                $media->deleteFile($filename, $settings);
            }
        }

        $this->setUpdatedMedia([]);
        $this->clearMediaCache();
    }

    /**
     * @return array
     */
    protected function doSerialize(): array
    {
        return [
            'type' => $this->getFlexType(),
            'key' => $this->getKey(),
            'elements' => $this->jsonSerialize(),
            'storage' => $this->getMetaData()
        ];
    }

    /**
     * @return UserGroupIndex
     */
    protected function getUserGroups()
    {
        $grav = Grav::instance();

        /** @var Flex $flex */
        $flex = $grav['flex'];

        /** @var UserGroupCollection|null $groups */
        $groups = $flex->getDirectory('user-groups');
        if ($groups) {
            /** @var UserGroupIndex $index */
            $index = $groups->getIndex();

            return $index;
        }

        return $grav['user_groups'];
    }

    /**
     * @return UserGroupIndex
     */
    protected function getGroups()
    {
        if (null === $this->_groups) {
            $this->_groups = $this->getUserGroups()->select((array)$this->getProperty('groups'));
        }

        return $this->_groups;
    }

    /**
     * @return Access
     */
    protected function getAccess(): Access
    {
        if (null === $this->_access) {
            $this->getProperty('access');
        }

        return $this->_access;
    }

    /**
     * @param mixed $value
     * @return array
     */
    protected function offsetLoad_access($value): array
    {
        if (!$value instanceof Access) {
            $value = new Access($value);
        }

        $this->_access = $value;

        return $value->jsonSerialize();
    }

    /**
     * @param mixed $value
     * @return array
     */
    protected function offsetPrepare_access($value): array
    {
        return $this->offsetLoad_access($value);
    }

    /**
     * @param array|null $value
     * @return array|null
     */
    protected function offsetSerialize_access(?array $value): ?array
    {
        return $value;
    }
}
