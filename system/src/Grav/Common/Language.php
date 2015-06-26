<?php
namespace Grav\Common;

/**
 * Contain some useful language functions
 */
class Language
{
    protected $languages = [];
    protected $default_key;
    protected $default_lang;
    protected $active_key;
    protected $active_lang;

    public function __construct(Grav $grav)
    {
        $this->languages = $grav['config']->get('system.languages', []);
        $this->default_key = key($this->languages);
        $this->default_lang = reset($this->languages);

    }

    public function setLanguage($key)
    {
        if (array_key_exists($key, $this->languages)) {
            $this->active_key = $key;
            $this->active_lang = $this->languages[$key];
            return true;
        }
        return false;
    }

    public function getAvailableKeys()
    {
        return implode('|', array_keys($this->languages));
    }

    public function getDefaultKey()
    {
        return $this->default_key;
    }

    public function getDefaultLanguage()
    {
        return $this->default_lang;
    }

    public function getActiveKey()
    {
        return $this->active_key;
    }

    public function getActiveLanguage()
    {
        return $this->active_lang;
    }

}
