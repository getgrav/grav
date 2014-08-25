<?php

namespace MrClay\Cli;

use BadMethodCallException;

/**
 * An argument for a CLI app. This specifies the argument, what values it expects and
 * how it's treated during validation.
 *
 * By default, the argument will be assumed to be an optional letter flag with no value following.
 *
 * If the argument may receive a value, call mayHaveValue(). If there's whitespace after the
 * flag, the value will be returned as true instead of the string.
 *
 * If the argument MUST be accompanied by a value, call mustHaveValue(). In this case, whitespace
 * is permitted between the flag and its value.
 *
 * Use assertFile() or assertDir() to indicate that the argument must return a string value
 * specifying a file or directory. During validation, the value will be resolved to a
 * full file/dir path (not necessarily existing!) and the original value will be accessible
 * via a "*.raw" key. E.g. $cli->values['f.raw']
 *
 * Use assertReadable()/assertWritable() to cause the validator to test the file/dir for
 * read/write permissions respectively.
 *
 * @method \MrClay\Cli\Arg mayHaveValue() Assert that the argument, if present, may receive a string value
 * @method \MrClay\Cli\Arg mustHaveValue() Assert that the argument, if present, must receive a string value
 * @method \MrClay\Cli\Arg assertFile() Assert that the argument's value must specify a file
 * @method \MrClay\Cli\Arg assertDir() Assert that the argument's value must specify a directory
 * @method \MrClay\Cli\Arg assertReadable() Assert that the specified file/dir must be readable
 * @method \MrClay\Cli\Arg assertWritable() Assert that the specified file/dir must be writable
 *
 * @property-read bool mayHaveValue
 * @property-read bool mustHaveValue
 * @property-read bool assertFile
 * @property-read bool assertDir
 * @property-read bool assertReadable
 * @property-read bool assertWritable
 * @property-read bool useAsInfile
 * @property-read bool useAsOutfile
 *
 * @author Steve Clay <steve@mrclay.org>
 * @license http://www.opensource.org/licenses/mit-license.php  MIT License
 */
class Arg {
    /**
     * @return array
     */
    public function getDefaultSpec()
    {
        return array(
            'mayHaveValue' => false,
            'mustHaveValue' => false,
            'assertFile' => false,
            'assertDir' => false,
            'assertReadable' => false,
            'assertWritable' => false,
            'useAsInfile' => false,
            'useAsOutfile' => false,
        );
    }

    /**
     * @var array
     */
    protected $spec = array();

    /**
     * @var bool
     */
    protected $required = false;

    /**
     * @var string
     */
    protected $description = '';

    /**
     * @param bool $isRequired
     */
    public function __construct($isRequired = false)
    {
        $this->spec = $this->getDefaultSpec();
        $this->required = (bool) $isRequired;
        if ($isRequired) {
            $this->spec['mustHaveValue'] = true;
        }
    }

    /**
     * Assert that the argument's value points to a writable file. When
     * Cli::openOutput() is called, a write pointer to this file will
     * be provided.
     * @return Arg
     */
    public function useAsOutfile()
    {
        $this->spec['useAsOutfile'] = true;
        return $this->assertFile()->assertWritable();
    }

    /**
     * Assert that the argument's value points to a readable file. When
     * Cli::openInput() is called, a read pointer to this file will
     * be provided.
     * @return Arg
     */
    public function useAsInfile()
    {
        $this->spec['useAsInfile'] = true;
        return $this->assertFile()->assertReadable();
    }

    /**
     * @return array
     */
    public function getSpec()
    {
        return $this->spec;
    }

    /**
     * @param string $desc
     * @return Arg
     */
    public function setDescription($desc)
    {
        $this->description = $desc;
        return $this;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @return bool
     */
    public function isRequired()
    {
        return $this->required;
    }

    /**
     * Note: magic methods declared in class PHPDOC
     *
     * @param string $name
     * @param array $args
     * @return Arg
     * @throws BadMethodCallException
     */
    public function __call($name, array $args = array())
    {
        if (array_key_exists($name, $this->spec)) {
            $this->spec[$name] = true;
            if ($name === 'assertFile' || $name === 'assertDir') {
                $this->spec['mustHaveValue'] = true;
            }
        } else {
            throw new BadMethodCallException('Method does not exist');
        }
        return $this;
    }

    /**
     * Note: magic properties declared in class PHPDOC
     *
     * @param string $name
     * @return bool|null
     */
    public function __get($name)
    {
        if (array_key_exists($name, $this->spec)) {
            return $this->spec[$name];
        }
        return null;
    }
}
