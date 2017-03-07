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

            /** @var ObjectStorageTrait $instance */
            $instance = new $c();
            $instance->load($keys);

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
     * @param   bool     $getKey Internal parameter, please do not use.
     *
     * @return  bool     True on success, false if the object doesn't exist.
     */
    public function load($keys = null, $getKey = true)
    {
        if ($getKey) {
            if (is_scalar($keys)) {
                $keys = ['id' => (string) $keys];
            }

            // Fetch internal key.
            $key = $this->getStorageKey($keys);

        } else {
            // Internal key was passed.
            $key = $keys;
            $keys = [];
        }

        // Get storage.
        $storage = $this->getStorage();

        // Load the object based on the keys.
        $items = $storage->load($key);
        $this->exists = !empty($items);

        // Append the defaults and keys if they were not set by load().
        $this->items = array_merge(static::$defaults, $keys, $this->items, $items);

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
     * @params  bool    $includeChildren
     * @return  bool    True on success.
     */
    public function save($includeChildren = false)
    {
        // Check the object.
        if ($this->readonly || !$this->check($includeChildren) || !$this->onBeforeSave()) {
            return false;
        }

        // Get storage.
        $storage = $this->getStorage();
        $key = $this->getStorageKey();

        // Get data to be saved.
        $data = $this->prepareSave($this->toArray());

        // Save the object.
        $id = $storage->save($key, $data);

        if (!$id) {
            throw new \LogicException('No id specified');
        }

        // If item was created, load the object.
        if (!$this->exists) {
            $this->load($id, false);
        }

        if ($includeChildren) {
            $this->saveChildren();
        }

        $this->onAfterSave();

        return true;
    }

    /**
     * Method to delete the object from the database.
     *
     * @param bool  $includeChildren
     * @return bool True on success.
     */
    public function delete($includeChildren = false)
    {
        if ($this->readonly || !$this->exists || !$this->onBeforeDelete()) {
            return false;
        }

        if ($includeChildren) {
            $this->deleteChildren();
        }

        // Get storage.
        $storage = $this->getStorage();

        if (!$storage->delete($this->getStorageKey())) {
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
     * @return  bool  True if the instance is sane and able to be stored in the storage.
     */
    public function check($includeChildren = false)
    {
        $result = true;

        if ($includeChildren) {
            foreach ($this->items as $field => $value) {
                if (is_object($value) && method_exists($value, 'check')) {
                    $result = $result && $value->check();
                }
            }
        }

        return $result && !empty($this->items['id']);
    }

    /**
     * Implementes JsonSerializable interface.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
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
     * @return bool
     */
    protected function onBeforeSave()
    {
        return true;
    }

    protected function onAfterSave()
    {
    }

    /**
     * @return bool
     */
    protected function onBeforeDelete()
    {
        return true;
    }

    protected function onAfterDelete()
    {
    }

    protected function saveChildren()
    {
        foreach ($this->toArray() as $field => $value) {
            if (is_object($value) && method_exists($value, 'save')) {
                $value->save(true);
            }
        }
    }

    protected function deleteChildren()
    {
        foreach ($this->toArray() as $field => $value) {
            if (is_object($value) && method_exists($value, 'delete')) {
                $value->delete(true);
            }
        }
    }

    protected function prepareSave(array $data)
    {
        foreach ($data as $field => $value) {
            if (is_object($value) && method_exists($value, 'save')) {
                unset($data[$field]);
            }
        }

        return $data;
    }

    /**
     * Method to get the storage key for the object.
     *
     * @param array
     * @return string
     */
    abstract public function getStorageKey(array $keys = []);

    /**
     * @return StorageInterface
     */
    protected static function getStorage()
    {
        if (!static::$storage) {
            static::loadStorage();
        }

        return static::$storage;
    }

    protected static function loadStorage()
    {
        throw new \RuntimeException('Storage has not been set.');
    }
}
