<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
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
     * Returns all translated languages.
     *
     * @param bool $includeDefault If set to true, return separate entries for '' and 'en' (default) language.
     * @return array
     */
    public function getLanguages(bool $includeDefault = false): array;

    /**
     * Get used language.
     *
     * @return string
     */
    public function getLanguage(): string;
}
