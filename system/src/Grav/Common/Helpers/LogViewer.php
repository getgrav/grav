<?php

/**
 * @package    Grav\Common\Helpers
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Helpers;

use DateTime;
use function array_slice;
use function is_array;
use function is_string;

/**
 * Class LogViewer
 * @package Grav\Common\Helpers
 */
class LogViewer
{
    /** @var string */
    protected $pattern = '/\[(?P<date>.*)\] (?P<logger>\w+).(?P<level>\w+): (?P<message>.*[^ ]+) (?P<context>[^ ]+) (?P<extra>[^ ]+)/';

    /**
     * Get the objects of a tailed file
     *
     * @param string $filepath
     * @param int $lines
     * @param bool $desc
     * @return array
     */
    public function objectTail($filepath, $lines = 1, $desc = true)
    {
        $data = $this->tail($filepath, $lines);
        $tailed_log = $data ? explode(PHP_EOL, $data) : [];
        $line_objects = [];

        foreach ($tailed_log as $line) {
            $line_objects[] = $this->parse($line);
        }

        return $desc ? $line_objects : array_reverse($line_objects);
    }

    /**
     * Optimized way to get just the last few entries of a log file
     *
     * @param string $filepath
     * @param int $lines
     * @return string|false
     */
    public function tail($filepath, $lines = 1)
    {
        $f = $filepath ? @fopen($filepath, 'rb') : false;
        if ($f === false) {
            return false;
        }

        $buffer = ($lines < 2 ? 64 : ($lines < 10 ? 512 : 4096));

        fseek($f, -1, SEEK_END);
        if (fread($f, 1) !== "\n") {
            --$lines;
        }

        // Start reading
        $output = '';
        // While we would like more
        while (ftell($f) > 0 && $lines >= 0) {
            // Figure out how far back we should jump
            $seek = min(ftell($f), $buffer);
            // Do the jump (backwards, relative to where we are)
            fseek($f, -$seek, SEEK_CUR);
            // Read a chunk and prepend it to our output
            $chunk = fread($f, $seek);
            if ($chunk === false) {
                throw new \RuntimeException('Cannot read file');
            }
            $output = $chunk . $output;
            // Jump back to where we started reading
            fseek($f, -mb_strlen($chunk, '8bit'), SEEK_CUR);
            // Decrease our line counter
            $lines -= substr_count($chunk, "\n");
        }
        // While we have too many lines
        // (Because of buffer size we might have read too many)
        while ($lines++ < 0) {
            // Find first newline and remove all text before that
            $output = substr($output, strpos($output, "\n") + 1);
        }
        // Close file and return
        fclose($f);

        return trim($output);
    }

    /**
     * Helper class to get level color
     *
     * @param string $level
     * @return string
     */
    public static function levelColor($level)
    {
        $colors = [
            'DEBUG'     => 'green',
            'INFO'      => 'cyan',
            'NOTICE'    => 'yellow',
            'WARNING'   => 'yellow',
            'ERROR'     => 'red',
            'CRITICAL'  => 'red',
            'ALERT'     => 'red',
            'EMERGENCY' => 'magenta'
        ];
        return $colors[$level] ?? 'white';
    }

    /**
     * Parse a monolog row into array bits
     *
     * @param string $line
     * @return array
     */
    public function parse($line)
    {
        if (!is_string($line) || $line === '') {
            return [];
        }

        preg_match($this->pattern, $line, $data);
        if (!isset($data['date'])) {
            return [];
        }

        preg_match('/(.*)- Trace:(.*)/', $data['message'], $matches);
        if (is_array($matches) && isset($matches[1])) {
            $data['message'] = trim($matches[1]);
            $data['trace'] = trim($matches[2]);
        }

        return [
            'date' => DateTime::createFromFormat('Y-m-d H:i:s', $data['date']),
            'logger' => $data['logger'],
            'level' => $data['level'],
            'message' => $data['message'],
            'trace' => isset($data['trace']) ? self::parseTrace($data['trace']) : null,
            'context' => json_decode($data['context'], true),
            'extra' => json_decode($data['extra'], true)
        ];
    }

    /**
     * Parse text of trace into an array of lines
     *
     * @param string $trace
     * @param int $rows
     * @return array
     */
    public static function parseTrace($trace, $rows = 10)
    {
        $lines = array_filter(preg_split('/#\d*/m', $trace));

        return array_slice($lines, 0, $rows);
    }
}
