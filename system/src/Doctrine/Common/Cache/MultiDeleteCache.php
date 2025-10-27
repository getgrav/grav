<?php

/**
 * Lightweight compatibility layer for the abandoned Doctrine Cache package.
 */

namespace Doctrine\Common\Cache;

/**
 * Interface for cache drivers that allows to delete many items at once.
 *
 * @deprecated
 *
 * @link   www.doctrine-project.org
 */
interface MultiDeleteCache
{
    /**
     * Deletes several cache entries.
     *
     * @param string[] $keys Array of keys to delete from cache
     *
     * @return bool TRUE if the operation was successful, FALSE if it wasn't.
     */
    public function deleteMultiple(array $keys);
}
