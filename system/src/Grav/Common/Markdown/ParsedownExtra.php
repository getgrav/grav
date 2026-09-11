<?php

/**
 * @package    Grav\Common\Markdown
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Markdown;

use DOMDocument;
use DOMElement;
use Exception;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Markdown\Excerpts;

/**
 * Class ParsedownExtra
 * @package Grav\Common\Markdown
 */
class ParsedownExtra extends \ParsedownExtra
{
    use ParsedownGravTrait;

    /** A `markdown="1"` attribute, quoted or not, anywhere in a raw HTML block. */
    private const MARKDOWN_ATTRIBUTE = '/\smarkdown\s*=\s*(?:"1"|\'1\'|1(?=[\s\/>]))/i';

    /**
     * ParsedownExtra constructor.
     *
     * @param Excerpts|PageInterface|null $excerpts
     * @param array|null $defaults
     * @throws Exception
     */
    public function __construct($excerpts = null, $defaults = null)
    {
        if (!$excerpts || $excerpts instanceof PageInterface || null !== $defaults) {
            // Deprecated in Grav 1.6.10
            if ($defaults) {
                $defaults = ['markdown' => $defaults];
            }
            $excerpts = new Excerpts($excerpts, $defaults);
            user_error(self::class . '::' . __FUNCTION__ . '($page, $defaults) is deprecated since Grav 1.6.10, use new ' . self::class . '(new ' . Excerpts::class . '($page, [\'markdown\' => $defaults])) instead.', E_USER_DEPRECATED);
        }

        parent::__construct();

        $this->init($excerpts, $defaults);
    }

    /**
     * Apply `{#id .class}` attribute syntax to fenced code blocks.
     *
     * Vanilla Parsedown Extra never overrides fenced code, so the base parser
     * folds a trailing `{...}` straight into the language token and emits a
     * broken `class="language-{.foo"`. This separates the info string from a
     * trailing attribute block: the first whitespace-delimited token becomes the
     * `language-*` class and the `{...}` contributes id/classes on the `<code>`.
     *
     * @param array $Line
     * @return array|null
     */
    protected function blockFencedCode($Line)
    {
        $Block = parent::blockFencedCode($Line);
        if ($Block === null || !isset($Block['element']['text'])) {
            return $Block;
        }

        $char = $Line['text'][0];
        if (!preg_match('/^[' . $char . ']{3,}[ ]*([^`]+)?[ ]*$/', (string) $Line['text'], $matches) || !isset($matches[1])) {
            return $Block;
        }

        $info = trim($matches[1]);
        $attributes = [];

        // Peel a trailing {…} attribute block off the info string.
        if (preg_match('/^(.*?)[ ]*\{(' . $this->regexAttribute . '+)\}[ ]*$/', $info, $am)) {
            $info = trim($am[1]);
            $attributes = $this->parseAttributeData($am[2]);
        }

        $classes = [];
        if ($info !== '') {
            $language = substr($info, 0, strcspn($info, " \t\n\f\r"));
            if ($language !== '') {
                $classes[] = 'language-' . $language;
            }
        }
        if (isset($attributes['class'])) {
            $classes[] = $attributes['class'];
            unset($attributes['class']);
        }
        if ($classes !== []) {
            $attributes['class'] = implode(' ', $classes);
        }

        if ($attributes !== []) {
            $Block['element']['text']['attributes'] = $attributes;
        } else {
            unset($Block['element']['text']['attributes']);
        }

        return $Block;
    }

    /**
     * Only a raw HTML block that asks for Markdown inside it (`markdown="1"`)
     * goes through Parsedown Extra's DOM round trip. Every other HTML block is
     * passed through exactly as written, the same as with Markdown Extra off.
     *
     * The round trip used to run on every HTML block. It rewrote the author's
     * markup (`{{ twig }}` in href/src was URL-encoded, SVG attributes were
     * lowercased, `/>` and entities were rewritten), kept only the block's first
     * top-level element and silently deleted the rest (#4291), and threw a
     * TypeError on a block that starts with `<html>`.
     *
     * @param array $Block
     * @return array
     */
    protected function blockMarkupComplete($Block)
    {
        if (!isset($Block['void']) && preg_match(self::MARKDOWN_ATTRIBUTE, (string) $Block['markup'])) {
            $Block['markup'] = $this->processTag($Block['markup']);
        }

        return $Block;
    }

    /**
     * Parsedown Extra's processTag() keeps only the first top-level node of the
     * markup it is given. A `markdown="1"` block can hold more than one (an
     * element or text after its closing tag, or a nested closing tag sharing a
     * line, which lets the block run on), so each top-level node is processed
     * on its own and nothing after the first one is lost.
     *
     * @param string $elementMarkup
     * @return string
     */
    protected function processTag($elementMarkup)
    {
        $elementMarkup = (string) $elementMarkup;
        if (trim($elementMarkup) === '') {
            return $elementMarkup;
        }

        $useErrors = libxml_use_internal_errors(true);
        $document = new DOMDocument();
        $document->loadHTML(mb_encode_numericentity($elementMarkup, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8'));
        libxml_clear_errors();
        libxml_use_internal_errors($useErrors);

        // libxml files elements such as <style> or <meta> under <head> and the
        // rest under <body>, so the block's top-level nodes are the children of both.
        $nodes = [];
        foreach ($document->documentElement->childNodes ?? [] as $section) {
            if (!$section instanceof DOMElement) {
                $nodes[] = $section;
                continue;
            }
            foreach ($section->childNodes as $node) {
                $nodes[] = $node;
            }
        }
        if ($nodes === []) {
            return $elementMarkup;
        }

        // A single node where the parent looks for it: nothing can be lost.
        $first = $document->documentElement->firstChild?->firstChild;
        if (count($nodes) === 1 && $first !== null && $nodes[0]->isSameNode($first)) {
            return parent::processTag($elementMarkup);
        }

        $markup = '';
        foreach ($nodes as $node) {
            $nodeMarkup = (string) $document->saveHTML($node);
            $markup .= $node instanceof DOMElement ? parent::processTag($nodeMarkup) : $nodeMarkup;
        }

        return $markup;
    }
}
