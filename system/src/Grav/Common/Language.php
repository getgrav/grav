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
    protected $page_extension;

    public function __construct(Grav $grav)
    {
        $this->languages = $grav['config']->get('system.languages.supported', []);
        $this->default = reset($this->languages);

    }

    public function enabled()
    {
        if (empty($this->languages)) {
            return false;
        }
        return true;
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

    public function getPageExtension()
    {
        if (empty($this->page_extension)) {

            if ($this->enabled()) {
                if ($this->active) {
                    $lang = '.' . $this->active;
                } else {
                    $lang = '.' . $this->default;
                }
            } else {
                $lang = '';
            }
            $this->page_extension = $lang . CONTENT_EXT;
        }
        return $this->page_extension;
    }

    public function validate($lang)
    {
        if (in_array($lang, $this->languages)) {
            return true;
        }
        return false;
    }

}
