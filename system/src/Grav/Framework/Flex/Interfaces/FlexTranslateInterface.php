<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Interfaces;

/**
 * Implements PageTranslateInterface
 */
interface FlexTranslateInterface
{
    /**
     * Returns true if object has a translation in given language (or any of its fallback languages).
     *
     * @param string|null $languageCode
     * @param bool|null $fallback
     * @return bool
     */
    public function hasTranslation(string $languageCode = null, bool $fallback = null): bool;

    /**
     * Get translation.
     *
     * @param string|null $languageCode
     * @param bool|null $fallback
     * @return static|null
     */
    public function getTranslation(string $languageCode = null, bool $fallback = null);

    /**
     * Get used language.
     *
     * @return string|null
     */
    public function getLanguage(): ?string;
}
