<?php
namespace Grav\Common;

/**
 * The URI object provides information about the current URL
 *
 * @author RocketTheme
 * @license MIT
 */
class Uri
{
    protected $base;
    protected $root;
    protected $bits;
    protected $extension;
    protected $host;
    protected $content_path;
    protected $path;
    protected $paths;
    protected $url;
    protected $query;
    protected $params;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $base = 'http://';
        $uri = $_SERVER["REQUEST_URI"];

        if (isset($_SERVER["HTTPS"])) {
            $base = (@$_SERVER["HTTPS"] == "on") ? "https://" : "http://";
        }

        $base .= $_SERVER["SERVER_NAME"];

        if ($_SERVER["SERVER_PORT"] != "80" && $_SERVER["SERVER_PORT"] != "443") {
            $base .= ":".$_SERVER["SERVER_PORT"];
        }

        $this->base = $base;
        $this->root = $base . rtrim(substr($_SERVER['PHP_SELF'], 0, strpos($_SERVER['PHP_SELF'], 'index.php')), '/');
        $this->url = $base . $uri;

        $this->init();

    }

    /**
     * Initializes the URI object based on the url set on the object
     */
    public function init()
    {
        // get any params and remove them
        $uri = str_replace($this->root, '', $this->url);

        $this->params = array();
        if (strpos($uri, ':')) {
            $bits = explode('/', $uri);
            $path = array();
            foreach ($bits as $bit) {
                if (strpos($bit, ':') !== false) {
                    $param = explode(':', $bit);
                    if (count($param) == 2) {
                        $this->params[$param[0]] = str_replace('%7C', '/', filter_var($param[1], FILTER_SANITIZE_STRING));
                    }
                } else {
                    $path[] = $bit;
                }
            }
            $uri = implode('/', $path);
        }

        // remove the extension if there is one set
        $parts = pathinfo($uri);
        if (strpos($parts['basename'], '.')) {
            $uri = rtrim($parts['dirname'], '/').'/'.$parts['filename'];
            $this->extension = $parts['extension'];
        }

        // set the new url
        $this->url = $this->root . $uri;

        // split into bits
        $this->bits = parse_url($uri);

        $this->query = array();
        if (isset($this->bits['query'])) {
            parse_str($this->bits['query'], $this->query);
        }

        $this->paths = array();
        $this->path = $this->bits['path'];
        $this->content_path = trim(str_replace($this->base, '', $this->path), '/');
        if ($this->content_path != '') {
            $this->paths = explode('/', $this->content_path);
        }
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
            return implode('/', $this->paths);
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
        return ($absolute ? $this->rootUrl($domain) : '') . '/' . implode('/', $this->paths);
    }

    /**
     * Return full query string or a single query attribute.
     *
     * @param  string  $id  Optional attribute.
     * @return string
     */
    public function query($id = null)
    {
        if (isset($id)) {
            return filter_var($this->query[$id], FILTER_SANITIZE_STRING) ;
        } else {
            return http_build_query($this->query);
        }
    }

    /**
     * Return all or a single query parameter as a URI compatible string.
     *
     * @param  string  $id  Optional parameter name.
     * @return null|string
     */
    public function params($id = null)
    {
        $params = null;
        if ($id === null) {
            $output = array();
            foreach ($this->params as $key => $value) {
                $output[] = $key . ':' . $value;
                $params = '/'.implode('/', $output);
            }
        } elseif (isset($this->params[$id])) {
            $params = "/{$id}:".$this->params[$id];
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
    public function path() {
        return $this->path;
    }

    /**
     * Return the Extension of the URI
     *
     * @return String The extension of the URI
     */
    public function extension() {
        return $this->extension;
    }

    /**
     * Return the host of the URI
     *
     * @return String The host of the URI
     */
    public function host() {
        return $this->host;
    }

    /**
     * Return the base of the URI
     *
     * @return String The base of the URI
     */
    public function base() {
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
}
