<?php

/**
 * @package    Grav\Common\Language
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Language;

use Grav\Common\Grav;
use Grav\Common\Config\Config;
use Negotiation\AcceptLanguage;
use Negotiation\LanguageNegotiator;

class Language
{
    protected $grav;
    protected $enabled = true;
    /**
     * @var array
     */
    protected $languages = [];
    protected $page_extensions = [];
    protected $fallback_languages = [];
    protected $default;
    protected $active = null;

    /** @var Config $config */
    protected $config;

    protected $http_accept_language;
    protected $lang_in_url = false;

    /**
     * Constructor
     *
     * @param \Grav\Common\Grav $grav
     */
    public function __construct(Grav $grav)
    {
        $this->grav = $grav;
        $this->config = $grav['config'];
        $this->languages = $this->config->get('system.languages.supported', []);
        $this->init();
    }

    /**
     * Initialize the default and enabled languages
     */
    public function init()
    {
        $default = $this->config->get('system.languages.default_lang');
        if (isset($default) && $this->validate($default)) {
            $this->default = $default;
        } else {
            $this->default = reset($this->languages);
        }

        $this->page_extensions = null;

        if (empty($this->languages)) {
            $this->enabled = false;
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
     */
    public function setLanguages($langs)
    {
        $this->languages = $langs;
        $this->init();
    }

    /**
     * Gets a pipe-separated string of available languages
     *
     * @return string
     */
    public function getAvailable()
    {
        $languagesArray = $this->languages; //Make local copy

        $languagesArray = array_map(function($value) {
            return preg_quote($value);
        }, $languagesArray);

        sort($languagesArray);

        return implode('|', array_reverse($languagesArray));
    }

    /**
     * Gets language, active if set, else default
     *
     * @return string
     */
    public function getLanguage()
    {
        return $this->active ?: $this->default;
    }

    /**
     * Gets current default language
     *
     * @return mixed
     */
    public function getDefault()
    {
        return $this->default;
    }

    /**
     * Sets default language manually
     *
     * @param string $lang
     *
     * @return bool
     */
    public function setDefault($lang)
    {
        if ($this->validate($lang)) {
            $this->default = $lang;

            return $lang;
        }

        return false;
    }

    /**
     * Gets current active language
     *
     * @return string
     */
    public function getActive()
    {
        return $this->active;
    }

    /**
     * Sets active language manually
     *
     * @param string $lang
     *
     * @return string|bool
     */
    public function setActive($lang)
    {
        if ($this->validate($lang)) {
            $this->active = $lang;

            return $lang;
        }

        return false;
    }

    /**
     * Sets the active language based on the first part of the URL
     *
     * @param string $uri
     *
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
                $this->active = $matches[2];
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
                    $this->active = $this->grav['session']->active_language ?: null;
                }
                // if still null, try from http_accept_language header
                if ($this->active === null &&
                    $this->config->get('system.languages.http_accept_language') &&
                    $accept = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? false) {

                    $negotiator = new LanguageNegotiator();
                    $best_language = $negotiator->getBest($accept, $this->languages);

                    if ($best_language instanceof AcceptLanguage) {
                        $this->active = $best_language->getType();
                    } else {
                        $this->active = $this->getDefault();
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
     * Gets an array of valid extensions with active first, then fallback extensions
     *
     * @param string|null $file_ext
     *
     * @return array
     */
    public function getFallbackPageExtensions($file_ext = null)
    {
        if (empty($this->page_extensions)) {
            if (!$file_ext) {
                $file_ext = CONTENT_EXT;
            }

            if ($this->enabled()) {
                $valid_lang_extensions = [];
                foreach ($this->languages as $lang) {
                    $valid_lang_extensions[] = '.' . $lang . $file_ext;
                }

                if ($this->active) {
                    $active_extension = '.' . $this->active . $file_ext;
                    $key = \array_search($active_extension, $valid_lang_extensions, true);

                    // Default behavior is to find any language other than active
                    if ($this->config->get('system.languages.pages_fallback_only')) {
                        $slice = \array_slice($valid_lang_extensions, 0, $key+1);
                        $valid_lang_extensions = array_reverse($slice);
                    } else {
                        unset($valid_lang_extensions[$key]);
                        array_unshift($valid_lang_extensions, $active_extension);
                    }
                }
                $valid_lang_extensions[] = $file_ext;
                $this->page_extensions = $valid_lang_extensions;
            } else {
                $this->page_extensions = (array)$file_ext;
            }
        }

        return $this->page_extensions;
    }

    /**
     * Resets the page_extensions value.
     *
     * Useful to re-initialize the pages and change site language at runtime, example:
     *
     * ```
     * $this->grav['language']->setActive('it');
     * $this->grav['language']->resetFallbackPageExtensions();
     * $this->grav['pages']->init();
     * ```
     */
    public function resetFallbackPageExtensions()
    {
        $this->page_extensions = null;
    }

    /**
     * Gets an array of languages with active first, then fallback languages
     *
     * @return array
     */
    public function getFallbackLanguages()
    {
        if (empty($this->fallback_languages)) {
            if ($this->enabled()) {
                $fallback_languages = $this->languages;

                if ($this->active) {
                    $active_extension = $this->active;
                    $key = \array_search($active_extension, $fallback_languages, true);
                    unset($fallback_languages[$key]);
                    array_unshift($fallback_languages, $active_extension);
                }
                $this->fallback_languages = $fallback_languages;
            }
            // always add english in case a translation doesn't exist
            $this->fallback_languages[] = 'en';
        }

        return $this->fallback_languages;
    }

    /**
     * Ensures the language is valid and supported
     *
     * @param string $lang
     *
     * @return bool
     */
    public function validate($lang)
    {
        return \in_array($lang, $this->languages, true);
    }

    /**
     * Translate a key and possibly arguments into a string using current lang and fallbacks
     *
     * @param string|array $args      The first argument is the lookup key value
     *                         Other arguments can be passed and replaced in the translation with sprintf syntax
     * @param array $languages
     * @param bool  $array_support
     * @param bool  $html_out
     *
     * @return string
     */
    public function translate($args, array $languages = null, $array_support = false, $html_out = false)
    {
        if (\is_array($args)) {
            $lookup = array_shift($args);
        } else {
            $lookup = $args;
            $args = [];
        }

        if ($this->config->get('system.languages.translations', true)) {
            if ($this->enabled() && $lookup) {
                if (empty($languages)) {
                    if ($this->config->get('system.languages.translations_fallback', true)) {
                        $languages = $this->getFallbackLanguages();
                    } else {
                        $languages = (array)$this->getLanguage();
                    }
                }
            } else {
                $languages = ['en'];
            }

            foreach ((array)$languages as $lang) {
                $translation = $this->getTranslation($lang, $lookup, $array_support);

                if ($translation) {
                    if (\count($args) >= 1) {
                        return vsprintf($translation, $args);
                    }

                    return $translation;
                }
            }
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
     *
     * @return string
     */
    public function translateArray($key, $index, $languages = null, $html_out = false)
    {
        if ($this->config->get('system.languages.translations', true)) {
            if ($this->enabled() && $key) {
                if (empty($languages)) {
                    if ($this->config->get('system.languages.translations_fallback', true)) {
                        $languages = $this->getFallbackLanguages();
                    } else {
                        $languages = (array)$this->getDefault();
                    }
                }
            } else {
                $languages = ['en'];
            }

            foreach ((array)$languages as $lang) {
                $translation_array = (array)Grav::instance()['languages']->get($lang . '.' . $key, null);
                if ($translation_array && array_key_exists($index, $translation_array)) {
                    return $translation_array[$index];
                }
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
     *
     * @return string
     */
    public function getTranslation($lang, $key, $array_support = false)
    {
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
     * @return bool
     */
    public function getLanguageCode($code, $type = 'name')
    {
        return LanguageCodes::get($code, $type);
    }

}
