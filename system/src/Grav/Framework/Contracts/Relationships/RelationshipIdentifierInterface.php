<?php declare(strict_types=1);

namespace Grav\Framework\Contracts\Relationships;

use ArrayAccess;
use Grav\Framework\Contracts\Object\IdentifierInterface;

/**
 * Interface RelationshipIdentifierInterface
 */
interface RelationshipIdentifierInterface extends IdentifierInterface
{
    /**
     * If identifier has meta.
     *
     * @return bool
     * @phpstan-pure
     */
    public function hasIdentifierMeta(): bool;

    /**
     * Get identifier meta.
     *
     * @return array<string,mixed>|ArrayAccess<string,mixed>
     * @phpstan-pure
     */
    public function getIdentifierMeta();
}
