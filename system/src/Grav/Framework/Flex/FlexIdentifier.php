<?php declare(strict_types=1);

namespace Grav\Framework\Flex;

use Grav\Common\Grav;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use Grav\Framework\Object\Identifiers\Identifier;
use RuntimeException;

/**
 * Interface IdentifierInterface
 *
 * @template T of FlexObjectInterface
 * @extends Identifier<T>
 */
class FlexIdentifier extends Identifier
{
    /** @var string */
    private $keyField;
    /** @var FlexObjectInterface|null */
    private $object = null;

    /**
     * @param FlexObjectInterface $object
     * @return FlexIdentifier<T>
     */
    public static function createFromObject(FlexObjectInterface $object): FlexIdentifier
    {
        $instance = new static($object->getKey(), $object->getFlexType(), 'key');
        $instance->setObject($object);

        return $instance;
    }

    /**
     * IdentifierInterface constructor.
     * @param string $id
     * @param string $type
     * @param string $keyField
     */
    public function __construct(string $id, string $type, string $keyField = 'key')
    {
        parent::__construct($id, $type);

        $this->keyField = $keyField;
    }

    /**
     * @return T
     */
    public function getObject(): ?FlexObjectInterface
    {
        if (!isset($this->object)) {
            /** @var Flex $flex */
            $flex = Grav::instance()['flex'];

            $this->object = $flex->getObject($this->getId(), $this->getType(), $this->keyField);
        }

        return $this->object;
    }

    /**
     * @param T $object
     */
    public function setObject(FlexObjectInterface $object): void
    {
        $type = $this->getType();
        if ($type !== $object->getFlexType()) {
            throw new RuntimeException(sprintf('Object has to be type %s, %s given', $type, $object->getFlexType()));
        }

        $this->object = $object;
    }
}
