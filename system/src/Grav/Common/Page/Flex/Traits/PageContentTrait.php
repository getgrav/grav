<?php

declare(strict_types=1);

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Flex\Traits;

use Grav\Common\Utils;

/**
 * Implements PageContentInterface.
 *
 * @phan-file-suppress PhanUndeclaredMethod
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
            $value = $this->language() . ($var ?? ($this->modified() . md5($this->filePath())));

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
     */
    public function isPage(): bool
    {
        // FIXME: needs to be better
        return !$this->exists() || !empty($this->getLanguages()) || $this->modular();
    }
}
