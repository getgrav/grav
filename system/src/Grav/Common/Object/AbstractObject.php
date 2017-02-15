<?php
namespace Grav\Common\Object;

use Grav\Common\Object\Storage\StorageInterface;
use RocketTheme\Toolbox\ArrayTraits\ArrayAccessWithGetters;
use RocketTheme\Toolbox\ArrayTraits\Export;

/**
 * Abstract base class for stored objects.
 *
 * @property string $id
 */
abstract class AbstractObject implements ObjectInterface
{
    use ArrayAccessWithGetters, Export;

    /**
     * If you don't have global instance ids, override this in extending class.
     * @var array
     */
    static protected $instances = [];

    /**
     * If you don't have global storage, override this in extending class.
     * @var StorageInterface
     */
    static protected $storage;

    /**
     * If you don't have global storage, override this in extending class.
     * @var ObjectFinderInterface
     */
    static protected $finder;

    /**
     * Default properties for the object.
     * @var array
     */
    static protected $defaults = ['id' => null];

    /**
     * @var string
     */
    static protected $collectionClass = 'Grav\\Common\\Object\\ObjectCollection';

    /**
     * Properties of the object.
     * @var array
     */
    protected $items;

    /**
     * Does object exist in storage?
     * @var boolean
     */
    protected $exists = false;

    /**
     * Readonly object.
     * @var bool
     */
    protected $readonly = false;

    /**
     * @var bool
     */
    protected $initialized = false;


    /**
     * @param StorageInterface $storage
     */
    static public function setStorage(StorageInterface $storage)
    {
        static::$storage = $storage;
    }

    /**
     * @param ObjectFinderInterface $finder
     */
    static public function setFinder(ObjectFinderInterface $finder)
    {
        static::$finder = $finder;
    }

    /**
     * Returns the global instance to the object.
     *
     * Note that using array of fields will always make a query, but it's very useful feature if you want to search one
     * item by using arbitrary set of matching fields. If there are more than one matching object, first one gets returned.
     *
     * @param  string|array $keys        An optional primary key value to load the object by, or an array of fields to match.
     * @param  boolean      $reload      Force object to reload.
     *
     * @return  Object
     */
    static public function instance($keys = null, $reload = false)
    {
        // If we are creating or loading a new item or we load instance by alternative keys, we need to create a new object.
        if (!$keys || is_array($keys) || (is_scalar($keys) && !isset(static::$instances[$keys]))) {
            $c = get_called_class();
            $instance = new $c($keys);

            /** @var Object $instance */
            if (!$instance->exists()) return $instance;

            // Instance exists: make sure that we return the global instance.
            $keys = $instance->id;
        }

        // Return global instance from the identifier.
        $instance = static::$instances[$keys];

        if ($reload) {
            $instance->load();
        }

        return $instance;
    }

    /**
     * @param array     $ids        List of primary Ids or null to return everything that has been loaded.
     * @param bool      $readonly
     * @return ObjectCollection
     */
    static public function instances(array $ids = null, $readonly = true)
    {
        $collectionClass = static::$collectionClass;

        if (is_null($ids)) {
            return new $collectionClass(static::$instances);
        }

        if (empty($ids)) {
            return new $collectionClass([]);
        }

        $results = [];
        $list = [];

        foreach ($ids as $id) {
            if (!isset(static::$instances[$id])) {
                $list[] = $id;
            }
        }

        if ($list) {
            $c = get_called_class();
            $storage = static::getStorage();
            $list = $storage->loadList($list);
            foreach ($list as $keys) {
                /** @var static $instance */
                $instance = new $c();
                $instance->set($keys);
                $instance->exists(true);
                $instance->initialize();
                $id = $instance->id;
                if ($id && !isset(static::$instances[$id])) {
                    static::$instances[$id] = $instance;
                }
            }
        }

        foreach ($ids as $id) {
            if (isset(static::$instances[$id])) {
                $results[$id] = $readonly ? clone static::$instances[$id] : static::$instances[$id];
            }
        }

        return new $collectionClass($results);
    }

    /**
     * @return ObjectFinderInterface
     */
    static public function search()
    {
        return static::$finder;
    }

    /**
     * Removes all or selected instances from the object cache.
     *
     * @param null|string|array $ids    An optional primary key or list of keys.
     */
    static public function freeInstances($ids = null)
    {
        if ($ids === null) {
            $ids = array_keys(static::$instances);
        }
        $ids = (array) $ids;

        foreach ($ids as $id) {
            unset(static::$instances[$id]);
        }
    }

    /**
     * Class constructor, overridden in descendant classes.
     *
     * @param string|array  $identifier     Identifier.
     */
    public function __construct($identifier = null)
    {
        if ($identifier) {
            $this->load($identifier);
        }
    }

    /**
     * Override this function if you need to initialize object right after creating it.
     *
     * Can be used for example if the fields need to be converted from json strings to array.
     *
     * @return bool True if initialization was done, false if object was already initialized.
     */
    public function initialize()
    {
        $initialized = $this->initialized;
        $this->initialized = true;

        return !$initialized;
    }

    /**
     * Convert instance to a read only object.
     *
     * @return $this
     */
    public function readonly()
    {
        $this->readonly = true;

        return $this;
    }

    /**
     * Returns true if the object exists.
     *
     * @param   boolean  $exists  Internal parameter to change state.
     *
     * @return  boolean  True if object exists.
     */
    public function exists($exists = null)
    {
        $return = $this->exists;
        if ($exists !== null) {
            $this->exists = (bool) $exists;
        }

        return $return;
    }

    /**
     * Method to load object from the storage.
     *
     * @param   mixed    $keys   An optional primary key value to load the object by, or an array of fields to match. If not
     *                           set the instance key value is used.
     *
     * @return  boolean  True on success, false if the object doesn't exist.
     */
    public function load($keys = null)
    {
        if (is_scalar($keys)) {
            $keys = ['id' => (string) $keys];
        }

        // Get storage.
        $storage = $this->getStorage();

        // Load the object based on the keys.
        $this->items = $storage->load($keys);
        $this->exists = !empty($this->items);

        // Append the keys and defaults if they were not set by load().
        $this->items += $keys + static::$defaults;

        $this->initialize();

        if ($this->id) {
            if (!isset(static::$instances[$this->id])) {
                static::$instances[$this->id] = $this;
            }
        }

        return $this->exists;
    }

    /**
     * Method to save the object to the storage.
     *
     * Before saving the object, this method checks if object can be safely saved.
     *
     * @return  boolean  True on success.
     */
    public function save()
    {
        // Check the object.
        if ($this->readonly || !$this->check() || !$this->onBeforeSave()) {
            return false;
        }

        // Get storage.
        $storage = $this->getStorage();

        // Save the object.
        $id = $storage->save($this);
        if (!$id) {
            return false;
        }

        // If item was created, load the object.
        if (!$this->exists) {
            $this->load($id);
        }

        $this->onAfterSave();

        return true;
    }

    /**
     * Method to delete the object from the database.
     *
     * @return boolean True on success.
     */
    public function delete()
    {
        if ($this->readonly || !$this->exists || !$this->onBeforeDelete()) {
            return false;
        }

        // Get storage.
        $storage = $this->getStorage();

        if (!$storage->delete($this)) {
            return false;
        }

        $this->exists = false;

        $this->onAfterDelete();

        return true;
    }

    /**
     * Method to perform sanity checks on the instance properties to ensure they are safe to store in the storage.
     *
     * Child classes should override this method to make sure the data they are storing in the storage is safe and as
     * expected before saving the object.
     *
     * @return  boolean  True if the instance is sane and able to be stored in the storage.
     */
    public function check()
    {
        return !empty($this->id);
    }

    // Internal functions

    /**
     * @param array $items
     * @return $this
     */
    protected function set(array $items)
    {
        $this->items = $items;

        return $this;
    }

    /**
     * @return boolean
     */
    protected function onBeforeSave()
    {
        return true;
    }

    protected function onAfterSave()
    {
    }

    /**
     * @return boolean
     */
    protected function onBeforeDelete()
    {
        return true;
    }

    protected function onAfterDelete()
    {
    }

    /**
     * @return StorageInterface
     */
    static protected function getStorage()
    {
        return static::$storage;
    }
}
