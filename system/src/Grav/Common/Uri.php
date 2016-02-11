<?php
namespace Grav\Common;

use Grav\Common\Page\Page;

/**
 * The URI object provides information about the current URL
 *
 * @author  RocketTheme
 * @license MIT
 */
class Uri
{
    const HOSTNAME_REGEX = '/^(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z0-9]|[A-Za-z0-9][A-Za-z0-9\-]*[A-Za-z0-9])$/';

    public $url;

    protected $base;
    protected $basename;
    protected $bits;
    protected $content_path;
    protected $extension;
    protected $host;
    protected $env;
    protected $params;
    protected $path;
    protected $paths;
    protected $scheme;
    protected $port;
    protected $query;
    protected $root;
    protected $root_path;
    protected $uri;

    /**
     * Constructor
     */
    public function __construct()
    {
        // resets
        $this->paths        = [];
        $this->params       = [];
        $this->query        = [];
        $this->name         = $this->buildHostname();
        $this->env          = $this->buildEnvironment();
        $this->port         = $this->buildPort();
        $this->uri          = $this->buildUri();
        $this->scheme       = $this->buildScheme();
        $this->base         = $this->buildBaseUrl();
        $this->host         = $this->buildHost();
        $this->root_path    = $this->buildRootPath();
        $this->root         = $this->base . $this->root_path;
        $this->url          = $this->base . $this->uri;
    }

    /**
     * Return the hostname from $_SERVER, validated and without port
     *
     * @return string
     */
    private function buildHostname()
    {
        $hostname = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost');

        // Remove port from HTTP_HOST generated $hostname
        $hostname = Utils::substrToString($hostname, ':');

        // Validate the hostname
        $hostname = $this->validateHostname($hostname) ? $hostname : 'unknown';

        return $hostname;
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
        return (bool)preg_match(Uri::HOSTNAME_REGEX, $hostname);
    }

    /**
     * Get the port from $_SERVER
     *
     * @return string
     */
    private function buildPort()
    {
        $port = isset($_SERVER['SERVER_PORT']) ? (string)$_SERVER['SERVER_PORT'] : '80';

        return $port;
    }

    /**
     * Get the Uri from $_SERVER
     *
     * @return string
     */
    private function buildUri()
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

        return $uri;
    }

    private function buildScheme()
    {
        // set the base
        if (isset($_SERVER['HTTPS'])) {
            $scheme = (strtolower(@$_SERVER['HTTPS']) == 'on') ? 'https://' : 'http://';
        } else {
            $scheme = 'http://';
        }

        return $scheme;
    }

    /**
     * Get the base URI with port if needed
     *
     * @return string
     */
    private function buildBaseUrl()
    {
        return $this->scheme . $this->name;
    }

    /**
     * Get the Grav Root Path
     *
     * @return string
     */
    private function buildRootPath()
    {
        $root_path = str_replace(' ', '%20', rtrim(substr($_SERVER['PHP_SELF'], 0, strpos($_SERVER['PHP_SELF'], 'index.php')), '/'));

        // check if userdir in the path and workaround PHP bug with PHP_SELF
        if (strpos($this->uri, '/~') !== false && strpos($_SERVER['PHP_SELF'], '/~') === false) {
            $root_path = substr($this->uri, 0, strpos($this->uri, '/', 1)) . $root_path;
        }

        return $root_path;
    }

    /**
     * Returns the hostname
     *
     * @return string
     */
    private function buildHost()
    {
        return $this->name;
    }

    private function buildEnvironment()
    {
        // set hostname
        $address = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '::1';

        // check for localhost variations
        if ($this->name == 'localhost' || $address == '::1' || $address == '127.0.0.1') {
            $env = 'localhost';
        } else {
            $env = $this->name;
        }

        return $env;
    }

    /**
     * Initialize the URI class with a url passed via parameter.
     * Used for testing purposes.
     *
     * @param string $url the URL to use in the class
     *
     * @return string
     */
    public function initializeWithUrl($url = '')
    {
        if (!$url) {
            return $this;
        }

        $this->paths    = [];
        $this->params   = [];
        $this->query    = [];
        $this->name     = [];
        $this->env      = [];
        $this->port     = [];
        $this->uri      = [];
        $this->base     = [];
        $this->host     = [];
        $this->root     = [];
        $this->url      = [];

        $grav = Grav::instance();

        $language = $grav['language'];

        $uri_bits = Uri::parseUrl($url);

        $this->name = $uri_bits['host'];
        $this->port = isset($uri_bits['port']) ? $uri_bits['port'] : '80';

        $this->uri = $uri_bits['path'];

        // set active language
        $uri = $language->setActiveFromUri($this->uri);

        if (isset($uri_bits['params'])) {
            $this->params = $uri_bits['params'];
        }

        if (isset($uri_bits['query'])) {
            $this->uri .= '?' . $uri_bits['query'];
            parse_str($uri_bits['query'], $this->query);
        }

        $this->base = $this->buildBaseUrl();
        $this->host = $this->buildHost();
        $this->env = $this->buildEnvironment();
        $this->root_path = $this->buildRootPath();
        $this->root = $this->base . $this->root_path;
        $this->url = $this->root . $uri;
        $this->path = $uri;

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
     * Initializes the URI object based on the url set on the object
     */
    public function init()
    {
        $grav = Grav::instance();

        $config = $grav['config'];
        $language = $grav['language'];

        // add the port to the base for non-standard ports
        if ($config->get('system.reverse_proxy_setup') === false && $this->port != '80' && $this->port != '443') {
            $this->base .= ":" . $this->port;
        }

        // Set some defaults
        $this->root = $this->base . $this->root_path;
        $this->url = $this->base . $this->uri;

        // get any params and remove them
        $uri = str_replace($this->root, '', $this->url);

        // remove double slashes
        $uri = preg_replace('#/{2,}#', '/', $uri);

        // remove the setup.php based base if set:
        $setup_base = $grav['pages']->base();
        if ($setup_base) {
            $uri = str_replace($setup_base, '', $uri);
        }

        // If configured to, redirect trailing slash URI's with a 301 redirect
        if ($config->get('system.pages.redirect_trailing_slash', false) && $uri != '/' && Utils::endsWith($uri, '/')) {
            $grav->redirect(rtrim($uri, '/'), 301);
        }

        // process params
        $uri = $this->processParams($uri, $config->get('system.param_sep'));

        // set active language
        $uri = $language->setActiveFromUri($uri);

        // split the URL and params
        $bits = parse_url($uri);

        // process query string
        if (isset($bits['query']) && isset($bits['path'])) {
            if (!$this->query) {
                $this->query = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
            }
            $uri = $bits['path'];
        }

        // remove the extension if there is one set
        $parts = pathinfo($uri);

        // set the original basename
        $this->basename = $parts['basename'];

        // set the extension
        if (isset($parts['extension'])) {
            $this->extension = $parts['extension'];
        }

        $valid_page_types = implode('|', $config->get('system.pages.types'));

        // Strip the file extension for valid page types
        if (preg_match("/\.(" . $valid_page_types . ")$/", $parts['basename'])) {
            $uri = rtrim(str_replace(DIRECTORY_SEPARATOR, DS, $parts['dirname']), DS) . '/' . $parts['filename'];
        }

        // set the new url
        $this->url = $this->root . $uri;
        $this->path = $uri;
        $this->content_path = trim(str_replace($this->base, '', $this->path), '/');
        if ($this->content_path != '') {
            $this->paths = explode('/', $this->content_path);
        }

        // Set some Grav stuff
        $grav['base_url_absolute'] = $this->rootUrl(true);
        $grav['base_url_relative'] = $this->rootUrl(false);
        $grav['base_url'] = $grav['config']->get('system.absolute_urls') ? $grav['base_url_absolute'] : $grav['base_url_relative'];
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
            $bits = explode('/', $uri);
            $path = [];
            foreach ($bits as $bit) {
                if (strpos($bit, $delimiter) !== false) {
                    $param = explode($delimiter, $bit);
                    if (count($param) == 2) {
                        $plain_var = filter_var(rawurldecode($param[1]), FILTER_SANITIZE_STRING);
                        $this->params[$param[0]] = $plain_var;
                    }
                } else {
                    $path[] = $bit;
                }
            }
            $uri = '/' . ltrim(implode('/', $path), '/');
        }

        return $uri;
    }

    /**
     * Return URI path.
     *
     * @param  string $id
     *
     * @return string
     */
    public function paths($id = null)
    {
        if (isset($id)) {
            return $this->paths[$id];
        } else {
            return $this->paths;
        }
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
        return rawurldecode(($absolute ? $this->rootUrl($domain) : '') . '/' . implode('/', $this->paths));
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
        if (isset($id)) {
            return isset($this->query[$id]) ? $this->query[$id] : null;
        } else {
            if ($raw) {
                return $this->query;
            } else {
                if (!$this->query) {
                    return '';
                }

                return http_build_query($this->query);
            }
        }
    }

    /**
     * Return all or a single query parameter as a URI compatible string.
     *
     * @param  string  $id    Optional parameter name.
     * @param  boolean $array return the array format or not
     *
     * @return null|string
     */
    public function params($id = null, $array = false)
    {
        $config = Grav::instance()['config'];

        $params = null;
        if ($id === null) {
            if ($array) {
                return $this->params;
            }
            $output = [];
            foreach ($this->params as $key => $value) {
                $output[] = $key . $config->get('system.param_sep') . $value;
                $params = '/' . implode('/', $output);
            }
        } elseif (isset($this->params[$id])) {
            if ($array) {
                return $this->params[$id];
            }
            $params = "/{$id}" . $config->get('system.param_sep') . $this->params[$id];
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
            return rawurldecode($this->params[$id]);
        } else {
            return false;
        }
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
        } else {
            $url = (str_replace($this->base, '', rtrim($this->url, '/')));

            return $url ? $url : '/';
        }
    }

    /**
     * Return the Path
     *
     * @return String The path of the URI
     */
    public function path()
    {
        $path = $this->path;
        if ($path === '') {
            $path = '/';
        }

        return $path;
    }

    /**
     * Return the Extension of the URI
     *
     * @param null $default
     *
     * @return String The extension of the URI
     */
    public function extension($default = null)
    {
        if (!$this->extension) {
            $this->extension = $default;
        }

        return $this->extension;
    }

    /**
     * Return the scheme of the URI
     *
     * @return String The scheme of the URI
     */
    public function scheme()
    {
        return $this->scheme;
    }


    /**
     * Return the host of the URI
     *
     * @return String The host of the URI
     */
    public function host()
    {
        return $this->host;
    }

    /**
     * Return the port number
     *
     * @return int
     */
    public function port()
    {
        return $this->port;
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
     * Return the base of the URI
     *
     * @return String The base of the URI
     */
    public function base()
    {
        return $this->base;
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
        } else {
            $root = str_replace($this->base, '', $this->root);

            return $root;
        }
    }

    /**
     * Return current page number.
     *
     * @return int
     */
    public function currentPage()
    {
        if (isset($this->params['page'])) {
            return $this->params['page'];
        } else {
            return 1;
        }
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
            $referrer = $default ? $default : $this->route(true, true);
        }

        if ($attributes) {
            $referrer .= $attributes;
        }

        // Return relative path.
        return substr($referrer, strlen($root));
    }

    /**
     * Return the IP address of the current user
     *
     * @return string ip address
     */
    public function ip()
    {
        if (getenv('HTTP_CLIENT_IP'))
            $ipaddress = getenv('HTTP_CLIENT_IP');
        else if(getenv('HTTP_X_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
        else if(getenv('HTTP_X_FORWARDED'))
            $ipaddress = getenv('HTTP_X_FORWARDED');
        else if(getenv('HTTP_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_FORWARDED_FOR');
        else if(getenv('HTTP_FORWARDED'))
           $ipaddress = getenv('HTTP_FORWARDED');
        else if(getenv('REMOTE_ADDR'))
            $ipaddress = getenv('REMOTE_ADDR');
        else
            $ipaddress = 'UNKNOWN';
        return $ipaddress;

    }

    /**
     * Is this an external URL? if it starts with `http` then yes, else false
     *
     * @param  string $url the URL in question
     *
     * @return boolean      is eternal state
     */
    public function isExternal($url)
    {
        if (Utils::startsWith($url, 'http')) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * The opposite of built-in PHP method parse_url()
     *
     * @param $parsed_url
     *
     * @return string
     */
    public static function buildUrl($parsed_url)
    {
        $scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
        $host     = isset($parsed_url['host']) ? $parsed_url['host'] : '';
        $port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
        $user     = isset($parsed_url['user']) ? $parsed_url['user'] : '';
        $pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
        $pass     = ($user || $pass) ? "$pass@" : '';
        $path     = isset($parsed_url['path']) ? $parsed_url['path'] : '';
        $params   = isset($parsed_url['params']) ? static::buildParams($parsed_url['params']) : '';
        $query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
        $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';

        return "$scheme$user$pass$host$port$path$params$query$fragment";
    }

    public static function buildParams($params)
    {
        $grav = Grav::instance();

        $params_string = '';
        foreach ($params as $key => $value) {
            $output[] = $key . $grav['config']->get('system.param_sep') . $value;
            $params_string .= '/' . implode('/', $output);
        }
        return $params_string;
    }

    /**
     * Converts links from absolute '/' or relative (../..) to a Grav friendly format
     *
     * @param Page   $page         the current page to use as reference
     * @param string $url the URL as it was written in the markdown
     * @param string $type         the type of URL, image | link
     * @param null   $absolute     if null, will use system default, if true will use absolute links internally
     *
     * @return string the more friendly formatted url
     */
    public static function convertUrl(Page $page, $url, $type = 'link', $absolute = false)
    {
        $grav = Grav::instance();

        $uri = $grav['uri'];

        // Link processing should prepend language
        $language = $grav['language'];
        $language_append = '';
        if ($type == 'link' && $language->enabled()) {
            $language_append = $language->getLanguageURLPrefix();
        }

        // Handle Excerpt style $url array
        if (is_array($url)) {
            $url_path = $url['path'];
        } else {
            $url_path = $url;
        }

        $external   = false;
        $base       = $grav['base_url_relative'];
        $base_url   = rtrim($base . $grav['pages']->base(), '/') . $language_append;
        $pages_dir  = $grav['locator']->findResource('page://');

        // if absolute and starts with a base_url move on
        if (isset($url['scheme']) && Utils::startsWith($url['scheme'], 'http')) {
            $external = true;
        } elseif (($base_url != '' && Utils::startsWith($url_path, $base_url)) ||
                   $url_path == '/' ||
                   Utils::startsWith($url_path, '#')) {
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
            if ($just_path == $page->path() || $normalized_url == '/') {
                $url_path = $normalized_url;
            } else {
                $url_bits = static::parseUrl($normalized_path);
                $full_path = ($url_bits['path']);
                $raw_full_path = rawurldecode($full_path);

                if (file_exists($raw_full_path)) {
                    $full_path = $raw_full_path;
                } elseif (file_exists($full_path)) {
                   // do nothing
                } else {
                    $full_path = false;
                }

                if ($full_path) {
                    $path_info = pathinfo($full_path);
                    $page_path = $path_info['dirname'];
                    $filename = '';

                    if ($url_path == '..') {
                        $page_path = $full_path;
                    } else {
                        // save the filename if a file is part of the path
                        if (is_file($full_path)) {
                            if ($path_info['extension'] != 'md') {
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
        if (!$external && ($absolute === true || $grav['config']->get('system.absolute_urls', false))) {

            $url['scheme'] = str_replace('://', '', $uri->scheme());
            $url['host'] = $uri->host();

            if ($uri->port() != 80 && $uri->port() != 443) {
                $url['port'] = $uri->port();
            }

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
                    if (isset($ssl_enabled)) {
                        if ($ssl_enabled) {
                            $url['scheme'] = 'https';
                        } else {
                            $url['scheme'] = 'http';
                        }
                    }
                }
            }
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
        $bits = parse_url($url);

        $grav = Grav::instance();

        list($stripped_path, $params) = static::extractParams($bits['path'], $grav['config']->get('system.param_sep'));

        if (!empty($params)) {
            $bits['path'] = $stripped_path;
            $bits['params'] = $params;
        }

        return $bits;
    }

    public static function extractParams($uri, $delimiter)
    {
        $params = [];

        if (strpos($uri, $delimiter) !== false) {
            $bits = explode('/', $uri);
            $path = [];
            foreach ($bits as $bit) {
                if (strpos($bit, $delimiter) !== false) {
                    $param = explode($delimiter, $bit);
                    if (count($param) == 2) {
                        $plain_var = filter_var(rawurldecode($param[1]), FILTER_SANITIZE_STRING);
                        $params[$param[0]] = $plain_var;
                    }
                } else {
                    $path[] = $bit;
                }
            }
            $uri = '/' . ltrim(implode('/', $path), '/');
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
        if ($type == 'link' && $language->enabled()) {
            $language_append = $language->getLanguageURLPrefix();
        }

        $pages_dir = $grav['locator']->findResource('page://');
        if (is_null($relative)) {
            $base = $grav['base_url'];
        } else {
            $base = $relative ? $grav['base_url_relative'] : $grav['base_url_absolute'];
        }

        $base_url = rtrim($base . $grav['pages']->base(), '/') . $language_append;

        // if absolute and starts with a base_url move on
        if (pathinfo($markdown_url, PATHINFO_DIRNAME) == '.' && $page->url() == '/') {
            return '/' . $markdown_url;
            // no path to convert
        } elseif ($base_url != '' && Utils::startsWith($markdown_url, $base_url)) {
            return $markdown_url;
            // if contains only a fragment
        } elseif (Utils::startsWith($markdown_url, '#')) {
            return $markdown_url;
        } else {
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
            if ($just_path == $page->path()) {
                return $normalized_url;
            }

            $url_bits = parse_url($normalized_path);
            $full_path = ($url_bits['path']);

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

            if ($markdown_url == '..') {
                $page_path = $full_path;
            } else {
                // save the filename if a file is part of the path
                if (is_file($full_path)) {
                    if ($path_info['extension'] != 'md') {
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

                return Uri::buildUrl($url_bits);
            }

            return $normalized_url;
        }
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
        $urlWithNonce = $url . '/' . $nonceParamName . Grav::instance()['config']->get('system.param_sep', ':') . Utils::getNonce($action);

        return $urlWithNonce;
    }
}
