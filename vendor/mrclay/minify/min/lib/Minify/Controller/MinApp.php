<?php
/**
 * Class Minify_Controller_MinApp  
 * @package Minify
 */

/**
 * Controller class for requests to /min/index.php
 * 
 * @package Minify
 * @author Stephen Clay <steve@mrclay.org>
 */
class Minify_Controller_MinApp extends Minify_Controller_Base {
    
    /**
     * Set up groups of files as sources
     * 
     * @param array $options controller and Minify options
     *
     * @return array Minify options
     */
    public function setupSources($options) {
        // PHP insecure by default: realpath() and other FS functions can't handle null bytes.
        foreach (array('g', 'b', 'f') as $key) {
            if (isset($_GET[$key])) {
                $_GET[$key] = str_replace("\x00", '', (string)$_GET[$key]);
            }
        }

        // filter controller options
        $cOptions = array_merge(
            array(
                'allowDirs' => '//'
                ,'groupsOnly' => false
                ,'groups' => array()
                ,'noMinPattern' => '@[-\\.]min\\.(?:js|css)$@i' // matched against basename
            )
            ,(isset($options['minApp']) ? $options['minApp'] : array())
        );
        unset($options['minApp']);
        $sources = array();
        $this->selectionId = '';
        $firstMissingResource = null;
        if (isset($_GET['g'])) {
            // add group(s)
            $this->selectionId .= 'g=' . $_GET['g'];
            $keys = explode(',', $_GET['g']);
            if ($keys != array_unique($keys)) {
                $this->log("Duplicate group key found.");
                return $options;
            }
            $keys = explode(',', $_GET['g']);
            foreach ($keys as $key) {
                if (! isset($cOptions['groups'][$key])) {
                    $this->log("A group configuration for \"{$key}\" was not found");
                    return $options;
                }
                $files = $cOptions['groups'][$key];
                // if $files is a single object, casting will break it
                if (is_object($files)) {
                    $files = array($files);
                } elseif (! is_array($files)) {
                    $files = (array)$files;
                }
                foreach ($files as $file) {
                    if ($file instanceof Minify_Source) {
                        $sources[] = $file;
                        continue;
                    }
                    if (0 === strpos($file, '//')) {
                        $file = $_SERVER['DOCUMENT_ROOT'] . substr($file, 1);
                    }
                    $realpath = realpath($file);
                    if ($realpath && is_file($realpath)) {
                        $sources[] = $this->_getFileSource($realpath, $cOptions);
                    } else {
                        $this->log("The path \"{$file}\" (realpath \"{$realpath}\") could not be found (or was not a file)");
                        if (null === $firstMissingResource) {
                            $firstMissingResource = basename($file);
                            continue;
                        } else {
                            $secondMissingResource = basename($file);
                            $this->log("More than one file was missing: '$firstMissingResource', '$secondMissingResource'");
                            return $options;
                        }
                    }
                }
                if ($sources) {
                    try {
                        $this->checkType($sources[0]);
                    } catch (Exception $e) {
                        $this->log($e->getMessage());
                        return $options;
                    }
                }
            }
        }
        if (! $cOptions['groupsOnly'] && isset($_GET['f'])) {
            // try user files
            // The following restrictions are to limit the URLs that minify will
            // respond to.
            if (// verify at least one file, files are single comma separated, 
                // and are all same extension
                ! preg_match('/^[^,]+\\.(css|js)(?:,[^,]+\\.\\1)*$/', $_GET['f'], $m)
                // no "//"
                || strpos($_GET['f'], '//') !== false
                // no "\"
                || strpos($_GET['f'], '\\') !== false
            ) {
                $this->log("GET param 'f' was invalid");
                return $options;
            }
            $ext = ".{$m[1]}";
            try {
                $this->checkType($m[1]);
            } catch (Exception $e) {
                $this->log($e->getMessage());
                return $options;
            }
            $files = explode(',', $_GET['f']);
            if ($files != array_unique($files)) {
                $this->log("Duplicate files were specified");
                return $options;
            }
            if (isset($_GET['b'])) {
                // check for validity
                if (preg_match('@^[^/]+(?:/[^/]+)*$@', $_GET['b'])
                    && false === strpos($_GET['b'], '..')
                    && $_GET['b'] !== '.') {
                    // valid base
                    $base = "/{$_GET['b']}/";       
                } else {
                    $this->log("GET param 'b' was invalid");
                    return $options;
                }
            } else {
                $base = '/';
            }
            $allowDirs = array();
            foreach ((array)$cOptions['allowDirs'] as $allowDir) {
                $allowDirs[] = realpath(str_replace('//', $_SERVER['DOCUMENT_ROOT'] . '/', $allowDir));
            }
            $basenames = array(); // just for cache id
            foreach ($files as $file) {
                $uri = $base . $file;
                $path = $_SERVER['DOCUMENT_ROOT'] . $uri;
                $realpath = realpath($path);
                if (false === $realpath || ! is_file($realpath)) {
                    $this->log("The path \"{$path}\" (realpath \"{$realpath}\") could not be found (or was not a file)");
                    if (null === $firstMissingResource) {
                        $firstMissingResource = $uri;
                        continue;
                    } else {
                        $secondMissingResource = $uri;
                        $this->log("More than one file was missing: '$firstMissingResource', '$secondMissingResource`'");
                        return $options;
                    }
                }
                try {
                    parent::checkNotHidden($realpath);
                    parent::checkAllowDirs($realpath, $allowDirs, $uri);
                } catch (Exception $e) {
                    $this->log($e->getMessage());
                    return $options;
                }
                $sources[] = $this->_getFileSource($realpath, $cOptions);
                $basenames[] = basename($realpath, $ext);
            }
            if ($this->selectionId) {
                $this->selectionId .= '_f=';
            }
            $this->selectionId .= implode(',', $basenames) . $ext;
        }
        if ($sources) {
            if (null !== $firstMissingResource) {
                array_unshift($sources, new Minify_Source(array(
                    'id' => 'missingFile'
                    // should not cause cache invalidation
                    ,'lastModified' => 0
                    // due to caching, filename is unreliable.
                    ,'content' => "/* Minify: at least one missing file. See " . Minify::URL_DEBUG . " */\n"
                    ,'minifier' => ''
                )));
            }
            $this->sources = $sources;
        } else {
            $this->log("No sources to serve");
        }
        return $options;
    }

    /**
     * @param string $file
     *
     * @param array $cOptions
     *
     * @return Minify_Source
     */
    protected function _getFileSource($file, $cOptions)
    {
        $spec['filepath'] = $file;
        if ($cOptions['noMinPattern'] && preg_match($cOptions['noMinPattern'], basename($file))) {
            if (preg_match('~\.css$~i', $file)) {
                $spec['minifyOptions']['compress'] = false;
            } else {
                $spec['minifier'] = '';
            }
        }
        return new Minify_Source($spec);
    }

    protected $_type = null;

    /**
     * Make sure that only source files of a single type are registered
     *
     * @param string $sourceOrExt
     *
     * @throws Exception
     */
    public function checkType($sourceOrExt)
    {
        if ($sourceOrExt === 'js') {
            $type = Minify::TYPE_JS;
        } elseif ($sourceOrExt === 'css') {
            $type = Minify::TYPE_CSS;
        } elseif ($sourceOrExt->contentType !== null) {
            $type = $sourceOrExt->contentType;
        } else {
            return;
        }
        if ($this->_type === null) {
            $this->_type = $type;
        } elseif ($this->_type !== $type) {
            throw new Exception('Content-Type mismatch');
        }
    }
}
