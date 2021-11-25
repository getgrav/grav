<?php

/**
 * @package    Grav\Framework\Collection
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Collection;

use ArrayIterator;
use Closure;
use Grav\Framework\Compat\Serializable;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use InvalidArgumentException;
use function array_key_exists;
use function array_slice;
use function count;

/**
 * Abstract Index Collection.
 * @template TKey of array-key
 * @template T
 * @template C of CollectionInterface
 * @implements CollectionInterface<TKey,T>
 */
abstract class AbstractIndexCollection implements CollectionInterface
{
    use Serializable;

    /**
     * @var array
     * @phpstan-var array<TKey,T>
     */
    private $entries;

    /**
     * Initializes a new IndexCollection.
     *
     * @param array $entries
     * @phpstan-param array<TKey,T> $entries
     */
    public function __construct(array $entries = [])
    {
        $this->entries = $entries;
    }

    /**
     * {@inheritDoc}
     */
    public function toArray()
    {
        return $this->loadElements($this->entries);
    }

    /**
     * {@inheritDoc}
     */
    public function first()
    {
        $value = reset($this->entries);
        $key = (string)key($this->entries);

        return $this->loadElement($key, $value);
    }

    /**
     * {@inheritDoc}
     */
    public function last()
    {
        $value = end($this->entries);
        $key = (string)key($this->entries);

        return $this->loadElement($key, $value);
    }

    /**
     * {@inheritDoc}
     */
    public function key()
    {
        /** @phpstan-var TKey $key */
        $key = (string)key($this->entries);

        return $key;
    }

    /**
     * {@inheritDoc}
     */
    public function next()
    {
        $value = next($this->entries);
        $key = (string)key($this->entries);

        return $this->loadElement($key, $value);
    }

    /**
     * {@inheritDoc}
     */
    public function current()
    {
        $value = current($this->entries);
        $key = (string)key($this->entries);

        return $this->loadElement($key, $value);
    }

    /**
     * {@inheritDoc}
     */
    public function remove($key)
    {
        if (!array_key_exists($key, $this->entries)) {
            return null;
        }

        $value = $this->entries[$key];
        unset($this->entries[$key]);

        return $this->loadElement((string)$key, $value);
    }

    /**
     * {@inheritDoc}
     */
    public function removeElement($element)
    {
        $key = $this->isAllowedElement($element) ? $this->getCurrentKey($element) : null;

        if (!$key || !isset($this->entries[$key])) {
            return false;
        }

        unset($this->entries[$key]);

        return true;
    }

    /**
     * Required by interface ArrayAccess.
     *
     * {@inheritDoc}
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return $this->containsKey($offset);
    }

    /**
     * Required by interface ArrayAccess.
     *
     * {@inheritDoc}
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * Required by interface ArrayAccess.
     *
     * {@inheritDoc}
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        if (null === $offset) {
            $this->add($value);
        }

        $this->set($offset, $value);
    }

    /**
     * Required by interface ArrayAccess.
     *
     * {@inheritDoc}
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        $this->remove($offset);
    }

    /**
     * {@inheritDoc}
     */
    public function containsKey($key)
    {
        return isset($this->entries[$key]) || array_key_exists($key, $this->entries);
    }

    /**
     * {@inheritDoc}
     */
    public function contains($element)
    {
        $key = $this->isAllowedElement($element) ? $this->getCurrentKey($element) : null;

        return $key && isset($this->entries[$key]);
    }

    /**
     * {@inheritDoc}
     */
    public function exists(Closure $p)
    {
        return $this->loadCollection($this->entries)->exists($p);
    }

    /**
     * {@inheritDoc}
     */
    public function indexOf($element)
    {
        $key = $this->isAllowedElement($element) ? $this->getCurrentKey($element) : null;

        return $key && isset($this->entries[$key]) ? $key : false;
    }

    /**
     * {@inheritDoc}
     */
    public function get($key)
    {
        if (!isset($this->entries[$key])) {
            return null;
        }

        return $this->loadElement((string)$key, $this->entries[$key]);
    }

    /**
     * {@inheritDoc}
     */
    public function getKeys()
    {
        return array_keys($this->entries);
    }

    /**
     * {@inheritDoc}
     */
    public function getValues()
    {
        return array_values($this->loadElements($this->entries));
    }

    /**
     * {@inheritDoc}
     */
    public function count()
    {
        return count($this->entries);
    }

    /**
     * {@inheritDoc}
     */
    public function set($key, $value)
    {
        if (!$this->isAllowedElement($value)) {
            throw new InvalidArgumentException('Invalid argument $value');
        }

        $this->entries[$key] = $this->getElementMeta($value);
    }

    /**
     * {@inheritDoc}
     */
    public function add($element)
    {
        if (!$this->isAllowedElement($element)) {
            throw new InvalidArgumentException('Invalid argument $element');
        }

        $this->entries[$this->getCurrentKey($element)] = $this->getElementMeta($element);

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function isEmpty()
    {
        return empty($this->entries);
    }

    /**
     * Required by interface IteratorAggregate.
     *
     * {@inheritDoc}
     */
    public function getIterator()
    {
        return new ArrayIterator($this->loadElements());
    }

    /**
     * {@inheritDoc}
     */
    public function map(Closure $func)
    {
        return $this->loadCollection($this->entries)->map($func);
    }

    /**
     * {@inheritDoc}
     */
    public function filter(Closure $p)
    {
        return $this->loadCollection($this->entries)->filter($p);
    }

    /**
     * {@inheritDoc}
     */
    public function forAll(Closure $p)
    {
        return $this->loadCollection($this->entries)->forAll($p);
    }

    /**
     * {@inheritDoc}
     */
    public function partition(Closure $p)
    {
        return $this->loadCollection($this->entries)->partition($p);
    }

    /**
     * Returns a string representation of this object.
     *
     * @return string
     */
    public function __toString()
    {
        return __CLASS__ . '@' . spl_object_hash($this);
    }

    /**
     * {@inheritDoc}
     */
    public function clear()
    {
        $this->entries = [];
    }

    /**
     * {@inheritDoc}
     */
    public function slice($offset, $length = null)
    {
        return $this->loadElements(array_slice($this->entries, $offset, $length, true));
    }

    /**
     * @param int $start
     * @param int|null $limit
     * @return static
     * @phpstan-return static<TKey,T,C>
     */
    public function limit($start, $limit = null)
    {
        return $this->createFrom(array_slice($this->entries, $start, $limit, true));
    }

    /**
     * Reverse the order of the items.
     *
     * @return static
     * @phpstan-return static<TKey,T,C>
     */
    public function reverse()
    {
        return $this->createFrom(array_reverse($this->entries));
    }

    /**
     * Shuffle items.
     *
     * @return static
     * @phpstan-return static<TKey,T,C>
     */
    public function shuffle()
    {
        $keys = $this->getKeys();
        shuffle($keys);

        return $this->createFrom(array_replace(array_flip($keys), $this->entries) ?? []);
    }

    /**
     * Select items from collection.
     *
     * Collection is returned in the order of $keys given to the function.
     *
     * @param array $keys
     * @return static
     * @phpstan-return static<TKey,T,C>
     */
    public function select(array $keys)
    {
        $list = [];
        foreach ($keys as $key) {
            if (isset($this->entries[$key])) {
                $list[$key] = $this->entries[$key];
            }
        }

        return $this->createFrom($list);
    }

    /**
     * Un-select items from collection.
     *
     * @param array $keys
     * @return static
     * @phpstan-return static<TKey,T,C>
     */
    public function unselect(array $keys)
    {
        return $this->select(array_diff($this->getKeys(), $keys));
    }

    /**
     * Split collection into chunks.
     *
     * @param int $size     Size of each chunk.
     * @return array
     */
    public function chunk($size)
    {
        return $this->loadCollection($this->entries)->chunk($size);
    }

    /**
     * @return array
     */
    public function __serialize(): array
    {
        return [
            'entries' => $this->entries
        ];
    }

    /**
     * @param array $data
     * @return void
     */
    public function __unserialize(array $data): void
    {
        $this->entries = $data['entries'];
    }

    /**
     * Implements JsonSerializable interface.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->loadCollection()->jsonSerialize();
    }

    /**
     * Creates a new instance from the specified elements.
     *
     * This method is provided for derived classes to specify how a new
     * instance should be created when constructor semantics have changed.
     *
     * @param array $entries Elements.
     * @return static
     * @phpstan-return static<TKey,T,C>
     */
    protected function createFrom(array $entries)
    {
        return new static($entries);
    }

    /**
     * @return array
     */
    protected function getEntries(): array
    {
        return $this->entries;
    }

    /**
     * @param array $entries
     * @return void
     * @phpstan-param array<TKey,T> $entries
     */
    protected function setEntries(array $entries): void
    {
        $this->entries = $entries;
    }

    /**
     * @param FlexObjectInterface $element
     * @return string
     * @phpstan-param T $element
     * @phpstan-return TKey
     */
    protected function getCurrentKey($element)
    {
        return $element->getKey();
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return mixed|null
     */
    abstract protected function loadElement($key, $value);

    /**
     * @param array|null $entries
     * @return array
     * @phpstan-return array<TKey,T>
     */
    abstract protected function loadElements(array $entries = null): array;

    /**
     * @param array|null $entries
     * @return CollectionInterface
     * @phpstan-return C
     */
    abstract protected function loadCollection(array $entries = null): CollectionInterface;

    /**
     * @param mixed $value
     * @return bool
     */
    abstract protected function isAllowedElement($value): bool;

    /**
     * @param mixed $element
     * @return mixed
     */
    abstract protected function getElementMeta($element);
}
