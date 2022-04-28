<?php declare(strict_types=1);

namespace Grav\Framework\Contracts\Relationships;

use ArrayAccess;
use Countable;
use Iterator;
use JsonSerializable;

/**
 * Interface RelationshipsInterface
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
     * @return RelationshipInterface|null
     */
    public function offsetGet($offset): ?RelationshipInterface;

    /**
     * @return RelationshipInterface|null
     */
    public function current(): ?RelationshipInterface;

    /**
     * @return string
     * @phpstan-pure
     */
    public function key(): string;
}
