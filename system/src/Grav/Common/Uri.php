<?php
/**
 * @package    Grav.Common
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

use Grav\Common\Config\Config;
use Grav\Common\Language\Language;
use Grav\Common\Page\Page;
use Grav\Common\Page\Pages;
use Grav\Framework\Route\RouteFactory;
use Grav\Framework\Uri\UriFactory;
use Grav\Framework\Uri\UriPartsFilter;
use RocketTheme\Toolbox\Event\Event;

class Uri
{
    const HOSTNAME_REGEX = '/^(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z0-9]|[A-Za-z0-9][A-Za-z0-9\-]*[A-Za-z0-9])$/';

    /** @var \Grav\Framework\Uri\Uri */
    protected static $currentUri;

    /** @var \Grav\Framework\Route\Route */
    protected static $currentRoute;

    public $url;

    // Uri parts.
    protected $scheme;
    protected $user;
    protected $password;
    protected $host;
    protected $port;
    protected $path;
    protected $query;
    protected $fragment;

    // Internal stuff.
    protected $base;
    protected $basename;
    protected $content_path;
    protected $extension;
    protected $env;
    protected $paths;
    protected $queries;
    protected $params;
    protected $root;
    protected $root_path;
    protected $uri;
    protected $content_type;
    protected $post;

    /**
     * Uri constructor.
     * @param string|array $env
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
     *
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
     *
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
     *
     * @return boolean
     */
    public function validateHostname($hostname)
    {
        return (bool)preg_match(static::HOSTNAME_REGEX, $hostname);
    }

    /**
     * Initializes the URI object based on the url set on the object
     */
    public function init()
    {
        $grav = Grav::instance();

        /** @var Config $config */
        $config = $grav['config'];

        /** @var Language $language */
        $language = $grav['language'];

        // add the port to the base for non-standard ports
        if ($this->port !== null && $config->get('system.reverse_proxy_setup') === false) {
            $this->base .= ':' . (string)$this->port;
        }

        // Handle custom base
        $custom_base = rtrim($grav['config']->get('system.custom_base_url'), '/');

        if ($custom_base) {
            $custom_parts = parse_url($custom_base);
            $orig_root_path = $this->root_path;
            $this->root_path = isset($custom_parts['path']) ? rtrim($custom_parts['path'], '/') : '';
            if (isset($custom_parts['scheme'])) {
                $this->base = $custom_parts['scheme'] . '://' . $custom_parts['host'];
                $this->root = $custom_base;
            } else {
                $this->root = $this->base . $this->root_path;
            }
            $this->uri       = Utils::replaceFirstOccurrence($orig_root_path, $this->root_path, $this->uri);
        } else {
            $this->root = $this->base . $this->root_path;
        }

        $this->url = $this->base . $this->uri;

        $uri = str_replace(static::filterPath($this->root), '', $this->url);

        // remove the setup.php based base if set:
        $setup_base = $grav['pages']->base();
        if ($setup_base) {
            $uri = preg_replace('|^' . preg_quote($setup_base, '|') . '|', '', $uri);
        }

        // process params
        $uri = $this->processParams($uri, $config->get('system.param_sep'));

        // set active language
        $uri = $language->setActiveFromUri($uri);

        // split the URL and params
        $bits = parse_url($uri);

        //process fragment
        if (isset($bits['fragment'])) {
            $this->fragment = $bits['fragment'];
        }

        // Get the path. If there's no path, make sure pathinfo() still returns dirname variable
        $path = isset($bits['path']) ? $bits['path'] : '/';

        // remove the extension if there is one set
        $parts = pathinfo($path);

        // set the original basename
        $this->basename = $parts['basename'];

        // set the extension
        if (isset($parts['extension'])) {
            $this->extension = $parts['extension'];
        }

        $valid_page_types = implode('|', $config->get('system.pages.types'));

        // Strip the file extension for valid page types
        if (preg_match('/\.(' . $valid_page_types . ')$/', $parts['basename'])) {
            $path = rtrim(str_replace(DIRECTORY_SEPARATOR, DS, $parts['dirname']), DS) . '/' . $parts['filename'];
        }

        // set the new url
        $this->url = $this->root . $path;
        $this->path = static::cleanPath($path);
        $this->content_path = trim(str_replace($this->base, '', $this->path), '/');
        if ($this->content_path !== '') {
            $this->paths = explode('/', $this->content_path);
        }

        // Set some Grav stuff
        $grav['base_url_absolute'] = $config->get('system.custom_base_url') ?: $this->rootUrl(true);
        $grav['base_url_relative'] = $this->rootUrl(false);
        $grav['base_url'] = $config->get('system.absolute_urls') ? $grav['base_url_absolute'] : $grav['base_url_relative'];

        RouteFactory::setRoot($this->root_path);
        RouteFactory::setLanguage($language->getLanguageURLPrefix());
    }

    /**
     * Return URI path.
     *
     * @param  string $id
     *
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
     * @param  bool $absolute True to include full path.
     * @param  bool $domain   True to include domain. Works only if first parameter is also true.
     *
     * @return string
     */
    public function route($absolute = false, $domain = false)
    {
        return ($absolute ? $this->rootUrl($domain) : '') . '/' . implode('/', $this->paths);
    }

    /**
     * Return full query string or a single query attribute.
     *
     * @param  string $id  Optional attribute. Get a single query attribute if set
     * @param  bool   $raw If true and $id is not set, return the full query array. Otherwise return the query string
     *
     * @return string|array Returns an array if $id = null and $raw = true
     */
    public function query($id = null, $raw = false)
    {
        if ($id !== null) {
            return isset($this->queries[$id]) ? $this->queries[$id] : null;
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
     * @param  string  $id    Optional parameter name.
     * @param  boolean $array return the array format or not
     *
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
     * @param  string $id
     *
     * @return bool|string
     */
    public function param($id)
    {
        if (isset($this->params[$id])) {
            return html_entity_decode(rawurldecode($this->params[$id]));
        }

        return false;
    }

    /**
     * Gets the Fragment portion of a URI (eg #target)
     *
     * @param string $fragment
     *
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
     * @param  bool $include_host Include hostname.
     *
     * @return string
     */
    public function url($include_host = false)
    {
        if ($include_host) {
            return $this->url;
        }

        $url = str_replace($this->base, '', rtrim($this->url, '/'));

        return $url ?: '/';
    }

    /**
     * Return the Path
     *
     * @return String The path of the URI
     */
    public function path()
    {
        return $this->path;
    }

    /**
     * Return the Extension of the URI
     *
     * @param string|null $default
     *
     * @return string The extension of the URI
     */
    public function extension($default = null)
    {
        if (!$this->extension) {
            $this->extension = $default;
        }

        return $this->extension;
    }

    public function method()
    {
        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';

        if ($method === 'POST' && isset($_SERVER['X-HTTP-METHOD-OVERRIDE'])) {
            $method = strtoupper($_SERVER['X-HTTP-METHOD-OVERRIDE']);
        }

        return $method;
    }

    /**
     * Return the scheme of the URI
     *
     * @param bool $raw
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
        // If not in raw mode and port is not set, figure it out from scheme.
        if (!$raw && $port === null) {
            if ($this->scheme === 'http') {
                $this->port = 80;
            } elseif ($this->scheme === 'https') {
                $this->port = 443;
            }
        }

        return $this->port;
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
     * @return String
     */
    public function environment()
    {
        return $this->env;
    }


    /**
     * Return the basename of the URI
     *
     * @return String The basename of the URI
     */
    public function basename()
    {
        return $this->basename;
    }

    /**
     * Return the full uri
     *
     * @param bool $include_root
     * @return mixed
     */
    public function uri($include_root = true)
    {
        if ($include_root) {
            return $this->uri;
        }

        return str_replace($this->root_path, '', $this->uri);
    }

    /**
     * Return the base of the URI
     *
     * @return String The base of the URI
     */
    public function base()
    {
        return $this->base;
    }

    /**
     * Return the base relative URL including the language prefix
     * or the base relative url if multi-language is not enabled
     *
     * @return String The base of the URI
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
     * @param  bool $include_host Include hostname.
     *
     * @return mixed
     */
    public function rootUrl($include_host = false)
    {
        if ($include_host) {
            return $this->root;
        }

        return str_replace($this->base, '', $this->root);
    }

    /**
     * Return current page number.
     *
     * @return int
     */
    public function currentPage()
    {
        return isset($this->params['page']) ? $this->params['page'] : 1;
    }

    /**
     * Return relative path to the referrer defaulting to current or given page.
     *
     * @param string $default
     * @param string $attributes
     *
     * @return string
     */
    public function referrer($default = null, $attributes = null)
    {
        $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;

        // Check that referrer came from our site.
        $root = $this->rootUrl(true);
        if ($referrer) {
            // Referrer should always have host set and it should come from the same base address.
            if (stripos($referrer, $root) !== 0) {
                $referrer = null;
            }
        }

        if (!$referrer) {
            $referrer = $default ?: $this->route(true, true);
        }

        if ($attributes) {
            $referrer .= $attributes;
        }

        // Return relative path.
        return substr($referrer, strlen($root));
    }

    public function __toString()
    {
        return static::buildUrl($this->toArray());
    }

    public function toArray()
    {
        return [
            'scheme'    => $this->scheme,
            'host'      => $this->host,
            'port'      => $this->port,
            'user'      => $this->user,
            'pass'      => $this->password,
            'path'      => $this->path,
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
        return '/\/([^\:\#\/\?]*' . Grav::instance()['config']->get('system.param_sep') . '[^\:\#\/\?]*)/';
    }

    /**
     * Return the IP address of the current user
     *
     * @return string ip address
     */
    public static function ip()
    {
        if (getenv('HTTP_CLIENT_IP')) {
            $ip = getenv('HTTP_CLIENT_IP');
        } elseif (getenv('HTTP_X_FORWARDED_FOR')) {
            $ip = getenv('HTTP_X_FORWARDED_FOR');
        } elseif (getenv('HTTP_X_FORWARDED')) {
            $ip = getenv('HTTP_X_FORWARDED');
        } elseif (getenv('HTTP_FORWARDED_FOR')) {
            $ip = getenv('HTTP_FORWARDED_FOR');
        } elseif (getenv('HTTP_FORWARDED')) {
            $ip = getenv('HTTP_FORWARDED');
        } elseif (getenv('REMOTE_ADDR')){
            $ip = getenv('REMOTE_ADDR');
        } else {
            $ip = 'UNKNOWN';
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
     * @return \Grav\Framework\Route\Route
     */
    public static function getCurrentRoute()
    {
        if (!static::$currentRoute) {
            $uri = Grav::instance()['uri'];
            static::$currentRoute = RouteFactory::createFromParts($uri->toArray());
        }

        return static::$currentRoute;
    }

    /**
     * Is this an external URL? if it starts with `http` then yes, else false
     *
     * @param  string $url the URL in question
     *
     * @return boolean      is eternal state
     */
    public static function isExternal($url)
    {
        return Utils::startsWith($url, 'http');
    }

    /**
     * The opposite of built-in PHP method parse_url()
     *
     * @param array $parsed_url
     *
     * @return string
     */
    public static function buildUrl($parsed_url)
    {
        $scheme    = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . ':' : '';
        $authority = isset($parsed_url['host']) ? '//' : '';
        $host      = isset($parsed_url['host']) ? $parsed_url['host'] : '';
        $port      = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
        $user      = isset($parsed_url['user']) ? $parsed_url['user'] : '';
        $pass      = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
        $pass      = ($user || $pass) ? "{$pass}@" : '';
        $path      = isset($parsed_url['path']) ? $parsed_url['path'] : '';
        $path      = !empty($parsed_url['params']) ? rtrim($path, '/') . static::buildParams($parsed_url['params']) : $path;
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
     * @param Page $page the current page to use as reference
     * @param string|array $url the URL as it was written in the markdown
     * @param string $type the type of URL, image | link
     * @param bool $absolute if null, will use system default, if true will use absolute links internally
     * @param bool $route_only only return the route, not full URL path
     * @return string the more friendly formatted url
     */
    public static function convertUrl(Page $page, $url, $type = 'link', $absolute = false, $route_only = false)
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
                $normalized_url = $base_url . Utils::normalizePath($page_route . '/' . $url_path);
                $normalized_path = Utils::normalizePath($page->path() . '/' . $url_path);
            }

            // special check to see if path checking is required.
            $just_path = str_replace($normalized_url, '', $normalized_path);
            if ($normalized_url === '/' || $just_path === $page->path()) {
                $url_path = $normalized_url;
            } else {
                $url_bits = static::parseUrl($normalized_path);
                $full_path = $url_bits['path'];
                $raw_full_path = rawurldecode($full_path);

                if (file_exists($raw_full_path)) {
                    $full_path = $raw_full_path;
                } elseif (!file_exists($full_path)) {
                    $full_path = false;
                }

                if ($full_path) {
                    $path_info = pathinfo($full_path);
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
                        /** @var Page $target */
                        $target = $instances[$page_path];
                        $url_bits['path'] = $base_url . rtrim($target->route(), '/') . $filename;

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
            $url_bits = pathinfo($url_path);
            if (isset($url_bits['extension'])) {
                $target_path = $url_bits['dirname'];
            } else {
                $target_path = $url_path;
            }

            // strip base from this path
            $target_path = str_replace($uri->rootUrl(), '', $target_path);

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
            $url_path = str_replace(static::filterPath($base_url), '', $url_path);
        }

        // transform back to string/array as needed
        if (is_array($url)) {
            $url['path'] = $url_path;
        } else {
            $url = $url_path;
        }

        return $url;
    }

    public static function parseUrl($url)
    {
        $grav = Grav::instance();

        $encodedUrl = preg_replace_callback(
            '%[^:/@?&=#]+%usD',
            function ($matches) { return rawurlencode($matches[0]); },
            $url
        );

        $parts = parse_url($encodedUrl);

        if (false === $parts) {
            return false;
        }

        foreach($parts as $name => $value) {
            $parts[$name] = rawurldecode($value);
        }

        if (!isset($parts['path'])) {
            $parts['path'] = '';
        }

        list($stripped_path, $params) = static::extractParams($parts['path'], $grav['config']->get('system.param_sep'));

        if (!empty($params)) {
            $parts['path'] = $stripped_path;
            $parts['params'] = $params;
        }

        return $parts;
    }

    public static function extractParams($uri, $delimiter)
    {
        $params = [];

        if (strpos($uri, $delimiter) !== false) {
            preg_match_all(static::paramsRegex(), $uri, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $param = explode($delimiter, $match[1]);
                if (count($param) === 2) {
                    $plain_var = filter_var(rawurldecode($param[1]), FILTER_SANITIZE_STRING);
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
     * @param Page   $page         the current page to use as reference
     * @param string $markdown_url the URL as it was written in the markdown
     * @param string $type         the type of URL, image | link
     * @param null   $relative     if null, will use system default, if true will use relative links internally
     *
     * @return string the more friendly formatted url
     */
    public static function convertUrlOld(Page $page, $markdown_url, $type = 'link', $relative = null)
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
        if (pathinfo($markdown_url, PATHINFO_DIRNAME) === '.' && $page->url() === '/') {
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
        $just_path = str_replace($normalized_url, '', $normalized_path);
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

        $path_info = pathinfo($full_path);
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
            /** @var Page $target */
            $target = $instances[$page_path];
            $url_bits['path'] = $base_url . rtrim($target->route(), '/') . $filename;

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
        $fake = $url && $url[0] === '/';

        if ($fake) {
            $url = 'http://domain.com' . $url;
        }
        $uri = new static($url);
        $parts = $uri->toArray();
        $nonce = Utils::getNonce($action);
        $parts['params'] = (isset($parts['params']) ? $parts['params'] : []) + [$nonceParamName => $nonce];

        if ($fake) {
            unset($parts['scheme'], $parts['host']);
        }

        return static::buildUrl($parts);
    }

    /**
     * Is the passed in URL a valid URL?
     *
     * @param $url
     * @return bool
     */
    public static function isValidUrl($url)
    {
        $regex = '/^(?:(https?|ftp|telnet):)?\/\/((?:[a-z0-9@:.-]|%[0-9A-F]{2}){3,})(?::(\d+))?((?:\/(?:[a-z0-9-._~!$&\'\(\)\*\+\,\;\=\:\@]|%[0-9A-F]{2})*)*)(?:\?((?:[a-z0-9-._~!$&\'\(\)\*\+\,\;\=\:\/?@]|%[0-9A-F]{2})*))?(?:#((?:[a-z0-9-._~!$&\'\(\)\*\+\,\;\=\:\/?@]|%[0-9A-F]{2})*))?/';
        if (preg_match($regex, $url)) {
            return true;
        }

        return false;
    }

    /**
     * Removes extra double slashes and fixes back-slashes
     *
     * @param $path
     * @return mixed|string
     */
    public static function cleanPath($path)
    {
        $regex = '/(\/)\/+/';
        $path = str_replace(['\\', '/ /'], '/', $path);
        $path = preg_replace($regex,'$1',$path);

        return $path;
    }

    /**
     * Filters the user info string.
     *
     * @param string $info The raw user or password.
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
     * @param  string $path The raw uri path.
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
     * @param string $query The raw uri query string.
     * @return string The percent-encoded query string.
     */
    public static function filterQuery($query)
    {
        return $query !== null ? UriPartsFilter::filterQueryOrFragment($query) : '';
    }

    /**
     * @param array $env
     */
    protected function createFromEnvironment(array $env)
    {
        // Build scheme.
        if (isset($env['HTTP_X_FORWARDED_PROTO'])) {
            $this->scheme = $env['HTTP_X_FORWARDED_PROTO'];
        } elseif (isset($env['X-FORWARDED-PROTO'])) {
            $this->scheme = $env['X-FORWARDED-PROTO'];
        } elseif (isset($env['HTTP_CLOUDFRONT_FORWARDED_PROTO'])) {
            $this->scheme = $env['HTTP_CLOUDFRONT_FORWARDED_PROTO'];
        } elseif (isset($env['REQUEST_SCHEME'])) {
           $this->scheme = $env['REQUEST_SCHEME'];
        } else {
            $https = isset($env['HTTPS']) ? $env['HTTPS'] : '';
            $this->scheme = (empty($https) || strtolower($https) === 'off') ? 'http' : 'https';
        }

        // Build user and password.
        $this->user = isset($env['PHP_AUTH_USER']) ? $env['PHP_AUTH_USER'] : null;
        $this->password = isset($env['PHP_AUTH_PW']) ? $env['PHP_AUTH_PW'] : null;

        // Build host.
        $hostname = 'localhost';
        if (isset($env['HTTP_HOST'])) {
            $hostname = $env['HTTP_HOST'];
        } elseif (isset($env['SERVER_NAME'])) {
            $hostname = $env['SERVER_NAME'];
        }
        // Remove port from HTTP_HOST generated $hostname
        $hostname = Utils::substrToString($hostname, ':');
        // Validate the hostname
        $this->host = $this->validateHostname($hostname) ? $hostname : 'unknown';

        // Build port.
        if (isset($env['HTTP_X_FORWARDED_PORT'])) {
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

        if ($this->hasStandardPort()) {
            $this->port = null;
        }

        // Build path.
        $request_uri = isset($env['REQUEST_URI']) ? $env['REQUEST_URI'] : '';
        $this->path = rawurldecode(parse_url('http://example.com' . $request_uri, PHP_URL_PATH));

        // Build query string.
        $this->query = isset($env['QUERY_STRING']) ? $env['QUERY_STRING'] : '';
        if ($this->query === '') {
            $this->query = parse_url('http://example.com' . $request_uri, PHP_URL_QUERY);
        }

        // Support ngnix routes.
        if (strpos($this->query, '_url=') === 0) {
            parse_str($this->query, $query);
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
        return ($this->scheme === 'http' && $this->port === 80) || ($this->scheme === 'https' && $this->port === 443);
    }

    /**
     * @param string $url
     */
    protected function createFromString($url)
    {
        // Set Uri parts.
        $parts = parse_url($url);
        if ($parts === false) {
            throw new \RuntimeException('Malformed URL: ' . $url);
        }
        $this->scheme = isset($parts['scheme']) ? $parts['scheme'] : null;
        $this->user = isset($parts['user']) ? $parts['user'] : null;
        $this->password = isset($parts['pass']) ? $parts['pass'] : null;
        $this->host = isset($parts['host']) ? $parts['host'] : null;
        $this->port = isset($parts['port']) ? (int)$parts['port'] : null;
        $this->path = isset($parts['path']) ? $parts['path'] : '';
        $this->query = isset($parts['query']) ? $parts['query'] : '';
        $this->fragment = isset($parts['fragment']) ? $parts['fragment'] : null;

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
     * Get's post from either $_POST or JSON response object
     * By default returns all data, or can return a single item
     *
     * @param string $element
     * @param string $filter_type
     * @return array|mixed|null
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
                $item = filter_var($item, $filter_type);
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
    private function getContentType($short = true)
    {
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $content_type = $_SERVER['CONTENT_TYPE'];
            if ($short) {
                return Utils::substrToString($content_type,';');
            }
            return $content_type;
        }
        return null;
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
     * @param        $uri
     * @param string $delimiter
     *
     * @return string
     */
    private function processParams($uri, $delimiter = ':')
    {
        if (strpos($uri, $delimiter) !== false) {
            preg_match_all(static::paramsRegex(), $uri, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $param = explode($delimiter, $match[1]);
                if (count($param) === 2) {
                    $plain_var = filter_var($param[1], FILTER_SANITIZE_STRING);
                    $this->params[$param[0]] = $plain_var;
                    $uri = str_replace($match[0], '', $uri);
                }
            }
        }
        return $uri;
    }
}
