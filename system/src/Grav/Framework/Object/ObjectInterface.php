<?php
/**
 * @package    Grav\Framework\Object
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Object;

/**
 * Object Interface
 * @package Grav\Framework\Object
 */
interface ObjectInterface extends \ArrayAccess, \JsonSerializable
{
    /**
     * Returns the global instance to the object.
     *
     * Note that using array of fields will always make a query, but it's very useful feature if you want to search one
     * item by using arbitrary set of matching fields. If there are more than one matching object, first one gets returned.
     *
     * @param  null|int|string|array $keys An optional primary key value to load the object by, or an array of fields to match.
     * @param  boolean $reload Force object to reload.
     *
     * @return  Object
     */
    static public function instance($keys = null, $reload = false);

    /**
     * @param array $ids List of primary Ids or null to return everything that has been loaded.
     * @param bool $readonly
     * @return AbstractObjectCollection
     */
    static public function instances(array $ids = null, $readonly = true);

    /**
     * Removes all or selected instances from the object cache.
     *
     * @param null|int|string|array $ids An optional primary key or list of keys.
     */
    static public function freeInstances($ids = null);

    /**
     * Override this function if you need to initialize object right after creating it.
     *
     * Can be used for example if the fields need to be converted from json strings to array.
     *
     * @return bool True if initialization was done, false if object was already initialized.
     */
    public function initialize();

    /**
     * Method to perform sanity checks on the instance properties to ensure they are safe to store in the storage.
     *
     * Child classes should override this method to make sure the data they are storing in the storage is safe and as
     * expected before saving the object.
     *
     * @return  boolean  True if the instance is sane and able to be stored in the storage.
     */
    public function check();
}
