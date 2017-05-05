<?php
/**
 * @package    Grav\Framework\Object\Storage
 *
 * @copyright  Copyright (C) 2014 - 2017 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Object\Storage;

/**
 * Interface StorageInterface
 * @package Grav\Framework\Object\Storage
 */
interface StorageInterface
{
    /**
     * @param string $key
     * @return bool
     */
    public function exists($key);

    /**
     * @param string $key
     * @return array
     */
    public function load($key);

    /**
     * @param string $key
     * @param array $data
     * @return string
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
