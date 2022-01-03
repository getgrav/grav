<?php

/**
 * @package    Grav\Common\Scheduler
 * @author     Originally based on peppeocchi/php-cron-scheduler modified for Grav integration
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Scheduler;

use Cron\CronExpression;
use InvalidArgumentException;
use function is_string;

/**
 * Trait IntervalTrait
 * @package Grav\Common\Scheduler
 */
trait IntervalTrait
{
    /**
     * Set the Job execution time.
     *compo
     * @param  string  $expression
     * @return self
     */
    public function at($expression)
    {
        $this->at = $expression;
        $this->executionTime = CronExpression::factory($expression);

        return $this;
    }

    /**
     * Set the execution time to every minute.
     *
     * @return self
     */
    public function everyMinute()
    {
        return $this->at('* * * * *');
    }

    /**
     * Set the execution time to every hour.
     *
     * @param  int|string  $minute
     * @return self
     */
    public function hourly($minute = 0)
    {
        $c = $this->validateCronSequence($minute);

        return $this->at("{$c['minute']} * * * *");
    }

    /**
     * Set the execution time to once a day.
     *
     * @param  int|string  $hour
     * @param  int|string  $minute
     * @return self
     */
    public function daily($hour = 0, $minute = 0)
    {
        if (is_string($hour)) {
            $parts = explode(':', $hour);
            $hour = $parts[0];
            $minute = $parts[1] ?? '0';
        }
        $c = $this->validateCronSequence($minute, $hour);

        return $this->at("{$c['minute']} {$c['hour']} * * *");
    }

    /**
     * Set the execution time to once a week.
     *
     * @param  int|string  $weekday
     * @param  int|string  $hour
     * @param  int|string  $minute
     * @return self
     */
    public function weekly($weekday = 0, $hour = 0, $minute = 0)
    {
        if (is_string($hour)) {
            $parts = explode(':', $hour);
            $hour = $parts[0];
            $minute = $parts[1] ?? '0';
        }
        $c = $this->validateCronSequence($minute, $hour, null, null, $weekday);

        return $this->at("{$c['minute']} {$c['hour']} * * {$c['weekday']}");
    }

    /**
     * Set the execution time to once a month.
     *
     * @param  int|string  $month
     * @param  int|string  $day
     * @param  int|string  $hour
     * @param  int|string  $minute
     * @return self
     */
    public function monthly($month = '*', $day = 1, $hour = 0, $minute = 0)
    {
        if (is_string($hour)) {
            $parts = explode(':', $hour);
            $hour = $parts[0];
            $minute = $parts[1] ?? '0';
        }
        $c = $this->validateCronSequence($minute, $hour, $day, $month);

        return $this->at("{$c['minute']} {$c['hour']} {$c['day']} {$c['month']} *");
    }

    /**
     * Set the execution time to every Sunday.
     *
     * @param  int|string  $hour
     * @param  int|string  $minute
     * @return self
     */
    public function sunday($hour = 0, $minute = 0)
    {
        return $this->weekly(0, $hour, $minute);
    }

    /**
     * Set the execution time to every Monday.
     *
     * @param  int|string  $hour
     * @param  int|string  $minute
     * @return self
     */
    public function monday($hour = 0, $minute = 0)
    {
        return $this->weekly(1, $hour, $minute);
    }

    /**
     * Set the execution time to every Tuesday.
     *
     * @param  int|string  $hour
     * @param  int|string  $minute
     * @return self
     */
    public function tuesday($hour = 0, $minute = 0)
    {
        return $this->weekly(2, $hour, $minute);
    }

    /**
     * Set the execution time to every Wednesday.
     *
     * @param  int|string  $hour
     * @param  int|string  $minute
     * @return self
     */
    public function wednesday($hour = 0, $minute = 0)
    {
        return $this->weekly(3, $hour, $minute);
    }

    /**
     * Set the execution time to every Thursday.
     *
     * @param  int|string  $hour
     * @param  int|string  $minute
     * @return self
     */
    public function thursday($hour = 0, $minute = 0)
    {
        return $this->weekly(4, $hour, $minute);
    }

    /**
     * Set the execution time to every Friday.
     *
     * @param  int|string  $hour
     * @param  int|string  $minute
     * @return self
     */
    public function friday($hour = 0, $minute = 0)
    {
        return $this->weekly(5, $hour, $minute);
    }

    /**
     * Set the execution time to every Saturday.
     *
     * @param  int|string  $hour
     * @param  int|string  $minute
     * @return self
     */
    public function saturday($hour = 0, $minute = 0)
    {
        return $this->weekly(6, $hour, $minute);
    }

    /**
     * Set the execution time to every January.
     *
     * @param  int|string  $day
     * @param  int|string  $hour
     * @param  int|string  $minute
     * @return self
     */
    public function january($day = 1, $hour = 0, $minute = 0)
    {
        return $this->monthly(1, $day, $hour, $minute);
    }

    /**
     * Set the execution time to every February.
     *
     * @param  int|string  $day
     * @param  int|string  $hour
     * @param  int|string  $minute
     * @return self
     */
    public function february($day = 1, $hour = 0, $minute = 0)
    {
        return $this->monthly(2, $day, $hour, $minute);
    }

    /**
     * Set the execution time to every March.
     *
     * @param  int|string  $day
     * @param  int|string  $hour
     * @param  int|string  $minute
     * @return self
     */
    public function march($day = 1, $hour = 0, $minute = 0)
    {
        return $this->monthly(3, $day, $hour, $minute);
    }

    /**
     * Set the execution time to every April.
     *
     * @param  int|string  $day
     * @param  int|string  $hour
     * @param  int|string  $minute
     * @return self
     */
    public function april($day = 1, $hour = 0, $minute = 0)
    {
        return $this->monthly(4, $day, $hour, $minute);
    }

    /**
     * Set the execution time to every May.
     *
     * @param  int|string  $day
     * @param  int|string  $hour
     * @param  int|string  $minute
     * @return self
     */
    public function may($day = 1, $hour = 0, $minute = 0)
    {
        return $this->monthly(5, $day, $hour, $minute);
    }

    /**
     * Set the execution time to every June.
     *
     * @param  int|string  $day
     * @param  int|string  $hour
     * @param  int|string  $minute
     * @return self
     */
    public function june($day = 1, $hour = 0, $minute = 0)
    {
        return $this->monthly(6, $day, $hour, $minute);
    }

    /**
     * Set the execution time to every July.
     *
     * @param  int|string  $day
     * @param  int|string  $hour
     * @param  int|string  $minute
     * @return self
     */
    public function july($day = 1, $hour = 0, $minute = 0)
    {
        return $this->monthly(7, $day, $hour, $minute);
    }

    /**
     * Set the execution time to every August.
     *
     * @param  int|string  $day
     * @param  int|string  $hour
     * @param  int|string  $minute
     * @return self
     */
    public function august($day = 1, $hour = 0, $minute = 0)
    {
        return $this->monthly(8, $day, $hour, $minute);
    }

    /**
     * Set the execution time to every September.
     *
     * @param  int|string  $day
     * @param  int|string  $hour
     * @param  int|string  $minute
     * @return self
     */
    public function september($day = 1, $hour = 0, $minute = 0)
    {
        return $this->monthly(9, $day, $hour, $minute);
    }

    /**
     * Set the execution time to every October.
     *
     * @param  int|string  $day
     * @param  int|string  $hour
     * @param  int|string  $minute
     * @return self
     */
    public function october($day = 1, $hour = 0, $minute = 0)
    {
        return $this->monthly(10, $day, $hour, $minute);
    }

    /**
     * Set the execution time to every November.
     *
     * @param  int|string  $day
     * @param  int|string  $hour
     * @param  int|string  $minute
     * @return self
     */
    public function november($day = 1, $hour = 0, $minute = 0)
    {
        return $this->monthly(11, $day, $hour, $minute);
    }

    /**
     * Set the execution time to every December.
     *
     * @param  int|string  $day
     * @param  int|string  $hour
     * @param  int|string  $minute
     * @return self
     */
    public function december($day = 1, $hour = 0, $minute = 0)
    {
        return $this->monthly(12, $day, $hour, $minute);
    }

    /**
     * Validate sequence of cron expression.
     *
     * @param  int|string|null  $minute
     * @param  int|string|null  $hour
     * @param  int|string|null  $day
     * @param  int|string|null  $month
     * @param  int|string|null  $weekday
     * @return array
     */
    private function validateCronSequence($minute = null, $hour = null, $day = null, $month = null, $weekday = null)
    {
        return [
            'minute' => $this->validateCronRange($minute, 0, 59),
            'hour' => $this->validateCronRange($hour, 0, 23),
            'day' => $this->validateCronRange($day, 1, 31),
            'month' => $this->validateCronRange($month, 1, 12),
            'weekday' => $this->validateCronRange($weekday, 0, 6),
        ];
    }

    /**
     * Validate sequence of cron expression.
     *
     * @param  int|string|null  $value
     * @param  int         $min
     * @param  int         $max
     * @return mixed
     */
    private function validateCronRange($value, $min, $max)
    {
        if ($value === null || $value === '*') {
            return '*';
        }

        if (! is_numeric($value) ||
            ! ($value >= $min && $value <= $max)
        ) {
            throw new InvalidArgumentException(
                "Invalid value: it should be '*' or between {$min} and {$max}."
            );
        }

        return $value;
    }
}
