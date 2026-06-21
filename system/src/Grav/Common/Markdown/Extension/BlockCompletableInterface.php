<?php

/**
 * @package    Grav\Common\Markdown
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Markdown\Extension;

/**
 * A block handler that finalizes its block once closed. Replaces the legacy
 * `block{Tag}Complete()` convention. Register the block with
 * `['completable' => true]`.
 *
 * @package Grav\Common\Markdown\Extension
 */
interface BlockCompletableInterface
{
    /**
     * @param array $block The completed block.
     * @return array The finalized block.
     */
    public function blockComplete(array $block): array;
}
