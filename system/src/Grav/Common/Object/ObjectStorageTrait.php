<?php
namespace Grav\Common\Object;

use Grav\Common\Object\Storage\StorageInterface;

/**
 * Abstract base class for stored objects.
 *
 * @property string $id
 */
trait ObjectStorageTrait
{
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
     * Returns the global instance to the object.
     *
     * Note that using array of fields will always make a query, but it's very useful feature if you want to search one
     * item by using arbitrary set of matching fields. If there are more than one matching object, first one gets returned.
     *
     * @param  string|array $keys        An optional primary key value to load the object by, or an array of fields to match.
     * @param  boolean      $reload      Force object to reload.
     *
     * @return  ObjectInterface
     */
    static public function instance($keys = null, $reload = false)
    {
        if (is_scalar($keys)) {
            $keys = ['id' => (string) $keys];
        }
        $id = $keys ? static::getInstanceId($keys) : null;

        // If we are creating or loading a new item or we load instance by alternative keys, we need to create a new object.
        if (!$id || !isset(static::$instances[$id])) {
            $c = get_called_class();

            /** @var ObjectStorageTrait|ObjectInterface $instance */
            $instance = new $c();
            if (!$instance->load($keys)) {
                return $instance;
            }

            // Instance exists in storage: make sure that we return the global instance.
            $id = $instance->getId();
            $reload = false;
        }

        // Return global instance from the identifier.
        $instance = static::$instances[$id];

        if ($reload) {
            $instance->load();
        }

        return $instance;
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
     * Returns true if the object has been stored.
     *
     * @return  boolean  True if object exists in storage.
     */
    public function isSaved()
    {
        return $this->getStorage()->exists($this->getStorageKey());
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
            $key = $keys ? $this->getStorageKey($keys) : null;

        } else {
            // Internal key was passed.
            $key = $keys;
            $keys = [];
        }

        $this->doLoad($this->getStorage()->load($key), $keys);
        $this->initialize();

        $id = $this->getId();
        if ($id) {
            if (!isset(static::$instances[$id])) {
                static::$instances[$id] = $this;
            }
        }

        return $this->isSaved();
    }

    /**
     * Method to save the object to the storage.
     *
     * Before saving the object, this method checks if object can be safely saved.
     *
     * @param   bool    $includeChildren
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
        $data = $this->prepareSave();

        // Save the object.
        $exists = $storage->exists($key);
        $id = $storage->save($key, $data);

        if (!$id) {
            throw new \LogicException('No id specified');
        }

        // If item was created, load the object (making sure it has been properly initialized).
        if (!$exists) {
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
        if ($this->readonly || !$this->isSaved() || !$this->onBeforeDelete()) {
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
            foreach ($this->toArray() as $field => $value) {
                if (is_object($value) && method_exists($value, 'check')) {
                    $result = $result && $value->check();
                }
            }
        }

        return $result;
    }

    // Internal functions

    abstract protected function doLoad(array $items, array $keys = []);

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

    protected function prepareSave(array $data = null)
    {
        if ($data === null) {
            $data = $this->toArray();
        }

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
     * @param array $keys
     * @return string
     */
    public function getInstanceId(array $keys)
    {
        return $this->getStorageKey($keys);
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->getStorageKey();
    }


    //abstract public function setStorageKey(array $keys = []);

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
