<?php
namespace Grav\Common;

/**
* This file was originally part of the Akelos Framework
*/

/**
* Inflector for pluralize and singularize English nouns.
*
* This Inflector is a port of Ruby on Rails Inflector.
*
* It can be really helpful for developers that want to
* create frameworks based on naming conventions rather than
* configurations.
*
* @author RocketTheme
* @license MIT
*/

class Inflector
{

    /**
    * Pluralizes English nouns.
    *
    * @access static public
    * @static
    * @param    string    $word    English noun to pluralize
    * @return string Plural noun
    */
    public static function pluralize($word, $count = 2)
    {
        if ($count == 1) {
            return $word;
        }

        $plural = array(
            '/(quiz)$/i' => '\1zes',
            '/^(ox)$/i' => '\1en',
            '/([m|l])ouse$/i' => '\1ice',
            '/(matr|vert|ind)ix|ex$/i' => '\1ices',
            '/(x|ch|ss|sh)$/i' => '\1es',
            '/([^aeiouy]|qu)ies$/i' => '\1y',
            '/([^aeiouy]|qu)y$/i' => '\1ies',
            '/(hive)$/i' => '\1s',
            '/(?:([^f])fe|([lr])f)$/i' => '\1\2ves',
            '/sis$/i' => 'ses',
            '/([ti])um$/i' => '\1a',
            '/(buffal|tomat)o$/i' => '\1oes',
            '/(bu)s$/i' => '\1ses',
            '/(alias|status)/i'=> '\1es',
            '/(octop|vir)us$/i'=> '\1i',
            '/(ax|test)is$/i'=> '\1es',
            '/s$/i'=> 's',
            '/$/'=> 's');

        $uncountable = array('equipment', 'information', 'rice', 'money', 'species', 'series', 'fish', 'sheep');

        $irregular = array(
            'person' => 'people',
            'man' => 'men',
            'child' => 'children',
            'sex' => 'sexes',
            'move' => 'moves');

        $lowercased_word = strtolower($word);

        foreach ($uncountable as $_uncountable) {
            if (substr($lowercased_word, (-1*strlen($_uncountable))) == $_uncountable) {
                return $word;
            }
        }

        foreach ($irregular as $_plural => $_singular) {
            if (preg_match('/('.$_plural.')$/i', $word, $arr)) {
                return preg_replace('/('.$_plural.')$/i', substr($arr[0], 0, 1).substr($_singular, 1), $word);
            }
        }

        foreach ($plural as $rule => $replacement) {
            if (preg_match($rule, $word)) {
                return preg_replace($rule, $replacement, $word);
            }
        }
        return false;

    }

    /**
    * Singularizes English nouns.
    *
    * @param    string    $word    English noun to singularize
    * @param    int       $count
    * @return string Singular noun.
    */
    public static function singularize($word, $count = 1)
    {
        if ($count != 1) {
            return $word;
        }

        $singular = array (
            '/(quiz)zes$/i' => '\1',
            '/(matr)ices$/i' => '\1ix',
            '/(vert|ind)ices$/i' => '\1ex',
            '/^(ox)en/i' => '\1',
            '/(alias|status)es$/i' => '\1',
            '/([octop|vir])i$/i' => '\1us',
            '/(cris|ax|test)es$/i' => '\1is',
            '/(shoe)s$/i' => '\1',
            '/(o)es$/i' => '\1',
            '/(bus)es$/i' => '\1',
            '/([m|l])ice$/i' => '\1ouse',
            '/(x|ch|ss|sh)es$/i' => '\1',
            '/(m)ovies$/i' => '\1ovie',
            '/(s)eries$/i' => '\1eries',
            '/([^aeiouy]|qu)ies$/i' => '\1y',
            '/([lr])ves$/i' => '\1f',
            '/(tive)s$/i' => '\1',
            '/(hive)s$/i' => '\1',
            '/([^f])ves$/i' => '\1fe',
            '/(^analy)ses$/i' => '\1sis',
            '/((a)naly|(b)a|(d)iagno|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/i' => '\1\2sis',
            '/([ti])a$/i' => '\1um',
            '/(n)ews$/i' => '\1ews',
            '/s$/i' => '',
            );

        $uncountable = array('equipment', 'information', 'rice', 'money', 'species', 'series', 'fish', 'sheep');

        $irregular = array(
            'person' => 'people',
            'man' => 'men',
            'child' => 'children',
            'sex' => 'sexes',
            'move' => 'moves');

        $lowercased_word = strtolower($word);
        foreach ($uncountable as $_uncountable) {
            if (substr($lowercased_word, (-1*strlen($_uncountable))) == $_uncountable) {
                return $word;
            }
        }

        foreach ($irregular as $_plural => $_singular) {
            if (preg_match('/('.$_singular.')$/i', $word, $arr)) {
                return preg_replace('/('.$_singular.')$/i', substr($arr[0], 0, 1).substr($_plural, 1), $word);
            }
        }

        foreach ($singular as $rule => $replacement) {
            if (preg_match($rule, $word)) {
                return preg_replace($rule, $replacement, $word);
            }
        }

        return $word;
    }

    /**
    * Converts an underscored or CamelCase word into a English
    * sentence.
    *
    * The titleize static public function converts text like "WelcomePage",
    * "welcome_page" or  "welcome page" to this "Welcome
    * Page".
    * If second parameter is set to 'first' it will only
    * capitalize the first character of the title.
    *
    * @access static public
    * @static
    * @param    string    $word    Word to format as tile
    * @param    string    $uppercase    If set to 'first' it will only uppercase the
    * first character. Otherwise it will uppercase all
    * the words in the title.
    * @return string Text formatted as title
    */
    public static function titleize($word, $uppercase = '')
    {
        $uppercase = $uppercase == 'first' ? 'ucfirst' : 'ucwords';
        return $uppercase(static::humanize(static::underscorize($word)));
    }

    /**
    * Returns given word as CamelCased
    *
    * Converts a word like "send_email" to "SendEmail". It
    * will remove non alphanumeric character from the word, so
    * "who's online" will be converted to "WhoSOnline"
    *
    * @access static public
    * @static
    * @see variablize
    * @param    string    $word    Word to convert to camel case
    * @return string UpperCamelCasedWord
    */
    public static function camelize($word)
    {
        return str_replace(' ', '', ucwords(preg_replace('/[^A-Z^a-z^0-9]+/', ' ', $word)));
    }

    /**
    * Converts a word "into_it_s_underscored_version"
    *
    * Convert any "CamelCased" or "ordinary Word" into an
    * "underscored_word".
    *
    * This can be really useful for creating friendly URLs.
    *
    * @access static public
    * @static
    * @param    string    $word    Word to underscore
    * @return string Underscored word
    */
    public static function underscorize($word)
    {
        $regex1 = preg_replace('/([A-Z]+)([A-Z][a-z])/', '\1_\2', $word);
        $regex2 = preg_replace('/([a-zd])([A-Z])/', '\1_\2', $regex1);
        $regex3 = preg_replace('/[^A-Z^a-z^0-9]+/', '_', $regex2);
        return  strtolower($regex3);
    }

    /**
    * Converts a word "into-it-s-hyphenated-version"
    *
    * Convert any "CamelCased" or "ordinary Word" into an
    * "hyphenated-word".
    *
    * This can be really useful for creating friendly URLs.
    *
    * @access static public
    * @static
    * @param    string    $word    Word to hyphenate
    * @return string hyphenized word
    */
    public static function hyphenize($word)
    {
        $regex1 = preg_replace('/([A-Z]+)([A-Z][a-z])/', '\1-\2', $word);
        $regex2 = preg_replace('/([a-zd])([A-Z])/', '\1-\2', $regex1);
        $regex3 = preg_replace('/[^A-Z^a-z^0-9]+/', '-', $regex2);
        return  strtolower($regex3);
    }

    /**
    * Returns a human-readable string from $word
    *
    * Returns a human-readable string from $word, by replacing
    * underscores with a space, and by upper-casing the initial
    * character by default.
    *
    * If you need to uppercase all the words you just have to
    * pass 'all' as a second parameter.
    *
    * @access static public
    * @static
    * @param    string    $word    String to "humanize"
    * @param    string    $uppercase    If set to 'all' it will uppercase all the words
    * instead of just the first one.
    * @return string Human-readable word
    */
    public static function humanize($word, $uppercase = '')
    {
        $uppercase = $uppercase == 'all' ? 'ucwords' : 'ucfirst';
        return $uppercase(str_replace('_', ' ', preg_replace('/_id$/', '', $word)));
    }

    /**
    * Same as camelize but first char is underscored
    *
    * Converts a word like "send_email" to "sendEmail". It
    * will remove non alphanumeric character from the word, so
    * "who's online" will be converted to "whoSOnline"
    *
    * @access static public
    * @static
    * @see camelize
    * @param    string    $word    Word to lowerCamelCase
    * @return string Returns a lowerCamelCasedWord
    */
    public static function variablize($word)
    {
        $word = static::camelize($word);
        return strtolower($word[0]).substr($word, 1);
    }

    /**
    * Converts a class name to its table name according to rails
    * naming conventions.
    *
    * Converts "Person" to "people"
    *
    * @access static public
    * @static
    * @see classify
    * @param    string    $class_name    Class name for getting related table_name.
    * @return string plural_table_name
    */
    public static function tableize($class_name)
    {
        return static::pluralize(static::underscore($class_name));
    }

    /**
    * Converts a table name to its class name according to rails
    * naming conventions.
    *
    * Converts "people" to "Person"
    *
    * @access static public
    * @static
    * @see tableize
    * @param    string    $table_name    Table name for getting related ClassName.
    * @return string SingularClassName
    */
    public static function classify($table_name)
    {
        return static::camelize(static::singularize($table_name));
    }

    /**
    * Converts number to its ordinal English form.
    *
    * This method converts 13 to 13th, 2 to 2nd ...
    *
    * @access static public
    * @static
    * @param    integer    $number    Number to get its ordinal value
    * @return string Ordinal representation of given string.
    */
    public static function ordinalize($number)
    {
        if (in_array(($number % 100), range(11, 13))) {
            return $number.'th';
        } else {
            switch (($number % 10)) {
                case 1:
                    return $number.'st';
                    break;
                case 2:
                    return $number.'nd';
                    break;
                case 3:
                    return $number.'rd';
                    break;
                default:
                    return $number.'th';
                    break;
            }
        }
    }

    public static function monthize($days)
    {
        $now = new \DateTime();
        $end = new \DateTime();

        $duration = new \DateInterval("P{$days}D");

        $diff = $end->add($duration)->diff($now);

    // handle years
        if ($diff->y > 0) {
            $diff->m = $diff->m + 12*$diff->y;
        }

        return $diff->m;
    }
}
