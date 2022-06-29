<?php declare(strict_types=1);

namespace Grav\Framework\Contracts\Relationships;

use Countable;
use Grav\Framework\Contracts\Object\IdentifierInterface;
use IteratorAggregate;
use JsonSerializable;
use Serializable;

/**
 * Interface Relationship
 *
 * @template T of IdentifierInterface
 * @template P of IdentifierInterface
 * @extends IteratorAggregate<string, T>
 */
interface RelationshipInterface extends Countable, IteratorAggregate, JsonSerializable, Serializable
{
    /**
     * @return string
     * @phpstan-pure
     */
    public function getName(): string;

    /**
     * @return string
     * @phpstan-pure
     */
    public function getType(): string;

    /**
     * @return bool
     * @phpstan-pure
     */
    public function isModified(): bool;

    /**
     * @return string
     * @phpstan-pure
     */
    public function getCardinality(): string;

    /**
     * @return P
     * @phpstan-pure
     */
    public function getParent(): IdentifierInterface;

    /**
     * @param string $id
     * @param string|null $type
     * @return bool
     * @phpstan-pure
     */
    public function has(string $id, string $type = null): bool;

    /**
     * @param T $identifier
     * @return bool
     * @phpstan-pure
     */
    public function hasIdentifier(IdentifierInterface $identifier): bool;

    /**
     * @param T $identifier
     * @return bool
     */
    public function addIdentifier(IdentifierInterface $identifier): bool;

    /**
     * @param T|null $identifier
     * @return bool
     */
    public function removeIdentifier(IdentifierInterface $identifier = null): bool;

    /**
     * @return iterable<T>
     */
    public function getIterator(): iterable;
}
