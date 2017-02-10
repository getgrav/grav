<?php
namespace Grav\Common\Collection;

interface ObjectCollectionInterface extends CollectionInterface
{
    /**
     * @param string $property      Object property to be fetched.
     * @return array                Values of the property.
     */
    public function get($property);

    /**
     * @param string $property  Object property to be updated.
     * @param string $value     New value.
     * @return $this
     */
    public function set($property, $value);

    /**
     * @param string $name          Method name.
     * @param array  $arguments     List of arguments passed to the function.
     * @return array                Return values.
     */
    public function __call($name, array $arguments);
}
