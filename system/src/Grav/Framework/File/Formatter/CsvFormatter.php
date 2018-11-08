<?php

/**
 * @package    Grav\Framework\File\Formatter
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\File\Formatter;

class CsvFormatter implements FormatterInterface
{
    /** @var array */
    private $config;

    /**
     * IniFormatter constructor.
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config + [
                'file_extension' => ['.csv', '.tsv'],
                'delimiter' => ','
            ];
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultFileExtension()
    {
        $extensions = $this->getSupportedFileExtensions();

        return (string) reset($extensions);
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedFileExtensions()
    {
        return (array) $this->config['file_extension'];
    }

    public function getDelimiter()
    {
        return $this->config['delimiter'];
    }

    /**
     * {@inheritdoc}
     */
    public function encode($data, $delimiter = null)
    {
        $delimiter = $delimiter ?? $this->getDelimiter();
        $header = array_keys(reset($lines));

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
     */
    public function decode($data, $delimiter = null)
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
