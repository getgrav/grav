<?php 

namespace MrClay;

use MrClay\Cli\Arg;
use InvalidArgumentException;

/**
 * Forms a front controller for a console app, handling and validating arguments (options)
 *
 * Instantiate, add arguments, then call validate(). Afterwards, the user's valid arguments
 * and their values will be available in $cli->values.
 *
 * You may also specify that some arguments be used to provide input/output. By communicating
 * solely through the file pointers provided by openInput()/openOutput(), you can make your
 * app more flexible to end users.
 *
 * @author Steve Clay <steve@mrclay.org>
 * @license http://www.opensource.org/licenses/mit-license.php  MIT License
 */
class Cli {
    
    /**
     * @var array validation errors
     */
    public $errors = array();
    
    /**
     * @var array option values available after validation.
     * 
     * E.g. array(
     *      'a' => false              // option was missing
     *     ,'b' => true               // option was present
     *     ,'c' => "Hello"            // option had value
     *     ,'f' => "/home/user/file"  // file path from root
     *     ,'f.raw' => "~/file"       // file path as given to option
     * )
     */
    public $values = array();

    /**
     * @var array
     */
    public $moreArgs = array();

    /**
     * @var array
     */
    public $debug = array();

    /**
     * @var bool The user wants help info
     */
    public $isHelpRequest = false;

    /**
     * @var Arg[]
     */
    protected $_args = array();

    /**
     * @var resource
     */
    protected $_stdin = null;

    /**
     * @var resource
     */
    protected $_stdout = null;
    
    /**
     * @param bool $exitIfNoStdin (default true) Exit() if STDIN is not defined
     */
    public function __construct($exitIfNoStdin = true)
    {
        if ($exitIfNoStdin && ! defined('STDIN')) {
            exit('This script is for command-line use only.');
        }
        if (isset($GLOBALS['argv'][1])
             && ($GLOBALS['argv'][1] === '-?' || $GLOBALS['argv'][1] === '--help')) {
            $this->isHelpRequest = true;
        }
    }

    /**
     * @param Arg|string $letter
     * @return Arg
     */
    public function addOptionalArg($letter)
    {
        return $this->addArgument($letter, false);
    }

    /**
     * @param Arg|string $letter
     * @return Arg
     */
    public function addRequiredArg($letter)
    {
        return $this->addArgument($letter, true);
    }

    /**
     * @param string $letter
     * @param bool $required
     * @param Arg|null $arg
     * @return Arg
     * @throws InvalidArgumentException
     */
    public function addArgument($letter, $required, Arg $arg = null)
    {
        if (! preg_match('/^[a-zA-Z]$/', $letter)) {
            throw new InvalidArgumentException('$letter must be in [a-zA-Z]');
        }
        if (! $arg) {
            $arg = new Arg($required);
        }
        $this->_args[$letter] = $arg;
        return $arg;
    }

    /**
     * @param string $letter
     * @return Arg|null
     */
    public function getArgument($letter)
    {
        return isset($this->_args[$letter]) ? $this->_args[$letter] : null;
    }

    /*
     * Read and validate options
     * 
     * @return bool true if all options are valid
     */
    public function validate()
    {
        $options = '';
        $this->errors = array();
        $this->values = array();
        $this->_stdin = null;
        
        if ($this->isHelpRequest) {
            return false;
        }
        
        $lettersUsed = '';
        foreach ($this->_args as $letter => $arg) {
            /* @var Arg $arg  */
            $options .= $letter;
            $lettersUsed .= $letter;
            
            if ($arg->mayHaveValue || $arg->mustHaveValue) {
                $options .= ($arg->mustHaveValue ? ':' : '::');
            }
        }

        $this->debug['argv'] = $GLOBALS['argv'];
        $argvCopy = array_slice($GLOBALS['argv'], 1);
        $o = getopt($options);
        $this->debug['getopt_options'] = $options;
        $this->debug['getopt_return'] = $o;

        foreach ($this->_args as $letter => $arg) {
            /* @var Arg $arg  */
            $this->values[$letter] = false;
            if (isset($o[$letter])) {
                if (is_bool($o[$letter])) {

                    // remove from argv copy
                    $k = array_search("-$letter", $argvCopy);
                    if ($k !== false) {
                        array_splice($argvCopy, $k, 1);
                    }

                    if ($arg->mustHaveValue) {
                        $this->addError($letter, "Missing value");
                    } else {
                        $this->values[$letter] = true;
                    }
                } else {
                    // string
                    $this->values[$letter] = $o[$letter];
                    $v =& $this->values[$letter];

                    // remove from argv copy
                    // first look for -ovalue or -o=value
                    $pattern = "/^-{$letter}=?" . preg_quote($v, '/') . "$/";
                    $foundInArgv = false;
                    foreach ($argvCopy as $k => $argV) {
                        if (preg_match($pattern, $argV)) {
                            array_splice($argvCopy, $k, 1);
                            $foundInArgv = true;
                            break;
                        }
                    }
                    if (! $foundInArgv) {
                        // space separated
                        $k = array_search("-$letter", $argvCopy);
                        if ($k !== false) {
                            array_splice($argvCopy, $k, 2);
                        }
                    }
                    
                    // check that value isn't really another option
                    if (strlen($lettersUsed) > 1) {
                        $pattern = "/^-[" . str_replace($letter, '', $lettersUsed) . "]/i";
                        if (preg_match($pattern, $v)) {
                            $this->addError($letter, "Value was read as another option: %s", $v);
                            return false;
                        }    
                    }
                    if ($arg->assertFile || $arg->assertDir) {
                        if ($v[0] !== '/' && $v[0] !== '~') {
                            $this->values["$letter.raw"] = $v;
                            $v = getcwd() . "/$v";
                        }
                    }
                    if ($arg->assertFile) {
                        if ($arg->useAsInfile) {
                            $this->_stdin = $v;
                        } elseif ($arg->useAsOutfile) {
                            $this->_stdout = $v;
                        }
                        if ($arg->assertReadable && ! is_readable($v)) {
                            $this->addError($letter, "File not readable: %s", $v);
                            continue;
                        }
                        if ($arg->assertWritable) {
                            if (is_file($v)) {
                                if (! is_writable($v)) {
                                    $this->addError($letter, "File not writable: %s", $v);
                                }
                            } else {
                                if (! is_writable(dirname($v))) {
                                    $this->addError($letter, "Directory not writable: %s", dirname($v));
                                }
                            }
                        }
                    } elseif ($arg->assertDir && $arg->assertWritable && ! is_writable($v)) {
                        $this->addError($letter, "Directory not readable: %s", $v);
                    }
                }
            } else {
                if ($arg->isRequired()) {
                    $this->addError($letter, "Missing");
                }
            }
        }
        $this->moreArgs = $argvCopy;
        reset($this->moreArgs);
        return empty($this->errors);
    }

    /**
     * Get the full paths of file(s) passed in as unspecified arguments
     *
     * @return array
     */
    public function getPathArgs()
    {
        $r = $this->moreArgs;
        foreach ($r as $k => $v) {
            if ($v[0] !== '/' && $v[0] !== '~') {
                $v = getcwd() . "/$v";
                $v = str_replace('/./', '/', $v);
                do {
                    $v = preg_replace('@/[^/]+/\\.\\./@', '/', $v, 1, $changed);
                } while ($changed);
                $r[$k] = $v;
            }
        }
        return $r;
    }
    
    /**
     * Get a short list of errors with options
     * 
     * @return string
     */
    public function getErrorReport()
    {
        if (empty($this->errors)) {
            return '';
        }
        $r = "Some arguments did not pass validation:\n";
        foreach ($this->errors as $letter => $arr) {
            $r .= "  $letter : " . implode(', ', $arr) . "\n";
        }
        $r .= "\n";
        return $r;
    }

    /**
     * @return string
     */
    public function getArgumentsListing()
    {
        $r = "\n";
        foreach ($this->_args as $letter => $arg) {
            /* @var Arg $arg  */
            $desc = $arg->getDescription();
            $flag = " -$letter ";
            if ($arg->mayHaveValue) {
                $flag .= "[VAL]";
            } elseif ($arg->mustHaveValue) {
                $flag .= "VAL";
            }
            if ($arg->assertFile) {
                $flag = str_replace('VAL', 'FILE', $flag);
            } elseif ($arg->assertDir) {
                $flag = str_replace('VAL', 'DIR', $flag);
            }
            if ($arg->isRequired()) {
                $desc = "(required) $desc";
            }
            $flag = str_pad($flag, 12, " ", STR_PAD_RIGHT);
            $desc = wordwrap($desc, 70);
            $r .= $flag . str_replace("\n", "\n            ", $desc) . "\n\n";
        }
        return $r;
    }
    
    /**
     * Get resource of open input stream. May be STDIN or a file pointer
     * to the file specified by an option with 'STDIN'.
     *
     * @return resource
     */
    public function openInput()
    {
        if (null === $this->_stdin) {
            return STDIN;
        } else {
            $this->_stdin = fopen($this->_stdin, 'rb');
            return $this->_stdin;
        }
    }
    
    public function closeInput()
    {
        if (null !== $this->_stdin) {
            fclose($this->_stdin);
        }
    }
    
    /**
     * Get resource of open output stream. May be STDOUT or a file pointer
     * to the file specified by an option with 'STDOUT'. The file will be
     * truncated to 0 bytes on opening.
     *
     * @return resource
     */
    public function openOutput()
    {
        if (null === $this->_stdout) {
            return STDOUT;
        } else {
            $this->_stdout = fopen($this->_stdout, 'wb');
            return $this->_stdout;
        }
    }
    
    public function closeOutput()
    {
        if (null !== $this->_stdout) {
            fclose($this->_stdout);
        }
    }

    /**
     * @param string $letter
     * @param string $msg
     * @param string $value
     */
    protected function addError($letter, $msg, $value = null)
    {
        if ($value !== null) {
            $value = var_export($value, 1);
        }
        $this->errors[$letter][] = sprintf($msg, $value);
    }
}

