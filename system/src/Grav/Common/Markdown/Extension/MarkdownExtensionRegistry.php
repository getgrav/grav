<?php

/**
 * @package    Grav\Common\Markdown
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Markdown\Extension;

use Grav\Common\Page\Interfaces\PageInterface;

/**
 * Facade bound to a live Parsedown instance (and the page being rendered) that
 * lets extensions register block/inline handlers. It maps straight onto the
 * trait's existing addBlockType()/addInlineType() plus the new
 * setBlockHandler()/setInlineHandler(), so the engine is unchanged.
 *
 * @package Grav\Common\Markdown\Extension
 */
class MarkdownExtensionRegistry
{
    /** @var object The Parsedown instance (uses ParsedownGravTrait). */
    protected $markdown;
    /** @var PageInterface|null */
    protected $page;

    /**
     * @param object $markdown
     * @param PageInterface|null $page
     */
    public function __construct($markdown, ?PageInterface $page = null)
    {
        $this->markdown = $markdown;
        $this->page = $page;
    }

    /**
     * Register an extension if it is enabled.
     */
    public function add(MarkdownExtensionInterface $extension): void
    {
        if ($extension->isEnabled()) {
            $extension->register($this);
        }
    }

    /**
     * @return object
     */
    public function getMarkdown()
    {
        return $this->markdown;
    }

    /**
     * @return PageInterface|null
     */
    public function getPage(): ?PageInterface
    {
        return $this->page;
    }

    /**
     * Register a custom block.
     *
     * @param string $tag    StudlyCase logical name, e.g. 'Alerts'.
     * @param string $marker Trigger character, e.g. '>'. Empty string = unmarked block.
     * @param object $handler Implements BlockHandlerInterface (+ optionally
     *                        BlockContinuableInterface / BlockCompletableInterface).
     * @param array  $options 'continuable' (bool), 'completable' (bool), 'index' (?int).
     */
    public function registerBlock(string $tag, string $marker, object $handler, array $options = []): void
    {
        $this->markdown->setBlockHandler($tag, $handler);
        $this->markdown->addBlockType(
            $marker,
            $tag,
            $options['continuable'] ?? $handler instanceof BlockContinuableInterface,
            $options['completable'] ?? $handler instanceof BlockCompletableInterface,
            $options['index'] ?? null
        );
    }

    /**
     * Register a custom inline element.
     *
     * @param string $tag     StudlyCase logical name, e.g. 'Strikethrough'.
     * @param string $marker  Trigger character, e.g. '~'.
     * @param object $handler Implements InlineHandlerInterface.
     * @param array  $options 'index' (?int).
     */
    public function registerInline(string $tag, string $marker, object $handler, array $options = []): void
    {
        $this->markdown->setInlineHandler($tag, $handler);
        $this->markdown->addInlineType($marker, $tag, $options['index'] ?? null);
    }
}
