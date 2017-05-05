<?php
/**
 * @package    Grav\Framework\Object
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Object;

/**
 * Stored Object Interface
 * @package Grav\Framework\Object
 */
interface StoredObjectInterface
{

    /**
     * Convert instance to a read only object.
     *
     * @return $this
     */
    public function readonly();

    /**
     * Returns true if the object exists in the storage.
     *
     * @return  boolean  True if object exists.
     */
    public function isSaved();

    /**
     * Method to load object from the storage.
     *
     * @param   null|int|string|array $keys An optional primary key value to load the object by, or an array of fields
     *                                      to match. If not set the instance key value is used.
     *
     * @return  boolean  True on success, false if the object doesn't exist.
     */
    public function load($keys = null);

    /**
     * Method to save the object to the storage.
     *
     * Before saving the object, this method checks if object can be safely saved.
     *
     * @return  boolean  True on success.
     */
    public function save();

    /**
     * Method to delete the object from the database.
     *
     * @return boolean True on success.
     */
    public function delete();
}
