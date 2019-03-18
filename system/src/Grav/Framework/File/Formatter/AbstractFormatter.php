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

/**
 * Abstract file formatter.
 *
 * @package Grav\Framework\File\Formatter
 */
abstract class AbstractFormatter implements FileFormatterInterface
{
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
    public function serialize(): string
    {
        return serialize($this->doSerialize());
    }

    /**
     * @param string $serialized
     */
    public function unserialize($serialized): void
    {
        $this->doUnserialize(unserialize($serialized, ['allowed_classes' => false]));
    }

    /**
     * {@inheritdoc}
     * @see FileFormatterInterface::getDefaultFileExtension()
     */
    public function getDefaultFileExtension(): string
    {
        $extensions = $this->getSupportedFileExtensions();

        // Call fails on bad configuration.
        return reset($extensions);
    }

    /**
     * {@inheritdoc}
     * @see FileFormatterInterface::getSupportedFileExtensions()
     */
    public function getSupportedFileExtensions(): array
    {
        $extensions = $this->getConfig('file_extension');

        // Call fails on bad configuration.
        return \is_string($extensions) ? [$extensions] : $extensions;
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

    /**
     * @return array
     */
    protected function doSerialize(): array
    {
        return ['config' => $this->config];
    }

    /**
     * Note: if overridden, make sure you call parent::doUnserialize()
     *
     * @param array $serialized
     */
    protected function doUnserialize(array $serialized): void
    {
        $this->config = $serialized['config'];
    }
}
