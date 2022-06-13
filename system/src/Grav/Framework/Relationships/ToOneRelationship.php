<?php declare(strict_types=1);

namespace Grav\Framework\Relationships;

use ArrayIterator;
use Grav\Framework\Compat\Serializable;
use Grav\Framework\Contracts\Object\IdentifierInterface;
use Grav\Framework\Contracts\Relationships\ToOneRelationshipInterface;
use Grav\Framework\Relationships\Traits\RelationshipTrait;
use function is_callable;

/**
 * Class ToOneRelationship
 *
 * @template T of IdentifierInterface
 * @template P of IdentifierInterface
 * @template-implements ToOneRelationshipInterface<T,P>
 */
class ToOneRelationship implements ToOneRelationshipInterface
{
    /** @template-use RelationshipTrait<T> */
    use RelationshipTrait;
    use Serializable;

    /** @var IdentifierInterface|null */
    protected $identifier = null;

    public function __construct(IdentifierInterface $parent, string $name, array $options, IdentifierInterface $identifier = null)
    {
        $this->parent = $parent;
        $this->name = $name;

        $this->parseOptions($options);
        $this->replaceIdentifier($identifier);

        $this->modified = false;
    }

    /**
     * @return string
     * @phpstan-pure
     */
    public function getCardinality(): string
    {
        return 'to-one';
    }

    /**
     * @return int
     * @phpstan-pure
     */
    public function count(): int
    {
        return $this->identifier ? 1 : 0;
    }

    /**
     * @return object|null
     */
    public function fetch(): ?object
    {
        $identifier = $this->identifier;
        if (is_callable([$identifier, 'getObject'])) {
            $identifier = $identifier->getObject();
        }

        return $identifier;
    }


    /**
     * @param string|null $id
     * @param string|null $type
     * @return bool
     * @phpstan-pure
     */
    public function has(string $id = null, string $type = null): bool
    {
        return $this->getIdentifier($id, $type) !== null;
    }

    /**
     * @param string|null $id
     * @param string|null $type
     * @return IdentifierInterface|null
     * @phpstan-pure
     */
    public function getIdentifier(string $id = null, string $type = null): ?IdentifierInterface
    {
        if ($id && $this->getType() === 'media' && !str_contains($id, '/')) {
            $name = $this->name;
            $id = $this->parent->getType() . '/' . $this->parent->getId() . '/'. $name . '/' . $id;
        }

        $identifier = $this->identifier ?? null;
        if (null === $identifier || ($type && $type !== $identifier->getType()) || ($id && $id !== $identifier->getId())) {
            return null;
        }

        return $identifier;
    }

    /**
     * @param string|null $id
     * @param string|null $type
     * @return T|null
     */
    public function getObject(string $id = null, string $type = null): ?object
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
        $this->identifier = $this->checkIdentifier($identifier);
        $this->modified = true;

        return true;
    }

    /**
     * @param IdentifierInterface|null $identifier
     * @return bool
     */
    public function replaceIdentifier(IdentifierInterface $identifier = null): bool
    {
        if ($identifier === null) {
            $this->identifier = null;
            $this->modified = true;

            return true;
        }

        return $this->addIdentifier($identifier);
    }

    /**
     * @param IdentifierInterface|null $identifier
     * @return bool
     */
    public function removeIdentifier(IdentifierInterface $identifier = null): bool
    {
        if (null === $identifier || $this->has($identifier->getId(), $identifier->getType())) {
            $this->identifier = null;
            $this->modified = true;

            return true;
        }

        return false;
    }

    /**
     * @return iterable<IdentifierInterface>
     * @phpstan-pure
     */
    public function getIterator(): iterable
    {
        return new ArrayIterator((array)$this->identifier);
    }

    /**
     * @return array|null
     */
    public function jsonSerialize(): ?array
    {
        return $this->identifier ? $this->identifier->jsonSerialize() : null;
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
            'identifier' => $this->identifier,
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
        $this->identifier = $data['identifier'];
    }
}
