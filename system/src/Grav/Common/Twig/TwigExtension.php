<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig;

use Cron\CronExpression;
use Grav\Common\Config\Config;
use Grav\Common\Data\Data;
use Grav\Common\Debugger;
use Grav\Common\Grav;
use Grav\Common\Language\Language;
use Grav\Common\Page\Collection;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Media;
use Grav\Common\Scheduler\Cron;
use Grav\Common\Security;
use Grav\Common\Twig\TokenParser\TwigTokenParserRender;
use Grav\Common\Twig\TokenParser\TwigTokenParserScript;
use Grav\Common\Twig\TokenParser\TwigTokenParserStyle;
use Grav\Common\Twig\TokenParser\TwigTokenParserSwitch;
use Grav\Common\Twig\TokenParser\TwigTokenParserThrow;
use Grav\Common\Twig\TokenParser\TwigTokenParserTryCatch;
use Grav\Common\Twig\TokenParser\TwigTokenParserMarkdown;
use Grav\Common\Twig\TokenParser\TwigTokenParserCache;
use Grav\Common\User\Interfaces\UserInterface;
use Grav\Common\Utils;
use Grav\Common\Yaml;
use Grav\Common\Helpers\Base32;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

class TwigExtension extends \Twig_Extension implements \Twig_Extension_GlobalsInterface
{
    /** @var Grav */
    protected $grav;

    /** @var Debugger */
    protected $debugger;

    /** @var Config */
    protected $config;

    /**
     * TwigExtension constructor.
     */
    public function __construct()
    {
        $this->grav     = Grav::instance();
        $this->debugger = $this->grav['debugger'] ?? null;
        $this->config   = $this->grav['config'];
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
            new \Twig_SimpleFilter('chunk_split', [$this, 'chunkSplitFilter']),
            new \Twig_SimpleFilter('nicenumber', [$this, 'niceNumberFunc']),
            new \Twig_SimpleFilter('nicefilesize', [$this, 'niceFilesizeFunc']),
            new \Twig_SimpleFilter('nicetime', [$this, 'nicetimeFunc']),
            new \Twig_SimpleFilter('defined', [$this, 'definedDefaultFilter']),
            new \Twig_SimpleFilter('ends_with', [$this, 'endsWithFilter']),
            new \Twig_SimpleFilter('fieldName', [$this, 'fieldNameFilter']),
            new \Twig_SimpleFilter('ksort', [$this, 'ksortFilter']),
            new \Twig_SimpleFilter('ltrim', [$this, 'ltrimFilter']),
            new \Twig_SimpleFilter('markdown', [$this, 'markdownFunction'], ['needs_context' => true, 'is_safe' => ['html']]),
            new \Twig_SimpleFilter('md5', [$this, 'md5Filter']),
            new \Twig_SimpleFilter('base32_encode', [$this, 'base32EncodeFilter']),
            new \Twig_SimpleFilter('base32_decode', [$this, 'base32DecodeFilter']),
            new \Twig_SimpleFilter('base64_encode', [$this, 'base64EncodeFilter']),
            new \Twig_SimpleFilter('base64_decode', [$this, 'base64DecodeFilter']),
            new \Twig_SimpleFilter('randomize', [$this, 'randomizeFilter']),
            new \Twig_SimpleFilter('modulus', [$this, 'modulusFilter']),
            new \Twig_SimpleFilter('rtrim', [$this, 'rtrimFilter']),
            new \Twig_SimpleFilter('pad', [$this, 'padFilter']),
            new \Twig_SimpleFilter('regex_replace', [$this, 'regexReplace']),
            new \Twig_SimpleFilter('safe_email', [$this, 'safeEmailFilter']),
            new \Twig_SimpleFilter('safe_truncate', ['\Grav\Common\Utils', 'safeTruncate']),
            new \Twig_SimpleFilter('safe_truncate_html', ['\Grav\Common\Utils', 'safeTruncateHTML']),
            new \Twig_SimpleFilter('sort_by_key', [$this, 'sortByKeyFilter']),
            new \Twig_SimpleFilter('starts_with', [$this, 'startsWithFilter']),
            new \Twig_SimpleFilter('truncate', ['\Grav\Common\Utils', 'truncate']),
            new \Twig_SimpleFilter('truncate_html', ['\Grav\Common\Utils', 'truncateHTML']),
            new \Twig_SimpleFilter('json_decode', [$this, 'jsonDecodeFilter']),
            new \Twig_SimpleFilter('array_unique', 'array_unique'),
            new \Twig_SimpleFilter('basename', 'basename'),
            new \Twig_SimpleFilter('dirname', 'dirname'),
            new \Twig_SimpleFilter('print_r', 'print_r'),
            new \Twig_SimpleFilter('yaml_encode', [$this, 'yamlEncodeFilter']),
            new \Twig_SimpleFilter('yaml_decode', [$this, 'yamlDecodeFilter']),
            new \Twig_SimpleFilter('nicecron', [$this, 'niceCronFilter']),

            // Translations
            new \Twig_SimpleFilter('t', [$this, 'translate'], ['needs_environment' => true]),
            new \Twig_SimpleFilter('tl', [$this, 'translateLanguage']),
            new \Twig_SimpleFilter('ta', [$this, 'translateArray']),

            // Casting values
            new \Twig_SimpleFilter('string', [$this, 'stringFilter']),
            new \Twig_SimpleFilter('int', [$this, 'intFilter'], ['is_safe' => ['all']]),
            new \Twig_SimpleFilter('bool', [$this, 'boolFilter']),
            new \Twig_SimpleFilter('float', [$this, 'floatFilter'], ['is_safe' => ['all']]),
            new \Twig_SimpleFilter('array', [$this, 'arrayFilter']),

            // Object Types
            new \Twig_SimpleFilter('get_type', [$this, 'getTypeFunc']),
            new \Twig_SimpleFilter('of_type', [$this, 'ofTypeFunc'])
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
            new \Twig_SimpleFunction('array', [$this, 'arrayFilter']),
            new \Twig_SimpleFunction('array_key_value', [$this, 'arrayKeyValueFunc']),
            new \Twig_SimpleFunction('array_key_exists', 'array_key_exists'),
            new \Twig_SimpleFunction('array_unique', 'array_unique'),
            new \Twig_SimpleFunction('array_intersect', [$this, 'arrayIntersectFunc']),
            new \Twig_SimpleFunction('authorize', [$this, 'authorize']),
            new \Twig_SimpleFunction('debug', [$this, 'dump'], ['needs_context' => true, 'needs_environment' => true]),
            new \Twig_SimpleFunction('dump', [$this, 'dump'], ['needs_context' => true, 'needs_environment' => true]),
            new \Twig_SimpleFunction('vardump', [$this, 'vardumpFunc']),
            new \Twig_SimpleFunction('print_r', 'print_r'),
            new \Twig_SimpleFunction('http_response_code', 'http_response_code'),
            new \Twig_SimpleFunction('evaluate', [$this, 'evaluateStringFunc'], ['needs_context' => true]),
            new \Twig_SimpleFunction('evaluate_twig', [$this, 'evaluateTwigFunc'], ['needs_context' => true]),
            new \Twig_SimpleFunction('gist', [$this, 'gistFunc']),
            new \Twig_SimpleFunction('nonce_field', [$this, 'nonceFieldFunc']),
            new \Twig_SimpleFunction('pathinfo', 'pathinfo'),
            new \Twig_SimpleFunction('random_string', [$this, 'randomStringFunc']),
            new \Twig_SimpleFunction('repeat', [$this, 'repeatFunc']),
            new \Twig_SimpleFunction('regex_replace', [$this, 'regexReplace']),
            new \Twig_SimpleFunction('regex_filter', [$this, 'regexFilter']),
            new \Twig_SimpleFunction('string', [$this, 'stringFunc']),
            new \Twig_SimpleFunction('url', [$this, 'urlFunc']),
            new \Twig_SimpleFunction('json_decode', [$this, 'jsonDecodeFilter']),
            new \Twig_SimpleFunction('get_cookie', [$this, 'getCookie']),
            new \Twig_SimpleFunction('redirect_me', [$this, 'redirectFunc']),
            new \Twig_SimpleFunction('range', [$this, 'rangeFunc']),
            new \Twig_SimpleFunction('isajaxrequest', [$this, 'isAjaxFunc']),
            new \Twig_SimpleFunction('exif', [$this, 'exifFunc']),
            new \Twig_SimpleFunction('media_directory', [$this, 'mediaDirFunc']),
            new \Twig_SimpleFunction('body_class', [$this, 'bodyClassFunc']),
            new \Twig_SimpleFunction('theme_var', [$this, 'themeVarFunc'], ['needs_context' => true]),
            new \Twig_SimpleFunction('header_var', [$this, 'pageHeaderVarFunc'], ['needs_context' => true]),
            new \Twig_SimpleFunction('read_file', [$this, 'readFileFunc']),
            new \Twig_SimpleFunction('nicenumber', [$this, 'niceNumberFunc']),
            new \Twig_SimpleFunction('nicefilesize', [$this, 'niceFilesizeFunc']),
            new \Twig_SimpleFunction('nicetime', [$this, 'nicetimeFunc']),
            new \Twig_SimpleFunction('cron', [$this, 'cronFunc']),
            new \Twig_SimpleFunction('svg_image', [$this, 'svgImageFunction']),
            new \Twig_SimpleFunction('xss', [$this, 'xssFunc']),


            // Translations
            new \Twig_SimpleFunction('t', [$this, 'translate'], ['needs_environment' => true]),
            new \Twig_SimpleFunction('tl', [$this, 'translateLanguage']),
            new \Twig_SimpleFunction('ta', [$this, 'translateArray']),

            // Object Types
            new \Twig_SimpleFunction('get_type', [$this, 'getTypeFunc']),
            new \Twig_SimpleFunction('of_type', [$this, 'ofTypeFunc'])
        ];
    }

    /**
     * @return array
     */
    public function getTokenParsers()
    {
        return [
            new TwigTokenParserRender(),
            new TwigTokenParserThrow(),
            new TwigTokenParserTryCatch(),
            new TwigTokenParserScript(),
            new TwigTokenParserStyle(),
            new TwigTokenParserMarkdown(),
            new TwigTokenParserSwitch(),
            new TwigTokenParserCache(),
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
        $path = explode('.', rtrim($str, '.'));

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
        $email   = '';
        for ($i = 0, $len = strlen($str); $i < $len; $i++) {
            $j = random_int(0, 1);

            $email .= $j === 0 ? '&#' . ord($str[$i]) . ';' : $str[$i];
        }

        return str_replace('@', '&#64;', $email);
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
        if (!\is_array($original)) {
            return $original;
        }

        if ($original instanceof \Traversable) {
            $original = iterator_to_array($original, false);
        }

        $sorted = [];
        $random = array_slice($original, $offset);
        shuffle($random);

        $sizeOf = \count($original);
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
     * @param  string|int   $number
     * @param  int          $divider
     * @param  array        $items array of items to select from to return
     *
     * @return int
     */
    public function modulusFilter($number, $divider, $items = null)
    {
        if (\is_string($number)) {
            $number = strlen($number);
        }

        $remainder = $number % $divider;

        if (\is_array($items)) {
            return $items[$remainder] ?? $items[0];
        }

        return $remainder;
    }

    /**
     * Inflector supports following notations:
     *
     * `{{ 'person'|pluralize }} => people`
     * `{{ 'shoes'|singularize }} => shoe`
     * `{{ 'welcome page'|titleize }} => "Welcome Page"`
     * `{{ 'send_email'|camelize }} => SendEmail`
     * `{{ 'CamelCased'|underscorize }} => camel_cased`
     * `{{ 'Something Text'|hyphenize }} => something-text`
     * `{{ 'something_text_to_read'|humanize }} => "Something text to read"`
     * `{{ '181'|monthize }} => 5`
     * `{{ '10'|ordinalize }} => 10th`
     *
     * @param string $action
     * @param string $data
     * @param int    $count
     *
     * @return string
     */
    public function inflectorFilter($action, $data, $count = null)
    {
        $action .= 'ize';

        $inflector = $this->grav['inflector'];

        if (\in_array(
            $action,
            ['titleize', 'camelize', 'underscorize', 'hyphenize', 'humanize', 'ordinalize', 'monthize'],
            true
        )) {
            return $inflector->{$action}($data);
        }

        if (\in_array($action, ['pluralize', 'singularize'], true)) {
            return $count ? $inflector->{$action}($data, $count) : $inflector->{$action}($data);
        }

        return $data;
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
     * Return Base32 encoded string
     *
     * @param string $str
     * @return string
     */
    public function base32EncodeFilter($str)
    {
        return Base32::encode($str);
    }

    /**
     * Return Base32 decoded string
     *
     * @param string $str
     * @return bool|string
     */
    public function base32DecodeFilter($str)
    {
        return Base32::decode($str);
    }

    /**
     * Return Base64 encoded string
     *
     * @param string $str
     * @return string
     */
    public function base64EncodeFilter($str)
    {
        return base64_encode($str);
    }

    /**
     * Return Base64 decoded string
     *
     * @param string $str
     * @return bool|string
     */
    public function base64DecodeFilter($str)
    {
        return base64_decode($str);
    }


    /**
     * Sorts a collection by key
     *
     * @param  array    $input
     * @param  string   $filter
     * @param  int      $direction
     * @param  int      $sort_flags
     *
     * @return array
     */
    public function sortByKeyFilter($input, $filter, $direction = SORT_ASC, $sort_flags = SORT_REGULAR)
    {
        return Utils::sortArrayByKey($input, $filter, $direction, $sort_flags);
    }

    /**
     * Return ksorted collection.
     *
     * @param  array $array
     *
     * @return array
     */
    public function ksortFilter($array)
    {
        if (null === $array) {
            $array = [];
        }
        ksort($array);

        return $array;
    }

    /**
     * Wrapper for chunk_split() function
     *
     * @param string $value
     * @param int $chars
     * @param string $split
     * @return string
     */
    public function chunkSplitFilter($value, $chars, $split = '-')
    {
        return chunk_split($value, $chars, $split);
    }

    /**
     * determine if a string contains another
     *
     * @param string $haystack
     * @param string $needle
     *
     * @return bool
     */
    public function containsFilter($haystack, $needle)
    {
        if (empty($needle)) {
            return $haystack;
        }

        return (strpos($haystack, (string) $needle) !== false);
    }

    /**
     * Gets a human readable output for cron syntax
     *
     * @param $at
     * @return string
     */
    public function niceCronFilter($at)
    {
        $cron = new Cron($at);
        return $cron->getText('en');
    }

    /**
     * Get Cron object for a crontab 'at' format
     *
     * @param string $at
     * @return CronExpression
     */
    public function cronFunc($at)
    {
        return CronExpression::factory($at);
    }

    /**
     * displays a facebook style 'time ago' formatted date/time
     *
     * @param string $date
     * @param bool $long_strings
     *
     * @param bool $show_tense
     * @return bool
     */
    public function nicetimeFunc($date, $long_strings = true, $show_tense = true)
    {
        if (empty($date)) {
            return $this->grav['language']->translate('GRAV.NICETIME.NO_DATE_PROVIDED', null, true);
        }

        if ($long_strings) {
            $periods = [
                'NICETIME.SECOND',
                'NICETIME.MINUTE',
                'NICETIME.HOUR',
                'NICETIME.DAY',
                'NICETIME.WEEK',
                'NICETIME.MONTH',
                'NICETIME.YEAR',
                'NICETIME.DECADE'
            ];
        } else {
            $periods = [
                'NICETIME.SEC',
                'NICETIME.MIN',
                'NICETIME.HR',
                'NICETIME.DAY',
                'NICETIME.WK',
                'NICETIME.MO',
                'NICETIME.YR',
                'NICETIME.DEC'
            ];
        }

        $lengths = ['60', '60', '24', '7', '4.35', '12', '10'];

        $now = time();

        // check if unix timestamp
        if ((string)(int)$date === (string)$date) {
            $unix_date = $date;
        } else {
            $unix_date = strtotime($date);
        }

        // check validity of date
        if (empty($unix_date)) {
            return $this->grav['language']->translate('GRAV.NICETIME.BAD_DATE', null, true);
        }

        // is it future date or past date
        if ($now > $unix_date) {
            $difference = $now - $unix_date;
            $tense      = $this->grav['language']->translate('GRAV.NICETIME.AGO', null, true);

        } elseif ($now == $unix_date) {
            $difference = $now - $unix_date;
            $tense      = $this->grav['language']->translate('GRAV.NICETIME.JUST_NOW', null, false);

        } else {
            $difference = $unix_date - $now;
            $tense      = $this->grav['language']->translate('GRAV.NICETIME.FROM_NOW', null, true);
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

        $periods[$j] = $this->grav['language']->translate('GRAV.'.$periods[$j], null, true);

        if ($now == $unix_date) {
            return $tense;
        }

        $time = "{$difference} {$periods[$j]}";
        $time .= $show_tense ? " {$tense}" : '';

        return $time;
    }

    /**
     * Allow quick check of a string for XSS Vulnerabilities
     *
     * @param string|array $data
     * @return bool|string|array
     */
    public function xssFunc($data)
    {
        if (!\is_array($data)) {
            return Security::detectXss($data);
        }

        $results = Security::detectXssFromArray($data);
        $results_parts = array_map(function($value, $key) {
            return $key.': \''.$value . '\'';
        }, array_values($results), array_keys($results));

        return implode(', ', $results_parts);
    }

    /**
     * @param string $string
     *
     * @return mixed
     */
    public function absoluteUrlFilter($string)
    {
        $url    = $this->grav['uri']->base();
        $string = preg_replace('/((?:href|src) *= *[\'"](?!(http|ftp)))/i', "$1$url", $string);

        return $string;

    }

    /**
     * @param string $string
     *
     * @param array $context
     * @param bool $block  Block or Line processing
     * @return mixed|string
     */
    public function markdownFunction($context, $string, $block = true)
    {
        $page = $context['page'] ?? null;
        return Utils::processMarkdown($string, $block, $page);
    }

    /**
     * @param string $haystack
     * @param string $needle
     *
     * @return bool
     */
    public function startsWithFilter($haystack, $needle)
    {
        return Utils::startsWith($haystack, $needle);
    }

    /**
     * @param string $haystack
     * @param string $needle
     *
     * @return bool
     */
    public function endsWithFilter($haystack, $needle)
    {
        return Utils::endsWith($haystack, $needle);
    }

    /**
     * @param mixed $value
     * @param null $default
     *
     * @return null
     */
    public function definedDefaultFilter($value, $default = null)
    {
        return null !== $value ? $value : $default;
        }

    /**
     * @param string $value
     * @param null $chars
     *
     * @return string
     */
    public function rtrimFilter($value, $chars = null)
    {
        return rtrim($value, $chars);
    }

    /**
     * @param string $value
     * @param null $chars
     *
     * @return string
     */
    public function ltrimFilter($value, $chars = null)
    {
        return ltrim($value, $chars);
    }

    /**
     * Casts input to string.
     *
     * @param mixed $input
     * @return string
     */
    public function stringFilter($input)
    {
        return (string) $input;
    }


    /**
     * Casts input to int.
     *
     * @param mixed $input
     * @return int
     */
    public function intFilter($input)
    {
        return (int) $input;
    }

    /**
     * Casts input to bool.
     *
     * @param mixed $input
     * @return bool
     */
    public function boolFilter($input)
    {
        return (bool) $input;
    }

    /**
     * Casts input to float.
     *
     * @param mixed $input
     * @return float
     */
    public function floatFilter($input)
    {
        return (float) $input;
    }

    /**
     * Casts input to array.
     *
     * @param mixed $input
     * @return array
     */
    public function arrayFilter($input)
    {
        return (array) $input;
    }

    /**
     * @return string
     */
    public function translate(\Twig_Environment $twig)
    {
        // shift off the environment
        $args = func_get_args();
        array_shift($args);

        // If admin and tu filter provided, use it
        if (isset($this->grav['admin'])) {
            $numargs = count($args);
            $lang = null;

            if (($numargs === 3 && is_array($args[1])) || ($numargs === 2 && !is_array($args[1]))) {
                $lang = array_pop($args);
            } elseif ($numargs === 2 && is_array($args[1])) {
                $subs = array_pop($args);
                $args = array_merge($args, $subs);
            }

            return $this->grav['admin']->translate($args, $lang);
        }

        // else use the default grav translate functionality
        return $this->grav['language']->translate($args);
    }

    /**
     * Translate Strings
     *
     * @param string|array $args
     * @param array|null $languages
     * @param bool $array_support
     * @param bool $html_out
     * @return string
     */
    public function translateLanguage($args, array $languages = null, $array_support = false, $html_out = false)
    {
        /** @var Language $language */
        $language = $this->grav['language'];

        return $language->translate($args, $languages, $array_support, $html_out);
    }

    /**
     * @param string $key
     * @param string $index
     * @param array|null $lang
     * @return string
     */
    public function translateArray($key, $index, $lang = null)
    {
        /** @var Language $language */
        $language = $this->grav['language'];

        return $language->translateArray($key, $index, $lang);
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
        return Utils::url($input, $domain);
        }

    /**
     * This function will evaluate Twig $twig through the $environment, and return its results.
     *
     * @param array $context
     * @param string $twig
     * @return mixed
     */
    public function evaluateTwigFunc($context, $twig ) {

        $loader = new \Twig_Loader_Filesystem('.');
        $env = new \Twig_Environment($loader);

        $template = $env->createTemplate($twig);

        return $template->render($context);
    }

    /**
     * This function will evaluate a $string through the $environment, and return its results.
     *
     * @param array $context
     * @param string $string
     * @return mixed
     */
    public function evaluateStringFunc($context, $string )
    {
        return $this->evaluateTwigFunc($context, "{{ $string }}");
    }


    /**
     * Based on Twig_Extension_Debug / twig_var_dump
     * (c) 2011 Fabien Potencier
     *
     * @param \Twig_Environment $env
     * @param string $context
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
     * @param  string|bool $file
     *
     * @return string
     */
    public function gistFunc($id, $file = false)
    {
        $url = 'https://gist.github.com/' . $id . '.js';
        if ($file) {
            $url .= '?file=' . $file;
        }
        return '<script src="' . $url . '"></script>';
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
     * @param string $input
     * @param int    $pad_length
     * @param string $pad_string
     * @param int    $pad_type
     *
     * @return string
     */
    public static function padFilter($input, $pad_length, $pad_string = ' ', $pad_type = STR_PAD_RIGHT)
    {
        return str_pad($input, (int)$pad_length, $pad_string, $pad_type);
    }

    /**
     * Workaround for twig associative array initialization
     * Returns a key => val array
     *
     * @param string $key           key of item
     * @param string $val           value of item
     * @param array  $current_array optional array to add to
     *
     * @return array
     */
    public function arrayKeyValueFunc($key, $val, $current_array = null)
    {
        if (empty($current_array)) {
            return array($key => $val);
        }

        $current_array[$key] = $val;

        return $current_array;
    }

    /**
     * Wrapper for array_intersect() method
     *
     * @param array $array1
     * @param array $array2
     * @return array
     */
    public function arrayIntersectFunc($array1, $array2)
    {
        if ($array1 instanceof Collection && $array2 instanceof Collection) {
            return $array1->intersect($array2);
        }

        return array_intersect($array1, $array2);
    }

    /**
     * Returns a string from a value. If the value is array, return it json encoded
     *
     * @param array|string $value
     *
     * @return string
     */
    public function stringFunc($value)
    {
        if (is_array($value)) { //format the array as a string
            return json_encode($value);
        }

        return $value;
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
     * Authorize an action. Returns true if the user is logged in and
     * has the right to execute $action.
     *
     * @param  string|array $action An action or a list of actions. Each
     *                              entry can be a string like 'group.action'
     *                              or without dot notation an associative
     *                              array.
     * @return bool                 Returns TRUE if the user is authorized to
     *                              perform the action, FALSE otherwise.
     */
    public function authorize($action)
    {
        /** @var UserInterface|null $user */
        $user = $this->grav['user'] ?? null;

        if (!$user || !$user->authenticated || (isset($user->authorized) && !$user->authorized)) {
            return false;
        }

        $action = (array) $action;
        foreach ($action as $key => $perms) {
            $prefix = is_int($key) ? '' : $key . '.';
            $perms = $prefix ? (array) $perms : [$perms => true];
            foreach ($perms as $action2 => $authenticated) {
                if ($user->authorize($prefix . $action2)) {
                    return $authenticated;
                }
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
        $string = '<input type="hidden" name="' . $nonceParamName . '" value="' . Utils::getNonce($action) . '" />';

        return $string;
    }

    /**
     * Decodes string from JSON.
     *
     * @param  string  $str
     * @param  bool  $assoc
     * @param  int $depth
     * @param  int $options
     * @return array
     */
    public function jsonDecodeFilter($str, $assoc = false, $depth = 512, $options = 0)
    {
        return json_decode(html_entity_decode($str, ENT_COMPAT | ENT_HTML401, 'UTF-8'), $assoc, $depth, $options);
    }

    /**
     * Used to retrieve a cookie value
     *
     * @param string $key     The cookie name to retrieve
     *
     * @return mixed
     */
    public function getCookie($key)
    {
        return filter_input(INPUT_COOKIE, $key, FILTER_SANITIZE_STRING);
    }

    /**
     * Twig wrapper for PHP's preg_replace method
     *
     * @param mixed $subject the content to perform the replacement on
     * @param mixed $pattern the regex pattern to use for matches
     * @param mixed $replace the replacement value either as a string or an array of replacements
     * @param int   $limit   the maximum possible replacements for each pattern in each subject
     *
     * @return string|string[]|null the resulting content
     */
    public function regexReplace($subject, $pattern, $replace, $limit = -1)
    {
        return preg_replace($pattern, $replace, $subject, $limit);
    }

    /**
     * Twig wrapper for PHP's preg_grep method
     *
     * @param array $array
     * @param string $regex
     * @param int $flags
     * @return array
     */
    public function regexFilter($array, $regex, $flags = 0)
    {
        return preg_grep($regex, $array, $flags);
    }

    /**
     * redirect browser from twig
     *
     * @param string $url          the url to redirect to
     * @param int $statusCode      statusCode, default 303
     */
    public function redirectFunc($url, $statusCode = 303)
    {
        header('Location: ' . $url, true, $statusCode);
        exit();
    }

    /**
     * Generates an array containing a range of elements, optionally stepped
     *
     * @param int $start      Minimum number, default 0
     * @param int $end        Maximum number, default `getrandmax()`
     * @param int $step       Increment between elements in the sequence, default 1
     *
     * @return array
     */
    public function rangeFunc($start = 0, $end = 100, $step = 1)
    {
        return range($start, $end, $step);
    }

    /**
     * Check if HTTP_X_REQUESTED_WITH has been set to xmlhttprequest,
     * in which case we may unsafely assume ajax. Non critical use only.
     *
     * @return bool True if HTTP_X_REQUESTED_WITH exists and has been set to xmlhttprequest
     */
    public function isAjaxFunc()
    {
        return (
            !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    }

    /**
     * Get the Exif data for a file
     *
     * @param string $image
     * @param bool $raw
     * @return mixed
     */
    public function exifFunc($image, $raw = false)
    {
        if (isset($this->grav['exif'])) {
            /** @var UniformResourceLocator $locator */
            $locator = $this->grav['locator'];

            if ($locator->isStream($image)) {
                $image = $locator->findResource($image);
            }

            $exif_reader = $this->grav['exif']->getReader();

            if ($image && file_exists($image) && $this->config->get('system.media.auto_metadata_exif') && $exif_reader) {

                $exif_data = $exif_reader->read($image);

                if ($exif_data) {
                    if ($raw) {
                        return $exif_data->getRawData();
                    }

                    return $exif_data->getData();
                }
            }
        }

        return null;
    }

    /**
     * Simple function to read a file based on a filepath and output it
     *
     * @param string $filepath
     * @return bool|string
     */
    public function readFileFunc($filepath)
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];

        if ($locator->isStream($filepath)) {
            $filepath = $locator->findResource($filepath);
        }

        if ($filepath && file_exists($filepath)) {
            return file_get_contents($filepath);
        }

        return false;
    }

    /**
     * Process a folder as Media and return a media object
     *
     * @param string $media_dir
     * @return Media|null
     */
    public function mediaDirFunc($media_dir)
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];

        if ($locator->isStream($media_dir)) {
            $media_dir = $locator->findResource($media_dir);
        }

        if ($media_dir && file_exists($media_dir)) {
            return new Media($media_dir);
        }

        return null;
    }

    /**
     * Dump a variable to the browser
     *
     * @param mixed $var
     */
    public function vardumpFunc($var)
    {
        var_dump($var);
    }

    /**
     * Returns a nicer more readable filesize based on bytes
     *
     * @param int $bytes
     * @return string
     */
    public function niceFilesizeFunc($bytes)
    {
        return Utils::prettySize($bytes);
    }

    /**
     * Returns a nicer more readable number
     *
     * @param int|float|string $n
     * @return string|bool
     */
    public function niceNumberFunc($n)
    {
        if (!\is_float($n) && !\is_int($n)) {
            if (!\is_string($n) || $n === '') {
                return false;
            }

            // Strip any thousand formatting and find the first number.
            $list = array_filter(preg_split("/\D+/", str_replace(',', '', $n)));
            $n = reset($list);

            if (!\is_numeric($n)) {
                return false;
            }

            $n = (float)$n;
        }

        // now filter it;
        if ($n > 1000000000000) {
            return round($n/1000000000000, 2).' t';
        }
        if ($n > 1000000000) {
            return round($n/1000000000, 2).' b';
        }
        if ($n > 1000000) {
            return round($n/1000000, 2).' m';
        }
        if ($n > 1000) {
            return round($n/1000, 2).' k';
        }

        return number_format($n);
    }

    /**
     * Get a theme variable
     * Will try to get the variable for the current page, if not found, it tries it's parent page on up to root.
     * If still not found, will use the theme's configuration value,
     * If still not found, will use the $default value passed in
     *
     * @param $context      Twig Context
     * @param string $var variable to be found (using dot notation)
     * @param null $default the default value to be used as last resort
     * @param null $page an optional page to use for the current page
     * @param bool $exists toggle to simply return the page where the variable is set, else null
     * @return string
     */
    public function themeVarFunc($context, $var, $default = null, $page = null, $exists = false)
    {
        $page = $page ?? $context['page'] ?? Grav::instance()['page'] ?? null;

        // Try to find var in the page headers
        if ($page instanceof PageInterface && $page->exists()) {
            // Loop over pages and look for header vars
            while ($page && !$page->root()) {
                $header = new Data((array)$page->header());
                $value = $header->get($var);
                if (isset($value)) {
                    if ($exists) {
                        return $page;
                    } else {
                        return $value;
                    }

                }
                $page = $page->parent();
            }
        }

        if ($exists) {
            return false;
        } else {
            return Grav::instance()['config']->get('theme.' . $var, $default);
        }
    }

    /**
     * Look for a page header variable in an array of pages working its way through until a value is found
     *
     * @param $context
     * @param string $var the variable to look for in the page header
     * @param string|string[]|null $pages array of pages to check (current page upwards if not null)
     * @param bool $exists if true, return the page where the var is found, not the value
     * @return mixed
     * @deprecated 1.7 Use themeVarFunc() instead
     */
    public function pageHeaderVarFunc($context, $var, $pages = null)
    {
        if (is_array($pages)) {
            $page = array_shift($pages);
        } else {
            $page = null;
        }
        return $this->themeVarFunc($context, $var, null, $page);
    }

    /**
     * takes an array of classes, and if they are not set on body_classes
     * look to see if they are set in theme config
     *
     * @param string|string[] $classes
     * @return string
     */
    public function bodyClassFunc($classes)
    {

        $header = $this->grav['page']->header();
        $body_classes = $header->body_classes ?? '';

        foreach ((array)$classes as $class) {
            if (!empty($body_classes) && Utils::contains($body_classes, $class)) {
                continue;
            }

            $val = $this->config->get('theme.' . $class, false) ? $class : false;
            $body_classes .= $val ? ' ' . $val : '';
        }

        return $body_classes;
    }

    /**
     * Dump/Encode data into YAML format
     *
     * @param array $data
     * @param int $inline integer number of levels of inline syntax
     * @return string
     */
    public function yamlEncodeFilter($data, $inline = 10)
    {
        return Yaml::dump($data, $inline);
    }

    /**
     * Decode/Parse data from YAML format
     *
     * @param string $data
     * @return array
     */
    public function yamlDecodeFilter($data)
    {
        return Yaml::parse($data);
    }

    /**
     * Function/Filter to return the type of variable
     *
     * @param mixed $var
     * @return string
     */
    public function getTypeFunc($var)
    {
        return gettype($var);
    }

    /**
     * Function/Filter to test type of variable
     *
     * @param mixed $var
     * @param string|null $typeTest
     * @param string|null $className
     * @return bool
     */
    public function ofTypeFunc($var, $typeTest=null, $className=null)
    {

        switch ($typeTest)
        {
            default:
                return false;
                break;

            case 'array':
                return is_array($var);
                break;

            case 'bool':
                return is_bool($var);
                break;

            case 'class':
                return is_object($var) === true && get_class($var) === $className;
                break;

            case 'float':
                return is_float($var);
                break;

            case 'int':
                return is_int($var);
                break;

            case 'numeric':
                return is_numeric($var);
                break;

            case 'object':
                return is_object($var);
                break;

            case 'scalar':
                return is_scalar($var);
                break;

            case 'string':
                return is_string($var);
                break;
        }
    }

    /**
     * Returns the content of an SVG image and adds extra classes as needed
     *
     * @param $path
     * @param $classes
     * @return string|string[]|null
     */
    public static function svgImageFunction($path, $classes)
    {
        $path = Utils::fullPath($path);

        if (file_exists($path)) {
            $svg = file_get_contents($path);
            $classes = " inline-block $classes";
            $matched = false;

            //Look for existing class
            $svg = preg_replace_callback('/^<svg[^>]*(class=\")([^"]*)(\")[^>]*>/', function($matches) use ($classes, &$matched) {
                if (isset($matches[2])) {
                    $new_classes = $matches[2] . $classes;
                    $matched = true;
                    return str_replace($matches[1], "class=\"$new_classes\"", $matches[0]);
                }
                return $matches[0];
            }, $svg
            );

            // no matches found just add the class
            if (!$matched) {
                $classes = trim($classes);
                $svg = str_replace('<svg ', "<svg class=\"$classes\" ", $svg);
            }

            return $svg;
        }
    }

}
