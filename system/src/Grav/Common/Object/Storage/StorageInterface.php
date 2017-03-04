<?php
namespace Grav\Common\Object\Storage;

interface StorageInterface
{
    /**
     * @param string $key
     * @return array
     */
    public function load($key);

    /**
     * @param string $key
     * @param array $data
     * @return mixed
     */
    public function save($key, array $data);

    /**
     * @param string $key
     * @return bool
     */
    public function delete($key);

    /**
     * @param string[] $list
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
     * @param int   $start
     * @param int   $limit
     * @return string[]
     */
    public function find(array $query, $start = 0, $limit = 0);
}
