<?php
/**
 * Class Minify_HTML_Helper
 * @package Minify
 */

/**
 * Helpers for writing Minfy URIs into HTML
 *
 * @package Minify
 * @author Stephen Clay <steve@mrclay.org>
 */
class Minify_HTML_Helper {
    public $rewriteWorks = true;
    public $minAppUri = '/min';
    public $groupsConfigFile = '';

    /**
     * Get an HTML-escaped Minify URI for a group or set of files
     * 
     * @param string|array $keyOrFiles a group key or array of filepaths/URIs
     * @param array $opts options:
     *   'farExpires' : (default true) append a modified timestamp for cache revving
     *   'debug' : (default false) append debug flag
     *   'charset' : (default 'UTF-8') for htmlspecialchars
     *   'minAppUri' : (default '/min') URI of min directory
     *   'rewriteWorks' : (default true) does mod_rewrite work in min app?
     *   'groupsConfigFile' : specify if different
     * @return string
     */
    public static function getUri($keyOrFiles, $opts = array())
    {
        $opts = array_merge(array( // default options
            'farExpires' => true
            ,'debug' => false
            ,'charset' => 'UTF-8'
            ,'minAppUri' => '/min'
            ,'rewriteWorks' => true
            ,'groupsConfigFile' => ''
        ), $opts);
        $h = new self;
        $h->minAppUri = $opts['minAppUri'];
        $h->rewriteWorks = $opts['rewriteWorks'];
        $h->groupsConfigFile = $opts['groupsConfigFile'];
        if (is_array($keyOrFiles)) {
            $h->setFiles($keyOrFiles, $opts['farExpires']);
        } else {
            $h->setGroup($keyOrFiles, $opts['farExpires']);
        }
        $uri = $h->getRawUri($opts['farExpires'], $opts['debug']);
        return htmlspecialchars($uri, ENT_QUOTES, $opts['charset']);
    }

    /**
     * Get non-HTML-escaped URI to minify the specified files
     *
     * @param bool $farExpires
     * @param bool $debug
     * @return string
     */
    public function getRawUri($farExpires = true, $debug = false)
    {
        $path = rtrim($this->minAppUri, '/') . '/';
        if (! $this->rewriteWorks) {
            $path .= '?';
        }
        if (null === $this->_groupKey) {
            // @todo: implement shortest uri
            $path = self::_getShortestUri($this->_filePaths, $path);
        } else {
            $path .= "g=" . $this->_groupKey;
        }
        if ($debug) {
            $path .= "&debug";
        } elseif ($farExpires && $this->_lastModified) {
            $path .= "&" . $this->_lastModified;
        }
        return $path;
    }

    /**
     * Set the files that will comprise the URI we're building
     *
     * @param array $files
     * @param bool $checkLastModified
     */
    public function setFiles($files, $checkLastModified = true)
    {
        $this->_groupKey = null;
        if ($checkLastModified) {
            $this->_lastModified = self::getLastModified($files);
        }
        // normalize paths like in /min/f=<paths>
        foreach ($files as $k => $file) {
            if (0 === strpos($file, '//')) {
                $file = substr($file, 2);
            } elseif (0 === strpos($file, '/')
                      || 1 === strpos($file, ':\\')) {
                $file = substr($file, strlen($_SERVER['DOCUMENT_ROOT']) + 1);
            }
            $file = strtr($file, '\\', '/');
            $files[$k] = $file;
        }
        $this->_filePaths = $files;
    }

    /**
     * Set the group of files that will comprise the URI we're building
     *
     * @param string $key
     * @param bool $checkLastModified
     */
    public function setGroup($key, $checkLastModified = true)
    {
        $this->_groupKey = $key;
        if ($checkLastModified) {
            if (! $this->groupsConfigFile) {
                $this->groupsConfigFile = dirname(dirname(dirname(dirname(__FILE__)))) . '/groupsConfig.php';
            }
            if (is_file($this->groupsConfigFile)) {
                $gc = (require $this->groupsConfigFile);
                $keys = explode(',', $key);
                foreach ($keys as $key) {
                    if (isset($gc[$key])) {
                        $this->_lastModified = self::getLastModified($gc[$key], $this->_lastModified);
                    }
                }
            }
        }
    }

    /**
     * Get the max(lastModified) of all files
     *
     * @param array|string $sources
     * @param int $lastModified
     * @return int
     */
    public static function getLastModified($sources, $lastModified = 0)
    {
        $max = $lastModified;
        foreach ((array)$sources as $source) {
            if (is_object($source) && isset($source->lastModified)) {
                $max = max($max, $source->lastModified);
            } elseif (is_string($source)) {
                if (0 === strpos($source, '//')) {
                    $source = $_SERVER['DOCUMENT_ROOT'] . substr($source, 1);
                }
                if (is_file($source)) {
                    $max = max($max, filemtime($source));
                }
            }
        }
        return $max;
    }

    protected $_groupKey = null; // if present, URI will be like g=...
    protected $_filePaths = array();
    protected $_lastModified = null;

    
    /**
     * In a given array of strings, find the character they all have at
     * a particular index
     *
     * @param array $arr array of strings
     * @param int $pos index to check
     * @return mixed a common char or '' if any do not match
     */
    protected static function _getCommonCharAtPos($arr, $pos) {
        if (!isset($arr[0][$pos])) {
            return '';
        }
        $c = $arr[0][$pos];
        $l = count($arr);
        if ($l === 1) {
            return $c;
        }
        for ($i = 1; $i < $l; ++$i) {
            if ($arr[$i][$pos] !== $c) {
                return '';
            }
        }
        return $c;
    }

    /**
     * Get the shortest URI to minify the set of source files
     *
     * @param array $paths root-relative URIs of files
     * @param string $minRoot root-relative URI of the "min" application
     * @return string
     */
    protected static function _getShortestUri($paths, $minRoot = '/min/') {
        $pos = 0;
        $base = '';
        while (true) {
            $c = self::_getCommonCharAtPos($paths, $pos);
            if ($c === '') {
                break;
            } else {
                $base .= $c;
            }
            ++$pos;
        }
        $base = preg_replace('@[^/]+$@', '', $base);
        $uri = $minRoot . 'f=' . implode(',', $paths);
        
        if (substr($base, -1) === '/') {
            // we have a base dir!
            $basedPaths = $paths;
            $l = count($paths);
            for ($i = 0; $i < $l; ++$i) {
                $basedPaths[$i] = substr($paths[$i], strlen($base));
            }
            $base = substr($base, 0, strlen($base) - 1);
            $bUri = $minRoot . 'b=' . $base . '&f=' . implode(',', $basedPaths);

            $uri = strlen($uri) < strlen($bUri)
                ? $uri
                : $bUri;
        }
        return $uri;
    }
}
