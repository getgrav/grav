<?php declare(strict_types=1);

namespace Grav\Framework\Relationships;

use ArrayIterator;
use Grav\Framework\Compat\Serializable;
use Grav\Framework\Contracts\Object\IdentifierInterface;
use Grav\Framework\Contracts\Relationships\ToManyRelationshipInterface;
use Grav\Framework\Relationships\Traits\RelationshipTrait;
use function count;
use function is_callable;

/**
 * Class ToManyRelationship
 *
 * @template T of IdentifierInterface
 * @template P of IdentifierInterface
 * @template-implements ToManyRelationshipInterface<T,P>
 */
class ToManyRelationship implements ToManyRelationshipInterface
{
    /** @template-use RelationshipTrait<T> */
    use RelationshipTrait;
    use Serializable;

    /** @var IdentifierInterface[] */
    protected $identifiers = [];

    /**
     * ToManyRelationship constructor.
     * @param string $name
     * @param IdentifierInterface $parent
     * @param iterable<IdentifierInterface> $identifiers
     */
    public function __construct(IdentifierInterface $parent, string $name, array $options, iterable $identifiers = [])
    {
        $this->parent = $parent;
        $this->name = $name;

        $this->parseOptions($options);
        $this->addIdentifiers($identifiers);

        $this->modified = false;
    }

    /**
     * @return string
     * @phpstan-pure
     */
    public function getCardinality(): string
    {
        return 'to-many';
    }

    /**
     * @return int
     * @phpstan-pure
     */
    public function count(): int
    {
        return count($this->identifiers);
    }

    /**
     * @return array
     */
    public function fetch(): array
    {
        $list = [];
        foreach ($this->identifiers as $identifier) {
            if (is_callable([$identifier, 'getObject'])) {
                $identifier = $identifier->getObject();
            }
            $list[] = $identifier;
        }

        return $list;
    }

    /**
     * @param string $id
     * @param string|null $type
     * @return bool
     * @phpstan-pure
     */
    public function has(string $id, string $type = null): bool
    {
        return $this->getIdentifier($id, $type) !== null;
    }

    /**
     * @param positive-int $pos
     * @return IdentifierInterface|null
     */
    public function getNthIdentifier(int $pos): ?IdentifierInterface
    {
        $items = array_keys($this->identifiers);
        $key = $items[$pos - 1] ?? null;
        if (null === $key) {
            return null;
        }

        return $this->identifiers[$key] ?? null;
    }

    /**
     * @param string $id
     * @param string|null $type
     * @return IdentifierInterface|null
     * @phpstan-pure
     */
    public function getIdentifier(string $id, string $type = null): ?IdentifierInterface
    {
        if (null === $type) {
            $type = $this->getType();
        }

        if ($type === 'media' && !str_contains($id, '/')) {
            $name = $this->name;
            $id = $this->parent->getType() . '/' . $this->parent->getId() . '/'. $name . '/' . $id;
        }

        $key = "{$type}/{$id}";

        return $this->identifiers[$key] ?? null;
    }

    /**
     * @param string $id
     * @param string|null $type
     * @return T|null
     */
    public function getObject(string $id, string $type = null): ?object
    {
        $identifier = $this->getIdentifier($id, $type);
        if ($identifier && is_callable([$identifier, 'getObject'])) {
            $identifier = $identifier->getObject();
        }

        return $identifier;
    }

    /**
     * @param IdentifierInterface $identifier
     * @return bool
     */
    public function addIdentifier(IdentifierInterface $identifier): bool
    {
        return $this->addIdentifiers([$identifier]);
    }

    /**
     * @param IdentifierInterface|null $identifier
     * @return bool
     */
    public function removeIdentifier(IdentifierInterface $identifier = null): bool
    {
        return !$identifier || $this->removeIdentifiers([$identifier]);
    }

    /**
     * @param iterable<IdentifierInterface> $identifiers
     * @return bool
     */
    public function addIdentifiers(iterable $identifiers): bool
    {
        foreach ($identifiers as $identifier) {
            $type = $identifier->getType();
            $id = $identifier->getId();
            $key = "{$type}/{$id}";

            $this->identifiers[$key] = $this->checkIdentifier($identifier);
            $this->modified = true;
        }

        return true;
    }

    /**
     * @param iterable<IdentifierInterface> $identifiers
     * @return bool
     */
    public function replaceIdentifiers(iterable $identifiers): bool
    {
        $this->identifiers = [];
        $this->modified = true;

        return $this->addIdentifiers($identifiers);
    }

    /**
     * @param iterable<IdentifierInterface> $identifiers
     * @return bool
     */
    public function removeIdentifiers(iterable $identifiers): bool
    {
        foreach ($identifiers as $identifier) {
            $type = $identifier->getType();
            $id = $identifier->getId();
            $key = "{$type}/{$id}";

            unset($this->identifiers[$key]);
            $this->modified = true;
        }

        return true;
    }

    /**
     * @return iterable<IdentifierInterface>
     * @phpstan-pure
     */
    public function getIterator(): iterable
    {
        return new ArrayIterator($this->identifiers);
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        $list = [];
        foreach ($this->getIterator() as $item) {
            $list[] = $item->jsonSerialize();
        }

        return $list;
    }

    /**
     * @return array
     */
    public function __serialize(): array
    {
        return [
            'parent' => $this->parent,
            'name' => $this->name,
            'type' => $this->type,
            'options' => $this->options,
            'modified' => $this->modified,
            'identifiers' => $this->identifiers,
        ];
    }

    /**
     * @param array $data
     * @return void
     */
    public function __unserialize(array $data): void
    {
        $this->parent = $data['parent'];
        $this->name = $data['name'];
        $this->type = $data['type'];
        $this->options = $data['options'];
        $this->modified = $data['modified'];
        $this->identifiers = $data['identifiers'];
    }
}
