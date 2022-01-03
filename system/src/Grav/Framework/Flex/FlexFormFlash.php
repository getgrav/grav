<?php

/**
 * @package    Grav\Common\Flex
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex;

use Grav\Framework\Flex\Interfaces\FlexInterface;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use Grav\Framework\Form\FormFlash;

/**
 * Class FlexFormFlash
 * @package Grav\Framework\Flex
 */
class FlexFormFlash extends FormFlash
{
    /** @var FlexDirectory|null */
    protected $directory;
    /** @var FlexObjectInterface|null */
    protected $object;

    /** @var FlexInterface */
    static protected $flex;

    public static function setFlex(FlexInterface $flex): void
    {
        static::$flex = $flex;
    }

    /**
     * @param FlexObjectInterface $object
     * @return void
     */
    public function setObject(FlexObjectInterface $object): void
    {
        $this->object = $object;
        $this->directory = $object->getFlexDirectory();
    }

    /**
     * @return FlexObjectInterface|null
     */
    public function getObject(): ?FlexObjectInterface
    {
        return $this->object;
    }

    /**
     * @param FlexDirectory $directory
     */
    public function setDirectory(FlexDirectory $directory): void
    {
        $this->directory = $directory;
    }

    /**
     * @return FlexDirectory|null
     */
    public function getDirectory(): ?FlexDirectory
    {
        return $this->directory;
    }

    /**
     * @return array
     */
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
        } else {
            $directory = $this->getDirectory();
            if ($directory instanceof FlexDirectory) {
                $serialized['directory'] = [
                    'type' => $directory->getFlexType()
                ];
            }
        }

        return $serialized;
    }

    /**
     * @param array|null $data
     * @param array $config
     * @return void
     */
    protected function init(?array $data, array $config): void
    {
        parent::init($data, $config);

        $data = $data ?? [];
        /** @var FlexObjectInterface|null $object */
        $object = $config['object'] ?? null;
        $create = true;
        if ($object) {
            $directory = $object->getFlexDirectory();
            $create = !$object->exists();
        } elseif (null === ($directory = $config['directory'] ?? null)) {
            $flex = $config['flex'] ?? static::$flex;
            $type = $data['object']['type'] ?? $data['directory']['type'] ?? null;
            $directory = $flex && $type ? $flex->getDirectory($type) : null;
        }

        if ($directory && $create && isset($data['object']['serialized'])) {
            // TODO: update instead of create new.
            $object = $directory->createObject($data['object']['serialized'], $data['object']['key'] ?? '');
        }

        if ($object) {
            $this->setObject($object);
        } elseif ($directory) {
            $this->setDirectory($directory);
        }
    }
}
