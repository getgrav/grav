<?php declare(strict_types=1);

namespace Grav\Framework\Contracts\Relationships;

use Grav\Framework\Contracts\Object\IdentifierInterface;

/**
 * Interface ToOneRelationshipInterface
 *
 * @template T of IdentifierInterface
 * @template P of IdentifierInterface
 * @template-extends RelationshipInterface<T,P>
 */
interface ToOneRelationshipInterface extends RelationshipInterface
{
    /**
     * @param string|null $id
     * @param string|null $type
     * @return T|null
     * @phpstan-pure
     */
    public function getIdentifier(string $id = null, string $type = null): ?IdentifierInterface;

    /**
     * @param string|null $id
     * @param string|null $type
     * @return T|null
     * @phpstan-pure
     */
    public function getObject(string $id = null, string $type = null): ?object;

    /**
     * @param T|null $identifier
     * @return bool
     */
    public function replaceIdentifier(IdentifierInterface $identifier = null): bool;
}
