<?php
namespace Grav\Common\Filesystem\File;

/**
 * File handling class.
 *
 * @author RocketTheme
 * @license MIT
 */
class Config extends General
{
    /**
     * @var string
     */
    protected $extension = '.php';

    /**
     * @var array|General[]
     */
    static protected $instances = array();

    /**
     * Saves configuration file and invalidates opcache.
     *
     * @param  mixed  $data  Optional data to be saved, usually array.
     * @throws \RuntimeException
     */
    public function save($data = null)
    {
        parent::save($data);

        // Invalidate configuration file from the opcache.
        if (function_exists('opcache_invalidate')) {
            // PHP 5.5.5+
            @opcache_invalidate($this->filename);
        } elseif (function_exists('apc_invalidate')) {
            // APC
            @apc_invalidate($this->filename);
        }
    }

    /**
     * Check contents and make sure it is in correct format.
     *
     * @param \Grav\Common\Config $var
     * @return \Grav\Common\Config
     * @throws \RuntimeException
     */
    protected function check($var)
    {
        if (!($var instanceof \Grav\Common\Config)) {
            throw new \RuntimeException('Provided data is not configuration');
        }

        return $var;
    }

    /**
     * Encode configuration object into RAW string (PHP class).
     *
     * @param \Grav\Common\Config $var
     * @return string
     * @throws \RuntimeException
     */
    protected function encode($var)
    {
        if (!($var instanceof \Grav\Common\Config)) {
            throw new \RuntimeException('Provided data is not configuration');
        }

        // Build the object variables string
        $vars = array();
        $options = $var->toArray();

        foreach ($options as $k => $v) {
            if (is_int($v)) {
                $vars[] = "\tpublic $" . $k . " = " . $v . ";";
            } elseif (is_bool($v)) {
                $vars[] = "\tpublic $" . $k . " = " . ($v ? 'true' : 'false') . ";";
            } elseif (is_scalar($v)) {
                $vars[] = "\tpublic $" . $k . " = '" . addcslashes($v, '\\\'') . "';";
            } elseif (is_array($v) || is_object($v)) {
                $vars[] = "\tpublic $" . $k . " = " . $this->encodeArray((array) $v) . ";";
            }
        }
        $vars = implode("\n", $vars);

        return "<?php\nnamespace Grav;\n\nclass Config extends \\Grav\\Common\\Config {\n {$vars}\n}";
    }

    /**
     * Method to get an array as an exported string.
     *
     * @param array $a      The array to get as a string.
     * @param int   $level  Used internally to indent rows.
     *
     * @return array
     */
    protected function encodeArray($a, $level = 1)
    {
        $r = array();
        foreach ($a as $k => $v) {
            if (is_array($v) || is_object($v)) {
                $r[] = '"' . $k . '" => ' . $this->encodeArray((array) $v, $level+1);
            } elseif (is_int($v)) {
                $r[] = "'" . $k . "' => " . $v;
            } elseif (is_bool($v)) {
                $r[] = "'" . $k . "' => " . ($v ? 'true' : 'false');
            } else {
                $r[] .= "'" . $k . "' => " . "'" . addslashes($v) . "'";
            }
        }

        $tabs = str_repeat("\t", $level);
        return "array(\n\t{$tabs}" . implode(",\n\t{$tabs}", $r) . "\n{$tabs})";
    }

    /**
     * Decode RAW string into contents.
     *
     * @param string $var
     * @return \Grav\Common\Config
     */
    protected function decode($var)
    {
        // TODO: improve this one later, works only for single file...
        return class_exists('\Grav\Config') ? new \Grav\Config($this->filename) : new Config($this->filename);
    }
}
