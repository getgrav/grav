<?php declare(strict_types=1);

namespace Grav\Framework\Relationships;

use Grav\Framework\Contracts\Object\IdentifierInterface;
use Grav\Framework\Contracts\Relationships\RelationshipInterface;
use Grav\Framework\Contracts\Relationships\RelationshipsInterface;
use Grav\Framework\Flex\FlexIdentifier;
use RuntimeException;
use function count;

/**
 * Class Relationships
 *
 * @template T of \Grav\Framework\Contracts\Object\IdentifierInterface
 * @template P of \Grav\Framework\Contracts\Object\IdentifierInterface
 * @implements RelationshipsInterface<T,P>
 */
class Relationships implements RelationshipsInterface
{
    /** @var P */
    protected $parent;
    /** @var array */
    protected $options;

    /** @var RelationshipInterface<T,P>[] */
    protected $relationships;

    /**
     * Relationships constructor.
     * @param P $parent
     * @param array $options
     */
    public function __construct(IdentifierInterface $parent, array $options)
    {
        $this->parent = $parent;
        $this->options = $options;
        $this->relationships = [];
    }

    /**
     * @return bool
     * @phpstan-pure
     */
    public function isModified(): bool
    {
        return !empty($this->getModified());
    }

    /**
     * @return RelationshipInterface<T,P>[]
     * @phpstan-pure
     */
    public function getModified(): array
    {
        $list = [];
        foreach ($this->relationships as $name => $relationship) {
            if ($relationship->isModified()) {
                $list[$name] = $relationship;
            }
        }

        return $list;
    }

    /**
     * @return int
     * @phpstan-pure
     */
    public function count(): int
    {
        return count($this->options);
    }

    /**
     * @param string $offset
     * @return bool
     * @phpstan-pure
     */
    public function offsetExists($offset): bool
    {
        return isset($this->options[$offset]);
    }

    /**
     * @param string $offset
     * @return RelationshipInterface<T,P>|null
     */
    public function offsetGet($offset): ?RelationshipInterface
    {
        if (!isset($this->relationships[$offset])) {
            $options = $this->options[$offset] ?? null;
            if (null === $options) {
                return null;
            }

            $this->relationships[$offset] = $this->createRelationship($offset, $options);
        }

        return $this->relationships[$offset];
    }

    /**
     * @param string $offset
     * @param mixed $value
     * @return never-return
     */
    public function offsetSet($offset, $value)
    {
        throw new RuntimeException('Setting relationship is not supported', 500);
    }

    /**
     * @param string $offset
     * @return never-return
     */
    public function offsetUnset($offset)
    {
        throw new RuntimeException('Removing relationship is not allowed', 500);
    }

    /**
     * @return RelationshipInterface<T,P>|null
     */
    public function current(): ?RelationshipInterface
    {
        $name = key($this->options);
        if ($name === null) {
            return null;
        }

        return $this->offsetGet($name);
    }

    /**
     * @return string
     * @phpstan-pure
     */
    public function key(): string
    {
        return key($this->options);
    }

    /**
     * @return void
     * @phpstan-pure
     */
    public function next(): void
    {
        next($this->options);
    }

    /**
     * @return void
     * @phpstan-pure
     */
    public function rewind(): void
    {
        reset($this->options);
    }

    /**
     * @return bool
     * @phpstan-pure
     */
    public function valid(): bool
    {
        return key($this->options) !== null;
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        $list = [];
        foreach ($this as $name => $relationship) {
            $list[$name] = $relationship->jsonSerialize();
        }

        return $list;
    }

    /**
     * @param string $name
     * @param array $options
     * @return ToOneRelationship|ToManyRelationship
     */
    private function createRelationship(string $name, array $options): RelationshipInterface
    {
        $data = null;

        $parent = $this->parent;
        if ($parent instanceof FlexIdentifier) {
            $object = $parent->getObject();
            if (!method_exists($object, 'initRelationship')) {
                throw new RuntimeException(sprintf('Bad relationship %s', $name), 500);
            }

            $data = $object->initRelationship($name);
        }

        $cardinality = $options['cardinality'] ?? '';
        switch ($cardinality) {
            case 'to-one':
                $relationship = new ToOneRelationship($parent, $name, $options, $data);
                break;
            case 'to-many':
                $relationship = new ToManyRelationship($parent, $name, $options, $data ?? []);
                break;
            default:
                throw new RuntimeException(sprintf('Bad relationship cardinality %s', $cardinality), 500);
        }

        return $relationship;
    }
}
