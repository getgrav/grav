<?php declare(strict_types=1);

namespace Grav\Framework\Contracts\Relationships;

use Grav\Framework\Contracts\Object\IdentifierInterface;

/**
 * Interface ToManyRelationshipInterface
 *
 * @template T of IdentifierInterface
 * @template P of IdentifierInterface
 * @template-extends RelationshipInterface<T,P>
 */
interface ToManyRelationshipInterface extends RelationshipInterface
{
    /**
     * @param positive-int $pos
     * @return IdentifierInterface|null
     */
    public function getNthIdentifier(int $pos): ?IdentifierInterface;

    /**
     * @param string $id
     * @param string|null $type
     * @return T|null
     * @phpstan-pure
     */
    public function getIdentifier(string $id, string $type = null): ?IdentifierInterface;

    /**
     * @param string $id
     * @param string|null $type
     * @return T|null
     * @phpstan-pure
     */
    public function getObject(string $id, string $type = null): ?object;

    /**
     * @param iterable<T> $identifiers
     * @return bool
     */
    public function addIdentifiers(iterable $identifiers): bool;

    /**
     * @param iterable<T> $identifiers
     * @return bool
     */
    public function replaceIdentifiers(iterable $identifiers): bool;

    /**
     * @param iterable<T> $identifiers
     * @return bool
     */
    public function removeIdentifiers(iterable $identifiers): bool;
}
