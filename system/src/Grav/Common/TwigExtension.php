<?php
namespace Grav\Common;

use Grav\Common\Markdown\Parsedown;
use Grav\Common\Markdown\ParsedownExtra;
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
            new \Twig_SimpleFilter('sort_by_key', [$this,'sortByKeyFilter']),
            new \Twig_SimpleFilter('ksort', [$this,'ksortFilter']),
            new \Twig_SimpleFilter('contains', [$this, 'containsFilter']),
            new \Twig_SimpleFilter('nicetime', [$this, 'nicetimeFilter']),
            new \Twig_SimpleFilter('absolute_url', [$this, 'absoluteUrlFilter']),
            new \Twig_SimpleFilter('markdown', [$this, 'markdownFilter']),
            new \Twig_SimpleFilter('starts_with', [$this, 'startsWithFilter']),
            new \Twig_SimpleFilter('ends_with', [$this, 'endsWithFilter'])
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
            new \Twig_SimpleFunction('gist', [$this, 'gistFunc']),
            new \Twig_simpleFunction('random_string', [$this, 'randomStringFunc']),
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
        $str_len = strlen($str);
        for ($i = 0; $i < $str_len; $i++) {
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
     * {{ 'something_text_to_read'|humanize }} => "Something text to read"
     * {{ '181'|monthize }} => 6
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
     * Return ksorted collection.
     *
     * @param  array $array
     * @return array
     */
    public function ksortFilter(array $array)
    {
        ksort($array);
        return $array;
    }

    /**
     * determine if a string contains another
     *
     * @param String $haystack
     * @param String $needle
     *
     * @return boolean
     */
    public function containsFilter($haystack, $needle)
    {
        return (strpos($haystack, $needle) !== false);
    }

    /**
     * displays a facebook style 'time ago' formatted date/time
     *
     * @param $date
     * @param $long_strings
     * @param String
     *
     * @return boolean
     */
    public function nicetimeFilter($date, $long_strings = true)
    {
        if (empty($date)) {
            return "No date provided";
        }

        if ($long_strings) {
            $periods = array("second", "minute", "hour", "day", "week", "month", "year", "decade");
        } else {
            $periods = array("sec", "min", "hr", "day", "wk", "mo", "yr", "dec");
        }

        $lengths         = array("60","60","24","7","4.35","12","10");

        $now             = time();

        // check if unix timestamp
        if ((string)(int)$date == $date) {
            $unix_date = $date;
        } else {
            $unix_date = strtotime($date);
        }

        // check validity of date
        if (empty($unix_date)) {
            return "Bad date";
        }

        // is it future date or past date
        if ($now > $unix_date) {
            $difference     = $now - $unix_date;
            $tense         = "ago";

        } else {
            $difference     = $unix_date - $now;
            $tense         = "from now";
        }

        for ($j = 0; $difference >= $lengths[$j] && $j < count($lengths)-1; $j++) {
            $difference /= $lengths[$j];
        }

        $difference = round($difference);

        if ($difference != 1) {
            $periods[$j].= "s";
        }

        return "$difference $periods[$j] {$tense}";
    }

    public function absoluteUrlFilter($string)
    {
        $url = $this->grav['uri']->base();
        $string = preg_replace('/((?:href|src) *= *[\'"](?!(http|ftp)))/i', "$1$url", $string);
        return $string;

    }

    public function markdownFilter($string)
    {
        $page = $this->grav['page'];
        $defaults = $this->grav['config']->get('system.pages.markdown');

        // Initialize the preferred variant of Parsedown
        if ($defaults['extra']) {
            $parsedown = new ParsedownExtra($page, $defaults);
        } else {
            $parsedown = new Parsedown($page, $defaults);
        }

        $string = $parsedown->text($string);

        return $string;
    }

    public function startsWithFilter($needle, $haystack)
    {
        return Utils::startsWith($needle, $haystack);
    }

    public function endsWithFilter($needle, $haystack)
    {
        return Utils::endsWith($needle, $haystack);
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
     * @example {{ url('theme://images/logo.png')|default('http://www.placehold.it/150x100/f4f4f4') }}
     *
     * @param  string $input    Resource to be located.
     * @param  bool $domain     True to include domain name.
     * @return string|null      Returns url to the resource or null if resource was not found.
     */
    public function urlFunc($input, $domain = false)
    {
        if (!trim((string) $input)) {
            return false;
        }

        if (strpos((string) $input, '://')) {
            /** @var UniformResourceLocator $locator */
            $locator = $this->grav['locator'];

            // Get relative path to the resource (or false if not found).
            $resource = $locator->findResource((string) $input, false);
        } else {
            $resource = (string) $input;
        }

        /** @var Uri $uri */
        $uri = $this->grav['uri'];

        return $resource ? rtrim($uri->rootUrl($domain), '/') . '/' . $resource : null;
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

    /**
     * Output a Gist
     *
     * @param  string $id
     * @return string
     */
    public function gistFunc($id)
    {
        return '<script src="https://gist.github.com/'.$id.'.js"></script>';
    }

    /**
     * Generate a random string
     *
     * @param int $count
     *
     * @return string
     */
    public function randomStringFunc($count = 5)
    {
        return Utils::generateRandomString($count);
    }
}
