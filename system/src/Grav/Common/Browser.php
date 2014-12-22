<?php
namespace Grav\Common;

/**
 * Simple wrapper for the very simple parse_user_agent() function
 */
class Browser
{

    protected $useragent = [];

    public function __construct()
    {
        try {
            $this->useragent = parse_user_agent();
        } catch (\InvalidArgumentException $e) {
            $this->useragent = parse_user_agent("Mozilla/5.0 (compatible; Unknown;)");
        }
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
