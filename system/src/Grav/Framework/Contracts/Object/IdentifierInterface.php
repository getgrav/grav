<?php declare(strict_types=1);

namespace Grav\Framework\Contracts\Object;

use JsonSerializable;

/**
 * Interface IdentifierInterface
 */
interface IdentifierInterface extends JsonSerializable
{
    /**
     * Get identifier's ID.
     *
     * @return string
     * @phpstan-pure
     */
    public function getId(): string;

    /**
     * Get identifier's type.
     *
     * @return string
     * @phpstan-pure
     */
    public function getType(): string;
}
