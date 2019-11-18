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
    /** @var FlexObjectInterface */
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
        $serialized = parent::jsonSerialize();

        $object = $this->getObject();
        if ($object instanceof FlexObjectInterface) {
            $serialized['object'] = [
                'type' => $object->getFlexType(),
                'key' => $object->getKey() ?: null,
                'storage_key' => $object->getStorageKey(),
                'timestamp' => $object->getTimestamp(),
                'serialized' => $object->prepareStorage()
            ];
        }

        return $serialized;
    }

    protected function init(?array $data, array $config): void
    {
        parent::init($data, $config);

        /** @var FlexObjectInterface $object */
        $object = $config['object'];
        if (isset($data['object']['serialized']) && !$object->exists()) {
            // TODO: update instead of create new.
            $object = $object->getFlexDirectory()->createObject($data['object']['serialized'], $data['object']['key']);
        }

        $this->setObject($object);
    }
}
