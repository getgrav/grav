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
    /** @var string */
    private $id;
    /** @var string */
    private $type;

    /**
     * IdentifierInterface constructor.
     * @param string $id
     * @param string $type
     */
    public function __construct(string $id, string $type)
    {
        $this->id = $id;
        $this->type = $type;
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
