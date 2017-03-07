<?php
namespace Grav\Common\Object;

interface ObjectInterface extends \ArrayAccess, \JsonSerializable
{
    /**
     * Returns the global instance to the object.
     *
     * Note that using array of fields will always make a query, but it's very useful feature if you want to search one
     * item by using arbitrary set of matching fields. If there are more than one matching object, first one gets returned.
     *
     * @param  int|array $keys An optional primary key value to load the object by, or an array of fields to match.
     * @param  boolean $reload Force object to reload.
     *
     * @return  Object
     */
    static public function instance($keys = null, $reload = false);

    /**
     * @param array $ids List of primary Ids or null to return everything that has been loaded.
     * @param bool $readonly
     * @return ObjectCollection
     */
    static public function instances(array $ids = null, $readonly = true);

    /**
     * Removes all or selected instances from the object cache.
     *
     * @param null|int|array $ids An optional primary key or list of keys.
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
     * Convert instance to a read only object.
     *
     * @return $this
     */
    public function readonly();

    /**
     * Returns true if the object exists.
     *
     * @param   boolean $exists Internal parameter to change state.
     *
     * @return  boolean  True if object exists.
     */
    public function exists($exists = null);

    /**
     * Method to load object from the storage.
     *
     * @param   mixed $keys An optional primary key value to load the object by, or an array of fields to match. If not
     *                           set the instance key value is used.
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
