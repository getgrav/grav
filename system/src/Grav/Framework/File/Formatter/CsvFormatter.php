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
        $string = implode($delimiter, $header). "\n";

        // Encode the data
        foreach ($data as $row) {
            $string .=  implode($delimiter, $row). "\n";
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

        // Get the data
        $list = [];
        foreach ($lines as $line) {
            $list[] = array_combine($header, str_getcsv($line, $delimiter));
        }

        return $list;
    }
}
