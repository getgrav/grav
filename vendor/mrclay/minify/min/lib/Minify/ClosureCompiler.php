<?php
/**
 * Class Minify_ClosureCompiler
 * @package Minify
 */

/**
 * Compress Javascript using the Closure Compiler
 *
 * You must set $jarFile and $tempDir before calling the minify functions.
 * Also, depending on your shell's environment, you may need to specify
 * the full path to java in $javaExecutable or use putenv() to setup the
 * Java environment.
 *
 * <code>
 * Minify_ClosureCompiler::$jarFile = '/path/to/closure-compiler-20120123.jar';
 * Minify_ClosureCompiler::$tempDir = '/tmp';
 * $code = Minify_ClosureCompiler::minify(
 *   $code,
 *   array('compilation_level' => 'SIMPLE_OPTIMIZATIONS')
 * );
 *
 * --compilation_level WHITESPACE_ONLY, SIMPLE_OPTIMIZATIONS, ADVANCED_OPTIMIZATIONS
 *
 * </code>
 *
 * @todo unit tests, $options docs
 * @todo more options support (or should just passthru them all?)
 *
 * @package Minify
 * @author Stephen Clay <steve@mrclay.org>
 * @author Elan Ruusamäe <glen@delfi.ee>
 */
class Minify_ClosureCompiler {

    const OPTION_CHARSET = 'charset';
    const OPTION_COMPILATION_LEVEL = 'compilation_level';

    public static $isDebug = false;

    /**
     * Filepath of the Closure Compiler jar file. This must be set before
     * calling minifyJs().
     *
     * @var string
     */
    public static $jarFile = null;

    /**
     * Writable temp directory. This must be set before calling minifyJs().
     *
     * @var string
     */
    public static $tempDir = null;

    /**
     * Filepath of "java" executable (may be needed if not in shell's PATH)
     *
     * @var string
     */
    public static $javaExecutable = 'java';

    /**
     * Minify a Javascript string
     *
     * @param string $js
     *
     * @param array $options (verbose is ignored)
     *
     * @see https://code.google.com/p/closure-compiler/source/browse/trunk/README
     *
     * @return string
     *
     * @throws Minify_ClosureCompiler_Exception
     */
    public static function minify($js, $options = array())
    {
        self::_prepare();
        if (! ($tmpFile = tempnam(self::$tempDir, 'cc_'))) {
            throw new Minify_ClosureCompiler_Exception('Minify_ClosureCompiler : could not create temp file in "'.self::$tempDir.'".');
        }
        file_put_contents($tmpFile, $js);
        $cmd = self::_getCmd($options, $tmpFile);
        exec($cmd, $output, $result_code);
        unlink($tmpFile);
        if ($result_code != 0) {
            $message = 'Minify_ClosureCompiler : Closure Compiler execution failed.';
            if (self::$isDebug) { 
                exec($cmd . ' 2>&1', $error);
                if ($error) {
                    $message .= "\nReason:\n" . join("\n", $error);
                }
            } 
            throw new Minify_ClosureCompiler_Exception($message);
        }
        return implode("\n", $output);
    }

    private static function _getCmd($userOptions, $tmpFile)
    {
        $o = array_merge(
            array(
                self::OPTION_CHARSET => 'utf-8',
                self::OPTION_COMPILATION_LEVEL => 'SIMPLE_OPTIMIZATIONS',
            ),
            $userOptions
        );
        $charsetOption = $o[self::OPTION_CHARSET];
        $cmd = self::$javaExecutable . ' -jar ' . escapeshellarg(self::$jarFile)
             . (preg_match('/^[\\da-zA-Z0-9\\-]+$/', $charsetOption)
                ? " --charset {$charsetOption}"
                : '');

        foreach (array(self::OPTION_COMPILATION_LEVEL) as $opt) {
            if ($o[$opt]) {
                $cmd .= " --{$opt} ". escapeshellarg($o[$opt]);
            }
        }
        return $cmd . ' ' . escapeshellarg($tmpFile);
    }

    private static function _prepare()
    {
        if (! is_file(self::$jarFile)) {
            throw new Minify_ClosureCompiler_Exception('Minify_ClosureCompiler : $jarFile('.self::$jarFile.') is not a valid file.');
        }
        if (! is_readable(self::$jarFile)) {
            throw new Minify_ClosureCompiler_Exception('Minify_ClosureCompiler : $jarFile('.self::$jarFile.') is not readable.');
        }
        if (! is_dir(self::$tempDir)) {
            throw new Minify_ClosureCompiler_Exception('Minify_ClosureCompiler : $tempDir('.self::$tempDir.') is not a valid direcotry.');
        }
        if (! is_writable(self::$tempDir)) {
            throw new Minify_ClosureCompiler_Exception('Minify_ClosureCompiler : $tempDir('.self::$tempDir.') is not writable.');
        }
    }
}

class Minify_ClosureCompiler_Exception extends Exception {}
