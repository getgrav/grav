<?php
namespace Grav\Common;

/**
 * Handles browser and platform versions
 *
 * Internally uses the PhpUserAgent package https://github.com/donatj/PhpUserAgent
 *
 * @author  RocketTheme
 * @license MIT
 */
class Browser
{
    protected $useragent = [];

    /**
     * Browser constructor.
     */
    public function __construct()
    {
        try {
            $this->useragent = parse_user_agent();
        } catch (\InvalidArgumentException $e) {
            $this->useragent = parse_user_agent("Mozilla/5.0 (compatible; Unknown;)");
        }
    }

    /**
     * Get the current browser identifier
     *
     * Currently detected browsers:
     *
     * Android Browser
     * BlackBerry Browser
     * Camino
     * Kindle / Silk
     * Firefox / Iceweasel
     * Safari
     * Internet Explorer
     * IEMobile
     * Chrome
     * Opera
     * Midori
     * Vivaldi
     * TizenBrowser
     * Lynx
     * Wget
     * Curl
     *
     * @return string the lowercase browser name
     */
    public function getBrowser()
    {
        return strtolower($this->useragent['browser']);
    }

    /**
     * Get the current platform identifier
     *
     * Currently detected platforms:
     *
     * Desktop
     *   -> Windows
     *   -> Linux
     *   -> Macintosh
     *   -> Chrome OS
     * Mobile
     *   -> Android
     *   -> iPhone
     *   -> iPad / iPod Touch
     *   -> Windows Phone OS
     *   -> Kindle
     *   -> Kindle Fire
     *   -> BlackBerry
     *   -> Playbook
     *   -> Tizen
     * Console
     *   -> Nintendo 3DS
     *   -> New Nintendo 3DS
     *   -> Nintendo Wii
     *   -> Nintendo WiiU
     *   -> PlayStation 3
     *   -> PlayStation 4
     *   -> PlayStation Vita
     *   -> Xbox 360
     *   -> Xbox One
     *
     * @return string the lowercase platform name
     */
    public function getPlatform()
    {
        return strtolower($this->useragent['platform']);
    }

    /**
     * Get the current full version identifier
     *
     * @return string the browser full version identifier
     */
    public function getLongVersion()
    {
        return $this->useragent['version'];
    }

    /**
     * Get the current major version identifier
     *
     * @return string the browser major version identifier
     */
    public function getVersion()
    {
        $version = explode('.', $this->getLongVersion());

        return intval($version[0]);
    }

    /**
     * Determine if the request comes from a human, or from a bot/crawler
     *
     * @return bool
     */
    public function isHuman()
    {
        $browser = $this->getBrowser();
        if (empty($browser)) {
            return false;
        }

        if (preg_match('~(bot|crawl)~i', $browser)) {
            return false;
        }

        return true;
    }
}
