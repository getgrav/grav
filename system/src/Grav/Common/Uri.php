<?php
namespace Grav\Common;

use Grav\Common\Page\Page;
use Grav\Common\Page\Pages;

/**
 * The URI object provides information about the current URL
 *
 * @author RocketTheme
 * @license MIT
 */
class Uri
{
    public $url;

    protected $basename;
    protected $base;
    protected $root;
    protected $bits;
    protected $extension;
    protected $host;
    protected $content_path;
    protected $path;
    protected $paths;
    protected $query;
    protected $params;

    /**
     * Constructor.
     */
    public function __construct()
    {

        $base = 'http://';
        $name = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost';
        $port = isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : 80;
        $uri  = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

        $root_path = str_replace(' ', '%20', rtrim(substr($_SERVER['PHP_SELF'], 0, strpos($_SERVER['PHP_SELF'], 'index.php')), '/'));

        if (isset($_SERVER['HTTPS'])) {
            $base = (@$_SERVER['HTTPS'] == 'on') ? 'https://' : 'http://';
        }

        $base .= $name;

        if ($port != '80' && $port != '443') {
            $base .= ":".$port;
        }

        // check if userdir in the path and workaround PHP bug with PHP_SELF
        if (strpos($uri, '/~') !== false && strpos($_SERVER['PHP_SELF'], '/~') === false) {
            $root_path = substr($uri, 0, strpos($uri, '/', 1)) . $root_path;
        }

        // set hostname
        $address = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '::1';

        // check for localhost variations
        if ($name == 'localhost' || $address == '::1' || $address == '127.0.0.1') {
            $this->host = 'localhost';
        } else {
            $this->host = $name;
        }

        $this->base = $base;
        $this->root = $base . $root_path;
        $this->url = $base . $uri;

    }

    /**
     * Initializes the URI object based on the url set on the object
     */
    public function init()
    {
        $grav = Grav::instance();

        $config = $grav['config'];
        $language = $grav['language'];

        // resets
        $this->paths = [];
        $this->params = [];
        $this->query = [];


        // get any params and remove them
        $uri = str_replace($this->root, '', $this->url);

        // process params
        $uri = $this->processParams($uri, $config->get('system.param_sep'));

        // set active language
        $uri = $language->setActiveFromUri($uri);

        // redirect to language specific homepage if configured to do so
        if ($uri == '/' && $language->enabled()) {
            if ($config->get('system.languages.home_redirect.include_route', true)) {
                $prefix = $config->get('system.languages.home_redirect.include_lang', true) ? $language->getLanguage() . '/' : '';
                $grav->redirect($prefix . Pages::getHomeRoute());
            } elseif ($config->get('system.languages.home_redirect.include_lang', true)) {
                $grav->redirect($language->getLanguage() . '/');
            }
        }

        // split the URL and params
        $bits = parse_url($uri);

        // process query string
        if (isset($bits['query'])) {
            parse_str($bits['query'], $this->query);
            $uri = $bits['path'];
        }

        // remove the extension if there is one set
        $parts = pathinfo($uri);

        // set the original basename
        $this->basename = $parts['basename'];

        $valid_page_types = implode('|', $config->get('system.pages.types'));

        if (preg_match("/\.(".$valid_page_types.")$/", $parts['basename'])) {
            $uri = rtrim(str_replace(DIRECTORY_SEPARATOR, DS, $parts['dirname']), DS). '/' .$parts['filename'];
            $this->extension = $parts['extension'];
        }

        // set the new url
        $this->url = $this->root . $uri;
        $this->path = $uri;
        $this->content_path = trim(str_replace($this->base, '', $this->path), '/');
        if ($this->content_path != '') {
            $this->paths = explode('/', $this->content_path);
        }
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
            $path = array();
            foreach ($bits as $bit) {
                if (strpos($bit, $delimiter) !== false) {
                    $param = explode($delimiter, $bit);
                    if (count($param) == 2) {
                        $this->params[$param[0]] = str_replace(urlencode($delimiter), '/', filter_var($param[1], FILTER_SANITIZE_STRING));
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
     * @param  string  $id
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
     * @param  bool  $absolute  True to include full path.
     * @param  bool  $domain    True to include domain. Works only if first parameter is also true.
     * @return string
     */
    public function route($absolute = false, $domain = false)
    {
        return urldecode(($absolute ? $this->rootUrl($domain) : '') . '/' . implode('/', $this->paths));
    }

    /**
     * Return full query string or a single query attribute.
     *
     * @param  string  $id  Optional attribute.
     * @return string
     */
    public function query($id = null, $raw = false)
    {
        if (isset($id)) {
            return isset($this->query[$id]) ? filter_var($this->query[$id], FILTER_SANITIZE_STRING) : null;
        } else {
            if ($raw) {
                return $this->query;
            } else {
                return http_build_query($this->query);
            }
        }
    }

    /**
     * Return all or a single query parameter as a URI compatible string.
     *
     * @param  string  $id  Optional parameter name.
     * @param  boolean $array return the array format or not
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
            $output = array();
            foreach ($this->params as $key => $value) {
                $output[] = $key . $config->get('system.param_sep') . $value;
                $params = '/'.implode('/', $output);
            }
        } elseif (isset($this->params[$id])) {
            if ($array) {
                return $this->params[$id];
            }
            $params = "/{$id}". $config->get('system.param_sep') . $this->params[$id];
        }

        return $params;
    }

    /**
     * Get URI parameter.
     *
     * @param  string  $id
     * @return bool|string
     */
    public function param($id)
    {
        if (isset($this->params[$id])) {
            return urldecode($this->params[$id]);
        } else {
            return false;
        }
    }

    /**
     * Return URL.
     *
     * @param  bool  $include_host  Include hostname.
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
     * Return the host of the URI
     *
     * @return String The host of the URI
     */
    public function host()
    {
        return $this->host;
    }

    /**
     * Gets the environment name
     *
     * @return String
     */
    public function environment()
    {
        return $this->host();
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
     * @param  bool  $include_host Include hostname.
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
     * @param  string  $url the URL in question
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
        $query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
        $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
        return "$scheme$user$pass$host$port$path$query$fragment";
    }

    /**
     * Converts links from absolute '/' or relative (../..) to a grav friendly format
     *
     * @param         $page         the current page to use as reference
     * @param  string $markdown_url the URL as it was written in the markdown
     *
     * @return string the more friendly formatted url
     */
    public static function convertUrl(Page $page, $markdown_url)
    {
        $grav = Grav::instance();

        $pages_dir = $grav['locator']->findResource('page://');
        $base_url = rtrim($grav['base_url'] . $grav['pages']->base(), '/');

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
            } elseif (file_exists(urldecode($full_path))) {
                $full_path = urldecode($full_path);
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
                $target = $instances[$page_path];
                $url_bits['path'] = $base_url . $target->route() . $filename;
                return Uri::buildUrl($url_bits);
            }

            return $normalized_url;
        }
    }
}
