<?php
namespace Grav\Common;

/**
 * Simple wrapper for the very simple parse_user_agent() function
 */
class Browser {

    protected $useragent;

    public function __construct()
    {
        $this->useragent = parse_user_agent();
    }

    public function getBrowser()
    {
        return strtolower($this->useragent['browser']);
    }

    public function getPlatform()
    {
        return strtolower($this->useragent['platform']);
    }

    public function getLongVersion()
    {
        return $this->useragent['version'];
    }

    public function getVersion()
    {
        $version = explode('.', $this->getLongVersion());
        return intval($version[0]);
    }
}
