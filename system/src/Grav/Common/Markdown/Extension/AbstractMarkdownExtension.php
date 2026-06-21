<?php

/**
 * @package    Grav\Common\Markdown
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Markdown\Extension;

/**
 * Convenience base class for markdown extensions. Holds optional config and
 * defaults to enabled, so a core built-in is on unless its config key is set
 * to false. Subclasses implement getName() and register().
 *
 * @package Grav\Common\Markdown\Extension
 */
abstract class AbstractMarkdownExtension implements MarkdownExtensionInterface
{
    /** @var mixed Extension configuration (array, Config, or null). */
    protected $config;

    /**
     * @param mixed $config
     */
    public function __construct($config = null)
    {
        $this->config = $config;
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled(): bool
    {
        return true;
    }

    /**
     * @return mixed
     */
    public function getConfig()
    {
        return $this->config;
    }
}
