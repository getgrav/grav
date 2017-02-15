<?php
namespace Grav\Common\Collection;

interface ObjectCollectionInterface extends CollectionInterface
{
    /**
     * Create a copy from this collection by cloning all objects in the collection.
     *
     * @return static
     */
    public function copy();

    /**
     * @param string $property      Object property to be fetched.
     * @return array                Values of the property.
     */
    public function getProperty($property);

    /**
     * @param string $property  Object property to be updated.
     * @param string $value     New value.
     * @return $this
     */
    public function setProperty($property, $value);

    /**
     * @param string $name          Method name.
     * @param array  $arguments     List of arguments passed to the function.
     * @return array                Return values.
     */
    public function call($name, array $arguments);
}
