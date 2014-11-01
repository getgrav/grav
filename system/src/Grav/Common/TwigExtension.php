<?php
namespace Grav\Common;

use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;


/**
 * The Twig extension adds some filters and functions that are useful for Grav
 *
 * @author RocketTheme
 * @license MIT
 */
class TwigExtension extends \Twig_Extension
{
    protected $grav;
    protected $debugger;

    public function __construct()
    {
        $this->grav = Grav::instance();
        $this->debugger = isset($this->grav['debugger']) ? $this->grav['debugger'] : null;
    }

    /**
     * Returns extension name.
     *
     * @return string
     */
    public function getName()
    {
        return 'GravTwigExtension';
    }

    /**
     * Return a list of all filters.
     *
     * @return array
     */
    public function getFilters()
    {
        return [
            new \Twig_SimpleFilter('fieldName', [$this,'fieldNameFilter']),
            new \Twig_SimpleFilter('safe_email', [$this,'safeEmailFilter']),
            new \Twig_SimpleFilter('randomize', [$this,'randomizeFilter']),
            new \Twig_SimpleFilter('truncate', [$this,'truncateFilter']),
            new \Twig_SimpleFilter('*ize', [$this,'inflectorFilter']),
            new \Twig_SimpleFilter('md5', [$this,'md5Filter']),
            new \Twig_SimpleFilter('sort_by_key',[$this,'sortByKeyFilter']),
        ];
    }

    /**
     * Return a list of all functions.
     *
     * @return array
     */
    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('repeat', [$this, 'repeatFunc']),
            new \Twig_SimpleFunction('url', [$this, 'urlFunc']),
            new \Twig_SimpleFunction('dump', [$this, 'dump'], ['needs_context' => true, 'needs_environment' => true]),
            new \Twig_SimpleFunction('debug', [$this, 'dump'], ['needs_context' => true, 'needs_environment' => true]),
        ];
    }

    /**
     * Filters field name by changing dot notation into array notation.
     *
     * @param  string  $str
     * @return string
     */
    public function fieldNameFilter($str)
    {
        $path = explode('.', $str);

        return array_shift($path) . ($path ? '[' . implode('][', $path) . ']' : '');
    }

    /**
     * Protects email address.
     *
     * @param  string  $str
     * @return string
     */
    public function safeEmailFilter($str)
    {
        $email = '';
        for ($i = 0; $i < strlen($str); $i++) {
            $email .= "&#" . ord($str[$i]);
        }
        return $email;
    }

    /**
     * Truncate content by a limit.
     *
     * @param  string $string
     * @param  int    $limit    Nax number of characters.
     * @param  string $break    Break point.
     * @param  string $pad      Appended padding to the end of the string.
     * @return string
     */
    public function truncateFilter($string, $limit = 150, $break = ".", $pad = "&hellip;")
    {
        // return with no change if string is shorter than $limit
        if (strlen($string) <= $limit) {
            return $string;
        }

        // is $break present between $limit and the end of the string?
        if (false !== ($breakpoint = strpos($string, $break, $limit))) {
            if ($breakpoint < strlen($string) - 1) {
                $string = substr($string, 0, $breakpoint) . $pad;
            }
        }

        return $string;
    }


    /**
     * Returns array in a random order.
     *
     * @param  array $original
     * @param  int   $offset   Can be used to return only slice of the array.
     * @return array
     */
    public function randomizeFilter($original, $offset = 0)
    {
        if (!is_array($original)) {
            return $original;
        }

        if ($original instanceof \Traversable) {
            $original = iterator_to_array($original, false);
        }

        $sorted = [];
        $random = array_slice($original, $offset);
        shuffle($random);

        $sizeOf = sizeof($original);
        for ($x=0; $x < $sizeOf; $x++) {
            if ($x < $offset) {
                $sorted[] = $original[$x];
            } else {
                $sorted[] = array_shift($random);
            }
        }
        return $sorted;
    }

    /**
     * Inflector supports following notations:
     *
     * {{ 'person'|pluralize }} => people
     * {{ 'shoes'|singularize }} => shoe
     * {{ 'welcome page'|titleize }} => "Welcome Page"
     * {{ 'send_email'|camelize }} => SendEmail
     * {{ 'CamelCased'|underscorize }} => camel_cased
     * {{ 'Something Text'|hyphenize }} => something-text
     * {{ 'something text to read'|humanize }} => "Something text to read"
     * {{ '181'|monthize}} => 6
     * {{ '10'|ordinalize }} => 10th
     *
     * @param string $action
     * @param string $data
     * @param int $count
     * @return mixed
     */
    public function inflectorFilter($action, $data, $count = null)
    {
        // TODO: check this and fix the docblock if needed.
        $action = $action.'ize';

        if (in_array(
            $action,
            ['titleize','camelize','underscorize','hyphenize', 'humanize','ordinalize','monthize']
        )) {
            return Inflector::$action($data);
        } elseif (in_array($action, ['pluralize','singularize'])) {
            if ($count) {
                return Inflector::$action($data, $count);
            } else {
                return Inflector::$action($data);
            }
        } else {
            return $data;
        }
    }

    /**
     * Return MD5 hash from the input.
     *
     * @param  string $str
     * @return string
     */
    public function md5Filter($str)
    {
        return md5($str);
    }

    /**
     * Repeat given string x times.
     *
     * @param  string $input
     * @param  int    $multiplier
     * @return string
     */
    public function repeatFunc($input, $multiplier)
    {
        return str_repeat($input, $multiplier);
    }

    /**
     * Return URL to the resource.
     *
     * @param  string $input
     * @param  bool $domain
     * @return string
     */
    public function urlFunc($input, $domain = false)
    {
        $grav = Grav::instance();

        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];

        /** @var Uri $uri */
        $uri = $grav['uri'];

        return $uri->rootUrl($domain) .'/'. $locator->findResource($input, false);
    }

    /**
     * Sorts a collection by key
     *
     * @param  array    $input
     * @param  string   $filter
     * @param array|int $direction
     *
     * @return string
     */
    public function sortByKeyFilter(array $input, $filter, $direction = SORT_ASC)
    {
        $output = [];

        if (!$input) {
            return $output;
        }

        foreach ($input as $key => $row) {
            $output[$key] = $row[$filter];
        }

        array_multisort($output, $direction, $input);

        return $input;
    }

    /**
     * Based on Twig_Extension_Debug / twig_var_dump
     * (c) 2011 Fabien Potencier
     *
     * @param \Twig_Environment $env
     * @param $context
     */
    public function dump(\Twig_Environment $env, $context)
    {
        if (!$env->isDebug() || !$this->debugger) {
            return;
        }

        $count = func_num_args();
        if (2 === $count) {
            $data = [];
            foreach ($context as $key => $value) {
                if (is_object($value)) {
                    if (method_exists($value, 'toArray')) {
                        $data[$key] = $value->toArray();
                    } else {
                        $data[$key] = "Object (" . get_class($value) . ")";
                    }
                } else {
                    $data[$key] = $value;
                }
            }
            $this->debugger->addMessage($data, 'debug');
        } else {
            for ($i = 2; $i < $count; $i++) {
                $this->debugger->addMessage(func_get_arg($i), 'debug');
            }
        }
    }
}
