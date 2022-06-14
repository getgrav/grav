<?php

/**
 * @package    Grav\Common\Twig
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig\Extension;

use CallbackFilterIterator;
use Cron\CronExpression;
use Grav\Common\Config\Config;
use Grav\Common\Data\Data;
use Grav\Common\Debugger;
use Grav\Common\Grav;
use Grav\Common\Inflector;
use Grav\Common\Language\Language;
use Grav\Common\Page\Collection;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Media;
use Grav\Common\Scheduler\Cron;
use Grav\Common\Security;
use Grav\Common\Twig\TokenParser\TwigTokenParserCache;
use Grav\Common\Twig\TokenParser\TwigTokenParserLink;
use Grav\Common\Twig\TokenParser\TwigTokenParserRender;
use Grav\Common\Twig\TokenParser\TwigTokenParserScript;
use Grav\Common\Twig\TokenParser\TwigTokenParserStyle;
use Grav\Common\Twig\TokenParser\TwigTokenParserSwitch;
use Grav\Common\Twig\TokenParser\TwigTokenParserThrow;
use Grav\Common\Twig\TokenParser\TwigTokenParserTryCatch;
use Grav\Common\Twig\TokenParser\TwigTokenParserMarkdown;
use Grav\Common\User\Interfaces\UserInterface;
use Grav\Common\Utils;
use Grav\Common\Yaml;
use Grav\Common\Helpers\Base32;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use Grav\Framework\Psr7\Response;
use Iterator;
use JsonSerializable;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use Traversable;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;
use function array_slice;
use function count;
use function func_get_args;
use function func_num_args;
use function get_class;
use function gettype;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_numeric;
use function is_object;
use function is_scalar;
use function is_string;
use function strlen;

/**
 * Class GravExtension
 * @package Grav\Common\Twig\Extension
 */
class GravExtension extends AbstractExtension implements GlobalsInterface
{
    /** @var Grav */
    protected $grav;
    /** @var Debugger|null */
    protected $debugger;
    /** @var Config */
    protected $config;

    /**
     * GravExtension constructor.
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
    public function getGlobals(): array
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
    public function getFilters(): array
    {
        return [
            new TwigFilter('*ize', [$this, 'inflectorFilter']),
            new TwigFilter('absolute_url', [$this, 'absoluteUrlFilter']),
            new TwigFilter('contains', [$this, 'containsFilter']),
            new TwigFilter('chunk_split', [$this, 'chunkSplitFilter']),
            new TwigFilter('nicenumber', [$this, 'niceNumberFunc']),
            new TwigFilter('nicefilesize', [$this, 'niceFilesizeFunc']),
            new TwigFilter('nicetime', [$this, 'nicetimeFunc']),
            new TwigFilter('defined', [$this, 'definedDefaultFilter']),
            new TwigFilter('ends_with', [$this, 'endsWithFilter']),
            new TwigFilter('fieldName', [$this, 'fieldNameFilter']),
            new TwigFilter('parent_field', [$this, 'fieldParentFilter']),
            new TwigFilter('ksort', [$this, 'ksortFilter']),
            new TwigFilter('ltrim', [$this, 'ltrimFilter']),
            new TwigFilter('markdown', [$this, 'markdownFunction'], ['needs_context' => true, 'is_safe' => ['html']]),
            new TwigFilter('md5', [$this, 'md5Filter']),
            new TwigFilter('base32_encode', [$this, 'base32EncodeFilter']),
            new TwigFilter('base32_decode', [$this, 'base32DecodeFilter']),
            new TwigFilter('base64_encode', [$this, 'base64EncodeFilter']),
            new TwigFilter('base64_decode', [$this, 'base64DecodeFilter']),
            new TwigFilter('randomize', [$this, 'randomizeFilter']),
            new TwigFilter('modulus', [$this, 'modulusFilter']),
            new TwigFilter('rtrim', [$this, 'rtrimFilter']),
            new TwigFilter('pad', [$this, 'padFilter']),
            new TwigFilter('regex_replace', [$this, 'regexReplace']),
            new TwigFilter('safe_email', [$this, 'safeEmailFilter'], ['is_safe' => ['html']]),
            new TwigFilter('safe_truncate', [Utils::class, 'safeTruncate']),
            new TwigFilter('safe_truncate_html', [Utils::class, 'safeTruncateHTML']),
            new TwigFilter('sort_by_key', [$this, 'sortByKeyFilter']),
            new TwigFilter('starts_with', [$this, 'startsWithFilter']),
            new TwigFilter('truncate', [Utils::class, 'truncate']),
            new TwigFilter('truncate_html', [Utils::class, 'truncateHTML']),
            new TwigFilter('json_decode', [$this, 'jsonDecodeFilter']),
            new TwigFilter('array_unique', 'array_unique'),
            new TwigFilter('basename', 'basename'),
            new TwigFilter('dirname', 'dirname'),
            new TwigFilter('print_r', [$this, 'print_r']),
            new TwigFilter('yaml_encode', [$this, 'yamlEncodeFilter']),
            new TwigFilter('yaml_decode', [$this, 'yamlDecodeFilter']),
            new TwigFilter('nicecron', [$this, 'niceCronFilter']),
            new TwigFilter('replace_last', [$this, 'replaceLastFilter']),

            // Translations
            new TwigFilter('t', [$this, 'translate'], ['needs_environment' => true]),
            new TwigFilter('tl', [$this, 'translateLanguage']),
            new TwigFilter('ta', [$this, 'translateArray']),

            // Casting values
            new TwigFilter('string', [$this, 'stringFilter']),
            new TwigFilter('int', [$this, 'intFilter'], ['is_safe' => ['all']]),
            new TwigFilter('bool', [$this, 'boolFilter']),
            new TwigFilter('float', [$this, 'floatFilter'], ['is_safe' => ['all']]),
            new TwigFilter('array', [$this, 'arrayFilter']),
            new TwigFilter('yaml', [$this, 'yamlFilter']),

            // Object Types
            new TwigFilter('get_type', [$this, 'getTypeFunc']),
            new TwigFilter('of_type', [$this, 'ofTypeFunc']),

            // PHP methods
            new TwigFilter('count', 'count'),
            new TwigFilter('array_diff', 'array_diff'),

            // Security fix
            new TwigFilter('filter', [$this, 'filterFilter'], ['needs_environment' => true]),
        ];
    }

    /**
     * Return a list of all functions.
     *
     * @return array
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('array', [$this, 'arrayFilter']),
            new TwigFunction('array_key_value', [$this, 'arrayKeyValueFunc']),
            new TwigFunction('array_key_exists', 'array_key_exists'),
            new TwigFunction('array_unique', 'array_unique'),
            new TwigFunction('array_intersect', [$this, 'arrayIntersectFunc']),
            new TwigFunction('array_diff', 'array_diff'),
            new TwigFunction('authorize', [$this, 'authorize']),
            new TwigFunction('debug', [$this, 'dump'], ['needs_context' => true, 'needs_environment' => true]),
            new TwigFunction('dump', [$this, 'dump'], ['needs_context' => true, 'needs_environment' => true]),
            new TwigFunction('vardump', [$this, 'vardumpFunc']),
            new TwigFunction('print_r', [$this, 'print_r']),
            new TwigFunction('http_response_code', 'http_response_code'),
            new TwigFunction('evaluate', [$this, 'evaluateStringFunc'], ['needs_context' => true]),
            new TwigFunction('evaluate_twig', [$this, 'evaluateTwigFunc'], ['needs_context' => true]),
            new TwigFunction('gist', [$this, 'gistFunc']),
            new TwigFunction('nonce_field', [$this, 'nonceFieldFunc']),
            new TwigFunction('pathinfo', 'pathinfo'),
            new TwigFunction('parseurl', 'parse_url'),
            new TwigFunction('random_string', [$this, 'randomStringFunc']),
            new TwigFunction('repeat', [$this, 'repeatFunc']),
            new TwigFunction('regex_replace', [$this, 'regexReplace']),
            new TwigFunction('regex_filter', [$this, 'regexFilter']),
            new TwigFunction('regex_match', [$this, 'regexMatch']),
            new TwigFunction('regex_split', [$this, 'regexSplit']),
            new TwigFunction('string', [$this, 'stringFilter']),
            new TwigFunction('url', [$this, 'urlFunc']),
            new TwigFunction('json_decode', [$this, 'jsonDecodeFilter']),
            new TwigFunction('get_cookie', [$this, 'getCookie']),
            new TwigFunction('redirect_me', [$this, 'redirectFunc']),
            new TwigFunction('range', [$this, 'rangeFunc']),
            new TwigFunction('isajaxrequest', [$this, 'isAjaxFunc']),
            new TwigFunction('exif', [$this, 'exifFunc']),
            new TwigFunction('media_directory', [$this, 'mediaDirFunc']),
            new TwigFunction('body_class', [$this, 'bodyClassFunc'], ['needs_context' => true]),
            new TwigFunction('theme_var', [$this, 'themeVarFunc'], ['needs_context' => true]),
            new TwigFunction('header_var', [$this, 'pageHeaderVarFunc'], ['needs_context' => true]),
            new TwigFunction('read_file', [$this, 'readFileFunc']),
            new TwigFunction('nicenumber', [$this, 'niceNumberFunc']),
            new TwigFunction('nicefilesize', [$this, 'niceFilesizeFunc']),
            new TwigFunction('nicetime', [$this, 'nicetimeFunc']),
            new TwigFunction('cron', [$this, 'cronFunc']),
            new TwigFunction('svg_image', [$this, 'svgImageFunction']),
            new TwigFunction('xss', [$this, 'xssFunc']),
            new TwigFunction('unique_id', [$this, 'uniqueId']),

            // Translations
            new TwigFunction('t', [$this, 'translate'], ['needs_environment' => true]),
            new TwigFunction('tl', [$this, 'translateLanguage']),
            new TwigFunction('ta', [$this, 'translateArray']),

            // Object Types
            new TwigFunction('get_type', [$this, 'getTypeFunc']),
            new TwigFunction('of_type', [$this, 'ofTypeFunc']),

            // PHP methods
            new TwigFunction('is_numeric', 'is_numeric'),
            new TwigFunction('is_iterable', 'is_iterable'),
            new TwigFunction('is_countable', 'is_countable'),
            new TwigFunction('is_null', 'is_null'),
            new TwigFunction('is_string', 'is_string'),
            new TwigFunction('is_array', 'is_array'),
            new TwigFunction('is_object', 'is_object'),
            new TwigFunction('count', 'count'),
            new TwigFunction('array_diff', 'array_diff'),
        ];
    }

    /**
     * @return array
     */
    public function getTokenParsers(): array
    {
        return [
            new TwigTokenParserRender(),
            new TwigTokenParserThrow(),
            new TwigTokenParserTryCatch(),
            new TwigTokenParserScript(),
            new TwigTokenParserStyle(),
            new TwigTokenParserLink(),
            new TwigTokenParserMarkdown(),
            new TwigTokenParserSwitch(),
            new TwigTokenParserCache(),
        ];
    }

    /**
     * @param mixed $var
     * @return string
     */
    public function print_r($var)
    {
        return print_r($var, true);
    }

    /**
     * Filters field name by changing dot notation into array notation.
     *
     * @param  string $str
     * @return string
     */
    public function fieldNameFilter($str)
    {
        $path = explode('.', rtrim($str, '.'));

        return array_shift($path) . ($path ? '[' . implode('][', $path) . ']' : '');
    }

    /**
     * Filters field name by changing dot notation into array notation.
     *
     * @param  string $str
     * @return string
     */
    public function fieldParentFilter($str)
    {
        $path = explode('.', rtrim($str, '.'));
        array_pop($path);

        return implode('.', $path);
    }

    /**
     * Protects email address.
     *
     * @param  string $str
     * @return string
     */
    public function safeEmailFilter($str)
    {
        static $list = [
            '"' => '&#34;',
            "'" => '&#39;',
            '&' => '&amp;',
            '<' => '&lt;',
            '>' => '&gt;',
            '@' => '&#64;'
        ];

        $characters = mb_str_split($str, 1, 'UTF-8');

        $encoded = '';
        foreach ($characters as $chr) {
            $encoded .= $list[$chr] ?? (random_int(0, 1) ? '&#' . mb_ord($chr) . ';' : $chr);
        }

        return $encoded;
    }

    /**
     * Returns array in a random order.
     *
     * @param  array|Traversable $original
     * @param  int   $offset Can be used to return only slice of the array.
     * @return array
     */
    public function randomizeFilter($original, $offset = 0)
    {
        if ($original instanceof Traversable) {
            $original = iterator_to_array($original, false);
        }

        if (!is_array($original)) {
            return $original;
        }

        $sorted = [];
        $random = array_slice($original, $offset);
        shuffle($random);

        $sizeOf = count($original);
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
     * @param  array|null   $items array of items to select from to return
     * @return int
     */
    public function modulusFilter($number, $divider, $items = null)
    {
        if (is_string($number)) {
            $number = strlen($number);
        }

        $remainder = $number % $divider;

        if (is_array($items)) {
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
     * @param int|null $count
     * @return string
     */
    public function inflectorFilter($action, $data, $count = null)
    {
        $action .= 'ize';

        /** @var Inflector $inflector */
        $inflector = $this->grav['inflector'];

        if (in_array(
            $action,
            ['titleize', 'camelize', 'underscorize', 'hyphenize', 'humanize', 'ordinalize', 'monthize'],
            true
        )) {
            return $inflector->{$action}($data);
        }

        if (in_array($action, ['pluralize', 'singularize'], true)) {
            return $count ? $inflector->{$action}($data, $count) : $inflector->{$action}($data);
        }

        return $data;
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
     * @return string
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
     * @return string|false
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
     * @return array
     */
    public function sortByKeyFilter($input, $filter, $direction = SORT_ASC, $sort_flags = SORT_REGULAR)
    {
        return Utils::sortArrayByKey($input, $filter, $direction, $sort_flags);
    }

    /**
     * Return ksorted collection.
     *
     * @param  array|null $array
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
     * @return string|bool
     * @todo returning $haystack here doesn't make much sense
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
     * @param string $at
     * @return string
     */
    public function niceCronFilter($at)
    {
        $cron = new Cron($at);
        return $cron->getText('en');
    }

    /**
     * @param string|mixed $str
     * @param string $search
     * @param string $replace
     * @return string|mixed
     */
    public function replaceLastFilter($str, $search, $replace)
    {
        if (is_string($str) && ($pos = mb_strrpos($str, $search)) !== false) {
            $str = mb_substr($str, 0, $pos) . $replace . mb_substr($str, $pos + mb_strlen($search));
        }

        return $str;
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
     * @param bool $show_tense
     * @return string
     */
    public function nicetimeFunc($date, $long_strings = true, $show_tense = true)
    {
        if (empty($date)) {
            return $this->grav['language']->translate('GRAV.NICETIME.NO_DATE_PROVIDED');
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
            return $this->grav['language']->translate('GRAV.NICETIME.BAD_DATE');
        }

        // is it future date or past date
        if ($now > $unix_date) {
            $difference = $now - $unix_date;
            $tense      = $this->grav['language']->translate('GRAV.NICETIME.AGO');
        } elseif ($now == $unix_date) {
            $difference = $now - $unix_date;
            $tense      = $this->grav['language']->translate('GRAV.NICETIME.JUST_NOW');
        } else {
            $difference = $unix_date - $now;
            $tense      = $this->grav['language']->translate('GRAV.NICETIME.FROM_NOW');
        }

        for ($j = 0; $difference >= $lengths[$j] && $j < count($lengths) - 1; $j++) {
            $difference /= $lengths[$j];
        }

        $difference = round($difference);

        if ($difference != 1) {
            $periods[$j] .= '_PLURAL';
        }

        if ($this->grav['language']->getTranslation(
            $this->grav['language']->getLanguage(),
            $periods[$j] . '_MORE_THAN_TWO'
        )
        ) {
            if ($difference > 2) {
                $periods[$j] .= '_MORE_THAN_TWO';
            }
        }

        $periods[$j] = $this->grav['language']->translate('GRAV.'.$periods[$j]);

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
        if (!is_array($data)) {
            return Security::detectXss($data);
        }

        $results = Security::detectXssFromArray($data);
        $results_parts = array_map(static function ($value, $key) {
            return $key.': \''.$value . '\'';
        }, array_values($results), array_keys($results));

        return implode(', ', $results_parts);
    }

    /**
     * Generates a random string with configurable length, prefix and suffix.
     * Unlike the built-in `uniqid()`, this string is non-conflicting and safe
     *
     * @param int $length
     * @param array $options
     * @return string
     * @throws \Exception
     */
    public function uniqueId(int $length = 9, array $options = ['prefix' => '', 'suffix' => '']): string
    {
        return Utils::uniqueId($length, $options);
    }

    /**
     * @param string $string
     * @return string
     */
    public function absoluteUrlFilter($string)
    {
        $url    = $this->grav['uri']->base();
        $string = preg_replace('/((?:href|src) *= *[\'"](?!(http|ftp)))/i', "$1$url", $string);

        return $string;
    }

    /**
     * @param array $context
     * @param string $string
     * @param bool $block  Block or Line processing
     * @return string
     */
    public function markdownFunction($context, $string, $block = true)
    {
        $page = $context['page'] ?? null;
        return Utils::processMarkdown($string, $block, $page);
    }

    /**
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    public function startsWithFilter($haystack, $needle)
    {
        return Utils::startsWith($haystack, $needle);
    }

    /**
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    public function endsWithFilter($haystack, $needle)
    {
        return Utils::endsWith($haystack, $needle);
    }

    /**
     * @param mixed $value
     * @param null $default
     * @return mixed|null
     */
    public function definedDefaultFilter($value, $default = null)
    {
        return $value ?? $default;
    }

    /**
     * @param string $value
     * @param string|null $chars
     * @return string
     */
    public function rtrimFilter($value, $chars = null)
    {
        return null !== $chars ? rtrim($value, $chars) : rtrim($value);
    }

    /**
     * @param string $value
     * @param string|null $chars
     * @return string
     */
    public function ltrimFilter($value, $chars = null)
    {
        return  null !== $chars ? ltrim($value, $chars) : ltrim($value);
    }

    /**
     * Returns a string from a value. If the value is array, return it json encoded
     *
     * @param mixed $value
     * @return string
     */
    public function stringFilter($value)
    {
        // Format the array as a string
        if (is_array($value)) {
            return json_encode($value);
        }

        // Boolean becomes '1' or '0'
        if (is_bool($value)) {
            $value = (int)$value;
        }

        // Cast the other values to string.
        return (string)$value;
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
        if (is_array($input)) {
            return $input;
        }

        if (is_object($input)) {
            if (method_exists($input, 'toArray')) {
                return $input->toArray();
            }

            if ($input instanceof Iterator) {
                return iterator_to_array($input);
            }
        }

        return (array)$input;
    }

    /**
     * @param array|object $value
     * @param int|null $inline
     * @param int|null $indent
     * @return string
     */
    public function yamlFilter($value, $inline = null, $indent = null): string
    {
        return Yaml::dump($value, $inline, $indent);
    }

    /**
     * @param Environment $twig
     * @return string
     */
    public function translate(Environment $twig, ...$args)
    {
        // If admin and tu filter provided, use it
        if (isset($this->grav['admin'])) {
            $numargs = count($args);
            $lang = null;

            if (($numargs === 3 && is_array($args[1])) || ($numargs === 2 && !is_array($args[1]))) {
                $lang = array_pop($args);
                /** @var Language $language */
                $language = $this->grav['language'];
                if (is_string($lang) && !$language->getLanguageCode($lang)) {
                    $args[] = $lang;
                    $lang = null;
                }
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
     * @param  bool   $failGracefully If true, return URL even if the file does not exist.
     * @return string|false      Returns url to the resource or null if resource was not found.
     */
    public function urlFunc($input, $domain = false, $failGracefully = false)
    {
        return Utils::url($input, $domain, $failGracefully);
    }

    /**
     * This function will evaluate Twig $twig through the $environment, and return its results.
     *
     * @param array $context
     * @param string $twig
     * @return mixed
     */
    public function evaluateTwigFunc($context, $twig)
    {

        $loader = new FilesystemLoader('.');
        $env = new Environment($loader);
        $env->addExtension($this);

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
    public function evaluateStringFunc($context, $string)
    {
        return $this->evaluateTwigFunc($context, "{{ $string }}");
    }

    /**
     * Based on Twig\Extension\Debug / twig_var_dump
     * (c) 2011 Fabien Potencier
     *
     * @param Environment $env
     * @param array $context
     */
    public function dump(Environment $env, $context)
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
                        $data[$key] = 'Object (' . get_class($value) . ')';
                    }
                } else {
                    $data[$key] = $value;
                }
            }
            $this->debugger->addMessage($data, 'debug');
        } else {
            for ($i = 2; $i < $count; $i++) {
                $var = func_get_arg($i);
                $this->debugger->addMessage($var, 'debug');
            }
        }
    }

    /**
     * Output a Gist
     *
     * @param  string $id
     * @param  string|false $file
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
     * @param array|null $current_array optional array to add to
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
     * @param array|Collection $array1
     * @param array|Collection $array2
     * @return array|Collection
     */
    public function arrayIntersectFunc($array1, $array2)
    {
        if ($array1 instanceof Collection && $array2 instanceof Collection) {
            return $array1->intersect($array2)->toArray();
        }

        return array_intersect($array1, $array2);
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
        // Admin can use Flex users even if the site does not; make sure we use the right version of the user.
        $admin = $this->grav['admin'] ?? null;
        if ($admin) {
            $user = $admin->user;
        } else {
            /** @var UserInterface|null $user */
            $user = $this->grav['user'] ?? null;
        }

        if (!$user) {
            return false;
        }

        if (is_array($action)) {
            if (Utils::isAssoc($action)) {
                // Handle nested access structure.
                $actions = Utils::arrayFlattenDotNotation($action);
            } else {
                // Handle simple access list.
                $actions = array_combine($action, array_fill(0, count($action), true));
            }
        } else {
            // Handle single action.
            $actions = [(string)$action => true];
        }

        $count = count($actions);
        foreach ($actions as $act => $authenticated) {
            // Ignore 'admin.super' if it's not the only value to be checked.
            if ($act === 'admin.super' && $count > 1 && $user instanceof FlexObjectInterface) {
                continue;
            }

            $auth = $user->authorize($act) ?? false;
            if (is_bool($auth) && $auth === Utils::isPositive($authenticated)) {
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
     * @return string
     */
    public function getCookie($key)
    {
        return filter_input(INPUT_COOKIE, $key, FILTER_SANITIZE_STRING);
    }

    /**
     * Twig wrapper for PHP's preg_replace method
     *
     * @param string|string[] $subject the content to perform the replacement on
     * @param string|string[] $pattern the regex pattern to use for matches
     * @param string|string[] $replace the replacement value either as a string or an array of replacements
     * @param int   $limit   the maximum possible replacements for each pattern in each subject
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
     * Twig wrapper for PHP's preg_match method
     *
     * @param string $subject the content to perform the match on
     * @param string $pattern the regex pattern to use for match
     * @param int $flags
     * @param int $offset
     * @return array|false returns the matches if there is at least one match in the subject for a given pattern or null if not.
     */
    public function regexMatch($subject, $pattern, $flags = 0, $offset = 0)
    {
        if (preg_match($pattern, $subject, $matches, $flags, $offset) === false) {
            return false;
        }

        return $matches;
    }

    /**
     * Twig wrapper for PHP's preg_split method
     *
     * @param string $subject the content to perform the split on
     * @param string $pattern the regex pattern to use for split
     * @param int $limit the maximum possible splits for the given pattern
     * @param int $flags
     * @return array|false the resulting array after performing the split operation
     */
    public function regexSplit($subject, $pattern, $limit = -1, $flags = 0)
    {
        return preg_split($pattern, $subject, $limit, $flags);
    }

    /**
     * redirect browser from twig
     *
     * @param string $url          the url to redirect to
     * @param int $statusCode      statusCode, default 303
     * @return void
     */
    public function redirectFunc($url, $statusCode = 303)
    {
        $response = new Response($statusCode, ['location' => $url]);

        $this->grav->close($response);
    }

    /**
     * Generates an array containing a range of elements, optionally stepped
     *
     * @param int $start      Minimum number, default 0
     * @param int $end        Maximum number, default `getrandmax()`
     * @param int $step       Increment between elements in the sequence, default 1
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
     * @return void
     */
    public function vardumpFunc($var)
    {
        dump($var);
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
        if (!is_float($n) && !is_int($n)) {
            if (!is_string($n) || $n === '') {
                return false;
            }

            // Strip any thousand formatting and find the first number.
            $list = array_filter(preg_split("/\D+/", str_replace(',', '', $n)));
            $n = reset($list);

            if (!is_numeric($n)) {
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
     * @param array $context      Twig Context
     * @param string $var variable to be found (using dot notation)
     * @param null $default the default value to be used as last resort
     * @param PageInterface|null $page an optional page to use for the current page
     * @param bool $exists toggle to simply return the page where the variable is set, else null
     * @return mixed
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
                    }

                    return $value;
                }
                $page = $page->parent();
            }
        }

        if ($exists) {
            return false;
        }

        return Grav::instance()['config']->get('theme.' . $var, $default);
    }

    /**
     * Look for a page header variable in an array of pages working its way through until a value is found
     *
     * @param array $context
     * @param string $var the variable to look for in the page header
     * @param string|string[]|null $pages array of pages to check (current page upwards if not null)
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
     * @param array $context
     * @param string|string[] $classes
     * @return string
     */
    public function bodyClassFunc($context, $classes)
    {

        $header = $context['page']->header();
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
     * Returns the content of an SVG image and adds extra classes as needed
     *
     * @param string $path
     * @param string|null $classes
     * @return string|string[]|null
     */
    public static function svgImageFunction($path, $classes = null, $strip_style = false)
    {
        $path = Utils::fullPath($path);

        $classes = $classes ?: '';

        if (file_exists($path) && !is_dir($path)) {
            $svg = file_get_contents($path);
            $classes = " inline-block $classes";
            $matched = false;

            //Remove xml tag if it exists
            $svg = preg_replace('/^<\?xml.*\?>/','', $svg);

            //Strip style if needed
            if ($strip_style) {
                $svg = preg_replace('/<style.*<\/style>/s', '', $svg);
            }

            //Look for existing class
            $svg = preg_replace_callback('/^<svg[^>]*(class=\"([^"]*)\")[^>]*>/', function($matches) use ($classes, &$matched) {
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

            return trim($svg);
        }

        return null;
    }


    /**
     * Dump/Encode data into YAML format
     *
     * @param array|object $data
     * @param int $inline integer number of levels of inline syntax
     * @return string
     */
    public function yamlEncodeFilter($data, $inline = 10)
    {
        if (!is_array($data)) {
            if ($data instanceof JsonSerializable) {
                $data = $data->jsonSerialize();
            } elseif (method_exists($data, 'toArray')) {
                $data = $data->toArray();
            } else {
                $data = json_decode(json_encode($data), true);
            }
        }

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
    public function ofTypeFunc($var, $typeTest = null, $className = null)
    {

        switch ($typeTest) {
            default:
                return false;

            case 'array':
                return is_array($var);

            case 'bool':
                return is_bool($var);

            case 'class':
                return is_object($var) === true && get_class($var) === $className;

            case 'float':
                return is_float($var);

            case 'int':
                return is_int($var);

            case 'numeric':
                return is_numeric($var);

            case 'object':
                return is_object($var);

            case 'scalar':
                return is_scalar($var);

            case 'string':
                return is_string($var);
        }
    }

    /**
     * @param Environment $env
     * @param array $array
     * @param callable|string $arrow
     * @return array|CallbackFilterIterator
     * @throws RuntimeError
     */
    function filterFilter(Environment $env, $array, $arrow)
    {
        if (is_string($arrow) && Utils::isDangerousFunction($arrow)) {
            throw new RuntimeError('Twig |filter("' . $arrow . '") is not allowed.');
        }

        return twig_array_filter($env, $array, $arrow);
    }
}
