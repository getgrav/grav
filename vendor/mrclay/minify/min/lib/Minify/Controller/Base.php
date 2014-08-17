<?php
/**
 * Class Minify_Controller_Base  
 * @package Minify
 */

/**
 * Base class for Minify controller
 * 
 * The controller class validates a request and uses it to create sources
 * for minification and set options like contentType. It's also responsible
 * for loading minifier code upon request.
 * 
 * @package Minify
 * @author Stephen Clay <steve@mrclay.org>
 */
abstract class Minify_Controller_Base {
    
    /**
     * Setup controller sources and set an needed options for Minify::source
     * 
     * You must override this method in your subclass controller to set 
     * $this->sources. If the request is NOT valid, make sure $this->sources 
     * is left an empty array. Then strip any controller-specific options from 
     * $options and return it. To serve files, $this->sources must be an array of
     * Minify_Source objects.
     * 
     * @param array $options controller and Minify options
     * 
     * @return array $options Minify::serve options
     */
    abstract public function setupSources($options);
    
    /**
     * Get default Minify options for this controller.
     * 
     * Override in subclass to change defaults
     *
     * @return array options for Minify
     */
    public function getDefaultMinifyOptions() {
        return array(
            'isPublic' => true
            ,'encodeOutput' => function_exists('gzdeflate')
            ,'encodeMethod' => null // determine later
            ,'encodeLevel' => 9
            ,'minifierOptions' => array() // no minifier options
            ,'contentTypeCharset' => 'utf-8'
            ,'maxAge' => 1800 // 30 minutes
            ,'rewriteCssUris' => true
            ,'bubbleCssImports' => false
            ,'quiet' => false // serve() will send headers and output
            ,'debug' => false
            
            // if you override these, the response codes MUST be directly after
            // the first space.
            ,'badRequestHeader' => 'HTTP/1.0 400 Bad Request'
            ,'errorHeader'      => 'HTTP/1.0 500 Internal Server Error'
            
            // callback function to see/modify content of all sources
            ,'postprocessor' => null
            // file to require to load preprocessor
            ,'postprocessorRequire' => null
        );
    }  

    /**
     * Get default minifiers for this controller.
     * 
     * Override in subclass to change defaults
     *
     * @return array minifier callbacks for common types
     */
    public function getDefaultMinifers() {
        $ret[Minify::TYPE_JS] = array('JSMin', 'minify');
        $ret[Minify::TYPE_CSS] = array('Minify_CSS', 'minify');
        $ret[Minify::TYPE_HTML] = array('Minify_HTML', 'minify');
        return $ret;
    }
    
    /**
     * Is a user-given file within an allowable directory, existing,
     * and having an extension js/css/html/txt ?
     * 
     * This is a convenience function for controllers that have to accept
     * user-given paths
     *
     * @param string $file full file path (already processed by realpath())
     * 
     * @param array $safeDirs directories where files are safe to serve. Files can also
     * be in subdirectories of these directories.
     * 
     * @return bool file is safe
     *
     * @deprecated use checkAllowDirs, checkNotHidden instead
     */
    public static function _fileIsSafe($file, $safeDirs)
    {
        $pathOk = false;
        foreach ((array)$safeDirs as $safeDir) {
            if (strpos($file, $safeDir) === 0) {
                $pathOk = true;
                break;
            }
        }
        $base = basename($file);
        if (! $pathOk || ! is_file($file) || $base[0] === '.') {
            return false;
        }
        list($revExt) = explode('.', strrev($base));
        return in_array(strrev($revExt), array('js', 'css', 'html', 'txt'));
    }

    /**
     * @param string $file
     * @param array $allowDirs
     * @param string $uri
     * @return bool
     * @throws Exception
     */
    public static function checkAllowDirs($file, $allowDirs, $uri)
    {
        foreach ((array)$allowDirs as $allowDir) {
            if (strpos($file, $allowDir) === 0) {
                return true;
            }
        }
        throw new Exception("File '$file' is outside \$allowDirs. If the path is"
            . " resolved via an alias/symlink, look into the \$min_symlinks option."
            . " E.g. \$min_symlinks['/" . dirname($uri) . "'] = '" . dirname($file) . "';");
    }

    /**
     * @param string $file
     * @throws Exception
     */
    public static function checkNotHidden($file)
    {
        $b = basename($file);
        if (0 === strpos($b, '.')) {
            throw new Exception("Filename '$b' starts with period (may be hidden)");
        }
    }

    /**
     * instances of Minify_Source, which provide content and any individual minification needs.
     *
     * @var array
     * 
     * @see Minify_Source
     */
    public $sources = array();
    
    /**
     * Short name to place inside cache id
     *
     * The setupSources() method may choose to set this, making it easier to
     * recognize a particular set of sources/settings in the cache folder. It
     * will be filtered and truncated to make the final cache id <= 250 bytes.
     * 
     * @var string
     */
    public $selectionId = '';

    /**
     * Mix in default controller options with user-given options
     * 
     * @param array $options user options
     * 
     * @return array mixed options
     */
    public final function mixInDefaultOptions($options)
    {
        $ret = array_merge(
            $this->getDefaultMinifyOptions(), $options
        );
        if (! isset($options['minifiers'])) {
            $options['minifiers'] = array();
        }
        $ret['minifiers'] = array_merge(
            $this->getDefaultMinifers(), $options['minifiers']
        );
        return $ret;
    }
    
    /**
     * Analyze sources (if there are any) and set $options 'contentType' 
     * and 'lastModifiedTime' if they already aren't.
     * 
     * @param array $options options for Minify
     * 
     * @return array options for Minify
     */
    public final function analyzeSources($options = array()) 
    {
        if ($this->sources) {
            if (! isset($options['contentType'])) {
                $options['contentType'] = Minify_Source::getContentType($this->sources);
            }
            // last modified is needed for caching, even if setExpires is set
            if (! isset($options['lastModifiedTime'])) {
                $max = 0;
                foreach ($this->sources as $source) {
                    $max = max($source->lastModified, $max);
                }
                $options['lastModifiedTime'] = $max;
            }    
        }
        return $options;
    }

    /**
     * Send message to the Minify logger
     *
     * @param string $msg
     *
     * @return null
     */
    public function log($msg) {
        Minify_Logger::log($msg);
    }
}
