<?php

/**
 * @package    Grav\Common\Scheduler
 * @author     Originally based on jqCron by Arnaud Buathier <arnaud@arnapou.net> modified for Grav integration
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
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

use DateInterval;
use DateTime;
use RuntimeException;
use function count;
use function in_array;
use function is_array;
use function is_string;

class Cron
{
    public const TYPE_UNDEFINED = '';
    public const TYPE_MINUTE = 'minute';
    public const TYPE_HOUR = 'hour';
    public const TYPE_DAY = 'day';
    public const TYPE_WEEK = 'week';
    public const TYPE_MONTH = 'month';
    public const TYPE_YEAR = 'year';
    /**
     *
     * @var array
     */
    protected $texts = [
        'fr' => [
            'empty' => '-tout-',
            'name_minute' => 'minute',
            'name_hour' => 'heure',
            'name_day' => 'jour',
            'name_week' => 'semaine',
            'name_month' => 'mois',
            'name_year' => 'année',
            'text_period' => 'Chaque %s',
            'text_mins' => 'à %s minutes',
            'text_time' => 'à %02s:%02s',
            'text_dow' => 'le %s',
            'text_month' => 'de %s',
            'text_dom' => 'le %s',
            'weekdays' => ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'],
            'months' => ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'],
        ],
        'en' => [
            'empty' => '-all-',
            'name_minute' => 'minute',
            'name_hour' => 'hour',
            'name_day' => 'day',
            'name_week' => 'week',
            'name_month' => 'month',
            'name_year' => 'year',
            'text_period' => 'Every %s',
            'text_mins' => 'at %s minutes past the hour',
            'text_time' => 'at %02s:%02s',
            'text_dow' => 'on %s',
            'text_month' => 'of %s',
            'text_dom' => 'on the %s',
            'weekdays' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
            'months' => ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'],
        ],
    ];

    /**
     * min hour dom month dow
     * @var string
     */
    protected $cron = '';
    /**
     *
     * @var array
     */
    protected $minutes = [];
    /**
     *
     * @var array
     */
    protected $hours = [];
    /**
     *
     * @var array
     */
    protected $months = [];
    /**
     * 0-7 : sunday, monday, ... saturday, sunday
     * @var array
     */
    protected $dow = [];
    /**
     *
     * @var array
     */
    protected $dom = [];

    /**
     * @param string|null $cron
     */
    public function __construct($cron = null)
    {
        if (null !== $cron) {
            $this->setCron($cron);
        }
    }

    /**
     * @return string
     */
    public function getCron()
    {
        return implode(' ', [
            $this->getCronMinutes(),
            $this->getCronHours(),
            $this->getCronDaysOfMonth(),
            $this->getCronMonths(),
            $this->getCronDaysOfWeek(),
        ]);
    }

    /**
     * @param string $lang 'fr' or 'en'
     * @return string
     */
    public function getText($lang)
    {
        // check lang
        if (!isset($this->texts[$lang])) {
            return $this->getCron();
        }

        $texts = $this->texts[$lang];
        // check type

        $type = $this->getType();
        if ($type === self::TYPE_UNDEFINED) {
            return $this->getCron();
        }

        // init
        $elements = [];
        $elements[] = sprintf($texts['text_period'], $texts['name_' . $type]);

        // hour
        if ($type === self::TYPE_HOUR) {
            $elements[] = sprintf($texts['text_mins'], $this->getCronMinutes());
        }

        // week
        if ($type === self::TYPE_WEEK) {
            $dow = $this->getCronDaysOfWeek();
            foreach ($texts['weekdays'] as $i => $wd) {
                $dow = str_replace((string) ($i + 1), $wd, $dow);
            }
            $elements[] = sprintf($texts['text_dow'], $dow);
        }

        // month + year
        if (in_array($type, [self::TYPE_MONTH, self::TYPE_YEAR], true)) {
            $elements[] = sprintf($texts['text_dom'], $this->getCronDaysOfMonth());
        }

        // year
        if ($type === self::TYPE_YEAR) {
            $months = $this->getCronMonths();
            for ($i = count($texts['months']) - 1; $i >= 0; $i--) {
                $months = str_replace((string) ($i + 1), $texts['months'][$i], $months);
            }
            $elements[] = sprintf($texts['text_month'], $months);
        }

        // day + week + month + year
        if (in_array($type, [self::TYPE_DAY, self::TYPE_WEEK, self::TYPE_MONTH, self::TYPE_YEAR], true)) {
            $elements[] = sprintf($texts['text_time'], $this->getCronHours(), $this->getCronMinutes());
        }

        return str_replace('*', $texts['empty'], implode(' ', $elements));
    }

    /**
     * @return string
     */
    public function getType()
    {
        $mask = preg_replace('/[^\* ]/', '-', $this->getCron());
        $mask = preg_replace('/-+/', '-', $mask);
        $mask = preg_replace('/[^-\*]/', '', $mask);

        if ($mask === '*****') {
            return self::TYPE_MINUTE;
        }

        if ($mask === '-****') {
            return self::TYPE_HOUR;
        }

        if (substr($mask, -3) === '***') {
            return self::TYPE_DAY;
        }

        if (substr($mask, -3) === '-**') {
            return self::TYPE_MONTH;
        }

        if (substr($mask, -3) === '**-') {
            return self::TYPE_WEEK;
        }

        if (substr($mask, -2) === '-*') {
            return self::TYPE_YEAR;
        }

        return self::TYPE_UNDEFINED;
    }

    /**
     * @param string $cron
     * @return $this
     */
    public function setCron($cron)
    {
        // sanitize
        $cron = trim($cron);
        $cron = preg_replace('/\s+/', ' ', $cron);
        // explode
        $elements = explode(' ', $cron);
        if (count($elements) !== 5) {
            throw new RuntimeException('Bad number of elements');
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
     * @return string
     */
    public function getCronMinutes()
    {
        return $this->arrayToCron($this->minutes);
    }

    /**
     * @return string
     */
    public function getCronHours()
    {
        return $this->arrayToCron($this->hours);
    }

    /**
     * @return string
     */
    public function getCronDaysOfMonth()
    {
        return $this->arrayToCron($this->dom);
    }

    /**
     * @return string
     */
    public function getCronMonths()
    {
        return $this->arrayToCron($this->months);
    }

    /**
     * @return string
     */
    public function getCronDaysOfWeek()
    {
        return $this->arrayToCron($this->dow);
    }

    /**
     * @return array
     */
    public function getMinutes()
    {
        return $this->minutes;
    }

    /**
     * @return array
     */
    public function getHours()
    {
        return $this->hours;
    }

    /**
     * @return array
     */
    public function getDaysOfMonth()
    {
        return $this->dom;
    }

    /**
     * @return array
     */
    public function getMonths()
    {
        return $this->months;
    }

    /**
     * @return array
     */
    public function getDaysOfWeek()
    {
        return $this->dow;
    }

    /**
     * @param string|string[] $minutes
     * @return $this
     */
    public function setMinutes($minutes)
    {
        $this->minutes = $this->cronToArray($minutes, 0, 59);

        return $this;
    }

    /**
     * @param string|string[] $hours
     * @return $this
     */
    public function setHours($hours)
    {
        $this->hours = $this->cronToArray($hours, 0, 23);

        return $this;
    }

    /**
     * @param string|string[] $months
     * @return $this
     */
    public function setMonths($months)
    {
        $this->months = $this->cronToArray($months, 1, 12);

        return $this;
    }

    /**
     * @param string|string[] $dow
     * @return $this
     */
    public function setDaysOfWeek($dow)
    {
        $this->dow = $this->cronToArray($dow, 0, 7);

        return $this;
    }

    /**
     * @param string|string[] $dom
     * @return $this
     */
    public function setDaysOfMonth($dom)
    {
        $this->dom = $this->cronToArray($dom, 1, 31);

        return $this;
    }

    /**
     * @param mixed $date
     * @param int $min
     * @param int $hour
     * @param int $day
     * @param int $month
     * @param int $weekday
     * @return DateTime
     */
    protected function parseDate($date, &$min, &$hour, &$day, &$month, &$weekday)
    {
        if (is_numeric($date) && (int)$date == $date) {
            $date = new DateTime('@' . $date);
        } elseif (is_string($date)) {
            $date = new DateTime('@' . strtotime($date));
        }
        if ($date instanceof DateTime) {
            $min = (int)$date->format('i');
            $hour = (int)$date->format('H');
            $day = (int)$date->format('d');
            $month = (int)$date->format('m');
            $weekday = (int)$date->format('w'); // 0-6
        } else {
            throw new RuntimeException('Date format not supported');
        }

        return new DateTime($date->format('Y-m-d H:i:sP'));
    }

    /**
     * @param int|string|DateTime $date
     */
    public function matchExact($date)
    {
        $date = $this->parseDate($date, $min, $hour, $day, $month, $weekday);

        return
            (empty($this->minutes) || in_array($min, $this->minutes, true)) &&
            (empty($this->hours) || in_array($hour, $this->hours, true)) &&
            (empty($this->dom) || in_array($day, $this->dom, true)) &&
            (empty($this->months) || in_array($month, $this->months, true)) &&
            (empty($this->dow) || in_array($weekday, $this->dow, true) || ($weekday == 0 && in_array(7, $this->dow, true)) || ($weekday == 7 && in_array(0, $this->dow, true))
            );
    }

    /**
     * @param int|string|DateTime $date
     * @param int $minuteBefore
     * @param int $minuteAfter
     */
    public function matchWithMargin($date, $minuteBefore = 0, $minuteAfter = 0)
    {
        if ($minuteBefore > 0) {
            throw new RuntimeException('MinuteBefore parameter cannot be positive !');
        }
        if ($minuteAfter < 0) {
            throw new RuntimeException('MinuteAfter parameter cannot be negative !');
        }

        $date = $this->parseDate($date, $min, $hour, $day, $month, $weekday);
        $interval = new DateInterval('PT1M'); // 1 min
        if ($minuteBefore !== 0) {
            $date->sub(new DateInterval('PT' . abs($minuteBefore) . 'M'));
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
     * @param array $array
     * @return string
     */
    protected function arrayToCron($array)
    {
        $n = count($array);
        if (!is_array($array) || $n === 0) {
            return '*';
        }

        $cron = [$array[0]];
        $s = $c = $array[0];
        for ($i = 1; $i < $n; $i++) {
            if ($array[$i] == $c + 1) {
                $c = $array[$i];
                $cron[count($cron) - 1] = $s . '-' . $c;
            } else {
                $s = $c = $array[$i];
                $cron[] = $c;
            }
        }

        return implode(',', $cron);
    }

    /**
     *
     * @param array|string $string
     * @param int $min
     * @param int $max
     * @return array
     */
    protected function cronToArray($string, $min, $max)
    {
        $array = [];
        if (is_array($string)) {
            foreach ($string as $val) {
                if (is_numeric($val) && (int)$val == $val && $val >= $min && $val <= $max) {
                    $array[] = (int)$val;
                }
            }
        } elseif ($string !== '*') {
            while ($string !== '') {
                // test "*/n" expression
                if (preg_match('/^\*\/([0-9]+),?/', $string, $m)) {
                    for ($i = max(0, $min); $i <= min(59, $max); $i += $m[1]) {
                        $array[] = (int)$i;
                    }
                    $string = substr($string, strlen($m[0]));
                    continue;
                }
                // test "a-b/n" expression
                if (preg_match('/^([0-9]+)-([0-9]+)\/([0-9]+),?/', $string, $m)) {
                    for ($i = max($m[1], $min); $i <= min($m[2], $max); $i += $m[3]) {
                        $array[] = (int)$i;
                    }
                    $string = substr($string, strlen($m[0]));
                    continue;
                }
                // test "a-b" expression
                if (preg_match('/^([0-9]+)-([0-9]+),?/', $string, $m)) {
                    for ($i = max($m[1], $min); $i <= min($m[2], $max); $i++) {
                        $array[] = (int)$i;
                    }
                    $string = substr($string, strlen($m[0]));
                    continue;
                }
                // test "c" expression
                if (preg_match('/^([0-9]+),?/', $string, $m)) {
                    if ($m[1] >= $min && $m[1] <= $max) {
                        $array[] = (int)$m[1];
                    }
                    $string = substr($string, strlen($m[0]));
                    continue;
                }

                // something goes wrong in the expression
                return [];
            }
        }
        sort($array, SORT_NUMERIC);

        return $array;
    }
}
