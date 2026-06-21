<?php

/**
 * @package    Grav\Common\Markdown
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Markdown;

/**
 * Fluent builder for Parsedown element arrays.
 *
 * Markdown extension authors describe an element with named methods instead of
 * hand-writing the raw `['name' => ..., 'handler' => 'line'|'lines'|'elements',
 * 'text' => ...]` arrays the engine consumes. `toArray()` compiles to exactly
 * that shape, so the rendered HTML is identical to a hand-built array.
 *
 * The handler each method selects maps onto the Parsedown render handlers:
 *  - setInlineText()  -> 'line'     (inline-parse a single string)
 *  - setRawLines()    -> 'lines'    (block-parse an array of raw text lines)
 *  - setChildren()    -> 'elements' (render an array of child elements)
 *  - setListItems()   -> 'li'       (render list-item lines)
 *  - setText()        -> (none)     (escaped literal text)
 *  - setRawHtml()     -> rawHtml    (raw passthrough; opt-in under safe mode)
 *
 * @package Grav\Common\Markdown
 */
class Element
{
    /** @var string */
    protected $name;
    /** @var array<string,string|null> */
    protected $attributes = [];
    /** @var string|array|null */
    protected $text = null;
    /** @var string|null */
    protected $handler = null;
    /** @var string|null */
    protected $rawHtml = null;
    /** @var bool */
    protected $allowRawHtmlInSafeMode = false;
    /** @var array<int,string> */
    protected $nonNestables = [];

    final public function __construct(string $name)
    {
        $this->name = $name;
    }

    public static function create(string $name): static
    {
        return new static($name);
    }

    public static function p(): static
    {
        return new static('p');
    }

    public static function div(): static
    {
        return new static('div');
    }

    public static function span(): static
    {
        return new static('span');
    }

    /**
     * Set a single attribute. A null value is preserved (Parsedown skips it at render time).
     */
    public function attr(string $name, ?string $value): static
    {
        $this->attributes[$name] = $value;

        return $this;
    }

    /**
     * Merge a map of attributes.
     *
     * @param array<string,string|null> $attributes
     */
    public function attributes(array $attributes): static
    {
        $this->attributes = array_merge($this->attributes, $attributes);

        return $this;
    }

    /**
     * Append one or more classes (each argument may itself be space-separated).
     */
    public function addClass(string ...$classes): static
    {
        $current = isset($this->attributes['class']) && $this->attributes['class'] !== ''
            ? preg_split('/\s+/', trim((string) $this->attributes['class']))
            : [];

        foreach ($classes as $class) {
            foreach (preg_split('/\s+/', trim($class)) as $token) {
                if ($token !== '') {
                    $current[] = $token;
                }
            }
        }

        $this->attributes['class'] = implode(' ', $current);

        return $this;
    }

    /**
     * Inline-parse a single string of markdown (handler: line).
     */
    public function setInlineText(string $text): static
    {
        $this->handler = 'line';
        $this->text = $text;
        $this->rawHtml = null;

        return $this;
    }

    /**
     * Block-parse an array of raw text lines (handler: lines). This is the
     * mutable body a continuable block appends to.
     *
     * @param array<int,string> $lines
     */
    public function setRawLines(array $lines): static
    {
        $this->handler = 'lines';
        $this->text = $lines;
        $this->rawHtml = null;

        return $this;
    }

    /**
     * Render an array of child elements (handler: elements). Children may be
     * Element instances or already-compiled arrays.
     *
     * @param array<int,Element|array> $children
     */
    public function setChildren(array $children): static
    {
        $this->handler = 'elements';
        $this->text = array_map(
            static fn ($child) => $child instanceof self ? $child->toArray() : $child,
            $children
        );
        $this->rawHtml = null;

        return $this;
    }

    /**
     * Render list-item lines (handler: li).
     *
     * @param array<int,string> $lines
     */
    public function setListItems(array $lines): static
    {
        $this->handler = 'li';
        $this->text = $lines;
        $this->rawHtml = null;

        return $this;
    }

    /**
     * Escaped literal text (no handler).
     */
    public function setText(string $text): static
    {
        $this->handler = null;
        $this->text = $text;
        $this->rawHtml = null;

        return $this;
    }

    /**
     * Raw HTML passthrough. By default it is stripped in safe mode; pass true to
     * allow it through (use with care).
     */
    public function setRawHtml(string $html, bool $allowInSafeMode = false): static
    {
        $this->rawHtml = $html;
        $this->allowRawHtmlInSafeMode = $allowInSafeMode;
        $this->handler = null;
        $this->text = null;

        return $this;
    }

    /**
     * Inline types that must not nest inside this element.
     *
     * @param array<int,string> $nonNestables
     */
    public function setNonNestables(array $nonNestables): static
    {
        $this->nonNestables = $nonNestables;

        return $this;
    }

    /**
     * Compile to the Parsedown element array.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        $element = ['name' => $this->name];

        if ($this->handler !== null) {
            $element['handler'] = $this->handler;
        }
        if ($this->attributes !== []) {
            $element['attributes'] = $this->attributes;
        }
        if ($this->text !== null) {
            $element['text'] = $this->text;
        } elseif ($this->rawHtml !== null) {
            $element['rawHtml'] = $this->rawHtml;
            if ($this->allowRawHtmlInSafeMode) {
                $element['allowRawHtmlInSafeMode'] = true;
            }
        }
        if ($this->nonNestables !== []) {
            $element['nonNestables'] = $this->nonNestables;
        }

        return $element;
    }
}
