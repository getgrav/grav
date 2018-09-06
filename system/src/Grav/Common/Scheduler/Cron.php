<?php
/**
 * @package    Grav.Common.Scheduler
 * @author     Originally based on jqCron by Arnaud Buathier <arnaud@arnapou.net> modified for Grav integration
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Scheduler;

/*
 * Usage examples :
 * ----------------
 *
 * $cron = new Cron('10-30/5 12 * * *');
 *
 * var_dump($cron->getMinutes());
 * //  array(5) {
 * //    [0]=> int(10)
 * //    [1]=> int(15)
 * //    [2]=> int(20)
 * //    [3]=> int(25)
 * //    [4]=> int(30)
 * //  }
 *
 * var_dump($cron->getText('fr'));
 * //  string(32) "Chaque jour à 12:10,15,20,25,30"
 *
 * var_dump($cron->getText('en'));
 * //  string(30) "Every day at 12:10,15,20,25,30"
 *
 * var_dump($cron->getType());
 * //  string(3) "day"
 *
 * var_dump($cron->getCronHours());
 * //  string(2) "12"
 *
 * var_dump($cron->matchExact(new \DateTime('2012-07-01 13:25:10')));
 * //  bool(false)
 *
 * var_dump($cron->matchExact(new \DateTime('2012-07-01 12:15:20')));
 * //  bool(true)
 *
 * var_dump($cron->matchWithMargin(new \DateTime('2012-07-01 12:32:50'), -3, 5));
 * //  bool(true)
 */
class Cron {
    const TYPE_UNDEFINED = '';
    const TYPE_MINUTE = 'minute';
    const TYPE_HOUR = 'hour';
    const TYPE_DAY = 'day';
    const TYPE_WEEK = 'week';
    const TYPE_MONTH = 'month';
    const TYPE_YEAR = 'year';
    /**
     *
     * @var array
     */
    protected $texts = array(
        'fr' => array(
            'empty' => '-tout-',
            'name_minute' => 'minute',
            'name_hour' => 'heure',
            'name_day' => 'jour',
            'name_week' => 'semaine',
            'name_month' => 'mois',
            'name_year' => 'année',
            'text_period' => 'Chaque %s',
            'text_mins' => 'à %s minutes',
            'text_time' => 'à %s:%s',
            'text_dow' => 'le %s',
            'text_month' => 'de %s',
            'text_dom' => 'le %s',
            'weekdays' => array('lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'),
            'months' => array('janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'),
        ),
        'en' => array(
            'empty' => '-all-',
            'name_minute' => 'minute',
            'name_hour' => 'hour',
            'name_day' => 'day',
            'name_week' => 'week',
            'name_month' => 'month',
            'name_year' => 'year',
            'text_period' => 'Every %s',
            'text_mins' => 'at %s minutes past the hour',
            'text_time' => 'at %s:%s',
            'text_dow' => 'on %s',
            'text_month' => 'of %s',
            'text_dom' => 'on the %s',
            'weekdays' => array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'),
            'months' => array('january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'),
        ),
    );
    /**
     * min hour dom month dow
     * @var string
     */
    protected $cron = '';
    /**
     *
     * @var array
     */
    protected $minutes = array();
    /**
     *
     * @var array
     */
    protected $hours = array();
    /**
     *
     * @var array
     */
    protected $months = array();
    /**
     * 0-7 : sunday, monday, ... saturday, sunday
     * @var array
     */
    protected $dow = array();
    /**
     *
     * @var array
     */
    protected $dom = array();
    /**
     *
     * @param string $cron
     */
    public function __construct($cron = null) {
        if (!empty($cron)) {
            $this->setCron($cron);
        }
    }
    /**
     *
     * @return string
     */
    public function getCron() {
        return implode(' ', array(
            $this->getCronMinutes(),
            $this->getCronHours(),
            $this->getCronDaysOfMonth(),
            $this->getCronMonths(),
            $this->getCronDaysOfWeek(),
        ));
    }
    /**
     *
     * @param string $lang 'fr' or 'en'
     * @return string
     */
    public function getText($lang) {
        // check lang
        if (!isset($this->texts[$lang])) {
            return $this->getCron();
        }
        $texts = $this->texts[$lang];
        // check type
        $type = $this->getType();
        if ($type == self::TYPE_UNDEFINED) {
            return $this->getCron();
        }
        // init
        $elements = array();
        $elements[] = sprintf($texts['text_period'], $texts['name_' . $type]);
        // hour
        if (in_array($type, array(self::TYPE_HOUR))) {
            $elements[] = sprintf($texts['text_mins'], $this->getCronMinutes());
        }
        // week
        if (in_array($type, array(self::TYPE_WEEK))) {
            $dow = $this->getCronDaysOfWeek();
            foreach ($texts['weekdays'] as $i => $wd) {
                $dow = str_replace((string) ($i + 1), $wd, $dow);
            }
            $elements[] = sprintf($texts['text_dow'], $dow);
        }
        // month + year
        if (in_array($type, array(self::TYPE_MONTH, self::TYPE_YEAR))) {
            $elements[] = sprintf($texts['text_dom'], $this->getCronDaysOfMonth());
        }
        // year
        if (in_array($type, array(self::TYPE_YEAR))) {
            $months = $this->getCronMonths();
            for ($i = count($texts['months']) - 1; $i >= 0; $i--) {
                $months = str_replace((string) ($i + 1), $texts['months'][$i], $months);
            }
            $elements[] = sprintf($texts['text_month'], $months);
        }
        // day + week + month + year
        if (in_array($type, array(self::TYPE_DAY, self::TYPE_WEEK, self::TYPE_MONTH, self::TYPE_YEAR))) {
            $elements[] = sprintf($texts['text_time'], $this->getCronHours(), $this->getCronMinutes());
        }
        return str_replace('*', $texts['empty'], implode(' ', $elements));
    }
    /**
     *
     * @return string
     */
    public function getType() {
        $mask = preg_replace('/[^\* ]/si', '-', $this->getCron());
        $mask = preg_replace('/-+/si', '-', $mask);
        $mask = preg_replace('/[^-\*]/si', '', $mask);
        if ($mask == '*****') {
            return self::TYPE_MINUTE;
        }
        elseif ($mask == '-****') {
            return self::TYPE_HOUR;
        }
        elseif (substr($mask, -3) == '***') {
            return self::TYPE_DAY;
        }
        elseif (substr($mask, -3) == '-**') {
            return self::TYPE_MONTH;
        }
        elseif (substr($mask, -3) == '**-') {
            return self::TYPE_WEEK;
        }
        elseif (substr($mask, -2) == '-*') {
            return self::TYPE_YEAR;
        }
        return self::TYPE_UNDEFINED;
    }
    /**
     *
     * @param string $cron
     * @return Cron
     */
    public function setCron($cron) {
        // sanitize
        $cron = trim($cron);
        $cron = preg_replace('/\s+/', ' ', $cron);
        // explode
        $elements = explode(' ', $cron);
        if (count($elements) != 5) {
            throw new Exception('Bad number of elements');
        }
        $this->cron = $cron;
        $this->setMinutes($elements[0]);
        $this->setHours($elements[1]);
        $this->setDaysOfMonth($elements[2]);
        $this->setMonths($elements[3]);
        $this->setDaysOfWeek($elements[4]);
        return $this;
    }
    /**
     *
     * @return string
     */
    public function getCronMinutes() {
        return $this->arrayToCron($this->minutes);
    }
    /**
     *
     * @return string
     */
    public function getCronHours() {
        return $this->arrayToCron($this->hours);
    }
    /**
     *
     * @return string
     */
    public function getCronDaysOfMonth() {
        return $this->arrayToCron($this->dom);
    }
    /**
     *
     * @return string
     */
    public function getCronMonths() {
        return $this->arrayToCron($this->months);
    }
    /**
     *
     * @return string
     */
    public function getCronDaysOfWeek() {
        return $this->arrayToCron($this->dow);
    }
    /**
     *
     * @return array
     */
    public function getMinutes() {
        return $this->minutes;
    }
    /**
     *
     * @return array
     */
    public function getHours() {
        return $this->hours;
    }
    /**
     *
     * @return array
     */
    public function getDaysOfMonth() {
        return $this->dom;
    }
    /**
     *
     * @return array
     */
    public function getMonths() {
        return $this->months;
    }
    /**
     *
     * @return array
     */
    public function getDaysOfWeek() {
        return $this->dow;
    }
    /**
     *
     * @param string|array $minutes
     * @return Cron
     */
    public function setMinutes($minutes) {
        $this->minutes = $this->cronToArray($minutes, 0, 59);
        return $this;
    }
    /**
     *
     * @param string|array $hours
     * @return Cron
     */
    public function setHours($hours) {
        $this->hours = $this->cronToArray($hours, 0, 23);
        return $this;
    }
    /**
     *
     * @param string|array $months
     * @return Cron
     */
    public function setMonths($months) {
        $this->months = $this->cronToArray($months, 1, 12);
        return $this;
    }
    /**
     *
     * @param string|array $dow
     * @return Cron
     */
    public function setDaysOfWeek($dow) {
        $this->dow = $this->cronToArray($dow, 0, 7);
        return $this;
    }
    /**
     *
     * @param string|array $dom
     * @return Cron
     */
    public function setDaysOfMonth($dom) {
        $this->dom = $this->cronToArray($dom, 1, 31);
        return $this;
    }
    /**
     *
     * @param mixed $date
     * @param int $min
     * @param int $hour
     * @param int $day
     * @param int $month
     * @param int $weekday
     * @return DateTime
     */
    protected function parseDate($date, &$min, &$hour, &$day, &$month, &$weekday) {
        if (is_numeric($date) && intval($date) == $date) {
            $date = new \DateTime('@' . $date);
        }
        elseif (is_string($date)) {
            $date = new \DateTime('@' . strtotime($date));
        }
        if ($date instanceof \DateTime) {
            $min = intval($date->format('i'));
            $hour = intval($date->format('H'));
            $day = intval($date->format('d'));
            $month = intval($date->format('m'));
            $weekday = intval($date->format('w')); // 0-6
        }
        else {
            throw new Exception('Date format not supported');
        }
        return new \DateTime($date->format('Y-m-d H:i:sP'));
    }
    /**
     *
     * @param int|string|\Datetime $date
     */
    public function matchExact($date) {
        $date = $this->parseDate($date, $min, $hour, $day, $month, $weekday);
        return
            (empty($this->minutes) || in_array($min, $this->minutes)) &&
            (empty($this->hours) || in_array($hour, $this->hours)) &&
            (empty($this->dom) || in_array($day, $this->dom)) &&
            (empty($this->months) || in_array($month, $this->months)) &&
            (empty($this->dow) || in_array($weekday, $this->dow) || ($weekday == 0 && in_array(7, $this->dow)) || ($weekday == 7 && in_array(0, $this->dow))
            );
    }
    /**
     *
     * @param int|string|\Datetime $date
     * @param int $minuteBefore
     * @param int $minuteAfter
     */
    public function matchWithMargin($date, $minuteBefore = 0, $minuteAfter = 0) {
        if ($minuteBefore > 0) {
            throw new Exception('MinuteBefore parameter cannot be positive !');
        }
        if ($minuteAfter < 0) {
            throw new Exception('MinuteAfter parameter cannot be negative !');
        }
        $date = $this->parseDate($date, $min, $hour, $day, $month, $weekday);
        $interval = new \DateInterval('PT1M'); // 1 min
        if ($minuteBefore != 0) {
            $date->sub(new \DateInterval('PT' . abs($minuteBefore) . 'M'));
        }
        $n = $minuteAfter - $minuteBefore + 1;
        for ($i = 0; $i < $n; $i++) {
            if ($this->matchExact($date)) {
                return true;
            }
            $date->add($interval);
        }
        return false;
    }
    /**
     *
     * @param array $array
     * @return string
     */
    protected function arrayToCron($array) {
        $n = count($array);
        if (!is_array($array) || $n == 0) {
            return '*';
        }
        $cron = array($array[0]);
        $s = $c = $array[0];
        for ($i = 1; $i < $n; $i++) {
            if ($array[$i] == $c + 1) {
                $c = $array[$i];
                $cron[count($cron) - 1] = $s . '-' . $c;
            }
            else {
                $s = $c = $array[$i];
                $cron[] = $c;
            }
        }
        return implode(',', $cron);
    }
    /**
     *
     * @param string $string
     * @param int $min
     * @param int $max
     * @return array
     */
    protected function cronToArray($string, $min, $max) {
        $array = array();
        if (is_array($string)) {
            foreach ($string as $val) {
                if (is_numeric($val) && intval($val) == $val && $val >= $min && $val <= $max) {
                    $array[] = intval($val);
                }
            }
        }
        else if ($string !== '*') {
            while ($string != '') {
                // test "*/n" expression
                if (preg_match('/^\*\/([0-9]+),?/', $string, $m)) {
                    for ($i = max(0, $min); $i <= min(59, $max); $i+=$m[1]) {
                        $array[] = intval($i);
                    }
                    $string = substr($string, strlen($m[0]));
                    continue;
                }
                // test "a-b/n" expression
                if (preg_match('/^([0-9]+)-([0-9]+)\/([0-9]+),?/', $string, $m)) {
                    for ($i = max($m[1], $min); $i <= min($m[2], $max); $i+=$m[3]) {
                        $array[] = intval($i);
                    }
                    $string = substr($string, strlen($m[0]));
                    continue;
                }
                // test "a-b" expression
                if (preg_match('/^([0-9]+)-([0-9]+),?/', $string, $m)) {
                    for ($i = max($m[1], $min); $i <= min($m[2], $max); $i++) {
                        $array[] = intval($i);
                    }
                    $string = substr($string, strlen($m[0]));
                    continue;
                }
                // test "c" expression
                if (preg_match('/^([0-9]+),?/', $string, $m)) {
                    if ($m[1] >= $min && $m[1] <= $max) {
                        $array[] = intval($m[1]);
                    }
                    $string = substr($string, strlen($m[0]));
                    continue;
                }
                // something goes wrong in the expression
                return array();
            }
        }
        sort($array);
        return $array;
    }
}
