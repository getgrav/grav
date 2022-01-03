<?php

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Pages\Traits;

use Grav\Common\Grav;
use Grav\Common\Language\Language;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use function is_bool;

/**
 * Implements PageTranslateInterface
 */
trait PageTranslateTrait
{
    /** @var array|null */
    private $_languages;

    /** @var PageInterface[] */
    private $_translations = [];

    /**
     * @return bool
     */
    public function translated(): bool
    {
        return (bool)$this->translatedLanguages(true);
    }

    /**
     * @param string|null $languageCode
     * @param bool|null $fallback
     * @return bool
     */
    public function hasTranslation(string $languageCode = null, bool $fallback = null): bool
    {
        $code = $this->findTranslation($languageCode, $fallback);

        return null !== $code;
    }

    /**
     * @param string|null $languageCode
     * @param bool|null $fallback
     * @return FlexObjectInterface|PageInterface|null
     */
    public function getTranslation(string $languageCode = null, bool $fallback = null)
    {
        if ($this->root()) {
            return $this;
        }

        $code = $this->findTranslation($languageCode, $fallback);
        if (null === $code) {
            $object = null;
        } elseif ('' === $code) {
            $object = $this->getLanguage() ? $this->getFlexDirectory()->getObject($this->getMasterKey(), 'storage_key') : $this;
        } else {
            $meta = $this->getMetaData();
            $meta['template'] = $this->getLanguageTemplates()[$code] ?? $meta['template'];
            $key = $this->getStorageKey() . '|' . $meta['template'] . '.' . $code;
            $meta['storage_key'] = $key;
            $meta['lang'] = $code;
            $object = $this->getFlexDirectory()->loadObjects([$key => $meta])[$key] ?? null;
        }

        return $object;
    }

    /**
     * @param bool $includeDefault If set to true, return separate entries for '' and 'en' (default) language.
     * @return array
     */
    public function getAllLanguages(bool $includeDefault = false): array
    {
        $grav = Grav::instance();

        /** @var Language $language */
        $language = $grav['language'];
        $languages = $language->getLanguages();
        if (!$languages) {
            return [];
        }

        $translated = $this->getLanguageTemplates();

        if ($includeDefault) {
            $languages[] = '';
        } elseif (isset($translated[''])) {
            $default = $language->getDefault();
            if (is_bool($default)) {
                $default = '';
            }
            $translated[$default] = $translated[''];
            unset($translated['']);
        }

        $languages = array_fill_keys($languages, false);
        $translated = array_fill_keys(array_keys($translated), true);

        return array_replace($languages, $translated);
    }

    /**
     * Returns all translated languages.
     *
     * @param bool $includeDefault If set to true, return separate entries for '' and 'en' (default) language.
     * @return array
     */
    public function getLanguages(bool $includeDefault = false): array
    {
        $languages = $this->getLanguageTemplates();

        if (!$includeDefault && isset($languages[''])) {
            $grav = Grav::instance();

            /** @var Language $language */
            $language = $grav['language'];
            $default = $language->getDefault();
            if (is_bool($default)) {
                $default = '';
            }
            $languages[$default] = $languages[''];
            unset($languages['']);
        }

        return array_keys($languages);
    }

    /**
     * @return string
     */
    public function getLanguage(): string
    {
        return $this->language() ?? '';
    }

    /**
     * @param string|null $languageCode
     * @param bool|null $fallback
     * @return string|null
     */
    public function findTranslation(string $languageCode = null, bool $fallback = null): ?string
    {
        $translated = $this->getLanguageTemplates();

        // If there's no translations (including default), we have an empty folder.
        if (!$translated) {
            return '';
        }

        // FIXME: only published is not implemented...
        $languages = $this->getFallbackLanguages($languageCode, $fallback);

        $language = null;
        foreach ($languages as $code) {
            if (isset($translated[$code])) {
                $language = $code;
                break;
            }
        }

        return $language;
    }

    /**
     * Return an array with the routes of other translated languages
     *
     * @param bool $onlyPublished only return published translations
     * @return array the page translated languages
     */
    public function translatedLanguages($onlyPublished = false): array
    {
        // FIXME: only published is not implemented...
        $translated = $this->getLanguageTemplates();
        if (!$translated) {
            return $translated;
        }

        $grav = Grav::instance();

        /** @var Language $language */
        $language = $grav['language'];
        $languages = $language->getLanguages();
        $languages[] = '';

        $translated = array_intersect_key($translated, array_flip($languages));
        $list = array_fill_keys($languages, null);
        foreach ($translated as $languageCode => $languageFile) {
            $path = ($languageCode ? '/' : '') . $languageCode;
            $list[$languageCode] = "{$path}/{$this->getKey()}";
        }

        return array_filter($list);
    }

    /**
     * Return an array listing untranslated languages available
     *
     * @param bool $includeUnpublished also list unpublished translations
     * @return array the page untranslated languages
     */
    public function untranslatedLanguages($includeUnpublished = false): array
    {
        $grav = Grav::instance();

        /** @var Language $language */
        $language = $grav['language'];

        $languages = $language->getLanguages();
        $translated = array_keys($this->translatedLanguages(!$includeUnpublished));

        return array_values(array_diff($languages, $translated));
    }

    /**
     * Get page language
     *
     * @param string|null $var
     * @return string|null
     */
    public function language($var = null): ?string
    {
        return $this->loadHeaderProperty(
            'lang',
            $var,
            function ($value) {
                $value = $value ?? $this->getMetaData()['lang'] ?? '';

                return trim($value) ?: null;
            }
        );
    }

    /**
     * @return array
     */
    protected function getLanguageTemplates(): array
    {
        if (null === $this->_languages) {
            $template = $this->getProperty('template');
            $meta = $this->getMetaData();
            $translations = $meta['markdown'] ?? [];
            $list = [];
            foreach ($translations as $code => $search) {
                if (isset($search[$template])) {
                    // Use main template if possible.
                    $list[$code] = $template;
                } elseif (!empty($search)) {
                    // Fall back to first matching template.
                    $list[$code] = key($search);
                }
            }

            $this->_languages = $list;
        }

        return $this->_languages;
    }

    /**
     * @param string|null $languageCode
     * @param bool|null $fallback
     * @return array
     */
    protected function getFallbackLanguages(string $languageCode = null, bool $fallback = null): array
    {
        $fallback = $fallback ?? true;
        if (!$fallback && null !== $languageCode) {
            return [$languageCode];
        }

        $grav = Grav::instance();

        /** @var Language $language */
        $language = $grav['language'];
        $languageCode = $languageCode ?? ($language->getLanguage() ?: '');
        if ($languageCode === '' && $fallback) {
            return $language->getFallbackLanguages(null, true);
        }

        return $fallback ? $language->getFallbackLanguages($languageCode, true) : [$languageCode];
    }
}
