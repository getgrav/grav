<?php
/**
 * Class Minify_CSS  
 * @package Minify
 */

/**
 * Minify CSS
 *
 * This class uses Minify_CSS_Compressor and Minify_CSS_UriRewriter to 
 * minify CSS and rewrite relative URIs.
 * 
 * @package Minify
 * @author Stephen Clay <steve@mrclay.org>
 * @author http://code.google.com/u/1stvamp/ (Issue 64 patch)
 */
class Minify_CSS {
    
    /**
     * Minify a CSS string
     * 
     * @param string $css
     * 
     * @param array $options available options:
     * 
     * 'preserveComments': (default true) multi-line comments that begin
     * with "/*!" will be preserved with newlines before and after to
     * enhance readability.
     *
     * 'removeCharsets': (default true) remove all @charset at-rules
     * 
     * 'prependRelativePath': (default null) if given, this string will be
     * prepended to all relative URIs in import/url declarations
     * 
     * 'currentDir': (default null) if given, this is assumed to be the
     * directory of the current CSS file. Using this, minify will rewrite
     * all relative URIs in import/url declarations to correctly point to
     * the desired files. For this to work, the files *must* exist and be
     * visible by the PHP process.
     *
     * 'symlinks': (default = array()) If the CSS file is stored in 
     * a symlink-ed directory, provide an array of link paths to
     * target paths, where the link paths are within the document root. Because 
     * paths need to be normalized for this to work, use "//" to substitute 
     * the doc root in the link paths (the array keys). E.g.:
     * <code>
     * array('//symlink' => '/real/target/path') // unix
     * array('//static' => 'D:\\staticStorage')  // Windows
     * </code>
     *
     * 'docRoot': (default = $_SERVER['DOCUMENT_ROOT'])
     * see Minify_CSS_UriRewriter::rewrite
     * 
     * @return string
     */
    public static function minify($css, $options = array()) 
    {
        $options = array_merge(array(
            'compress' => true,
            'removeCharsets' => true,
            'preserveComments' => true,
            'currentDir' => null,
            'docRoot' => $_SERVER['DOCUMENT_ROOT'],
            'prependRelativePath' => null,
            'symlinks' => array(),
        ), $options);
        
        if ($options['removeCharsets']) {
            $css = preg_replace('/@charset[^;]+;\\s*/', '', $css);
        }
        if ($options['compress']) {
            if (! $options['preserveComments']) {
                $css = Minify_CSS_Compressor::process($css, $options);
            } else {
                $css = Minify_CommentPreserver::process(
                    $css
                    ,array('Minify_CSS_Compressor', 'process')
                    ,array($options)
                );
            }
        }
        if (! $options['currentDir'] && ! $options['prependRelativePath']) {
            return $css;
        }
        if ($options['currentDir']) {
            return Minify_CSS_UriRewriter::rewrite(
                $css
                ,$options['currentDir']
                ,$options['docRoot']
                ,$options['symlinks']
            );  
        } else {
            return Minify_CSS_UriRewriter::prepend(
                $css
                ,$options['prependRelativePath']
            );
        }
    }
}
