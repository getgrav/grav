<?php

/**
 * Symfony-backed cache provider that implements the legacy Doctrine Cache API.
 */

namespace Grav\Common\Cache;

use Doctrine\Common\Cache\CacheProvider;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use function array_map;
use function rawurlencode;

class SymfonyCacheProvider extends CacheProvider
{
    /** @var AdapterInterface */
    private $adapter;

    public function __construct(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * Expose the underlying Symfony cache pool for callers needing direct access.
     */
    public function getAdapter(): AdapterInterface
    {
        return $this->adapter;
    }

    /**
     * {@inheritdoc}
     */
    protected function doFetch($id)
    {
        try {
            $item = $this->adapter->getItem($this->encode($id));
        } catch (InvalidArgumentException) {
            return false;
        }

        return $item->isHit() ? $item->get() : false;
    }

    /**
     * {@inheritdoc}
     */
    protected function doFetchMultiple(array $keys)
    {
        if (!$keys) {
            return [];
        }

        $encoded = array_map([$this, 'encode'], $keys);

        try {
            $items = $this->adapter->getItems($encoded);
        } catch (InvalidArgumentException) {
            return [];
        }

        $results = [];
        foreach ($items as $encodedKey => $item) {
            if ($item->isHit()) {
                $results[$encodedKey] = $item->get();
            }
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    protected function doContains($id)
    {
        try {
            return $this->adapter->hasItem($this->encode($id));
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function doSave($id, $data, $lifeTime = 0)
    {
        try {
            $item = $this->adapter->getItem($this->encode($id));
        } catch (InvalidArgumentException) {
            return false;
        }

        if ($lifeTime > 0) {
            $item->expiresAfter($lifeTime);
        }

        return $this->adapter->save($item->set($data));
    }

    /**
     * {@inheritdoc}
     */
    protected function doSaveMultiple(array $keysAndValues, $lifetime = 0)
    {
        if (!$keysAndValues) {
            return true;
        }

        $success = true;
        foreach ($keysAndValues as $key => $value) {
            if (!$this->doSave($key, $value, $lifetime)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    protected function doDelete($id)
    {
        try {
            return $this->adapter->deleteItem($this->encode($id));
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function doDeleteMultiple(array $keys)
    {
        if (!$keys) {
            return true;
        }

        try {
            return $this->adapter->deleteItems(array_map([$this, 'encode'], $keys));
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function doFlush()
    {
        return $this->adapter->clear();
    }

    /**
     * {@inheritdoc}
     */
    protected function doGetStats()
    {
        return null;
    }

    private function encode(string $id): string
    {
        return rawurlencode($id);
    }
}
