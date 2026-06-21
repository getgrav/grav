<?php

/**
 * @package    Grav\Common\Markdown
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Markdown\Extension;

/**
 * Parses a custom inline element. Replaces the legacy `inline{Tag}()`
 * method/closure convention.
 *
 * @package Grav\Common\Markdown\Extension
 */
interface InlineHandlerInterface
{
    /**
     * @param array $excerpt The excerpt (keys: text, context).
     * @return array|null `['extent' => int, 'element' => [...]]` (or 'markup'), or null to decline.
     */
    public function inline(array $excerpt): ?array;
}
