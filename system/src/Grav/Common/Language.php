<?php
namespace Grav\Common;

/**
 * Contain some useful language functions
 */
class Language
{
    protected $languages = [];
    protected $default;
    protected $active;
    protected $page_extensions;
    protected $enabled = true;

    public function __construct(Grav $grav)
    {
        $this->languages = $grav['config']->get('system.languages.supported', []);
        $this->default = reset($this->languages);

        if (empty($this->languages)) {
            $this->enabled = false;
        }

    }

    public function enabled()
    {
        return $this->enabled;
    }

    public function getLanguages()
    {
        return $this->languages;
    }

    public function setLanguages($langs)
    {
        $this->languages = $langs;
    }

    public function getAvailable()
    {
        return implode('|', $this->languages);
    }

    public function getDefault()
    {
        return $this->default;
    }

    public function setDefault($lang)
    {
        if ($this->validate($lang)) {
            $this->default = $lang;
            return $lang;
        }
        return false;
    }

    public function getActive()
    {
        return $this->active;
    }

    public function setActive($lang)
    {
        if ($this->validate($lang)) {
            $this->active = $lang;
            return $lang;
        }
        return false;
    }

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

    public function getValidPageExtensions()
    {
        if (empty($this->page_extensions)) {

            if ($this->enabled()) {
                $valid_lang_extensions = [];
                foreach ($this->languages as $lang) {
                    $valid_lang_extensions[] = '.'.$lang.CONTENT_EXT;
                }


                if ($this->active) {
                    $active_extension = '.'.$this->active.CONTENT_EXT;
                    $key = array_search($active_extension, $valid_lang_extensions);
                    unset($valid_lang_extensions[$key]);
                    array_unshift($valid_lang_extensions, $active_extension);
                }

                $this->page_extensions = array_merge($valid_lang_extensions, (array) CONTENT_EXT);
            } else {
                $this->page_extensions = (array) CONTENT_EXT;
            }
        }
        return $this->page_extensions;
    }

    public function validate($lang)
    {
        if (in_array($lang, $this->languages)) {
            return true;
        }
        return false;
    }

}
