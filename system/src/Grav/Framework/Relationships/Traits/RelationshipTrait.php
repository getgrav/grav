<?php declare(strict_types=1);

namespace Grav\Framework\Relationships\Traits;

use Grav\Framework\Contracts\Object\IdentifierInterface;
use Grav\Framework\Flex\FlexIdentifier;
use Grav\Framework\Media\MediaIdentifier;
use Grav\Framework\Object\Identifiers\Identifier;
use RuntimeException;
use function get_class;

/**
 * Trait RelationshipTrait
 *
 * @template T of object
 */
trait RelationshipTrait
{
    /** @var IdentifierInterface */
    protected $parent;
    /** @var string */
    protected $name;
    /** @var string */
    protected $type;
    /** @var array */
    protected $options;
    /** @var bool */
    protected $modified = false;

    /**
     * @return string
     * @phpstan-pure
     */
    public function getName(): string
    {
        return $this->name;
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
     * @return bool
     * @phpstan-pure
     */
    public function isModified(): bool
    {
        return $this->modified;
    }

    /**
     * @return IdentifierInterface
     * @phpstan-pure
     */
    public function getParent(): IdentifierInterface
    {
        return $this->parent;
    }

    /**
     * @param IdentifierInterface $identifier
     * @return bool
     * @phpstan-pure
     */
    public function hasIdentifier(IdentifierInterface $identifier): bool
    {
        return $this->getIdentifier($identifier->getId(), $identifier->getType()) !== null;
    }

    /**
     * @return int
     * @phpstan-pure
     */
    abstract public function count(): int;

    /**
     * @return void
     * @phpstan-pure
     */
    public function check(): void
    {
        $min = $this->options['min'] ?? 0;
        $max = $this->options['max'] ?? 0;

        if ($min || $max) {
            $count = $this->count();
            if ($min && $count < $min) {
                throw new RuntimeException(sprintf('%s relationship has too few objects in it', $this->name));
            }
            if ($max && $count > $max) {
                throw new RuntimeException(sprintf('%s relationship has too many objects in it', $this->name));
            }
        }
    }

    /**
     * @param IdentifierInterface $identifier
     * @return IdentifierInterface
     */
    private function checkIdentifier(IdentifierInterface $identifier): IdentifierInterface
    {
        if ($this->type !== $identifier->getType()) {
            throw new RuntimeException(sprintf('Bad identifier type %s', $identifier->getType()));
        }

        if (get_class($identifier) !== Identifier::class) {
            return $identifier;
        }

        if ($this->type === 'media') {
            return new MediaIdentifier($identifier->getId());
        }

        return new FlexIdentifier($identifier->getId(), $identifier->getType());
    }

    private function parseOptions(array $options): void
    {
        $this->type = $options['type'];
        $this->options = $options;
    }
}
