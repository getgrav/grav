<?php
/**
 * @package    Grav\Framework\Object
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Object;

use RocketTheme\Toolbox\ArrayTraits\ArrayAccessWithGetters;
use RocketTheme\Toolbox\ArrayTraits\Export;

/**
 * Abstract base class for stored objects.
 *
 * @property string $id
 * @package Grav\Framework\Object
 */
abstract class AbstractObject implements StoredObjectInterface
{
    use ObjectStorageTrait {
        check as traitcheck;
    }
    use ArrayAccessWithGetters, Export;

    /**
     * If you don't have global storage, override this in extending class.
     * @var ObjectFinderInterface
     */
    static protected $finder;

    /**
     * Primary key for the object.
     * @var array
     */
    static protected $primaryKey = [
        'id' => null
    ];

    /**
     * Default properties for the object.
     * @var array
     */
    static protected $defaults = [];

    /**
     * @var string
     */
    static protected $collectionClass = 'Grav\\Framework\\Object\\AbstractObjectCollection';

    /**
     * Properties of the object.
     * @var array
     */
    protected $items;

    /**
     * @param ObjectFinderInterface $finder
     */
    static public function setFinder(ObjectFinderInterface $finder)
    {
        static::$finder = $finder;
    }

    /**
     * @param array     $ids        List of primary Ids or null to return everything that has been loaded.
     * @param bool      $readonly
     * @return AbstractObjectCollection
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
                $instance->doLoad($keys);
                $id = $instance->getId();
                if ($id && !isset(static::$instances[$id])) {
                    $instance->initialize();
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
     * @return string
     */
    public function getId()
    {
        return implode('-', $this->getKeys());
    }

    /**
     * @return ObjectFinderInterface
     */
    static public function search()
    {
        return static::$finder;
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
        return $this->checkKeys() && $this->traitCheck($includeChildren);
    }

    /**
     * @param array $keys
     * @return array
     */
    public function getKeys(array $keys = [])
    {
        foreach (static::$primaryKey as $key => $value) {
            if (!isset($keys[$key])) {
                if (isset($this->items[$key])) {
                    $keys[$key] = $this->items[$key];
                } else {
                    $keys[$key] = $value;
                }

            }
        }

        return $keys;
    }

    /**
     * @param array $keys
     * @return bool
     */
    public function checkKeys(array $keys = [])
    {
        if (!$keys) {
            $keys = $this->getKeys();
        }

        foreach ($keys as $key => $value) {
            if ($value === null) {
                return false;
            }
        }

        return true;
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

    /**
     * Returns a string representation of this object.
     *
     * @return string
     */
    public function __toString()
    {
        return __CLASS__ . '@' . spl_object_hash($this);
    }

    // Internal functions

    /**
     * @param array $items
     * @param array $keys
     */
    protected function doLoad(array $items, array $keys = [])
    {
        $this->items = array_replace(static::$defaults, $this->items, $this->getKeys($keys), $items);
    }
}
