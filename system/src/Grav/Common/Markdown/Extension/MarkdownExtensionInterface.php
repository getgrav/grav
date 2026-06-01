<?php

/**
 * @package    Grav\Common\Markdown
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Markdown\Extension;

/**
 * A markdown extension registers custom block and/or inline syntax with a
 * Parsedown instance through the registry, without touching Parsedown's
 * internals or the legacy closure-injection mechanism.
 *
 * @package Grav\Common\Markdown\Extension
 */
interface MarkdownExtensionInterface
{
    /**
     * A stable identifier for the extension (e.g. 'github-alerts').
     */
    public function getName(): string;

    /**
     * Whether this extension should be registered. Core built-ins read their
     * own config key here; a disabled extension is skipped by the registry.
     */
    public function isEnabled(): bool;

    /**
     * Register block/inline handlers via the registry.
     */
    public function register(MarkdownExtensionRegistry $registry): void;
}
