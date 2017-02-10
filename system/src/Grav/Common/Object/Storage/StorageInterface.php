<?php
namespace Grav\Common\Object\Storage;

use Grav\Common\Object\AbstractObject;

interface StorageInterface
{
    /**
     * @param array $keys
     * @return array
     */
    public function load(array $keys);

    /**
     * @param AbstractObject $object
     * @return string|int Id
     */
    public function save(AbstractObject $object);

    /**
     * @param AbstractObject $object
     * @return bool
     */
    public function delete(AbstractObject $object);

    /**
     * @param array|int[]|string[] $list
     * @return array
     */
    public function loadList(array $list);

    /**
     * @param array $query
     * @return int
     */
    public function count(array $query);

    /**
     * @param array $query
     * @return array|int[]|string[]
     */
    public function find(array $query);
}
