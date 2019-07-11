<?php

/**
 * @package    Grav\Common\Flex
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex;

use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use Grav\Framework\Form\FormFlash;

class FlexFormFlash extends FormFlash
{
    /**
     * @var FlexObjectInterface
     */
    protected $object;

    public function setObject(FlexObjectInterface $object)
    {
        $this->object = $object;
    }

    public function getObject(): FlexObjectInterface
    {
        return $this->object;
    }

    public function jsonSerialize(): array
    {
        $object = $this->getObject();

        $serialized = parent::jsonSerialize();
        if ($object) {
            $serialized['object'] = [
                'type' => $object->getFlexType(),
                'key' => $object->hasKey() ? $object->getKey() : null,
                'storage_key' => $object->exists() ? $object->getStorageKey() : null,
                'timestamp' => $object->getTimestamp(),
                'serialized' => $object->jsonSerialize()
            ];
        }

        return $serialized;
    }

    protected function init(?array $data, array $config): void
    {
        parent::init($data, $config);

        $object = $config['object'] ?? null;

        if ($object) {
            $this->setObject($object);
            /*
            $serialized = $data['object'] ?? null;
            if ($serialized && !$object->exists()) {
                $fields = $data['object']['serialized'] ?? [];

                $object->update($fields);
            }
            */
        }
    }
}
