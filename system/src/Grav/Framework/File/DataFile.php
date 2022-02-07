<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\File
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\File;

use Grav\Framework\File\Interfaces\FileFormatterInterface;
use RuntimeException;
use function is_string;

/**
 * Class DataFile
 * @package Grav\Framework\File
 */
class DataFile extends AbstractFile
{
    /** @var FileFormatterInterface */
    protected $formatter;

    /**
     * File constructor.
     * @param string $filepath
     * @param FileFormatterInterface $formatter
     */
    public function __construct($filepath, FileFormatterInterface $formatter)
    {
        parent::__construct($filepath);

        $this->formatter = $formatter;
    }

    /**
     * {@inheritdoc}
     * @see FileInterface::load()
     */
    public function load()
    {
        $raw = parent::load();

        try {
            if (!is_string($raw)) {
                throw new RuntimeException('Bad Data');
            }

            return $this->formatter->decode($raw);
        } catch (RuntimeException $e) {
            throw new RuntimeException(sprintf("Failed to load file '%s': %s", $this->getFilePath(), $e->getMessage()), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     * @see FileInterface::save()
     */
    public function save($data): void
    {
        if (is_string($data)) {
            // Make sure that the string is valid data.
            try {
                $this->formatter->decode($data);
            } catch (RuntimeException $e) {
                throw new RuntimeException(sprintf("Failed to save file '%s': %s", $this->getFilePath(), $e->getMessage()), $e->getCode(), $e);
            }
            $encoded = $data;
        } else {
            $encoded = $this->formatter->encode($data);
        }

        parent::save($encoded);
    }
}
