<?php
namespace Grav\Common\Filesystem\File;

/**
 * File handling class for Log files.
 *
 * @author RocketTheme
 * @license MIT
 */
class Log extends General
{
    /**
     * @var array|General[]
     */
    static protected $instances = array();

    /**
     * Constructor.
     */
    protected function __construct()
    {
        parent::__construct();

        $this->extension = '.log';
    }

    /**
     * Check contents and make sure it is in correct format.
     *
     * @param array $var
     * @return array
     */
    protected function check($var)
    {
        return (array) $var;
    }

    /**
     * Encode contents into RAW string (unsupported).
     *
     * @param string $var
     * @throws \Exception
     */
    protected function encode($var)
    {
        throw new \Exception('Saving log file is forbidden.');
    }

    /**
     * Decode RAW string into contents.
     *
     * @param string $var
     * @return array mixed
     */
    protected function decode($var)
    {
        $lines = (array) preg_split('#(\r\n|\n|\r)#', $var);

        $results = array();
        foreach ($lines as $line) {
            preg_match('#^\[(.*)\] (.*)  @  (.*)  @@  (.*)$#', $line, $matches);
            if ($matches) {
                $results[] = ['date' => $matches[1], 'message' => $matches[2], 'url' => $matches[3], 'file' => $matches[4]];
            }
        }

        return $results;
    }
}
