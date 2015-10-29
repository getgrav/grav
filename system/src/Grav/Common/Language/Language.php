<?php
namespace Grav\Common\Language;

use Grav\Common\Grav;

/**
 * Language and translation functionality for Grav
 */
class Language
{
    protected $grav;
    protected $enabled = true;
    protected $languages = [];
    protected $page_extensions = [];
    protected $fallback_languages = [];
    protected $default;
    protected $active = null;
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
        $this->default = reset($this->languages);

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
     * @param $langs
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
        sort($languagesArray);
        return implode('|', array_reverse($languagesArray));
    }

    /**
     * Gets language, active if set, else default
     *
     * @return mixed
     */
    public function getLanguage()
    {
        return $this->active ? $this->active : $this->default;
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
     * @param $lang
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
     * @return mixed
     */
    public function getActive()
    {
        return $this->active;
    }

    /**
     * Sets active language manually
     *
     * @param $lang
     *
     * @return bool
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
     * @param $uri
     *
     * @return mixed
     */
    public function setActiveFromUri($uri)
    {
        $regex = '/(^\/(' . $this->getAvailable() . '))(?:\/.*|$)/i';

        // if languages set
        if ($this->enabled()) {
            // try setting from prefix of URL (/en/blah/blah)
            if (preg_match($regex, $uri, $matches)) {
                $this->lang_in_url = true;
                $this->active = $matches[2];
                $uri = preg_replace("/\\" . $matches[1] . "/", '', $matches[0], 1);

                // store in session if different
                if ($this->config->get('system.session.enabled', false)
                    && $this->config->get('system.languages.session_store_active', true)
                    && $this->grav['session']->active_language != $this->active
                ) {
                    $this->grav['session']->active_language = $this->active;
                }
            } else {
                // try getting from session, else no active
                if ($this->config->get('system.session.enabled', false) &&
                    $this->config->get('system.languages.session_store_active', true)) {
                    $this->active = $this->grav['session']->active_language ?: null;
                }
                // if still null, try from http_accept_language header
                if ($this->active === null && $this->config->get('system.languages.http_accept_language')) {
                    $preferred = $this->getBrowserLanguages();
                    foreach ($preferred as $lang) {
                        if ($this->validate($lang)) {
                            $this->active = $lang;
                            break;
                        }
                    }

                }
            }
        }

        return $uri;
    }

    /**
     * Get's a URL prefix based on configuration
     *
     * @param null $lang
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
     * @param null $lang
     * @return bool
     */
    public function isIncludeDefaultLanguage($lang = null)
    {
        // if active lang is not passed in, use current active
        if (!$lang) {
            $lang = $this->getLanguage();
        }

        if ($this->default == $lang && $this->config->get('system.languages.include_default_lang') === false) {
            return false;
        } else {
            return true;
        }
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
     * @return array
     */
    public function getFallbackPageExtensions($file_ext = null)
    {
        if (empty($this->page_extensions)) {
            if (empty($file_ext)) {
                $file_ext = CONTENT_EXT;
            }

            if ($this->enabled()) {
                $valid_lang_extensions = [];
                foreach ($this->languages as $lang) {
                    $valid_lang_extensions[] = '.' . $lang . $file_ext;
                }

                if ($this->active) {
                    $active_extension = '.' . $this->active . $file_ext;
                    $key = array_search($active_extension, $valid_lang_extensions);
                    unset($valid_lang_extensions[$key]);
                    array_unshift($valid_lang_extensions, $active_extension);
                }

                $this->page_extensions = array_merge($valid_lang_extensions, (array)$file_ext);
            } else {
                $this->page_extensions = (array)$file_ext;
            }
        }

        return $this->page_extensions;
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
                    $key = array_search($active_extension, $fallback_languages);
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
     * @param $lang
     *
     * @return bool
     */
    public function validate($lang)
    {
        if (in_array($lang, $this->languages)) {
            return true;
        }

        return false;
    }

    /**
     * Translate a key and possibly arguments into a string using current lang and fallbacks
     *
     * @param       $args       first argument is the lookup key value
     *                          other arguments can be passed and replaced in the translation with sprintf syntax
     * @param Array $languages
     * @param bool  $array_support
     * @param bool  $html_out
     *
     * @return string
     */
    public function translate($args, Array $languages = null, $array_support = false, $html_out = false)
    {
        if (is_array($args)) {
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
                        $languages = (array)$this->getDefault();
                    }
                }
            } else {
                $languages = ['en'];
            }

            foreach ((array)$languages as $lang) {
                $translation = $this->getTranslation($lang, $lookup, $array_support);

                if ($translation) {
                    if (count($args) >= 1) {
                        return vsprintf($translation, $args);
                    } else {
                        return $translation;
                    }
                }
            }
        }

        if ($html_out) {
            return '<span class="untranslated">' . $lookup . '</span>';
        } else {
            return $lookup;
        }
    }

    /**
     * Translate Array
     *
     * @param      $key
     * @param      $index
     * @param null $languages
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
                $translation_array = (array)$this->config->getLanguages()->get($lang . '.' . $key, null);
                if ($translation_array && array_key_exists($index, $translation_array)) {
                    return $translation_array[$index];
                }
            }
        }

        if ($html_out) {
            return '<span class="untranslated">' . $key . '[' . $index . ']</span>';
        } else {
            return $key . '[' . $index . ']';
        }
    }

    /**
     * Lookup the translation text for a given lang and key
     *
     * @param      $lang lang code
     * @param      $key  key to lookup with
     * @param bool $array_support
     *
     * @return string
     */
    public function getTranslation($lang, $key, $array_support = false)
    {
        $translation = $this->config->getLanguages()->get($lang . '.' . $key, null);
        if (!$array_support && is_array($translation)) {
            return (string)array_shift($translation);
        }

        return $translation;
    }

    public function getBrowserLanguages($accept_langs = [])
    {
        if (empty($this->http_accept_language)) {
            if (empty($accept_langs) && isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
                $accept_langs = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
            } else {
                return $accept_langs;
            }

            foreach (explode(',', $accept_langs) as $k => $pref) {
                // split $pref again by ';q='
                // and decorate the language entries by inverted position
                if (false !== ($i = strpos($pref, ';q='))) {
                    $langs[substr($pref, 0, $i)] = array((float)substr($pref, $i + 3), -$k);
                } else {
                    $langs[$pref] = array(1, -$k);
                }
            }
            arsort($langs);

            // no need to undecorate, because we're only interested in the keys
            $this->http_accept_language = array_keys($langs);
        }
        return $this->http_accept_language;
    }

}
