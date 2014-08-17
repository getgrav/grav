<?php
/**
 * Class Minify_ImportProcessor
 * @package Minify
 */

/**
 * Linearize a CSS/JS file by including content specified by CSS import
 * declarations. In CSS files, relative URIs are fixed.
 *
 * @imports will be processed regardless of where they appear in the source
 * files; i.e. @imports commented out or in string content will still be
 * processed!
 *
 * This has a unit test but should be considered "experimental".
 *
 * @package Minify
 * @author Stephen Clay <steve@mrclay.org>
 * @author Simon Schick <simonsimcity@gmail.com>
 */
class Minify_ImportProcessor {

    public static $filesIncluded = array();

    public static function process($file)
    {
        self::$filesIncluded = array();
        self::$_isCss = (strtolower(substr($file, -4)) === '.css');
        $obj = new Minify_ImportProcessor(dirname($file));
        return $obj->_getContent($file);
    }

    // allows callback funcs to know the current directory
    private $_currentDir = null;

    // allows callback funcs to know the directory of the file that inherits this one
    private $_previewsDir = null;

    // allows _importCB to write the fetched content back to the obj
    private $_importedContent = '';

    private static $_isCss = null;

    /**
     * @param String $currentDir
     * @param String $previewsDir Is only used internally
     */
    private function __construct($currentDir, $previewsDir = "")
    {
        $this->_currentDir = $currentDir;
        $this->_previewsDir = $previewsDir;
    }

    private function _getContent($file, $is_imported = false)
    {
        $file = realpath($file);
        if (! $file
            || in_array($file, self::$filesIncluded)
            || false === ($content = @file_get_contents($file))
        ) {
            // file missing, already included, or failed read
            return '';
        }
        self::$filesIncluded[] = realpath($file);
        $this->_currentDir = dirname($file);

        // remove UTF-8 BOM if present
        if (pack("CCC",0xef,0xbb,0xbf) === substr($content, 0, 3)) {
            $content = substr($content, 3);
        }
        // ensure uniform EOLs
        $content = str_replace("\r\n", "\n", $content);

        // process @imports
        $content = preg_replace_callback(
            '/
                @import\\s+
                (?:url\\(\\s*)?      # maybe url(
                [\'"]?               # maybe quote
                (.*?)                # 1 = URI
                [\'"]?               # maybe end quote
                (?:\\s*\\))?         # maybe )
                ([a-zA-Z,\\s]*)?     # 2 = media list
                ;                    # end token
            /x'
            ,array($this, '_importCB')
            ,$content
        );

        // You only need to rework the import-path if the script is imported
        if (self::$_isCss && $is_imported) {
            // rewrite remaining relative URIs
            $content = preg_replace_callback(
                '/url\\(\\s*([^\\)\\s]+)\\s*\\)/'
                ,array($this, '_urlCB')
                ,$content
            );
        }

        return $this->_importedContent . $content;
    }

    private function _importCB($m)
    {
        $url = $m[1];
        $mediaList = preg_replace('/\\s+/', '', $m[2]);

        if (strpos($url, '://') > 0) {
            // protocol, leave in place for CSS, comment for JS
            return self::$_isCss
                ? $m[0]
                : "/* Minify_ImportProcessor will not include remote content */";
        }
        if ('/' === $url[0]) {
            // protocol-relative or root path
            $url = ltrim($url, '/');
            $file = realpath($_SERVER['DOCUMENT_ROOT']) . DIRECTORY_SEPARATOR
                . strtr($url, '/', DIRECTORY_SEPARATOR);
        } else {
            // relative to current path
            $file = $this->_currentDir . DIRECTORY_SEPARATOR
                . strtr($url, '/', DIRECTORY_SEPARATOR);
        }
        $obj = new Minify_ImportProcessor(dirname($file), $this->_currentDir);
        $content = $obj->_getContent($file, true);
        if ('' === $content) {
            // failed. leave in place for CSS, comment for JS
            return self::$_isCss
                ? $m[0]
                : "/* Minify_ImportProcessor could not fetch '{$file}' */";
        }
        return (!self::$_isCss || preg_match('@(?:^$|\\ball\\b)@', $mediaList))
            ? $content
            : "@media {$mediaList} {\n{$content}\n}\n";
    }

    private function _urlCB($m)
    {
        // $m[1] is either quoted or not
        $quote = ($m[1][0] === "'" || $m[1][0] === '"')
            ? $m[1][0]
            : '';
        $url = ($quote === '')
            ? $m[1]
            : substr($m[1], 1, strlen($m[1]) - 2);
        if ('/' !== $url[0]) {
            if (strpos($url, '//') > 0) {
                // probably starts with protocol, do not alter
            } else {
                // prepend path with current dir separator (OS-independent)
                $path = $this->_currentDir
                    . DIRECTORY_SEPARATOR . strtr($url, '/', DIRECTORY_SEPARATOR);
                // update the relative path by the directory of the file that imported this one
                $url = self::getPathDiff(realpath($this->_previewsDir), $path);
            }
        }
        return "url({$quote}{$url}{$quote})";
    }

    /**
     * @param string $from
     * @param string $to
     * @param string $ps
     * @return string
     */
    private function getPathDiff($from, $to, $ps = DIRECTORY_SEPARATOR)
    {
        $realFrom = $this->truepath($from);
        $realTo = $this->truepath($to);

        $arFrom = explode($ps, rtrim($realFrom, $ps));
        $arTo = explode($ps, rtrim($realTo, $ps));
        while (count($arFrom) && count($arTo) && ($arFrom[0] == $arTo[0]))
        {
            array_shift($arFrom);
            array_shift($arTo);
        }
        return str_pad("", count($arFrom) * 3, '..' . $ps) . implode($ps, $arTo);
    }

    /**
     * This function is to replace PHP's extremely buggy realpath().
     * @param string $path The original path, can be relative etc.
     * @return string The resolved path, it might not exist.
     * @see http://stackoverflow.com/questions/4049856/replace-phps-realpath
     */
    function truepath($path)
    {
        // whether $path is unix or not
        $unipath = strlen($path) == 0 || $path{0} != '/';
        // attempts to detect if path is relative in which case, add cwd
        if (strpos($path, ':') === false && $unipath)
            $path = $this->_currentDir . DIRECTORY_SEPARATOR . $path;

        // resolve path parts (single dot, double dot and double delimiters)
        $path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
        $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
        $absolutes = array();
        foreach ($parts as $part) {
            if ('.' == $part)
                continue;
            if ('..' == $part) {
                array_pop($absolutes);
            } else {
                $absolutes[] = $part;
            }
        }
        $path = implode(DIRECTORY_SEPARATOR, $absolutes);
        // resolve any symlinks
        if (file_exists($path) && linkinfo($path) > 0)
            $path = readlink($path);
        // put initial separator that could have been lost
        $path = !$unipath ? '/' . $path : $path;
        return $path;
    }
}
