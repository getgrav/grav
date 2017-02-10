<?php
namespace Grav\Common\Collection;

use RocketTheme\Toolbox\ArrayTraits\ArrayAccess;

trait ObjectCollectionTrait
{
    use ArrayAccess;

    /**
     * @var string
     */
    protected $keyProperty = null;

    /**
     * Add item to the list.
     *
     * @param object $object
     * @param string $key
     * @return $this
     */
    public function add($object, $key = null)
    {
        $objKey = $key ?: $this->getObjectKey($object);
        $this->offsetSet(is_null($objKey) ? $key : $objKey, $object);

        return $this;
    }

    /**
     * Remove item from the list.
     *
     * @param string|object $key
     * @return $this
     */
    public function remove($key)
    {
        if (is_object($key)) {
            $key = $this->getObjectKey($key);
            if (is_null($key)) {
                return $this;
            }
        }
        $this->offsetUnset($key);

        return $this;
    }

    /**
     * @param string $property      Object property to be fetched.
     * @return array                Values of the property.
     */
    public function get($property)
    {
        $list = [];

        foreach ($this as $id => $object) {
            $key = $this->getObjectKey($object);
            $list[is_null($key) ? $id : $key] = $this->getObjectValue($object, $property);
        }

        return $list;
    }

    /**
     * @param string $property  Object property to be updated.
     * @param string $value     New value.
     * @return $this
     */
    public function set($property, $value)
    {
        foreach ($this as $object) {
            $object->{$property} = $value;
        }

        return $this;
    }

    /**
     * @param string $name          Method name.
     * @param array  $arguments     List of arguments passed to the function.
     * @return array                Return values.
     */
    public function __call($name, array $arguments)
    {
        $list = [];

        foreach ($this as $id => $object) {
            $key = $this->getObjectKey($object);
            $list[is_null($key) ? $id : $key] = $this->getObjectCallResult($object, $name, $arguments);
        }

        return $list;
    }

    /**
     * @param object $object
     * @return string|null
     */
    protected function getObjectKey($object)
    {
        $keyProperty = $this->keyProperty;

        return $keyProperty && isset($object->{$keyProperty}) ? (string) $object->{$keyProperty} : null;
    }

    /**
     * @param object $object
     * @param string $property
     * @return mixed
     */
    protected function getObjectValue($object, $property)
    {
        return isset($object->{$property}) ? $object->{$property} : null;
    }

    /**
     * @param object $object
     * @param string $name;
     * @param array  $arguments
     * @return mixed
     */
    protected function getObjectCallResult($object, $name, $arguments)
    {
        return method_exists($object, $name) ? call_user_func_array([$object, $name], $arguments) : null;
    }
}
