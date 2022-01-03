<?php

declare(strict_types=1);

/**
 * @package    Grav\Common\Flex
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Flex\Types\Pages\Traits;

use Grav\Common\Utils;

/**
 * Implements PageContentInterface.
 */
trait PageContentTrait
{
    /**
     * @inheritdoc
     */
    public function id($var = null): string
    {
        $property = 'id';
        $value = null === $var ? $this->getProperty($property) : null;
        if (null === $value) {
            $value = $this->language() . ($var ?? ($this->modified() . md5($this->filePath() ?? $this->getKey())));

            $this->setProperty($property, $value);
            if ($this->doHasProperty($property)) {
                $value = $this->getProperty($property);
            }
        }

        return $value;
    }


    /**
     * @inheritdoc
     */
    public function date($var = null): int
    {
        return $this->loadHeaderProperty(
            'date',
            $var,
            function ($value) {
                $value = $value ? Utils::date2timestamp($value, $this->getProperty('dateformat')) : false;

                if (!$value) {
                    // Get the specific translation updated date.
                    $meta = $this->getMetaData();
                    $language = $meta['lang'] ?? '';
                    $template = $this->getProperty('template');
                    $value = $meta['markdown'][$language][$template] ?? 0;
                }

                return $value ?: $this->modified();
            }
        );
    }

    /**
     * @inheritdoc
     * @param bool $bool
     */
    public function isPage(bool $bool = true): bool
    {
        $meta = $this->getMetaData();

        return empty($meta['markdown']) !== $bool;
    }
}
