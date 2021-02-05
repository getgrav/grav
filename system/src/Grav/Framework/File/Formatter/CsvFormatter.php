<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\File\Formatter
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\File\Formatter;

use Exception;
use Grav\Framework\File\Interfaces\FileFormatterInterface;
use JsonSerializable;
use RuntimeException;
use stdClass;
use function is_array;
use function is_object;
use function is_scalar;

/**
 * Class CsvFormatter
 * @package Grav\Framework\File\Formatter
 */
class CsvFormatter extends AbstractFormatter
{
    /**
     * IniFormatter constructor.
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $config += [
            'file_extension' => ['.csv', '.tsv'],
            'delimiter' => ',',
            'mime' => 'text/x-csv'
        ];

        parent::__construct($config);
    }

    /**
     * Returns delimiter used to both encode and decode CSV.
     *
     * @return string
     */
    public function getDelimiter(): string
    {
        // Call fails on bad configuration.
        return $this->getConfig('delimiter');
    }

    /**
     * @param array $data
     * @param string|null $delimiter
     * @return string
     * @see FileFormatterInterface::encode()
     */
    public function encode($data, $delimiter = null): string
    {
        if (count($data) === 0) {
            return '';
        }
        $delimiter = $delimiter ?? $this->getDelimiter();
        $header = array_keys(reset($data));

        // Encode the field names
        $string = $this->encodeLine($header, $delimiter);

        // Encode the data
        foreach ($data as $row) {
            $string .= $this->encodeLine($row, $delimiter);
        }

        return $string;
    }

    /**
     * @param string $data
     * @param string|null $delimiter
     * @return array
     * @see FileFormatterInterface::decode()
     */
    public function decode($data, $delimiter = null): array
    {
        $delimiter = $delimiter ?? $this->getDelimiter();
        $lines = preg_split('/\r\n|\r|\n/', $data);
        if ($lines === false) {
            throw new RuntimeException('Decoding CSV failed');
        }

        // Get the field names
        $headerStr = array_shift($lines);
        if (!$headerStr) {
            throw new RuntimeException('CSV header missing');
        }

        $header = str_getcsv($headerStr, $delimiter);

        // Allow for replacing a null string with null/empty value
        $null_replace = $this->getConfig('null');

        // Get the data
        $list = [];
        $line = null;
        try {
            foreach ($lines as $line) {
                if (!empty($line)) {
                    $csv_line = str_getcsv($line, $delimiter);

                    if ($null_replace) {
                        array_walk($csv_line, static function (&$el) use ($null_replace) {
                            $el = str_replace($null_replace, "\0", $el);
                        });
                    }

                    $list[] = array_combine($header, $csv_line);
                }
            }
        } catch (Exception $e) {
            throw new RuntimeException('Badly formatted CSV line: ' . $line);
        }

        return $list;
    }

    /**
     * @param array $line
     * @param string $delimiter
     * @return string
     */
    protected function encodeLine(array $line, string $delimiter): string
    {
        foreach ($line as $key => &$value) {
            // Oops, we need to convert the line to a string.
            if (!is_scalar($value)) {
                if (is_array($value) || $value instanceof JsonSerializable || $value instanceof stdClass) {
                    $value = json_encode($value);
                } elseif (is_object($value)) {
                    if (method_exists($value, 'toJson')) {
                        $value = $value->toJson();
                    } elseif (method_exists($value, 'toArray')) {
                        $value = json_encode($value->toArray());
                    }
                }
            }

            $value = $this->escape((string)$value);
        }
        unset($value);

        return implode($delimiter, $line). "\n";
    }

    /**
     * @param string $value
     * @return string
     */
    protected function escape(string $value)
    {
        if (preg_match('/[,"\r\n]/u', $value)) {
            $value = '"' . preg_replace('/"/', '""', $value) . '"';
        }

        return $value;
    }
}
