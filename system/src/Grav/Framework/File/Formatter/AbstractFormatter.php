<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\File\Formatter
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\File\Formatter;

use Grav\Framework\Compat\Serializable;
use Grav\Framework\File\Interfaces\FileFormatterInterface;
use function is_string;

/**
 * Abstract file formatter.
 *
 * @package Grav\Framework\File\Formatter
 */
abstract class AbstractFormatter implements FileFormatterInterface
{
    use Serializable;

    /** @var array */
    private $config;

    /**
     * IniFormatter constructor.
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * @return string
     */
    public function getMimeType(): string
    {
        $mime = $this->getConfig('mime');

        return is_string($mime) ? $mime : 'application/octet-stream';
    }

    /**
     * {@inheritdoc}
     * @see FileFormatterInterface::getDefaultFileExtension()
     */
    public function getDefaultFileExtension(): string
    {
        $extensions = $this->getSupportedFileExtensions();

        // Call fails on bad configuration.
        return reset($extensions) ?: '';
    }

    /**
     * {@inheritdoc}
     * @see FileFormatterInterface::getSupportedFileExtensions()
     */
    public function getSupportedFileExtensions(): array
    {
        $extensions = $this->getConfig('file_extension');

        // Call fails on bad configuration.
        return is_string($extensions) ? [$extensions] : $extensions;
    }

    /**
     * {@inheritdoc}
     * @see FileFormatterInterface::encode()
     */
    abstract public function encode($data): string;

    /**
     * {@inheritdoc}
     * @see FileFormatterInterface::decode()
     */
    abstract public function decode($data);


    /**
     * @return array
     */
    public function __serialize(): array
    {
        return ['config' => $this->config];
    }

    /**
     * @param array $data
     * @return void
     */
    public function __unserialize(array $data): void
    {
        $this->config = $data['config'];
    }

    /**
     * Get either full configuration or a single option.
     *
     * @param string|null $name Configuration option (optional)
     * @return mixed
     */
    protected function getConfig(string $name = null)
    {
        if (null !== $name) {
            return $this->config[$name] ?? null;
        }

        return $this->config;
    }
}
