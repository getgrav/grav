<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\File\Formatter
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\File\Formatter;

use Grav\Framework\File\Interfaces\FileFormatterInterface;

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
            'delimiter' => ','
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
     * {@inheritdoc}
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
     * {@inheritdoc}
     * @see FileFormatterInterface::decode()
     */
    public function decode($data, $delimiter = null): array
    {
        $delimiter = $delimiter ?? $this->getDelimiter();
        $lines = preg_split('/\r\n|\r|\n/', $data);

        if ($lines === false) {
            throw new \RuntimeException('Decoding CSV failed');
        }

        // Get the field names
        $header = str_getcsv(array_shift($lines), $delimiter);

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
                        array_walk($csv_line, function(&$el) use ($null_replace) {
                           $el = str_replace($null_replace, "\0", $el);
                        });
                    }

                    $list[] = array_combine($header, $csv_line);
                }
            }
        } catch (\Exception $e) {
            throw new \Exception('Badly formatted CSV line: ' . $line);
        }

        return $list;
    }

    protected function encodeLine(array $line, $delimiter = null): string
    {
        foreach ($line as $key => &$value) {
            $value = $this->escape((string)$value);
        }
        unset($value);

        return implode($delimiter, $line). "\n";
    }

    protected function escape(string $value)
    {
        if (preg_match('/[,"\r\n]/u', $value)) {
            $value = '"' . preg_replace('/"/', '""', $value) . '"';
        }

        return $value;
    }
}
