<?php

/**
 * @package    Grav\Common\Language
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Language;

use Grav\Common\Debugger;
use Grav\Common\Grav;
use Grav\Common\Config\Config;
use Negotiation\AcceptLanguage;
use Negotiation\LanguageNegotiator;
use function array_key_exists;
use function count;
use function in_array;
use function is_array;
use function is_string;

/**
 * Class Language
 * @package Grav\Common\Language
 */
class Language
{
    /** @var Grav */
    protected $grav;
    /** @var Config */
    protected $config;
    /** @var bool */
    protected $enabled = true;
    /** @var array */
    protected $languages = [];
    /** @var array */
    protected $fallback_languages = [];
    /** @var array */
    protected $fallback_extensions = [];
    /** @var array */
    protected $page_extensions = [];
    /** @var string|false */
    protected $default;
    /** @var string|false */
    protected $active;
    /** @var array */
    protected $http_accept_language;
    /** @var bool */
    protected $lang_in_url = false;

    /**
     * Constructor
     *
     * @param Grav $grav
     */
    public function __construct(Grav $grav)
    {
        $this->grav = $grav;
        $this->config = $grav['config'];

        $languages = $this->config->get('system.languages.supported', []);
        foreach ($languages as &$language) {
            $language = (string)$language;
        }
        unset($language);

        $this->languages = $languages;

        $this->init();
    }

    /**
     * Initialize the default and enabled languages
     *
     * @return void
     */
    public function init()
    {
        $default = $this->config->get('system.languages.default_lang');
        if (null !== $default) {
            $default = (string)$default;
        }

        // Note that reset returns false on empty languages.
        $this->default = $default ?? reset($this->languages);

        $this->resetFallbackPageExtensions();

        if (empty($this->languages)) {
            // If no languages are set, turn of multi-language support.
            $this->enabled = false;
        } elseif ($default && !in_array($default, $this->languages, true)) {
            // If default language isn't in the language list, we need to add it.
            array_unshift($this->languages, $default);
        }
    }

    /**
     * Ensure that languages are enabled
     *
     * @return bool
     */
    public function enabled()
    {
        return $this->enabled;
    }

    /**
     * Returns true if language debugging is turned on.
     *
     * @return bool
     */
    public function isDebug(): bool
    {
        return !$this->config->get('system.languages.translations', true);
    }

    /**
     * Gets the array of supported languages
     *
     * @return array
     */
    public function getLanguages()
    {
        return $this->languages;
    }

    /**
     * Sets the current supported languages manually
     *
     * @param array $langs
     * @return void
     */
    public function setLanguages($langs)
    {
        $this->languages = $langs;

        $this->init();
    }

    /**
     * Gets a pipe-separated string of available languages
     *
     * @param string|null $delimiter Delimiter to be quoted.
     * @return string
     */
    public function getAvailable($delimiter = null)
    {
        $languagesArray = $this->languages; //Make local copy

        $languagesArray = array_map(static function ($value) use ($delimiter) {
            return preg_quote($value, $delimiter);
        }, $languagesArray);

        sort($languagesArray);

        return implode('|', array_reverse($languagesArray));
    }

    /**
     * Gets language, active if set, else default
     *
     * @return string|false
     */
    public function getLanguage()
    {
        return $this->active ?: $this->default;
    }

    /**
     * Gets current default language
     *
     * @return string|false
     */
    public function getDefault()
    {
        return $this->default;
    }

    /**
     * Sets default language manually
     *
     * @param string $lang
     * @return string|bool
     */
    public function setDefault($lang)
    {
        $lang = (string)$lang;
        if ($this->validate($lang)) {
            $this->default = $lang;

            return $lang;
        }

        return false;
    }

    /**
     * Gets current active language
     *
     * @return string|false
     */
    public function getActive()
    {
        return $this->active;
    }

    /**
     * Sets active language manually
     *
     * @param string|false $lang
     * @return string|false
     */
    public function setActive($lang)
    {
        $lang = (string)$lang;
        if ($this->validate($lang)) {
            /** @var Debugger $debugger */
            $debugger = $this->grav['debugger'];
            $debugger->addMessage('Active language set to ' . $lang, 'debug');

            $this->active = $lang;

            return $lang;
        }

        return false;
    }

    /**
     * Sets the active language based on the first part of the URL
     *
     * @param string $uri
     * @return string
     */
    public function setActiveFromUri($uri)
    {
        $regex = '/(^\/(' . $this->getAvailable() . '))(?:\/|\?|$)/i';

        // if languages set
        if ($this->enabled()) {
            // Try setting language from prefix of URL (/en/blah/blah).
            if (preg_match($regex, $uri, $matches)) {
                $this->lang_in_url = true;
                $this->setActive($matches[2]);
                $uri = preg_replace("/\\" . $matches[1] . '/', '', $uri, 1);

                // Store in session if language is different.
                if (isset($this->grav['session']) && $this->grav['session']->isStarted()
                    && $this->config->get('system.languages.session_store_active', true)
                    && $this->grav['session']->active_language != $this->active
                ) {
                    $this->grav['session']->active_language = $this->active;
                }
            } else {
                // Try getting language from the session, else no active.
                if (isset($this->grav['session']) && $this->grav['session']->isStarted() &&
                    $this->config->get('system.languages.session_store_active', true)) {
                    $this->setActive($this->grav['session']->active_language ?: null);
                }
                // if still null, try from http_accept_language header
                if ($this->active === null &&
                    $this->config->get('system.languages.http_accept_language') &&
                    $accept = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? false) {
                    $negotiator = new LanguageNegotiator();
                    $best_language = $negotiator->getBest($accept, $this->languages);

                    if ($best_language instanceof AcceptLanguage) {
                        $this->setActive($best_language->getType());
                    } else {
                        $this->setActive($this->getDefault());
                    }
                }
            }
        }

        return $uri;
    }

    /**
     * Get a URL prefix based on configuration
     *
     * @param string|null $lang
     * @return string
     */
    public function getLanguageURLPrefix($lang = null)
    {
        if (!$this->enabled()) {
            return '';
        }

        // if active lang is not passed in, use current active
        if (!$lang) {
            $lang = $this->getLanguage();
        }

        return $this->isIncludeDefaultLanguage($lang) ? '/' . $lang : '';
    }

    /**
     * Test to see if language is default and language should be included in the URL
     *
     * @param string|null $lang
     * @return bool
     */
    public function isIncludeDefaultLanguage($lang = null)
    {
        if (!$this->enabled()) {
            return false;
        }

        // if active lang is not passed in, use current active
        if (!$lang) {
            $lang = $this->getLanguage();
        }

        return !($this->default === $lang && $this->config->get('system.languages.include_default_lang') === false);
    }

    /**
     * Simple getter to tell if a language was found in the URL
     *
     * @return bool
     */
    public function isLanguageInUrl()
    {
        return (bool) $this->lang_in_url;
    }

    /**
     * Get full list of used language page extensions: [''=>'.md', 'en'=>'.en.md', ...]
     *
     * @param string|null $fileExtension
     * @return array
     */
    public function getPageExtensions($fileExtension = null)
    {
        $fileExtension = $fileExtension ?: CONTENT_EXT;

        if (!isset($this->fallback_extensions[$fileExtension])) {
            $extensions[''] = $fileExtension;
            foreach ($this->languages as $code) {
                $extensions[$code] = ".{$code}{$fileExtension}";
            }

            $this->fallback_extensions[$fileExtension] = $extensions;
        }

        return $this->fallback_extensions[$fileExtension];
    }

    /**
     * Gets an array of valid extensions with active first, then fallback extensions
     *
     * @param string|null $fileExtension
     * @param string|null $languageCode
     * @param bool $assoc  Return values in ['en' => '.en.md', ...] format.
     * @return array Key is the language code, value is the file extension to be used.
     */
    public function getFallbackPageExtensions(string $fileExtension = null, string $languageCode = null, bool $assoc = false)
    {
        $fileExtension = $fileExtension ?: CONTENT_EXT;
        $key = $fileExtension . '-' . ($languageCode ?? 'default') . '-' . (int)$assoc;

        if (!isset($this->fallback_extensions[$key])) {
            $all = $this->getPageExtensions($fileExtension);
            $list = [];
            $fallback = $this->getFallbackLanguages($languageCode, true);
            foreach ($fallback as $code) {
                $ext = $all[$code] ?? null;
                if (null !== $ext) {
                    $list[$code] = $ext;
                }
            }
            if (!$assoc) {
                $list = array_values($list);
            }

            $this->fallback_extensions[$key] = $list;
        }

        return $this->fallback_extensions[$key];
    }

    /**
     * Resets the fallback_languages value.
     *
     * Useful to re-initialize the pages and change site language at runtime, example:
     *
     * ```
     * $this->grav['language']->setActive('it');
     * $this->grav['language']->resetFallbackPageExtensions();
     * $this->grav['pages']->init();
     * ```
     *
     * @return void
     */
    public function resetFallbackPageExtensions()
    {
        $this->fallback_languages = [];
        $this->fallback_extensions = [];
        $this->page_extensions = [];
    }

    /**
     * Gets an array of languages with active first, then fallback languages.
     *
     *
     * @param string|null  $languageCode
     * @param bool $includeDefault  If true, list contains '', which can be used for default
     * @return array
     */
    public function getFallbackLanguages(string $languageCode = null, bool $includeDefault = false)
    {
        // Handle default.
        if ($languageCode === '' || !$this->enabled()) {
            return [''];
        }

        $default = $this->getDefault() ?? 'en';
        $active = $languageCode ?? $this->getActive() ?? $default;
        $key = $active . '-' . (int)$includeDefault;

        if (!isset($this->fallback_languages[$key])) {
            $fallback = $this->config->get('system.languages.content_fallback.' . $active);
            $fallback_languages = [];

            if (null === $fallback && $this->config->get('system.languages.pages_fallback_only', false)) {
                user_error('Configuration option `system.languages.pages_fallback_only` is deprecated since Grav 1.7, use `system.languages.content_fallback` instead', E_USER_DEPRECATED);

                // Special fallback list returns itself and all the previous items in reverse order:
                // active: 'v2', languages: ['v1', 'v2', 'v3', 'v4'] => ['v2', 'v1', '']
                if ($includeDefault) {
                    $fallback_languages[''] = '';
                }
                foreach ($this->languages as $code) {
                    $fallback_languages[$code] = $code;
                    if ($code === $active) {
                        break;
                    }
                }
                $fallback_languages = array_reverse($fallback_languages);
            } else {
                if (null === $fallback) {
                    $fallback = [$default];
                } elseif (!is_array($fallback)) {
                    $fallback = is_string($fallback) && $fallback !== '' ? explode(',', $fallback) : [];
                }
                array_unshift($fallback, $active);
                $fallback = array_unique($fallback);

                foreach ($fallback as $code) {
                    // Default fallback list has active language followed by default language and extensionless file:
                    // active: 'fi', default: 'en', languages: ['sv', 'en', 'de', 'fi'] => ['fi', 'en', '']
                    $fallback_languages[$code] = $code;
                    if ($includeDefault && $code === $default) {
                        $fallback_languages[''] = '';
                    }
                }
            }

            $fallback_languages = array_values($fallback_languages);

            $this->fallback_languages[$key] = $fallback_languages;
        }

        return $this->fallback_languages[$key];
    }

    /**
     * Ensures the language is valid and supported
     *
     * @param string $lang
     * @return bool
     */
    public function validate($lang)
    {
        return in_array($lang, $this->languages, true);
    }

    /**
     * Translate a key and possibly arguments into a string using current lang and fallbacks
     *
     * @param string|array $args      The first argument is the lookup key value
     *                         Other arguments can be passed and replaced in the translation with sprintf syntax
     * @param array|null $languages
     * @param bool  $array_support
     * @param bool  $html_out
     * @return string|string[]
     */
    public function translate($args, array $languages = null, $array_support = false, $html_out = false)
    {
        if (is_array($args)) {
            $lookup = array_shift($args);
        } else {
            $lookup = $args;
            $args = [];
        }

        if (!$this->isDebug()) {
            if ($lookup && $this->enabled() && empty($languages)) {
                $languages = $this->getTranslatedLanguages();
            }

            $languages = $languages ?: ['en'];

            foreach ((array)$languages as $lang) {
                $translation = $this->getTranslation($lang, $lookup, $array_support);

                if ($translation) {
                    if (is_string($translation) && count($args) >= 1) {
                        return vsprintf($translation, $args);
                    }

                    return $translation;
                }
            }
        } elseif ($array_support) {
            return [$lookup];
        }

        if ($html_out) {
            return '<span class="untranslated">' . $lookup . '</span>';
        }

        return $lookup;
    }

    /**
     * Translate Array
     *
     * @param string $key
     * @param string $index
     * @param array|null $languages
     * @param bool $html_out
     * @return string
     */
    public function translateArray($key, $index, $languages = null, $html_out = false)
    {
        if ($this->isDebug()) {
            return $key . '[' . $index . ']';
        }

        if ($key && empty($languages) && $this->enabled()) {
            $languages = $this->getTranslatedLanguages();
        }

        $languages = $languages ?: ['en'];

        foreach ((array)$languages as $lang) {
            $translation_array = (array)Grav::instance()['languages']->get($lang . '.' . $key, null);
            if ($translation_array && array_key_exists($index, $translation_array)) {
                return $translation_array[$index];
            }
        }

        if ($html_out) {
            return '<span class="untranslated">' . $key . '[' . $index . ']</span>';
        }

        return $key . '[' . $index . ']';
    }

    /**
     * Lookup the translation text for a given lang and key
     *
     * @param string $lang lang code
     * @param string $key  key to lookup with
     * @param bool $array_support
     * @return string|string[]
     */
    public function getTranslation($lang, $key, $array_support = false)
    {
        if ($this->isDebug()) {
            return $key;
        }

        $translation = Grav::instance()['languages']->get($lang . '.' . $key, null);
        if (!$array_support && is_array($translation)) {
            return (string)array_shift($translation);
        }

        return $translation;
    }

    /**
     * Get the browser accepted languages
     *
     * @param array $accept_langs
     * @return array
     * @deprecated 1.6 No longer used - using content negotiation.
     */
    public function getBrowserLanguages($accept_langs = [])
    {
        user_error(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated since Grav 1.6, no longer used', E_USER_DEPRECATED);

        if (empty($this->http_accept_language)) {
            if (empty($accept_langs) && isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
                $accept_langs = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
            } else {
                return $accept_langs;
            }

            $langs = [];

            foreach (explode(',', $accept_langs) as $k => $pref) {
                // split $pref again by ';q='
                // and decorate the language entries by inverted position
                if (false !== ($i = strpos($pref, ';q='))) {
                    $langs[substr($pref, 0, $i)] = [(float)substr($pref, $i + 3), -$k];
                } else {
                    $langs[$pref] = [1, -$k];
                }
            }
            arsort($langs);

            // no need to undecorate, because we're only interested in the keys
            $this->http_accept_language = array_keys($langs);
        }
        return $this->http_accept_language;
    }

    /**
     * Accessible wrapper to LanguageCodes
     *
     * @param string $code
     * @param string $type
     * @return string|false
     */
    public function getLanguageCode($code, $type = 'name')
    {
        return LanguageCodes::get($code, $type);
    }

    /**
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function __debugInfo()
    {
        $vars = get_object_vars($this);
        unset($vars['grav'], $vars['config']);

        return $vars;
    }

    /**
     * @return array
     */
    protected function getTranslatedLanguages(): array
    {
        if ($this->config->get('system.languages.translations_fallback', true)) {
            $languages = $this->getFallbackLanguages();
        } else {
            $languages = [$this->getLanguage()];
        }

        $languages[] = 'en';

        return array_values(array_unique($languages));
    }
}
