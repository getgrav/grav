<?php
namespace Grav\Common\Twig;

use Grav\Common\Grav;
use Grav\Common\Utils;
use Grav\Common\Markdown\Parsedown;
use Grav\Common\Markdown\ParsedownExtra;
use Grav\Common\Uri;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * The Twig extension adds some filters and functions that are useful for Grav
 *
 * @author  RocketTheme
 * @license MIT
 */
class TwigExtension extends \Twig_Extension
{
    protected $grav;
    protected $debugger;
    protected $config;

    /**
     * TwigExtension constructor.
     */
    public function __construct()
    {
        $this->grav = Grav::instance();
        $this->debugger = isset($this->grav['debugger']) ? $this->grav['debugger'] : null;
        $this->config = $this->grav['config'];
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
     * Register some standard globals
     *
     * @return array
     */
    public function getGlobals()
    {
        return [
            'grav' => $this->grav,
        ];
    }

    /**
     * Return a list of all filters.
     *
     * @return array
     */
    public function getFilters()
    {
        return [
            new \Twig_SimpleFilter('*ize', [$this, 'inflectorFilter']),
            new \Twig_SimpleFilter('absolute_url', [$this, 'absoluteUrlFilter']),
            new \Twig_SimpleFilter('contains', [$this, 'containsFilter']),
            new \Twig_SimpleFilter('defined', [$this, 'definedDefaultFilter']),
            new \Twig_SimpleFilter('ends_with', [$this, 'endsWithFilter']),
            new \Twig_SimpleFilter('fieldName', [$this, 'fieldNameFilter']),
            new \Twig_SimpleFilter('ksort', [$this, 'ksortFilter']),
            new \Twig_SimpleFilter('ltrim', [$this, 'ltrimFilter']),
            new \Twig_SimpleFilter('markdown', [$this, 'markdownFilter']),
            new \Twig_SimpleFilter('md5', [$this, 'md5Filter']),
            new \Twig_SimpleFilter('nicetime', [$this, 'nicetimeFilter']),
            new \Twig_SimpleFilter('randomize', [$this, 'randomizeFilter']),
            new \Twig_SimpleFilter('modulus', [$this, 'modulusFilter']),
            new \Twig_SimpleFilter('rtrim', [$this, 'rtrimFilter']),
            new \Twig_SimpleFilter('pad', [$this, 'padFilter']),
            new \Twig_SimpleFilter('safe_email', [$this, 'safeEmailFilter']),
            new \Twig_SimpleFilter('safe_truncate', ['\Grav\Common\Utils', 'safeTruncate']),
            new \Twig_SimpleFilter('safe_truncate_html', ['\Grav\Common\Utils', 'safeTruncateHTML']),
            new \Twig_SimpleFilter('sort_by_key', [$this, 'sortByKeyFilter']),
            new \Twig_SimpleFilter('starts_with', [$this, 'startsWithFilter']),
            new \Twig_SimpleFilter('t', [$this, 'translate']),
            new \Twig_SimpleFilter('ta', [$this, 'translateArray']),
            new \Twig_SimpleFilter('truncate', ['\Grav\Common\Utils', 'truncate']),
            new \Twig_SimpleFilter('truncate_html', ['\Grav\Common\Utils', 'truncateHTML']),
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
            new \Twig_SimpleFunction('array', [$this, 'arrayFunc']),
            new \Twig_simpleFunction('authorize', [$this, 'authorize']),
            new \Twig_SimpleFunction('debug', [$this, 'dump'], ['needs_context' => true, 'needs_environment' => true]),
            new \Twig_SimpleFunction('dump', [$this, 'dump'], ['needs_context' => true, 'needs_environment' => true]),
            new \Twig_SimpleFunction('evaluate', [$this, 'evaluateFunc']),
            new \Twig_SimpleFunction('gist', [$this, 'gistFunc']),
            new \Twig_SimpleFunction('nonce_field', [$this, 'nonceFieldFunc']),
            new \Twig_simpleFunction('random_string', [$this, 'randomStringFunc']),
            new \Twig_SimpleFunction('repeat', [$this, 'repeatFunc']),
            new \Twig_SimpleFunction('string', [$this, 'stringFunc']),
            new \Twig_simpleFunction('t', [$this, 'translate']),
            new \Twig_simpleFunction('ta', [$this, 'translateArray']),
            new \Twig_SimpleFunction('url', [$this, 'urlFunc']),


        ];
    }

    /**
     * Filters field name by changing dot notation into array notation.
     *
     * @param  string $str
     *
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
     * @param  string $str
     *
     * @return string
     */
    public function safeEmailFilter($str)
    {
        $email = '';
        $str_len = strlen($str);
        for ($i = 0; $i < $str_len; $i++) {
            $email .= "&#" . ord($str[$i]) . ";";
        }

        return $email;
    }

    /**
     * Returns array in a random order.
     *
     * @param  array $original
     * @param  int   $offset Can be used to return only slice of the array.
     *
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
        for ($x = 0; $x < $sizeOf; $x++) {
            if ($x < $offset) {
                $sorted[] = $original[$x];
            } else {
                $sorted[] = array_shift($random);
            }
        }

        return $sorted;
    }

    /**
     * Returns the modulus of an integer
     *
     * @param  int   $number
     * @param  int   $divider
     * @param  array $items array of items to select from to return
     *
     * @return int
     */
    public function modulusFilter($number, $divider, $items = null)
    {
        if (is_string($number)) {
            $number = strlen($number);
        }

        $remainder = $number % $divider;

        if (is_array($items)) {
            if (isset($items[$remainder])) {
                return $items[$remainder];
            } else {
                return $items[0];
            }
        }

        return $remainder;
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
     * @param int    $count
     *
     * @return mixed
     */
    public function inflectorFilter($action, $data, $count = null)
    {
        $action = $action . 'ize';

        $inflector = $this->grav['inflector'];

        if (in_array(
            $action,
            ['titleize', 'camelize', 'underscorize', 'hyphenize', 'humanize', 'ordinalize', 'monthize']
        )) {
            return $inflector->$action($data);
        } elseif (in_array($action, ['pluralize', 'singularize'])) {
            if ($count) {
                return $inflector->$action($data, $count);
            } else {
                return $inflector->$action($data);
            }
        } else {
            return $data;
        }
    }

    /**
     * Return MD5 hash from the input.
     *
     * @param  string $str
     *
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
     *
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
            return $this->grav['language']->translate('NICETIME.NO_DATE_PROVIDED', null, true);
        }

        if ($long_strings) {
            $periods = [
                "NICETIME.SECOND",
                "NICETIME.MINUTE",
                "NICETIME.HOUR",
                "NICETIME.DAY",
                "NICETIME.WEEK",
                "NICETIME.MONTH",
                "NICETIME.YEAR",
                "NICETIME.DECADE"
            ];
        } else {
            $periods = [
                "NICETIME.SEC",
                "NICETIME.MIN",
                "NICETIME.HR",
                "NICETIME.DAY",
                "NICETIME.WK",
                "NICETIME.MO",
                "NICETIME.YR",
                "NICETIME.DEC"
            ];
        }

        $lengths = ["60", "60", "24", "7", "4.35", "12", "10"];

        $now = time();

        // check if unix timestamp
        if ((string)(int)$date == $date) {
            $unix_date = $date;
        } else {
            $unix_date = strtotime($date);
        }

        // check validity of date
        if (empty($unix_date)) {
            return $this->grav['language']->translate('NICETIME.BAD_DATE', null, true);
        }

        // is it future date or past date
        if ($now > $unix_date) {
            $difference = $now - $unix_date;
            $tense = $this->grav['language']->translate('NICETIME.AGO', null, true);

        } else {
            $difference = $unix_date - $now;
            $tense = $this->grav['language']->translate('NICETIME.FROM_NOW', null, true);
        }

        for ($j = 0; $difference >= $lengths[$j] && $j < count($lengths) - 1; $j++) {
            $difference /= $lengths[$j];
        }

        $difference = round($difference);

        if ($difference != 1) {
            $periods[$j] .= '_PLURAL';
        }

        if ($this->grav['language']->getTranslation($this->grav['language']->getLanguage(),
            $periods[$j] . '_MORE_THAN_TWO')
        ) {
            if ($difference > 2) {
                $periods[$j] .= '_MORE_THAN_TWO';
            }
        }

        $periods[$j] = $this->grav['language']->translate($periods[$j], null, true);

        return "$difference $periods[$j] {$tense}";
    }

    /**
     * @param $string
     *
     * @return mixed
     */
    public function absoluteUrlFilter($string)
    {
        $url = $this->grav['uri']->base();
        $string = preg_replace('/((?:href|src) *= *[\'"](?!(http|ftp)))/i', "$1$url", $string);

        return $string;

    }

    /**
     * @param $string
     *
     * @return mixed|string
     */
    public function markdownFilter($string)
    {
        $page = $this->grav['page'];
        $defaults = $this->config->get('system.pages.markdown');

        // Initialize the preferred variant of Parsedown
        if ($defaults['extra']) {
            $parsedown = new ParsedownExtra($page, $defaults);
        } else {
            $parsedown = new Parsedown($page, $defaults);
        }

        $string = $parsedown->text($string);

        return $string;
    }

    /**
     * @param $haystack
     * @param $needle
     *
     * @return bool
     */
    public function startsWithFilter($haystack, $needle)
    {
        return Utils::startsWith($haystack, $needle);
    }

    /**
     * @param $haystack
     * @param $needle
     *
     * @return bool
     */
    public function endsWithFilter($haystack, $needle)
    {
        return Utils::endsWith($haystack, $needle);
    }

    /**
     * @param      $value
     * @param null $default
     *
     * @return null
     */
    public function definedDefaultFilter($value, $default = null)
    {
        if (isset($value)) {
            return $value;
        } else {
            return $default;
        }
    }

    /**
     * @param      $value
     * @param null $chars
     *
     * @return string
     */
    public function rtrimFilter($value, $chars = null)
    {
        return rtrim($value, $chars);
    }

    /**
     * @param      $value
     * @param null $chars
     *
     * @return string
     */
    public function ltrimFilter($value, $chars = null)
    {
        return ltrim($value, $chars);
    }

    /**
     * @return mixed
     */
    public function translate()
    {
        return $this->grav['language']->translate(func_get_args());
    }

    /**
     * @param      $key
     * @param      $index
     * @param null $lang
     *
     * @return mixed
     */
    public function translateArray($key, $index, $lang = null)
    {
        return $this->grav['language']->translateArray($key, $index, $lang);
    }

    /**
     * Repeat given string x times.
     *
     * @param  string $input
     * @param  int    $multiplier
     *
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
     * @param  string $input  Resource to be located.
     * @param  bool   $domain True to include domain name.
     *
     * @return string|null      Returns url to the resource or null if resource was not found.
     */
    public function urlFunc($input, $domain = false)
    {
        if (!trim((string)$input)) {
            return false;
        }

        if ($this->grav['config']->get('system.absolute_urls', false)) {
            $domain = true;
        }


        if (strpos((string)$input, '://')) {
            /** @var UniformResourceLocator $locator */
            $locator = $this->grav['locator'];

            // Get relative path to the resource (or false if not found).
            $resource = $locator->findResource((string)$input, false);
        } else {
            $resource = (string)$input;
        }

        /** @var Uri $uri */
        $uri = $this->grav['uri'];

        return $resource ? rtrim($uri->rootUrl($domain), '/') . '/' . $resource : null;
    }

    /**
     * Evaluate a string
     *
     * @example {{ evaluate('grav.language.getLanguage') }}
     *
     * @param  string $input String to be evaluated
     *
     * @return string           Returns the evaluated string
     */
    public function evaluateFunc($input)
    {
        return $this->grav['twig']->processString("{{ $input }}");
    }

    /**
     * Based on Twig_Extension_Debug / twig_var_dump
     * (c) 2011 Fabien Potencier
     *
     * @param \Twig_Environment $env
     * @param                   $context
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
     *
     * @return string
     */
    public function gistFunc($id)
    {
        return '<script src="https://gist.github.com/' . $id . '.js"></script>';
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

    /**
     * Pad a string to a certain length with another string
     *
     * @param        $input
     * @param        $pad_length
     * @param string $pad_string
     * @param int    $pad_type
     *
     * @return string
     */
    public static function padFilter($input, $pad_length, $pad_string = " ", $pad_type = STR_PAD_RIGHT)
    {
        return str_pad($input, (int)$pad_length, $pad_string, $pad_type);
    }


    /**
     * Cast a value to array
     *
     * @param $value
     *
     * @return array
     */
    public function arrayFunc($value)
    {
        return (array)$value;
    }

    /**
     * Returns a string from a value. If the value is array, return it json encoded
     *
     * @param $value
     *
     * @return string
     */
    public function stringFunc($value)
    {
        if (is_array($value)) { //format the array as a string
            return json_encode($value);
        } else {
            return $value;
        }
    }

    /**
     * Translate a string
     *
     * @return string
     */
    public function translateFunc()
    {
        return $this->grav['language']->translate(func_get_args());
    }

    /**
     * Authorize an action. Returns true if the user is logged in and has the right to execute $action.
     *
     * @param string $action
     *
     * @return bool
     */
    public function authorize($action)
    {
        if (!$this->grav['user']->authenticated) {
            return false;
        }

        $action = (array)$action;

        foreach ($action as $a) {
            if ($this->grav['user']->authorize($a)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Used to add a nonce to a form. Call {{ nonce_field('action') }} specifying a string representing the action.
     *
     * For maximum protection, ensure that the string representing the action is as specific as possible
     *
     * @param string $action         the action
     * @param string $nonceParamName a custom nonce param name
     *
     * @return string the nonce input field
     */
    public function nonceFieldFunc($action, $nonceParamName = 'nonce')
    {
        $string = '<input type="hidden" id="' . $nonceParamName . '" name="' . $nonceParamName . '" value="' . Utils::getNonce($action) . '" />';

        return $string;
    }
}
