<?php
namespace Grav\Common\Object\Storage;

use Grav\Common\Object\AbstractObject;

class FilesystemStorage implements StorageInterface
{
    /**
     * @param array $keys
     * @return array
     */
    public function load(array $keys)
    {
        // TODO
        return [];
    }

    /**
     * @param AbstractObject $object
     * @return string|int Id
     */
    public function save(AbstractObject $object)
    {
        // TODO
        return 'xxx';
    }

    /**
     * @param AbstractObject $object
     * @return bool
     */
    public function delete(AbstractObject $object)
    {
        // TODO
        return false;
    }

    /**
     * @param array|int[]|string[] $list
     * @return array
     */
    public function loadList(array $list)
    {
        // TODO
        return [];
    }

    /**
     * @param array $query
     * @return int
     */
    public function count(array $query)
    {
        // TODO
        return 0;
    }

    /**
     * @param array $query
     * @return array|int[]|string[]
     */
    public function find(array $query)
    {
        // TODO
        return [];
    }
}
