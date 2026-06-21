<?php

/**
 * @package    Grav\Common\Markdown
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Markdown\Extension;

/**
 * Parses the opening line of a custom block. Replaces the legacy
 * `block{Tag}()` method/closure convention.
 *
 * @package Grav\Common\Markdown\Extension
 */
interface BlockHandlerInterface
{
    /**
     * @param array $line  The current line (keys: body, indent, text).
     * @param array|null $block The currently open block, if any.
     * @return array|null A block array (`['element' => [...], ...state]`) or null to decline.
     */
    public function block(array $line, ?array $block = null): ?array;
}
