<?php
/**
 * @package    Grav.Common
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

use Grav\Common\Grav;

/**
* This file was originally part of the Akelos Framework
*/

class Inflector
{
    protected $plural;
    protected $singular;
    protected $uncountable;
    protected $irregular;
    protected $ordinals;

    public function init()
    {
        if (empty($this->plural)) {
            $language = Grav::instance()['language'];
            $this->plural = $language->translate('INFLECTOR_PLURALS', null, true) ?: [];
            $this->singular = $language->translate('INFLECTOR_SINGULAR', null, true) ?: [];
            $this->uncountable = $language->translate('INFLECTOR_UNCOUNTABLE', null, true) ?: [];
            $this->irregular = $language->translate('INFLECTOR_IRREGULAR', null, true) ?: [];
            $this->ordinals = $language->translate('INFLECTOR_ORDINALS', null, true) ?: [];
        }
    }

    /**
     * Pluralizes English nouns.
     *
     * @param string $word  English noun to pluralize
     * @param int    $count The count
     *
     * @return string Plural noun
     */
    public function pluralize($word, $count = 2)
    {
        $this->init();

        if ($count == 1) {
            return $word;
        }

        $lowercased_word = strtolower($word);

        foreach ($this->uncountable as $_uncountable) {
            if (substr($lowercased_word, (-1 * strlen($_uncountable))) == $_uncountable) {
                return $word;
            }
        }

        foreach ($this->irregular as $_plural => $_singular) {
            if (preg_match('/(' . $_plural . ')$/i', $word, $arr)) {
                return preg_replace('/(' . $_plural . ')$/i', substr($arr[0], 0, 1) . substr($_singular, 1), $word);
            }
        }

        foreach ($this->plural as $rule => $replacement) {
            if (preg_match($rule, $word)) {
                return preg_replace($rule, $replacement, $word);
            }
        }

        return false;

    }

    /**
     * Singularizes English nouns.
     *
     * @param    string $word English noun to singularize
     * @param    int    $count
     *
     * @return string Singular noun.
     */
    public function singularize($word, $count = 1)
    {
        $this->init();

        if ($count != 1) {
            return $word;
        }

        $lowercased_word = strtolower($word);
        foreach ($this->uncountable as $_uncountable) {
            if (substr($lowercased_word, (-1 * strlen($_uncountable))) == $_uncountable) {
                return $word;
            }
        }

        foreach ($this->irregular as $_plural => $_singular) {
            if (preg_match('/(' . $_singular . ')$/i', $word, $arr)) {
                return preg_replace('/(' . $_singular . ')$/i', substr($arr[0], 0, 1) . substr($_plural, 1), $word);
            }
        }

        foreach ($this->singular as $rule => $replacement) {
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
     * The titleize public function converts text like "WelcomePage",
     * "welcome_page" or  "welcome page" to this "Welcome
     * Page".
     * If second parameter is set to 'first' it will only
     * capitalize the first character of the title.
     *
     * @param    string $word      Word to format as tile
     * @param    string $uppercase If set to 'first' it will only uppercase the
     *                             first character. Otherwise it will uppercase all
     *                             the words in the title.
     *
     * @return string Text formatted as title
     */
    public function titleize($word, $uppercase = '')
    {
        $uppercase = $uppercase == 'first' ? 'ucfirst' : 'ucwords';

        return $uppercase($this->humanize($this->underscorize($word)));
    }

    /**
     * Returns given word as CamelCased
     *
     * Converts a word like "send_email" to "SendEmail". It
     * will remove non alphanumeric character from the word, so
     * "who's online" will be converted to "WhoSOnline"
     *
     * @see variablize
     *
     * @param    string $word Word to convert to camel case
     *
     * @return string UpperCamelCasedWord
     */
    public function camelize($word)
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
     * @param    string $word Word to underscore
     *
     * @return string Underscored word
     */
    public function underscorize($word)
    {
        $regex1 = preg_replace('/([A-Z]+)([A-Z][a-z])/', '\1_\2', $word);
        $regex2 = preg_replace('/([a-zd])([A-Z])/', '\1_\2', $regex1);
        $regex3 = preg_replace('/[^A-Z^a-z^0-9]+/', '_', $regex2);

        return strtolower($regex3);
    }

    /**
     * Converts a word "into-it-s-hyphenated-version"
     *
     * Convert any "CamelCased" or "ordinary Word" into an
     * "hyphenated-word".
     *
     * This can be really useful for creating friendly URLs.
     *
     * @param    string $word Word to hyphenate
     *
     * @return string hyphenized word
     */
    public function hyphenize($word)
    {
        $regex1 = preg_replace('/([A-Z]+)([A-Z][a-z])/', '\1-\2', $word);
        $regex2 = preg_replace('/([a-zd])([A-Z])/', '\1-\2', $regex1);
        $regex3 = preg_replace('/[^A-Z^a-z^0-9]+/', '-', $regex2);

        return strtolower($regex3);
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
     * @param    string $word      String to "humanize"
     * @param    string $uppercase If set to 'all' it will uppercase all the words
     *                             instead of just the first one.
     *
     * @return string Human-readable word
     */
    public function humanize($word, $uppercase = '')
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
     * @see camelize
     *
     * @param    string $word Word to lowerCamelCase
     *
     * @return string Returns a lowerCamelCasedWord
     */
    public function variablize($word)
    {
        $word = $this->camelize($word);

        return strtolower($word[0]) . substr($word, 1);
    }

    /**
     * Converts a class name to its table name according to rails
     * naming conventions.
     *
     * Converts "Person" to "people"
     *
     * @see classify
     *
     * @param    string $class_name Class name for getting related table_name.
     *
     * @return string plural_table_name
     */
    public function tableize($class_name)
    {
        return $this->pluralize($this->underscorize($class_name));
    }

    /**
     * Converts a table name to its class name according to rails
     * naming conventions.
     *
     * Converts "people" to "Person"
     *
     * @see tableize
     *
     * @param    string $table_name Table name for getting related ClassName.
     *
     * @return string SingularClassName
     */
    public function classify($table_name)
    {
        return $this->camelize($this->singularize($table_name));
    }

    /**
     * Converts number to its ordinal English form.
     *
     * This method converts 13 to 13th, 2 to 2nd ...
     *
     * @param    integer $number Number to get its ordinal value
     *
     * @return string Ordinal representation of given string.
     */
    public function ordinalize($number)
    {
        $this->init();

        if (in_array(($number % 100), range(11, 13))) {
            return $number . $this->ordinals['default'];
        } else {
            switch (($number % 10)) {
                case 1:
                    return $number . $this->ordinals['first'];
                    break;
                case 2:
                    return $number . $this->ordinals['second'];
                    break;
                case 3:
                    return $number . $this->ordinals['third'];
                    break;
                default:
                    return $number . $this->ordinals['default'];
                    break;
            }
        }
    }

    /**
     * Converts a number of days to a number of months
     *
     * @param int $days
     *
     * @return int
     */
    public function monthize($days)
    {
        $now = new \DateTime();
        $end = new \DateTime();

        $duration = new \DateInterval("P{$days}D");

        $diff = $end->add($duration)->diff($now);

        // handle years
        if ($diff->y > 0) {
            $diff->m = $diff->m + 12 * $diff->y;
        }

        return $diff->m;
    }
}
