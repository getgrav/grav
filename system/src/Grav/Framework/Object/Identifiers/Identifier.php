<?php declare(strict_types=1);

namespace Grav\Framework\Object\Identifiers;

use Grav\Framework\Contracts\Object\IdentifierInterface;

/**
 * Interface IdentifierInterface
 *
 * @template T of object
 */
class Identifier implements IdentifierInterface
{
    /**
     * IdentifierInterface constructor.
     * @param string $id
     * @param string $type
     */
    public function __construct(private readonly string $id, private readonly string $type)
    {
    }

    /**
     * @return string
     * @phpstan-pure
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return string
     * @phpstan-pure
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id
        ];
    }

    /**
     * @return array
     */
    public function __debugInfo(): array
    {
        return $this->jsonSerialize();
    }
}
