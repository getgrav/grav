<?php
namespace Grav\Common;

class TwigExtension extends \Twig_Extension
{
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
        return array(
            new \Twig_SimpleFilter('fieldName', array($this,'fieldNameFilter')),
            new \Twig_SimpleFilter('safe_email', array($this,'safeEmailFilter')),
            new \Twig_SimpleFilter('randomize', array($this,'randomizeFilter')),
            new \Twig_SimpleFilter('truncate', array($this,'truncateFilter')),
            new \Twig_SimpleFilter('removeDisabled', array($this,'removeDisabledFilter')),
            new \Twig_SimpleFilter('growText', array($this, 'growTextFilter')),
            new \Twig_SimpleFilter('*ize', array($this,'inflectorFilter')),
            new \Twig_SimpleFilter('md5', array($this,'md5Filter')),
        );
    }

    /**
     * Return a list of all functions.
     *
     * @return array
     */
    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('repeat', array($this, 'repeatFunc'))
        );
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
     * Add HTML markup to grow the text size. Effect depends on the text length.
     *
     * @param $text
     * @return string
     */
    public function growTextFilter($text)
    {
        $count = str_word_count($text);
        if ($count < 20) {
            return '<span class="text-grow-more">'.$text.'</span>';
        } elseif ($count < 40) {
            return '<span class="text-grow">'.$text.'</span>';
        } else {
            return $text;
        }
    }

    /**
     * Remove disabled objects from the array. If input isn't array, do nothing.
     *
     * @param  array  $original
     * @return array
     */
    public function removeDisabledFilter($original)
    {
        if (!is_array($original)) {
            return $original;
        }
        $new = array();

        foreach ($original as $entry) {
            if (is_object($entry) && !isset($entry->disabled)) {
                $new[] = $entry;
            }
        }
        return $new;
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

        $sorted = array();
        $random = array_slice($original, $offset);
        shuffle($random);

        for ($x=0; $x < sizeof($original); $x++) {
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
            array('titleize','camelize','underscorize','hyphenize', 'humanize','ordinalize','monthize')
        )) {
            return Inflector::$action($data);
        } elseif (in_array($action, array('pluralize','singularize'))) {
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
}
