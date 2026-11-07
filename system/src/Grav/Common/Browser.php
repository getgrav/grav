<?php

/**
 * @package    Grav\Common
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

use InvalidArgumentException;
use function donatj\UserAgent\parse_user_agent;

/**
 * Internally uses the PhpUserAgent package https://github.com/donatj/PhpUserAgent
 */
class Browser
{
    /**
     * Clients that are not a person reading the page, matched case-insensitively
     * anywhere in the raw User-Agent header.
     *
     * The user-agent parser reports many of these as an ordinary browser (for
     * example `facebookexternalhit` or `chatgpt-user`), so the `bot`/`crawl`
     * test on the parsed name lets them through. Nothing listed here may appear
     * in a real browser's User-Agent: Facebook's in-app browser (`FBAN`/`FBAV`,
     * `FB_IAB`) and Instagram's, for instance, are people and must stay human.
     *
     * @var string[]
     */
    protected const NON_HUMAN_AGENTS = [
        // Link-preview and social fetchers
        'facebookexternalhit',
        'facebookcatalog',
        'meta-externalagent',
        'meta-externalfetcher',
        'twitterbot',
        'linkedinbot',
        'slackbot',
        'slack-imgproxy',
        'discordbot',
        'whatsapp/',
        'telegrambot',
        'skypeuripreview',
        'iframely',
        'embedly',
        'redditbot',
        'pinterestbot',
        'pinterest.com/bot',
        'vkshare',
        'applebot',

        // AI fetchers and crawlers
        'chatgpt-user',
        'oai-searchbot',
        'gptbot',
        'claudebot',
        'claude-user',
        'claude-searchbot',
        'anthropic-ai',
        'perplexitybot',
        'perplexity-user',
        'google-extended',
        'bytespider',
        'ccbot',
        'cohere-ai',
        'amazonbot',
        'duckassistbot',
        'mistralai-user',

        // Uptime and monitoring services
        'uptimerobot',
        'pingdom',
        'statuscake',
        'site24x7',
        'better uptime',
        'betteruptime',
        'betterstack',
        'uptime-kuma',
        'freshping',
        'hetrixtools',
        'datadog',
        'newrelicpinger',
        'checkly',
        'guardprobe',
        'guardpinger',
        'gravguard',

        // Generic tokens
        'spider',
        'crawler',
        'scraper',
        'headlesschrome',
        'lighthouse',
        'preview',
        'monitor',
        'uptime',
        'externalhit',
        'fetcher',
    ];

    /** @var string[] */
    protected $useragent = [];

    /** @var string */
    protected $rawAgent = '';

    /**
     * Browser constructor.
     */
    public function __construct()
    {
        $this->rawAgent = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');

        try {
            $this->useragent = parse_user_agent();
        } catch (InvalidArgumentException) {
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
        return strtolower($this->useragent['browser'] ?? '');
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
        return strtolower($this->useragent['platform'] ?? '');
    }

    /**
     * Get the current full version identifier
     *
     * @return string the browser full version identifier
     */
    public function getLongVersion()
    {
        return $this->useragent['version'] ?? '';
    }

    /**
     * Get the current major version identifier
     *
     * @return int the browser major version identifier
     */
    public function getVersion()
    {
        $version = explode('.', $this->getLongVersion());

        return (int)$version[0];
    }

    /**
     * Determine if the request comes from a human, or from a bot, crawler, link-preview
     * fetcher, AI fetcher or uptime monitor
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

        if ($this->rawAgent !== '') {
            $agent = strtolower($this->rawAgent);
            foreach (static::NON_HUMAN_AGENTS as $token) {
                if (str_contains($agent, $token)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Determine if “Do Not Track” is set by browser
     * @see https://www.w3.org/TR/tracking-dnt/
     *
     * @return bool
     */
    public function isTrackable(): bool
    {
        return !(isset($_SERVER['HTTP_DNT']) && $_SERVER['HTTP_DNT'] === '1');
    }
}
