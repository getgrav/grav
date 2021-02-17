<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Interfaces;

use ArrayAccess;
use Grav\Common\Data\Blueprint;
use Grav\Framework\Flex\Flex;
use Grav\Framework\Object\Interfaces\NestedObjectInterface;
use Grav\Framework\Flex\FlexDirectory;
use InvalidArgumentException;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

/**
 * Defines Flex Objects.
 *
 * @used-by \Grav\Framework\Flex\FlexObject
 * @since 1.6
 */
interface FlexObjectInterface extends FlexCommonInterface, NestedObjectInterface, ArrayAccess
{
    /**
     * Construct a new Flex Object instance.
     *
     * @used-by FlexDirectory::createObject()   Method to create Flex Object.
     *
     * @param array $elements Array of object properties.
     * @param string $key Identifier key for the new object.
     * @param FlexDirectory $directory Flex Directory the object belongs into.
     * @param bool $validate True if the object should be validated against blueprint.
     * @throws InvalidArgumentException
     */
    public function __construct(array $elements, $key, FlexDirectory $directory, bool $validate = false);

    /**
     * Search a string from the object, returns weight between 0 and 1.
     *
     * Note: If you override this function, make sure you return value in range 0...1!
     *
     * @used-by FlexCollectionInterface::search()   If you want to search a string from a Flex Collection.
     *
     * @param string                $search     Search string.
     * @param string|string[]|null  $properties Properties to search for, defaults to configured properties.
     * @param array|null            $options    Search options, defaults to configured options.
     * @return float                Returns a weight between 0 and 1.
     * @api
     */
    public function search(string $search, $properties = null, array $options = null): float;

    /**
     * Returns true if object has a key.
     *
     * @return bool
     */
    public function hasKey();

    /**
     * Get a unique key for the object.
     *
     * Flex Keys can be used without knowing the Directory the Object belongs into.
     *
     * @see Flex::getObject()   If you want to get Flex Object from any Flex Directory.
     * @see Flex::getObjects()  If you want to get list of Flex Objects from any Flex Directory.
     *
     * NOTE: Please do not override the method!
     *
     * @return string Returns Flex Key of the object.
     * @api
     */
    public function getFlexKey(): string;

    /**
     * Get an unique storage key (within the directory) which is used for figuring out the filename or database id.
     *
     * @see FlexDirectory::getObject()      If you want to get Flex Object from the Flex Directory.
     * @see FlexDirectory::getCollection()  If you want to get Flex Collection with selected keys from the Flex Directory.
     *
     * @return string Returns storage key of the Object.
     * @api
     */
    public function getStorageKey(): string;

    /**
     * Get index data associated to the object.
     *
     * @return array Returns metadata of the object.
     */
    public function getMetaData(): array;

    /**
     * Returns true if the object exists in the storage.
     *
     * @return bool Returns `true` if the object exists, `false` otherwise.
     * @api
     */
    public function exists(): bool;

    /**
     * Prepare object for saving into the storage.
     *
     * @return array Returns an array of object properties containing only scalars and arrays.
     */
    public function prepareStorage(): array;

    /**
     * Updates object in the memory.
     *
     * @see FlexObjectInterface::save() You need to save the object after calling this method.
     *
     * @param array $data   Data containing updated properties with their values. To unset a value, use `null`.
     * @param array|UploadedFileInterface[] $files List of uploaded files to be saved within the object.
     * @return static
     * @throws RuntimeException
     * @api
     */
    public function update(array $data, array $files = []);

    /**
     * Create new object into the storage.
     *
     * @see FlexDirectory::createObject() If you want to create a new object instance.
     * @see FlexObjectInterface::update() If you want to update properties of the object.
     *
     * @param string|null $key Optional new key. If key isn't given, random key will be associated to the object.
     * @return static
     * @throws RuntimeException if object already exists.
     * @api
     */
    public function create(string $key = null);

    /**
     * Save object into the storage.
     *
     * @see FlexObjectInterface::update() If you want to update properties of the object.
     *
     * @return static
     * @api
     */
    public function save();

    /**
     * Delete object from the storage.
     *
     * @return static
     * @api
     */
    public function delete();

    /**
     * Returns the blueprint of the object.
     *
     * @see FlexObjectInterface::getForm()
     * @used-by FlexForm::getBlueprint()
     *
     * @param string $name Name of the Blueprint form. Used to create customized forms for different use cases.
     * @return Blueprint Returns a Blueprint.
     */
    public function getBlueprint(string $name = '');

    /**
     * Returns a form instance for the object.
     *
     * @param string $name Name of the form. Can be used to create customized forms for different use cases.
     * @param array|null $options  Options can be used to further customize the form.
     * @return FlexFormInterface Returns a Form.
     * @api
     */
    public function getForm(string $name = '', array $options = null);

    /**
     * Returns default value suitable to be used in a form for the given property.
     *
     * @see FlexObjectInterface::getForm()
     *
     * @param  string $name         Property name.
     * @param  string|null $separator   Optional nested property separator.
     * @return mixed|null           Returns default value of the field, null if there is no default value.
     */
    public function getDefaultValue(string $name, string $separator = null);

    /**
     * Returns default values suitable to be used in a form for the given property.
     *
     * @see FlexObjectInterface::getForm()
     *
     * @return array                Returns default values.
     */
    public function getDefaultValues(): array;

    /**
     * Returns raw value suitable to be used in a form for the given property.
     *
     * @see FlexObjectInterface::getForm()
     *
     * @param  string $name         Property name.
     * @param  mixed  $default      Default value.
     * @param  string|null $separator   Optional nested property separator.
     * @return mixed                Returns value of the field.
     */
    public function getFormValue(string $name, $default = null, string $separator = null);
}
