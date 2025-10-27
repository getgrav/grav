<?php

/**
 * This file provides a lightweight replacement for the legacy Doctrine Cache
 * interfaces so that existing Grav extensions depending on the Doctrine
 * namespace continue to function without the abandoned package.
 */

namespace Doctrine\Common\Cache;

/**
 * Interface for cache drivers.
 *
 * @link   www.doctrine-project.org
 */
interface Cache
{
    public const STATS_HITS             = 'hits';
    public const STATS_MISSES           = 'misses';
    public const STATS_UPTIME           = 'uptime';
    public const STATS_MEMORY_USAGE     = 'memory_usage';
    public const STATS_MEMORY_AVAILABLE = 'memory_available';
    /**
     * Only for backward compatibility (may be removed in next major release)
     *
     * @deprecated
     */
    public const STATS_MEMORY_AVAILIABLE = 'memory_available';

    /**
     * Fetches an entry from the cache.
     *
     * @param string $id The id of the cache entry to fetch.
     *
     * @return mixed The cached data or FALSE, if no cache entry exists for the given id.
     */
    public function fetch($id);

    /**
     * Tests if an entry exists in the cache.
     *
     * @param string $id The cache id of the entry to check for.
     *
     * @return bool TRUE if a cache entry exists for the given cache id, FALSE otherwise.
     */
    public function contains($id);

    /**
     * Puts data into the cache.
     *
     * If a cache entry with the given id already exists, its data will be replaced.
     *
     * @param string $id       The cache id.
     * @param mixed  $data     The cache entry/data.
     * @param int    $lifeTime The lifetime in number of seconds for this cache entry.
     *                         If zero (the default), the entry never expires (although it may be deleted from the cache
     *                         to make place for other entries).
     *
     * @return bool TRUE if the entry was successfully stored in the cache, FALSE otherwise.
     */
    public function save($id, $data, $lifeTime = 0);

    /**
     * Deletes a cache entry.
     *
     * @param string $id The cache id.
     *
     * @return bool TRUE if the cache entry was successfully deleted, FALSE otherwise.
     *              Deleting a non-existing entry is considered successful.
     */
    public function delete($id);

    /**
     * Retrieves cached information from the data store.
     *
     * @return mixed[]|null An associative array with server's statistics if available, NULL otherwise.
     */
    public function getStats();
}
