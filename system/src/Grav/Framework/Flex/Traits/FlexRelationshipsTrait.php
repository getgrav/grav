<?php declare(strict_types=1);

namespace Grav\Framework\Flex\Traits;

use Grav\Framework\Contracts\Relationships\RelationshipInterface;
use Grav\Framework\Contracts\Relationships\RelationshipsInterface;
use Grav\Framework\Flex\FlexIdentifier;
use Grav\Framework\Relationships\Relationships;

/**
 * Trait FlexRelationshipsTrait
 */
trait FlexRelationshipsTrait
{
    /** @var RelationshipsInterface|null */
    private $_relationships = null;

    /**
     * @return Relationships
     */
    public function getRelationships(): Relationships
    {
        if (!isset($this->_relationships)) {
            $blueprint = $this->getBlueprint();
            $options = $blueprint->get('config/relationships', []);
            $parent = FlexIdentifier::createFromObject($this);

            $this->_relationships = new Relationships($parent, $options);
        }

        return $this->_relationships;
    }

    /**
     * @param string $name
     * @return RelationshipInterface|null
     */
    public function getRelationship(string $name): ?RelationshipInterface
    {
        return $this->getRelationships()[$name];
    }

    protected function resetRelationships(): void
    {
        $this->_relationships = null;
    }

    /**
     * @param iterable $collection
     * @return array
     */
    protected function buildFlexIdentifierList(iterable $collection): array
    {
        $list = [];
        foreach ($collection as $object) {
            $list[] = FlexIdentifier::createFromObject($object);
        }

        return $list;
    }
}
