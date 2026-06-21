<?php

/**
 * @package    Grav\Common\Markdown
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Markdown;

/**
 * Small helper for the value a block parser returns: the rendered element plus
 * any extra state keys the engine and the block's own continue/complete steps
 * read (e.g. a custom flag, or `interrupted`). Compiles to
 * `[...state, 'element' => [...]]`.
 *
 * @package Grav\Common\Markdown
 */
class BlockResult
{
    /** @var array<string,mixed> */
    protected $element;
    /** @var array<string,mixed> */
    protected $state = [];

    /**
     * @param Element|array<string,mixed> $element
     */
    final public function __construct($element)
    {
        $this->element = $element instanceof Element ? $element->toArray() : $element;
    }

    /**
     * @param Element|array<string,mixed> $element
     */
    public static function fromElement($element): static
    {
        return new static($element);
    }

    /**
     * Merge extra top-level state keys into the block.
     *
     * @param array<string,mixed> $state
     */
    public function with(array $state): static
    {
        $this->state = array_merge($this->state, $state);

        return $this;
    }

    /**
     * Set a single top-level state key.
     *
     * @param mixed $value
     */
    public function set(string $key, $value): static
    {
        $this->state[$key] = $value;

        return $this;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return $this->state + ['element' => $this->element];
    }
}
