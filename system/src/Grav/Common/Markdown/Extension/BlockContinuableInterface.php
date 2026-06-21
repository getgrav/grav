<?php

/**
 * @package    Grav\Common\Markdown
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Markdown\Extension;

/**
 * A block handler whose block can span multiple lines. Replaces the legacy
 * `block{Tag}Continue()` convention. Register the block with
 * `['continuable' => true]`.
 *
 * @package Grav\Common\Markdown\Extension
 */
interface BlockContinuableInterface
{
    /**
     * @param array $line  The current line.
     * @param array $block The block being continued.
     * @return array|null The mutated block to keep it open, or null to close it.
     */
    public function blockContinue(array $line, array $block): ?array;
}
