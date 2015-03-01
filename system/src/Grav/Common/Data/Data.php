<?php
namespace Grav\Common\Data;

use RocketTheme\Toolbox\ArrayTraits\ArrayAccessWithGetters;
use RocketTheme\Toolbox\ArrayTraits\Countable;
use RocketTheme\Toolbox\ArrayTraits\Export;
use RocketTheme\Toolbox\File\File;
use RocketTheme\Toolbox\File\FileInterface;

/**
 * Recursive data object
 *
 * @author RocketTheme
 * @license MIT
 */
class Data implements DataInterface
{
    use ArrayAccessWithGetters, Countable, Export, DataMutatorTrait;

    protected $gettersVariable = 'items';
    protected $items;

    /**
     * @var Blueprints
     */
    protected $blueprints;

    /**
     * @var File
     */
    protected $storage;

    /**
     * @param array $items
     * @param Blueprint $blueprints
     */
    public function __construct(array $items = array(), Blueprint $blueprints = null)
    {
        $this->items = $items;

        $this->blueprints = $blueprints;
    }

    /**
     * Get value by using dot notation for nested arrays/objects.
     *
     * @example $value = $data->value('this.is.my.nested.variable');
     *
     * @param string  $name       Dot separated path to the requested value.
     * @param mixed   $default    Default value (or null).
     * @param string  $separator  Separator, defaults to '.'
     * @return mixed  Value.
     */
    public function value($name, $default = null, $separator = '.')
    {
        return $this->get($name, $default, $separator);
    }

    /**
     * Set default value by using dot notation for nested arrays/objects.
     *
     * @example $data->def('this.is.my.nested.variable', 'default');
     *
     * @param string  $name       Dot separated path to the requested value.
     * @param mixed   $default    Default value (or null).
     * @param string  $separator  Separator, defaults to '.'
     */
    public function def($name, $default = null, $separator = '.')
    {
        $this->set($name, $this->get($name, $default, $separator), $separator);
    }

    /**
     * Join two values together by using blueprints if available.
     *
     * @param string  $name       Dot separated path to the requested value.
     * @param mixed   $value      Value to be joined.
     * @param string  $separator  Separator, defaults to '.'
     */
    public function join($name, $value, $separator = '.')
    {
        $old = $this->get($name, null, $separator);
        if ($old === null) {
            // Variable does not exist yet: just use the incoming value.
        } elseif ($this->blueprints) {
            // Blueprints: join values by using blueprints.
            $value = $this->blueprints->mergeData($old, $value, $name, $separator);
        } else {
            // No blueprints: replace existing top level variables with the new ones.
            $value = array_merge($old, $value);
        }

        $this->set($name, $value, $separator);
    }

    /**
     * Join two values together by using blueprints if available.
     *
     * @param string  $name       Dot separated path to the requested value.
     * @param mixed   $value      Value to be joined.
     * @param string  $separator  Separator, defaults to '.'
     */
    public function joinDefaults($name, $value, $separator = '.')
    {
        $old = $this->get($name, null, $separator);
        if ($old === null) {
            // Variable does not exist yet: just use the incoming value.
        } elseif ($this->blueprints) {
            // Blueprints: join values by using blueprints.
            $value = $this->blueprints->mergeData($value, $old, $name, $separator);
        } else {
            // No blueprints: replace existing top level variables with the new ones.
            $value = array_merge($value, $old);
        }

        $this->set($name, $value, $separator);
    }


    /**
     * Merge two sets of data together.
     *
     * @param array $data
     * @return void
     */
    public function merge(array $data)
    {
        if ($this->blueprints) {
            $this->items = $this->blueprints->mergeData($this->items, $data);
        } else {
            $this->items = array_merge($this->items, $data);
        }
    }

    /**
     * Add default data to the set.
     *
     * @param array $data
     * @return void
     */
    public function setDefaults(array $data)
    {
        if ($this->blueprints) {
            $this->items = $this->blueprints->mergeData($data, $this->items);
        } else {
            $this->items = array_merge($data, $this->items);
        }
    }

    /**
     * Return blueprints.
     *
     * @return Blueprint
     */
    public function blueprints()
    {
        return $this->blueprints;
    }

    /**
     * Validate by blueprints.
     *
     * @throws \Exception
     */
    public function validate()
    {
        if ($this->blueprints) {
            $this->blueprints->validate($this->items);
        }
    }

    /**
     * Filter all items by using blueprints.
     */
    public function filter()
    {
        if ($this->blueprints) {
            $this->items = $this->blueprints->filter($this->items);
        }
    }

    /**
     * Get extra items which haven't been defined in blueprints.
     *
     * @return array
     */
    public function extra()
    {
        return $this->blueprints ? $this->blueprints->extra($this->items) : array();
    }

    /**
     * Save data if storage has been defined.
     */
    public function save()
    {
        $file = $this->file();
        if ($file) {
            $file->save($this->items);
        }
    }

    /**
     * Returns whether the data already exists in the storage.
     *
     * NOTE: This method does not check if the data is current.
     *
     * @return bool
     */
    public function exists()
    {
        return $this->file()->exists();
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
        return $this->file()->raw();
    }

    /**
     * Set or get the data storage.
     *
     * @param FileInterface $storage Optionally enter a new storage.
     * @return FileInterface
     */
    public function file(FileInterface $storage = null)
    {
        if ($storage) {
            $this->storage = $storage;
        }
        return $this->storage;
    }
}
