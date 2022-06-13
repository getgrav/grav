<?php declare(strict_types=1);

namespace Grav\Framework\Contracts\Relationships;

use ArrayAccess;
use Countable;
use Iterator;
use JsonSerializable;

/**
 * Interface RelationshipsInterface
 *
 * @template T of \Grav\Framework\Contracts\Object\IdentifierInterface
 * @template P of \Grav\Framework\Contracts\Object\IdentifierInterface
 * @extends ArrayAccess<string,RelationshipInterface<T,P>>
 * @extends Iterator<string,RelationshipInterface<T,P>>
 */
interface RelationshipsInterface extends Countable, ArrayAccess, Iterator, JsonSerializable
{
    /**
     * @return bool
     * @phpstan-pure
     */
    public function isModified(): bool;

    /**
     * @return array
     */
    public function getModified(): array;

    /**
     * @return int
     * @phpstan-pure
     */
    public function count(): int;

    /**
     * @param string $offset
     * @return RelationshipInterface<T,P>|null
     */
    public function offsetGet($offset): ?RelationshipInterface;

    /**
     * @return RelationshipInterface<T,P>|null
     */
    public function current(): ?RelationshipInterface;

    /**
     * @return string
     * @phpstan-pure
     */
    public function key(): string;
}
