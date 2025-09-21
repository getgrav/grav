<?php

/**
 * @package    Grav\Common
 *
 * @copyright  Copyright (c) 2015 - 2025 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

use Grav\Common\Config\Config;
use Grav\Common\Language\Language;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Pages;
use Grav\Framework\Route\Route;
use Grav\Framework\Route\RouteFactory;
use Grav\Framework\Uri\UriFactory;
use Grav\Framework\Uri\UriPartsFilter;
use RocketTheme\Toolbox\Event\Event;
use RuntimeException;
use function array_key_exists;
use function count;
use function in_array;
use function is_array;
use function is_string;
use function strlen;

/**
 * Class Uri
 * @package Grav\Common
 */
class Uri implements \Stringable
{
    const HOSTNAME_REGEX = '/^(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z0-9]|[A-Za-z0-9][A-Za-z0-9\-]*[A-Za-z0-9])$/';

    /** @var \Grav\Framework\Uri\Uri|null */
    protected static $currentUri;

    /** @var Route|null */
    protected static $currentRoute;

    /** @var string */
    public $url;

    // Uri parts.
    /** @var string|null */
    protected $scheme;
    /** @var string|null */
    protected $user;
    /** @var string|null */
    protected $password;
    /** @var string|null */
    protected $host;
    /** @var int|null */
    protected $port;
    /** @var string */
    protected $path;
    /** @var string */
    protected $query;
    /** @var string|null */
    protected $fragment;

    // Internal stuff.
    /** @var string */
    protected $base;
    /** @var string|null */
    protected $basename;
    /** @var string */
    protected $content_path;
    /** @var string|null */
    protected $extension;
    /** @var string */
    protected $env;
    /** @var array */
    protected $paths;
    /** @var array */
    protected $queries;
    /** @var array */
    protected $params;
    /** @var string */
    protected $root;
    /** @var string */
    protected $setup_base;
    /** @var string */
    protected $root_path;
    /** @var string */
    protected $uri;
    /** @var array */
    protected $post;

    /**
     * Uri constructor.
     * @param string|array|null $env
     */
    public function __construct($env = null)
    {
        if (is_string($env)) {
            $this->createFromString($env);
        } else {
            $this->createFromEnvironment(is_array($env) ? $env : $_SERVER);
        }
    }

    /**
     * Initialize the URI class with a url passed via parameter.
     * Used for testing purposes.
     *
     * @param string $url the URL to use in the class
     * @return $this
     */
    public function initializeWithUrl($url = '')
    {
        if ($url) {
            $this->createFromString($url);
        }

        return $this;
    }

    /**
     * Initialize the URI class by providing url and root_path arguments
     *
     * @param string $url
     * @param string $root_path
     * @return $this
     */
    public function initializeWithUrlAndRootPath($url, $root_path)
    {
        $this->initializeWithUrl($url);
        $this->root_path = $root_path;

        return $this;
    }

    /**
     * Validate a hostname
     *
     * @param string $hostname The hostname
     * @return bool
     */
    public function validateHostname($hostname)
    {
        return (bool)preg_match(static::HOSTNAME_REGEX, $hostname);
    }

    /**
     * Initializes the URI object based on the url set on the object
     *
     * @return void
     */
    public function init()
    {
        $grav = Grav::instance();

        /** @var Config $config */
        $config = $grav['config'];

        /** @var Language $language */
        $language = $grav['language'];

        // add the port to the base for non-standard ports
        if ($this->port && $config->get('system.reverse_proxy_setup') === false) {
            $this->base .= ':' . $this->port;
        }

        // Handle custom base
        $custom_base = rtrim((string) $grav['config']->get('system.custom_base_url', ''), '/');
        if ($custom_base) {
            $custom_parts = parse_url($custom_base);
            if ($custom_parts === false) {
                throw new RuntimeException('Bad configuration: system.custom_base_url');
            }
            $orig_root_path = $this->root_path;
            $this->root_path = isset($custom_parts['path']) ? rtrim($custom_parts['path'], '/') : '';
            if (isset($custom_parts['scheme'])) {
                $this->base = $custom_parts['scheme'] . '://' . $custom_parts['host'];
                $this->port = $custom_parts['port'] ?? null;
                if ($this->port && $config->get('system.reverse_proxy_setup') === false) {
                    $this->base .= ':' . $this->port;
                }
                $this->root = $custom_base;
            } else {
                $this->root = $this->base . $this->root_path;
            }
            $this->uri = Utils::replaceFirstOccurrence($orig_root_path, $this->root_path, $this->uri);
        } else {
            $this->root = $this->base . $this->root_path;
        }

        $this->url = $this->base . $this->uri;

        $uri = Utils::replaceFirstOccurrence(static::filterPath($this->root), '', $this->url);

        // remove the setup.php based base if set:
        $setup_base = $grav['pages']->base();
        if ($setup_base) {
            $uri = preg_replace('|^' . preg_quote((string) $setup_base, '|') . '|', '', $uri);
        }
        $this->setup_base = $setup_base;

        // process params
        $uri = $this->processParams($uri, $config->get('system.param_sep'));

        // set active language
        $uri = $language->setActiveFromUri($uri);

        // split the URL and params (and make sure that the path isn't seen as domain)
        $bits = static::parseUrl('http://domain.com' . $uri);

        //process fragment
        if (isset($bits['fragment'])) {
            $this->fragment = $bits['fragment'];
        }

        // Get the path. If there's no path, make sure pathinfo() still returns dirname variable
        $path = $bits['path'] ?? '/';

        // remove the extension if there is one set
        $parts = Utils::pathinfo($path);

        // set the original basename
        $this->basename = $parts['basename'];

        // set the extension
        if (isset($parts['extension'])) {
            $this->extension = $parts['extension'];
        }

        // Strip the file extension for valid page types
        if ($this->isValidExtension($this->extension)) {
            $path = Utils::replaceLastOccurrence(".{$this->extension}", '', $path);
        }

        // set the new url
        $this->url = $this->root . $path;
        $this->path = static::cleanPath($path);
        $this->content_path = trim(Utils::replaceFirstOccurrence($this->base, '', $this->path), '/');
        if ($this->content_path !== '') {
            $this->paths = explode('/', $this->content_path);
        }

        // Set some Grav stuff
        $grav['base_url_absolute'] = $config->get('system.custom_base_url') ?: $this->rootUrl(true);
        $grav['base_url_relative'] = $this->rootUrl(false);
        $grav['base_url'] = $config->get('system.absolute_urls') ? $grav['base_url_absolute'] : $grav['base_url_relative'];

        RouteFactory::setRoot($this->root_path . $setup_base);
        RouteFactory::setLanguage($language->getLanguageURLPrefix());
        RouteFactory::setParamValueDelimiter($config->get('system.param_sep'));
    }

    /**
     * Return URI path.
     *
     * @param int|null $id
     * @return string|string[]
     */
    public function paths($id = null)
    {
        if ($id !== null) {
            return $this->paths[$id];
        }

        return $this->paths;
    }


    /**
     * Return route to the current URI. By default route doesn't include base path.
     *
     * @param bool $absolute True to include full path.
     * @param bool $domain True to include domain. Works only if first parameter is also true.
     * @return string
     */
    public function route($absolute = false, $domain = false)
    {
        return ($absolute ? $this->rootUrl($domain) : '') . '/' . implode('/', $this->paths);
    }

    /**
     * Return full query string or a single query attribute.
     *
     * @param string|null $id Optional attribute. Get a single query attribute if set
     * @param bool $raw If true and $id is not set, return the full query array. Otherwise return the query string
     *
     * @return string|array Returns an array if $id = null and $raw = true
     */
    public function query($id = null, $raw = false)
    {
        if ($id !== null) {
            return $this->queries[$id] ?? null;
        }

        if ($raw) {
            return $this->queries;
        }

        if (!$this->queries) {
            return '';
        }

        return http_build_query($this->queries);
    }

    /**
     * Return all or a single query parameter as a URI compatible string.
     *
     * @param string|null $id Optional parameter name.
     * @param boolean $array return the array format or not
     * @return null|string|array
     */
    public function params($id = null, $array = false)
    {
        $config = Grav::instance()['config'];
        $sep = $config->get('system.param_sep');

        $params = null;
        if ($id === null) {
            if ($array) {
                return $this->params;
            }
            $output = [];
            foreach ($this->params as $key => $value) {
                $output[] = "{$key}{$sep}{$value}";
                $params = '/' . implode('/', $output);
            }
        } elseif (isset($this->params[$id])) {
            if ($array) {
                return $this->params[$id];
            }
            $params = "/{$id}{$sep}{$this->params[$id]}";
        }

        return $params;
    }

    /**
     * Get URI parameter.
     *
     * @param string $id
     * @param string|false|null $default
     * @return string|false|null
     */
    public function param($id, $default = false)
    {
        if (isset($this->params[$id])) {
            return html_entity_decode(rawurldecode((string) $this->params[$id]), ENT_COMPAT | ENT_HTML401, 'UTF-8');
        }

        return $default;
    }

    /**
     * Gets the Fragment portion of a URI (eg #target)
     *
     * @param string|null $fragment
     * @return string|null
     */
    public function fragment($fragment = null)
    {
        if ($fragment !== null) {
            $this->fragment = $fragment;
        }
        return $this->fragment;
    }

    /**
     * Return URL.
     *
     * @param bool $include_host Include hostname.
     * @return string
     */
    public function url($include_host = false)
    {
        if ($include_host) {
            return $this->url;
        }

        $url = Utils::replaceFirstOccurrence($this->base, '', rtrim($this->url, '/'));

        return $url ?: '/';
    }

    /**
     * Return the Path
     *
     * @return string The path of the URI
     */
    public function path()
    {
        return $this->path;
    }

    /**
     * Return the Extension of the URI
     *
     * @param string|null $default
     * @return string|null The extension of the URI
     */
    public function extension($default = null)
    {
        if (!$this->extension) {
            $this->extension = $default;
        }

        return $this->extension;
    }

    /**
     * @return string
     */
    public function method()
    {
        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';

        if ($method === 'POST' && isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            $method = strtoupper((string) $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
        }

        return $method;
    }

    /**
     * Return the scheme of the URI
     *
     * @param bool|null $raw
     * @return string The scheme of the URI
     */
    public function scheme($raw = false)
    {
        if (!$raw) {
            $scheme = '';
            if ($this->scheme) {
                $scheme = $this->scheme . '://';
            } elseif ($this->host) {
                $scheme = '//';
            }

            return $scheme;
        }

        return $this->scheme;
    }


    /**
     * Return the host of the URI
     *
     * @return string|null The host of the URI
     */
    public function host()
    {
        return $this->host;
    }

    /**
     * Return the port number if it can be figured out
     *
     * @param bool $raw
     * @return int|null
     */
    public function port($raw = false)
    {
        $port = $this->port;
        // If not in raw mode and port is not set or is 0, figure it out from scheme.
        if (!$raw && !$port) {
            if ($this->scheme === 'http') {
                $this->port = 80;
            } elseif ($this->scheme === 'https') {
                $this->port = 443;
            }
        }

        return $this->port ?: null;
    }

    /**
     * Return user
     *
     * @return string|null
     */
    public function user()
    {
        return $this->user;
    }

    /**
     * Return password
     *
     * @return string|null
     */
    public function password()
    {
        return $this->password;
    }

    /**
     * Gets the environment name
     *
     * @return string
     */
    public function environment()
    {
        return $this->env;
    }


    /**
     * Return the basename of the URI
     *
     * @return string The basename of the URI
     */
    public function basename()
    {
        return $this->basename;
    }

    /**
     * Return the full uri
     *
     * @param bool $include_root
     * @return string
     */
    public function uri($include_root = true)
    {
        if ($include_root) {
            return $this->uri;
        }

        return Utils::replaceFirstOccurrence($this->root_path, '', $this->uri);
    }

    /**
     * Return the base of the URI
     *
     * @return string The base of the URI
     */
    public function base()
    {
        return $this->base;
    }

    /**
     * Return the base relative URL including the language prefix
     * or the base relative url if multi-language is not enabled
     *
     * @return string The base of the URI
     */
    public function baseIncludingLanguage()
    {
        $grav = Grav::instance();

        /** @var Pages $pages */
        $pages = $grav['pages'];

        return $pages->baseUrl(null, false);
    }

    /**
     * Return root URL to the site.
     *
     * @param bool $include_host Include hostname.
     * @return string
     */
    public function rootUrl($include_host = false)
    {
        if ($include_host) {
            return $this->root;
        }

        return Utils::replaceFirstOccurrence($this->base, '', $this->root);
    }

    /**
     * Return current page number.
     *
     * @return int
     */
    public function currentPage()
    {
        $page = (int)($this->params['page'] ?? 1);

        return max(1, $page);
    }

    /**
     * Return relative path to the referrer defaulting to current or given page.
     *
     * You should set the third parameter to `true` for redirects as long as you came from the same sub-site and language.
     *
     * @param string|null $default
     * @param string|null $attributes
     * @param bool $withoutBaseRoute
     * @return string
     */
    public function referrer($default = null, $attributes = null, bool $withoutBaseRoute = false)
    {
        $referrer = $_SERVER['HTTP_REFERER'] ?? null;

        // Check that referrer came from our site.
        if ($withoutBaseRoute) {
            /** @var Pages $pages */
            $pages = Grav::instance()['pages'];
            $base = $pages->baseUrl(null, true);
        } else {
            $base = $this->rootUrl(true);
        }

        // Referrer should always have host set and it should come from the same base address.
        if (!is_string($referrer) || !str_starts_with($referrer, $base)) {
            $referrer = $default ?: $this->route(true, true);
        }

        // Relative path from grav root.
        $referrer = substr($referrer, strlen($base));
        if ($attributes) {
            $referrer .= $attributes;
        }

        return $referrer;
    }

    /**
     * @return string
     */
    #[\ReturnTypeWillChange]
    public function __toString(): string
    {
        return static::buildUrl($this->toArray());
    }

    /**
     * @return string
     */
    public function toOriginalString()
    {
        return static::buildUrl($this->toArray(true));
    }

    /**
     * @param bool $full
     * @return array
     */
    public function toArray($full = false)
    {
        if ($full === true) {
            $root_path = $this->root_path ?? '';
            $extension = isset($this->extension) && $this->isValidExtension($this->extension) ? '.' . $this->extension : '';
            $path = $root_path . $this->path . $extension;
        } else {
            $path = $this->path;
        }

        return [
            'scheme'    => $this->scheme,
            'host'      => $this->host,
            'port'      => $this->port ?: null,
            'user'      => $this->user,
            'pass'      => $this->password,
            'path'      => $path,
            'params'    => $this->params,
            'query'     => $this->query,
            'fragment'  => $this->fragment
        ];
    }

    /**
     * Calculate the parameter regex based on the param_sep setting
     *
     * @return string
     */
    public static function paramsRegex()
    {
        return '/\/{1,}([^\:\#\/\?]*' . Grav::instance()['config']->get('system.param_sep') . '[^\:\#\/\?]*)/';
    }

    /**
     * Return the IP address of the current user
     *
     * @return string ip address
     */
    public static function ip()
    {
        $ip = 'UNKNOWN';

        if (getenv('HTTP_CLIENT_IP')) {
            $ip = getenv('HTTP_CLIENT_IP');
        } elseif (getenv('HTTP_CF_CONNECTING_IP')) {
            $ip = getenv('HTTP_CF_CONNECTING_IP');
        } elseif (getenv('HTTP_X_FORWARDED_FOR') && Grav::instance()['config']->get('system.http_x_forwarded.ip')) {
            $ips = array_map('trim', explode(',', getenv('HTTP_X_FORWARDED_FOR')));
            $ip = array_shift($ips);
        } elseif (getenv('HTTP_X_FORWARDED') && Grav::instance()['config']->get('system.http_x_forwarded.ip')) {
            $ip = getenv('HTTP_X_FORWARDED');
        } elseif (getenv('HTTP_FORWARDED_FOR')) {
            $ip = getenv('HTTP_FORWARDED_FOR');
        } elseif (getenv('HTTP_FORWARDED')) {
            $ip = getenv('HTTP_FORWARDED');
        } elseif (getenv('REMOTE_ADDR')) {
            $ip = getenv('REMOTE_ADDR');
        }

        return $ip;
    }

    /**
     * Returns current Uri.
     *
     * @return \Grav\Framework\Uri\Uri
     */
    public static function getCurrentUri()
    {
        if (!static::$currentUri) {
            static::$currentUri = UriFactory::createFromEnvironment($_SERVER);
        }

        return static::$currentUri;
    }

    /**
     * Returns current route.
     *
     * @return Route
     */
    public static function getCurrentRoute()
    {
        if (!static::$currentRoute) {
            /** @var Uri $uri */
            $uri = Grav::instance()['uri'];

            static::$currentRoute = RouteFactory::createFromLegacyUri($uri);
        }

        return static::$currentRoute;
    }

    /**
     * Is this an external URL? if it starts with `http` then yes, else false
     *
     * @param  string $url the URL in question
     * @return bool      is eternal state
     */
    public static function isExternal($url)
    {
        return (str_starts_with($url, 'http://') || str_starts_with($url, 'https://') || str_starts_with($url, '//') || str_starts_with($url, 'mailto:') || str_starts_with($url, 'tel:') || str_starts_with($url, 'ftp://') || str_starts_with($url, 'ftps://') || str_starts_with($url, 'news:') || str_starts_with($url, 'irc:') || str_starts_with($url, 'gopher:') || str_starts_with($url, 'nntp:') || str_starts_with($url, 'feed:') || str_starts_with($url, 'cvs:') || str_starts_with($url, 'ssh:') || str_starts_with($url, 'git:') || str_starts_with($url, 'svn:') || str_starts_with($url, 'hg:'));
    }

    /**
     * The opposite of built-in PHP method parse_url()
     *
     * @param array $parsed_url
     * @return string
     */
    public static function buildUrl($parsed_url)
    {
        $scheme    = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . ':' : '';
        $authority = isset($parsed_url['host']) ? '//' : '';
        $host      = $parsed_url['host'] ?? '';
        $port      = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
        $user      = $parsed_url['user'] ?? '';
        $pass      = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
        $pass      = ($user || $pass) ? "{$pass}@" : '';
        $path      = $parsed_url['path'] ?? '';
        $path      = !empty($parsed_url['params']) ? rtrim((string) $path, '/') . static::buildParams($parsed_url['params']) : $path;
        $query     = !empty($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
        $fragment  = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';

        return "{$scheme}{$authority}{$user}{$pass}{$host}{$port}{$path}{$query}{$fragment}";
    }

    /**
     * @param array $params
     * @return string
     */
    public static function buildParams(array $params)
    {
        if (!$params) {
            return '';
        }

        $grav = Grav::instance();
        $sep = $grav['config']->get('system.param_sep');

        $output = [];
        foreach ($params as $key => $value) {
            $output[] = "{$key}{$sep}{$value}";
        }

        return '/' . implode('/', $output);
    }

    /**
     * Converts links from absolute '/' or relative (../..) to a Grav friendly format
     *
     * @param PageInterface $page the current page to use as reference
     * @param string|array $url the URL as it was written in the markdown
     * @param string $type the type of URL, image | link
     * @param bool $absolute if null, will use system default, if true will use absolute links internally
     * @param bool $route_only only return the route, not full URL path
     * @return string|array the more friendly formatted url
     */
    public static function convertUrl(PageInterface $page, $url, $type = 'link', $absolute = false, $route_only = false)
    {
        $grav = Grav::instance();

        $uri = $grav['uri'];

        // Link processing should prepend language
        $language = $grav['language'];
        $language_append = '';
        if ($type === 'link' && $language->enabled()) {
            $language_append = $language->getLanguageURLPrefix();
        }

        // Handle Excerpt style $url array
        $url_path = is_array($url) ? $url['path'] : $url;

        $external          = false;
        $base              = $grav['base_url_relative'];
        $base_url          = rtrim($base . $grav['pages']->base(), '/') . $language_append;
        $pages_dir         = $grav['locator']->findResource('page://');

        // if absolute and starts with a base_url move on
        if (isset($url['scheme']) && Utils::startsWith($url['scheme'], 'http')) {
            $external = true;
        } elseif ($url_path === '' && isset($url['fragment'])) {
            $external = true;
        } elseif ($url_path === '/' || ($base_url !== '' && Utils::startsWith($url_path, $base_url))) {
            $url_path = $base_url . $url_path;
        } else {
            // see if page is relative to this or absolute
            if (Utils::startsWith($url_path, '/')) {
                $normalized_url = Utils::normalizePath($base_url . $url_path);
                $normalized_path = Utils::normalizePath($pages_dir . $url_path);
            } else {
                $page_route = ($page->home() && !empty($url_path)) ? $page->rawRoute() : $page->route();
                $normalized_url = $base_url . Utils::normalizePath(rtrim((string) $page_route, '/') . '/' . $url_path);
                $normalized_path = Utils::normalizePath($page->path() . '/' . $url_path);
            }

            // special check to see if path checking is required.
            $just_path = Utils::replaceFirstOccurrence($normalized_url, '', $normalized_path);
            if ($normalized_url === '/' || $just_path === $page->path()) {
                $url_path = $normalized_url;
            } else {
                $url_bits = static::parseUrl($normalized_path);
                $full_path = $url_bits['path'];
                $raw_full_path = rawurldecode((string) $full_path);

                if (file_exists($raw_full_path)) {
                    $full_path = $raw_full_path;
                } elseif (!file_exists($full_path)) {
                    $full_path = false;
                }

                if ($full_path) {
                    $path_info = Utils::pathinfo($full_path);
                    $page_path = $path_info['dirname'];
                    $filename = '';

                    if ($url_path === '..') {
                        $page_path = $full_path;
                    } else {
                        // save the filename if a file is part of the path
                        if (is_file($full_path)) {
                            if ($path_info['extension'] !== 'md') {
                                $filename = '/' . $path_info['basename'];
                            }
                        } else {
                            $page_path = $full_path;
                        }
                    }

                    // get page instances and try to find one that fits
                    $instances = $grav['pages']->instances();
                    if (isset($instances[$page_path])) {
                        /** @var PageInterface $target */
                        $target = $instances[$page_path];
                        $url_bits['path'] = $base_url . rtrim((string) $target->route(), '/') . $filename;

                        $url_path = Uri::buildUrl($url_bits);
                    } else {
                        $url_path = $normalized_url;
                    }
                } else {
                    $url_path = $normalized_url;
                }
            }
        }

        // handle absolute URLs
        if (is_array($url) && !$external && ($absolute === true || $grav['config']->get('system.absolute_urls', false))) {
            $url['scheme'] = $uri->scheme(true);
            $url['host'] = $uri->host();
            $url['port'] = $uri->port(true);

            // check if page exists for this route, and if so, check if it has SSL enabled
            $pages = $grav['pages'];
            $routes = $pages->routes();

            // if this is an image, get the proper path
            $url_bits = Utils::pathinfo($url_path);
            if (isset($url_bits['extension'])) {
                $target_path = $url_bits['dirname'];
            } else {
                $target_path = $url_path;
            }

            // strip base from this path
            $target_path = Utils::replaceFirstOccurrence($uri->rootUrl(), '', $target_path);

            // set to / if root
            if (empty($target_path)) {
                $target_path = '/';
            }

            // look to see if this page exists and has ssl enabled
            if (isset($routes[$target_path])) {
                $target_page = $pages->get($routes[$target_path]);
                if ($target_page) {
                    $ssl_enabled = $target_page->ssl();
                    if ($ssl_enabled !== null) {
                        if ($ssl_enabled) {
                            $url['scheme'] = 'https';
                        } else {
                            $url['scheme'] = 'http';
                        }
                    }
                }
            }
        }

        // Handle route only
        if ($route_only) {
            $url_path = Utils::replaceFirstOccurrence(static::filterPath($base_url), '', $url_path);
        }

        // transform back to string/array as needed
        if (is_array($url)) {
            $url['path'] = $url_path;
        } else {
            $url = $url_path;
        }

        return $url;
    }

    /**
     * @param string $url
     * @return array|false
     */
    public static function parseUrl($url)
    {
        $grav = Grav::instance();

        // Remove extra slash from streams, parse_url() doesn't like it.
        $url = preg_replace('/([^:])(\/{2,})/', '$1/', $url);

        $encodedUrl = preg_replace_callback(
            '%[^:/@?&=#]+%usD',
            static fn($matches) => rawurlencode((string) $matches[0]),
            (string) $url
        );

        $parts = parse_url((string) $encodedUrl);

        if (false === $parts) {
            return false;
        }

        foreach ($parts as $name => $value) {
            $parts[$name] = rawurldecode((string) $value);
        }

        if (!isset($parts['path'])) {
            $parts['path'] = '';
        }

        [$stripped_path, $params] = static::extractParams($parts['path'], $grav['config']->get('system.param_sep'));

        if (!empty($params)) {
            $parts['path'] = $stripped_path;
            $parts['params'] = $params;
        }

        return $parts;
    }

    /**
     * @param string $uri
     * @param string $delimiter
     * @return array
     */
    public static function extractParams($uri, $delimiter)
    {
        $params = [];

        if (str_contains($uri, $delimiter)) {
            preg_match_all(static::paramsRegex(), $uri, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $param = explode($delimiter, $match[1]);
                if (count($param) === 2) {
                    $plain_var = htmlspecialchars(strip_tags(rawurldecode($param[1])), ENT_QUOTES, 'UTF-8');
                    $params[$param[0]] = $plain_var;
                    $uri = str_replace($match[0], '', $uri);
                }
            }
        }

        return [$uri, $params];
    }

    /**
     * Converts links from absolute '/' or relative (../..) to a Grav friendly format
     *
     * @param PageInterface   $page         the current page to use as reference
     * @param string $markdown_url the URL as it was written in the markdown
     * @param string $type         the type of URL, image | link
     * @param bool|null $relative     if null, will use system default, if true will use relative links internally
     *
     * @return string the more friendly formatted url
     */
    public static function convertUrlOld(PageInterface $page, $markdown_url, $type = 'link', $relative = null)
    {
        $grav = Grav::instance();

        $language = $grav['language'];

        // Link processing should prepend language
        $language_append = '';
        if ($type === 'link' && $language->enabled()) {
            $language_append = $language->getLanguageURLPrefix();
        }
        $pages_dir = $grav['locator']->findResource('page://');
        if ($relative === null) {
            $base = $grav['base_url'];
        } else {
            $base = $relative ? $grav['base_url_relative'] : $grav['base_url_absolute'];
        }

        $base_url = rtrim($base . $grav['pages']->base(), '/') . $language_append;

        // if absolute and starts with a base_url move on
        if (Utils::pathinfo($markdown_url, PATHINFO_DIRNAME) === '.' && $page->url() === '/') {
            return '/' . $markdown_url;
        }
        // no path to convert
        if ($base_url !== '' && Utils::startsWith($markdown_url, $base_url)) {
            return $markdown_url;
        }
        // if contains only a fragment
        if (Utils::startsWith($markdown_url, '#')) {
            return $markdown_url;
        }

        $target = null;
        // see if page is relative to this or absolute
        if (Utils::startsWith($markdown_url, '/')) {
            $normalized_url = Utils::normalizePath($base_url . $markdown_url);
            $normalized_path = Utils::normalizePath($pages_dir . $markdown_url);
        } else {
            $normalized_url = $base_url . Utils::normalizePath($page->route() . '/' . $markdown_url);
            $normalized_path = Utils::normalizePath($page->path() . '/' . $markdown_url);
        }

        // special check to see if path checking is required.
        $just_path = Utils::replaceFirstOccurrence($normalized_url, '', $normalized_path);
        if ($just_path === $page->path()) {
            return $normalized_url;
        }

        $url_bits = parse_url($normalized_path);
        $full_path = $url_bits['path'];

        if (file_exists($full_path)) {
            // do nothing
        } elseif (file_exists(rawurldecode($full_path))) {
            $full_path = rawurldecode($full_path);
        } else {
            return $normalized_url;
        }

        $path_info = Utils::pathinfo($full_path);
        $page_path = $path_info['dirname'];
        $filename = '';

        if ($markdown_url === '..') {
            $page_path = $full_path;
        } else {
            // save the filename if a file is part of the path
            if (is_file($full_path)) {
                if ($path_info['extension'] !== 'md') {
                    $filename = '/' . $path_info['basename'];
                }
            } else {
                $page_path = $full_path;
            }
        }

        // get page instances and try to find one that fits
        $instances = $grav['pages']->instances();
        if (isset($instances[$page_path])) {
            /** @var PageInterface $target */
            $target = $instances[$page_path];
            $url_bits['path'] = $base_url . rtrim((string) $target->route(), '/') . $filename;

            return static::buildUrl($url_bits);
        }

        return $normalized_url;
    }

    /**
     * Adds the nonce to a URL for a specific action
     *
     * @param string $url            the url
     * @param string $action         the action
     * @param string $nonceParamName the param name to use
     *
     * @return string the url with the nonce
     */
    public static function addNonce($url, $action, $nonceParamName = 'nonce')
    {
        $fake = $url && str_starts_with($url, '/');

        if ($fake) {
            $url = 'http://domain.com' . $url;
        }
        $uri = new static($url);
        $parts = $uri->toArray();
        $nonce = Utils::getNonce($action);
        $parts['params'] = ($parts['params'] ?? []) + [$nonceParamName => $nonce];

        if ($fake) {
            unset($parts['scheme'], $parts['host']);
        }

        return static::buildUrl($parts);
    }

    /**
     * Is the passed in URL a valid URL?
     *
     * @param string $url
     * @return bool
     */
    public static function isValidUrl($url)
    {
        $regex = '/^(?:(https?|ftp|telnet):)?\/\/((?:[a-z0-9@:.-]|%[0-9A-F]{2}){3,})(?::(\d+))?((?:\/(?:[a-z0-9-._~!$&\'\(\)\*\+\,\;\=\:\@]|%[0-9A-F]{2})*)*)(?:\?((?:[a-z0-9-._~!$&\'\(\)\*\+\,\;\=\:\/?@]|%[0-9A-F]{2})*))?(?:#((?:[a-z0-9-._~!$&\'\(\)\*\+\,\;\=\:\/?@]|%[0-9A-F]{2})*))?/';

        return (bool)preg_match($regex, $url);
    }

    /**
     * Removes extra double slashes and fixes back-slashes
     *
     * @param string $path
     * @return string
     */
    public static function cleanPath($path)
    {
        $regex = '/(\/)\/+/';
        $path = str_replace(['\\', '/ /'], '/', $path);
        $path = preg_replace($regex, '$1', $path);

        return $path;
    }

    /**
     * Filters the user info string.
     *
     * @param string|null $info The raw user or password.
     * @return string The percent-encoded user or password string.
     */
    public static function filterUserInfo($info)
    {
        return $info !== null ? UriPartsFilter::filterUserInfo($info) : '';
    }

    /**
     * Filter Uri path.
     *
     * This method percent-encodes all reserved
     * characters in the provided path string. This method
     * will NOT double-encode characters that are already
     * percent-encoded.
     *
     * @param  string|null $path The raw uri path.
     * @return string       The RFC 3986 percent-encoded uri path.
     * @link   http://www.faqs.org/rfcs/rfc3986.html
     */
    public static function filterPath($path)
    {
        return $path !== null ? UriPartsFilter::filterPath($path) : '';
    }

    /**
     * Filters the query string or fragment of a URI.
     *
     * @param string|null $query The raw uri query string.
     * @return string The percent-encoded query string.
     */
    public static function filterQuery($query)
    {
        return $query !== null ? UriPartsFilter::filterQueryOrFragment($query) : '';
    }

    /**
     * @param array $env
     * @return void
     */
    protected function createFromEnvironment(array $env)
    {
        // Build scheme.
        if (isset($env['HTTP_X_FORWARDED_PROTO']) && Grav::instance()['config']->get('system.http_x_forwarded.protocol')) {
            $this->scheme = $env['HTTP_X_FORWARDED_PROTO'];
        } elseif (isset($env['X-FORWARDED-PROTO'])) {
            $this->scheme = $env['X-FORWARDED-PROTO'];
        } elseif (isset($env['HTTP_CLOUDFRONT_FORWARDED_PROTO'])) {
            $this->scheme = $env['HTTP_CLOUDFRONT_FORWARDED_PROTO'];
        } elseif (isset($env['REQUEST_SCHEME']) && empty($env['HTTPS'])) {
            $this->scheme = $env['REQUEST_SCHEME'];
        } else {
            $https = $env['HTTPS'] ?? '';
            $this->scheme = (empty($https) || strtolower((string) $https) === 'off') ? 'http' : 'https';
        }

        // Build user and password.
        $this->user = $env['PHP_AUTH_USER'] ?? null;
        $this->password = $env['PHP_AUTH_PW'] ?? null;

        // Build host.
        if (isset($env['HTTP_X_FORWARDED_HOST']) && Grav::instance()['config']->get('system.http_x_forwarded.host')) {
            $hostname = $env['HTTP_X_FORWARDED_HOST'];
        } else if (isset($env['HTTP_HOST'])) {
            $hostname = $env['HTTP_HOST'];
        } elseif (isset($env['SERVER_NAME'])) {
            $hostname = $env['SERVER_NAME'];
        } else {
            $hostname = 'localhost';
        }
        // Remove port from HTTP_HOST generated $hostname
        $hostname = Utils::substrToString($hostname, ':');
        // Validate the hostname
        $this->host = $this->validateHostname($hostname) ? $hostname : 'unknown';

        // Build port.
        if (isset($env['HTTP_X_FORWARDED_PORT']) && Grav::instance()['config']->get('system.http_x_forwarded.port')) {
            $this->port = (int)$env['HTTP_X_FORWARDED_PORT'];
        } elseif (isset($env['X-FORWARDED-PORT'])) {
            $this->port = (int)$env['X-FORWARDED-PORT'];
        } elseif (isset($env['HTTP_CLOUDFRONT_FORWARDED_PROTO'])) {
           // Since AWS Cloudfront does not provide a forwarded port header,
           // we have to build the port using the scheme.
            $this->port = $this->port();
        } elseif (isset($env['SERVER_PORT'])) {
            $this->port = (int)$env['SERVER_PORT'];
        } else {
            $this->port = null;
        }

        if ($this->port === 0 || $this->hasStandardPort()) {
            $this->port = null;
        }

        // Build path.
        $request_uri = $env['REQUEST_URI'] ?? '/';
        $this->path = rawurldecode(parse_url('http://example.com' . $request_uri, PHP_URL_PATH));

        // Build query string.
        $this->query = $env['QUERY_STRING'] ?? '';
        if ($this->query === '') {
            $this->query = parse_url('http://example.com' . $request_uri, PHP_URL_QUERY) ?? '';
        }

        // Support ngnix routes.
        if (str_starts_with((string) $this->query, '_url=')) {
            parse_str((string) $this->query, $query);
            unset($query['_url']);
            $this->query = http_build_query($query);
        }

        // Build fragment.
        $this->fragment = null;

        // Filter userinfo, path and query string.
        $this->user = $this->user !== null ? static::filterUserInfo($this->user) : null;
        $this->password = $this->password !== null ? static::filterUserInfo($this->password) : null;
        $this->path = empty($this->path) ? '/' : static::filterPath($this->path);
        $this->query = static::filterQuery($this->query);

        $this->reset();
    }

    /**
     * Does this Uri use a standard port?
     *
     * @return bool
     */
    protected function hasStandardPort()
    {
        return (!$this->port || $this->port === 80 || $this->port === 443);
    }

    /**
     * @param string $url
     */
    protected function createFromString($url)
    {
        // Set Uri parts.
        $parts = parse_url($url);
        if ($parts === false) {
            throw new RuntimeException('Malformed URL: ' . $url);
        }
        $port = (int)($parts['port'] ?? 0);

        $this->scheme = $parts['scheme'] ?? null;
        $this->user = $parts['user'] ?? null;
        $this->password = $parts['pass'] ?? null;
        $this->host = $parts['host'] ?? null;
        $this->port = $port ?: null;
        $this->path = $parts['path'] ?? '';
        $this->query = $parts['query'] ?? '';
        $this->fragment = $parts['fragment'] ?? null;

        // Validate the hostname
        if ($this->host) {
            $this->host = $this->validateHostname($this->host) ? $this->host : 'unknown';
        }
        // Filter userinfo, path, query string and fragment.
        $this->user = $this->user !== null ? static::filterUserInfo($this->user) : null;
        $this->password = $this->password !== null ? static::filterUserInfo($this->password) : null;
        $this->path = empty($this->path) ? '/' : static::filterPath($this->path);
        $this->query = static::filterQuery($this->query);
        $this->fragment = $this->fragment !== null ? static::filterQuery($this->fragment) : null;

        $this->reset();
    }

    /**
     * @return void
     */
    protected function reset()
    {
        // resets
        parse_str($this->query, $this->queries);
        $this->extension    = null;
        $this->basename     = null;
        $this->paths        = [];
        $this->params       = [];
        $this->env          = $this->buildEnvironment();
        $this->uri          = $this->path . (!empty($this->query) ? '?' . $this->query : '');

        $this->base         = $this->buildBaseUrl();
        $this->root_path    = $this->buildRootPath();
        $this->root         = $this->base . $this->root_path;
        $this->url          = $this->base . $this->uri;
    }

    /**
     * Get post from either $_POST or JSON response object
     * By default returns all data, or can return a single item
     *
     * @param string|null $element
     * @param string|null $filter_type
     * @return array|null
     */
    public function post($element = null, $filter_type = null)
    {
        if (!$this->post) {
            $content_type = $this->getContentType();
            if ($content_type === 'application/json') {
                $json = file_get_contents('php://input');
                $this->post = json_decode($json, true);
            } elseif (!empty($_POST)) {
                $this->post = (array)$_POST;
            }

            $event = new Event(['post' => &$this->post]);
            Grav::instance()->fireEvent('onHttpPostFilter', $event);
        }

        if ($this->post && null !== $element) {
            $item = Utils::getDotNotation($this->post, $element);
            if ($filter_type) {
                if ($filter_type === FILTER_SANITIZE_STRING || $filter_type === GRAV_SANITIZE_STRING) {
                    $item = htmlspecialchars(strip_tags((string) $item), ENT_QUOTES, 'UTF-8');
                } else {
                    $item = filter_var($item, $filter_type);
                }
            }
            return $item;
        }

        return $this->post;
    }

    /**
     * Get content type from request
     *
     * @param bool $short
     * @return null|string
     */
    public function getContentType($short = true)
    {
       $content_type = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? $_SERVER['HTTP_ACCEPT'] ?? null;
        if ($content_type) {
            if ($short) {
                return Utils::substrToString($content_type, ';');
            }
        }
        return $content_type;
    }

    /**
     * Check if this is a valid Grav extension
     *
     * @param string|null $extension
     * @return bool
     */
    public function isValidExtension($extension): bool
    {
        $extension = (string)$extension;

        return $extension !== '' && in_array($extension, Utils::getSupportPageTypes(), true);
    }

    /**
     * Allow overriding of any element (be careful!)
     *
     * @param array $data
     * @return Uri
     */
    public function setUriProperties($data)
    {
        foreach (get_object_vars($this) as $property => $default) {
            if (!array_key_exists($property, $data)) {
                continue;
            }
            $this->{$property} = $data[$property]; // assign value to object
        }
        return $this;
    }


    /**
     * Compatibility in case getallheaders() is not available on platform
     */
    public static function getAllHeaders()
    {
        if (!function_exists('getallheaders')) {
            $headers = [];
            foreach ($_SERVER as $name => $value) {
                if (str_starts_with((string) $name, 'HTTP_')) {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr((string) $name, 5)))))] = $value;
                }
            }
            return $headers;
        }
        return getallheaders();
    }

    /**
     * Get the base URI with port if needed
     *
     * @return string
     */
    private function buildBaseUrl()
    {
        return $this->scheme() . $this->host;
    }

    /**
     * Get the Grav Root Path
     *
     * @return string
     */
    private function buildRootPath()
    {
        // In Windows script path uses backslash, convert it:
        $scriptPath = str_replace('\\', '/', $_SERVER['PHP_SELF']);
        $rootPath = str_replace(' ', '%20', rtrim(substr($scriptPath, 0, strpos($scriptPath, 'index.php')), '/'));

        return $rootPath;
    }

    /**
     * @return string
     */
    private function buildEnvironment()
    {
        // check for localhost variations
        if ($this->host === '127.0.0.1' || $this->host === '::1') {
            return 'localhost';
        }

        return $this->host ?: 'unknown';
    }

    /**
     * Process any params based in this URL, supports any valid delimiter
     *
     * @param string $uri
     * @param string $delimiter
     * @return string
     */
    private function processParams(string $uri, string $delimiter = ':'): string
    {
        if (str_contains($uri, $delimiter)) {
            preg_match_all(static::paramsRegex(), $uri, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $param = explode($delimiter, $match[1]);
                if (count($param) === 2) {
                    $plain_var = htmlspecialchars(strip_tags($param[1]), ENT_QUOTES, 'UTF-8');
                    $this->params[$param[0]] = $plain_var;
                    $uri = str_replace($match[0], '', $uri);
                }
            }
        }
        return $uri;
    }
}
