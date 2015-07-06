<?php
namespace Grav\Common;

/**
 * Language and translation functionality for Grav
 */
class Language
{
    protected $enabled = true;
    protected $languages = [];
    protected $page_extensions = [];
    protected $fallback_languages = [];
    protected $default;
    protected $active;
    protected $config;

    /**
     * Constructor
     *
     * @param \Grav\Common\Grav $grav
     */
    public function __construct(Grav $grav)
    {
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
        return implode('|', $this->languages);
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
        $regex = '/(\/('.$this->getAvailable().')).*/';
        // if languages set

        if ($this->enabled()) {
            if (preg_match($regex, $uri, $matches)) {
                $this->active = $matches[2];
                $uri = preg_replace("/\\".$matches[1]."/", '', $matches[0], 1);
            } else {
                $this->active = null;
            }
        }
        return $uri;
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
                    $valid_lang_extensions[] = '.'.$lang.$file_ext;
                }

                if ($this->active) {
                    $active_extension = '.'.$this->active.$file_ext;
                    $key = array_search($active_extension, $valid_lang_extensions);
                    unset($valid_lang_extensions[$key]);
                    array_unshift($valid_lang_extensions, $active_extension);
                }

                $this->page_extensions = array_merge($valid_lang_extensions, (array) $file_ext);
            } else {
                $this->page_extensions = (array) $file_ext;
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
     * @param Array $args     first argument is the lookup key value
     *                  other arguments can be passed and replaced in the translation with sprintf syntax
     * @return string
     */
    public function translate(Array $args)
    {
        $lookup = array_shift($args);

        if ($this->enabled() && $lookup) {
            foreach ($this->getFallbackLanguages() as $lang) {
                $translation = $this->getTranslation($lang, $lookup);

                if ($translation) {
                    if (count($args) >= 1) {
                        return vsprintf($translation, $args);
                    } else {
                        return $translation;
                    }
                }
            }
        }
        return '<span class="untranslated">'.$lookup.'</span>';
    }

    /**
     * Lookup the translation text for a given lang and key
     *
     * @param $lang lang code
     * @param $key  key to lookup with
     *
     * @return string
     */
    public function getTranslation($lang, $key) {
        $languages = $this->config->getLanguages();

        return $languages->get($lang.'.'.$key, null);
    }
}
