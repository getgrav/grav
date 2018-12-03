<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\File\Formatter
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\File\Formatter;

abstract class AbstractFormatter implements FormatterInterface
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
        $this->doUnserialize(unserialize($serialized, false));
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultFileExtension(): string
    {
        $extensions = $this->getSupportedFileExtensions();

        // Call fails on bad configuration.
        return reset($extensions);
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedFileExtensions(): array
    {
        $extensions = $this->getConfig('file_extension');

        // Call fails on bad configuration.
        return \is_string($extensions) ? [$extensions] : $extensions;
    }

    /**
     * {@inheritdoc}
     */
    abstract public function encode($data): string;

    /**
     * {@inheritdoc}
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
