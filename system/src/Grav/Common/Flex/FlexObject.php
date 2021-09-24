<?php

declare(strict_types=1);

/**
 * @package    Grav\Common\Flex
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Flex;

use Grav\Common\Flex\Traits\FlexGravTrait;
use Grav\Common\Flex\Traits\FlexObjectTrait;
use Grav\Common\Media\Interfaces\MediaInterface;
use Grav\Framework\Flex\Traits\FlexMediaTrait;
use function is_array;

/**
 * Class FlexObject
 *
 * @package Grav\Common\Flex
 */
abstract class FlexObject extends \Grav\Framework\Flex\FlexObject implements MediaInterface
{
    use FlexGravTrait;
    use FlexObjectTrait;
    use FlexMediaTrait;

    /**
     * {@inheritdoc}
     * @see FlexObjectInterface::getFormValue()
     */
    public function getFormValue(string $name, $default = null, string $separator = null)
    {
        $value = $this->getNestedProperty($name, null, $separator);

        // Handle media order field.
        if (null === $value && $name === 'media_order') {
            return implode(',', $this->getMediaOrder());
        }

        // Handle media fields.
        $settings = $this->getFieldSettings($name);
        if (($settings['media_field'] ?? false) === true) {
            return $this->parseFileProperty($value, $settings);
        }

        return $value ?? $default;
    }

    /**
     * {@inheritdoc}
     * @see FlexObjectInterface::prepareStorage()
     */
    public function prepareStorage(): array
    {
        // Remove extra content from media fields.
        $fields = $this->getMediaFields();
        foreach ($fields as $field) {
            $data = $this->getNestedProperty($field);
            if (is_array($data)) {
                foreach ($data as $name => &$image) {
                    unset($image['image_url'], $image['thumb_url']);
                }
                unset($image);
                $this->setNestedProperty($field, $data);
            }
        }

        return parent::prepareStorage();
    }
}
