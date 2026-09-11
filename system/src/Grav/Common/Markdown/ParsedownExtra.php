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

    /** A start tag: its name, then everything up to the `>` that ends it (a quoted value may hold a `>`). */
    private const START_TAG = '/\G<([a-zA-Z][\w:.-]*)(?=[\s\/>])((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>/';

    /** An end tag. */
    private const END_TAG = '/\G<\/([a-zA-Z][\w:.-]*)[^>]*>/';

    /** One attribute of a start tag, with the whitespace in front of it. */
    private const TAG_ATTRIBUTE = '/\G\s*([^\s"\'>\/=]+)(?:\s*=\s*("[^"]*"|\'[^\']*\'|[^\s"\'=<>`]+))?/';

    /** Elements whose content is raw text: no tag inside one is a tag, and Markdown means nothing there. */
    private const RAW_TEXT_ELEMENTS = ['script', 'style', 'textarea', 'title', 'xmp', 'iframe', 'noembed', 'noframes'];

    /** Elements that never have an end tag. */
    private const VOID_ELEMENTS = ['area', 'base', 'br', 'col', 'command', 'embed', 'hr', 'img', 'input', 'keygen', 'link', 'meta', 'param', 'source', 'track', 'wbr'];

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
     * is touched. Every other HTML block is passed through exactly as written,
     * the same as with Markdown Extra off.
     *
     * Parsedown Extra used to send every HTML block through a DOM round trip.
     * It rewrote the author's markup (`{{ twig }}` in href/src was URL-encoded,
     * SVG attributes were lowercased, `/>` and entities were rewritten), kept
     * only the block's first top-level element and silently deleted the rest
     * (#4291), and threw a TypeError on a block that starts with `<html>`.
     *
     * The Markdown inside a `markdown="1"` element is taken from the block as
     * the author wrote it (renderMarkdownElements()). The round trip rebuilt it
     * from the DOM, which lowercased tags in fenced code (#1840), turned `>`
     * into `&gt;` so blockquotes never formed (#3204), escaped `&` a second
     * time in code spans and entities (#764, #2590), broke `<...>` autolinks
     * (#287) and wrapped content in a phantom `</source>` (#1168). The round
     * trip is only kept for a block whose element has no end tag to match.
     *
     * @param array $Block
     * @return array
     */
    protected function blockMarkupComplete($Block)
    {
        if (!isset($Block['void']) && preg_match(self::MARKDOWN_ATTRIBUTE, (string) $Block['markup'])) {
            $markup = (string) $Block['markup'];
            $Block['markup'] = $this->renderMarkdownElements($markup) ?? $this->processTag($markup);
        }

        return $Block;
    }

    /**
     * The DOM round trip, used only when renderMarkdownElements() can't match
     * a `markdown="1"` element with its end tag.
     *
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

    /**
     * Render every `markdown="1"` element in a raw HTML block from the markup
     * as written, and pass everything else through untouched. The element's
     * opening tag keeps its attributes apart from `markdown`, its content goes
     * through text() as in Parsedown Extra, and whatever follows its end tag,
     * on the same line or after it, is kept as written.
     *
     * An element is rendered where Parsedown Extra would render it: at the top
     * of the block, or nested only in elements that are not text-level (so a
     * `<span markdown="1">` inside a block is left alone). On a raw-text element
     * such as `<script>` the attribute is dropped and the content kept.
     *
     * @param string $html
     * @return string|null null when an element's end tag can't be matched
     */
    private function renderMarkdownElements(string $html): ?string
    {
        $out = '';
        $copied = 0;
        $offset = 0;
        $open = [];

        while (($lt = strpos($html, '<', $offset)) !== false) {
            if (substr($html, $lt, 4) === '<!--') {
                $offset = $this->skipComment($html, $lt);
                continue;
            }
            if (preg_match(self::END_TAG, $html, $m, 0, $lt)) {
                $name = strtolower($m[1]);
                for ($i = count($open) - 1; $i >= 0; $i--) {
                    if ($open[$i] === $name) {
                        array_splice($open, $i);
                        break;
                    }
                }
                $offset = $lt + strlen($m[0]);
                continue;
            }
            if (!preg_match(self::START_TAG, $html, $m, 0, $lt)) {
                $offset = $lt + 1;
                continue;
            }

            $name = strtolower($m[1]);
            $end = $lt + strlen($m[0]);
            [$markdown, $attributes] = $this->splitMarkdownAttribute($m[2]);
            $markdown = $markdown && !$this->insideTextLevelElement($open, $name);

            if (in_array($name, self::RAW_TEXT_ELEMENTS, true)) {
                if ($markdown) {
                    $out .= substr($html, $copied, $lt - $copied) . '<' . $m[1] . $attributes . '>';
                    $copied = $end;
                }
                $offset = $this->skipRawText($html, $end, $name);
                continue;
            }

            if ($markdown) {
                $close = $this->findEndTag($html, $end, $name);
                if ($close === null) {
                    return null;
                }
                [$closeStart, $closeEnd] = $close;
                $out .= substr($html, $copied, $lt - $copied)
                    . '<' . $m[1] . $attributes . '>'
                    . "\n" . $this->text(substr($html, $end, $closeStart - $end)) . "\n"
                    . substr($html, $closeStart, $closeEnd - $closeStart);
                $copied = $offset = $closeEnd;
                continue;
            }

            if (!in_array($name, self::VOID_ELEMENTS, true) && !str_ends_with(rtrim($m[2]), '/')) {
                $open[] = $name;
            }
            $offset = $end;
        }

        return $out . substr($html, $copied);
    }

    /**
     * Whether a start tag's attributes ask for Markdown (the first `markdown`
     * attribute counts, as in a browser, and only the value 1 turns it on),
     * and the attributes as written with every `markdown` attribute taken out.
     *
     * @param string $attributes
     * @return array{0: bool, 1: string}
     */
    private function splitMarkdownAttribute(string $attributes): array
    {
        $markdown = null;
        $kept = '';
        $offset = 0;
        while (preg_match(self::TAG_ATTRIBUTE, $attributes, $m, 0, $offset)) {
            $offset += strlen($m[0]);
            if (strcasecmp($m[1], 'markdown') !== 0) {
                $kept .= $m[0];
                continue;
            }
            if ($markdown === null) {
                $value = $m[2] ?? '';
                if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                    $value = substr($value, 1, -1);
                }
                $markdown = $value === '1';
            }
        }

        return [$markdown === true, $kept . substr($attributes, $offset)];
    }

    /**
     * Below the block's first element, Parsedown Extra only looks for
     * `markdown="1"` through elements that are not text-level.
     *
     * @param list<string> $open names of the open elements, outermost first
     * @param string $name
     * @return bool
     */
    private function insideTextLevelElement(array $open, string $name): bool
    {
        if ($open === []) {
            return false;
        }
        foreach ([...array_slice($open, 1), $name] as $element) {
            if (in_array($element, $this->textLevelElements, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find the end tag of a `markdown="1"` element whose content starts at
     * $offset. Elements with the same name nest. The content is Markdown, so a
     * tag inside a fenced code block or a code span is text, and so is a tag
     * inside an HTML comment or a raw-text element such as `<script>`.
     *
     * @param string $html
     * @param int $offset
     * @param string $name lowercase element name
     * @return array{0: int, 1: int}|null where the end tag starts and ends
     */
    private function findEndTag(string $html, int $offset, string $name): ?array
    {
        $depth = 0;
        $length = strlen($html);
        $lineStart = true;

        while ($offset < $length) {
            if ($lineStart) {
                $lineStart = false;
                if (preg_match('/\G[ \t]*(`{3,}|~{3,})[^`\n]*(?:\n|$)/', $html, $m, 0, $offset)) {
                    $fence = preg_quote($m[1][0], '/');
                    if (!preg_match('/^[ \t]*' . $fence . '{3,}[ \t]*$/m', $html, $c, PREG_OFFSET_CAPTURE, $offset + strlen($m[0]))) {
                        return null;
                    }
                    $offset = $c[0][1] + strlen($c[0][0]);
                    continue;
                }
            }

            $offset += strcspn($html, "<`\n", $offset);
            if ($offset >= $length) {
                break;
            }
            $char = $html[$offset];
            if ($char === "\n") {
                $offset++;
                $lineStart = true;
                continue;
            }
            if ($char === '`') {
                // A code span ends at the next run of as many backticks, within the same paragraph.
                $run = strspn($html, '`', $offset);
                if (preg_match('/(?<!`)`{' . $run . '}(?!`)/', $html, $c, PREG_OFFSET_CAPTURE, $offset + $run)
                    && !preg_match('/\n[ \t]*\n/', substr($html, $offset, $c[0][1] - $offset))) {
                    $offset = $c[0][1] + $run;
                } else {
                    $offset += $run;
                }
                continue;
            }
            if (substr($html, $offset, 4) === '<!--') {
                $offset = $this->skipComment($html, $offset);
                continue;
            }
            if (preg_match(self::END_TAG, $html, $m, 0, $offset)) {
                if (strtolower($m[1]) === $name) {
                    if ($depth === 0) {
                        return [$offset, $offset + strlen($m[0])];
                    }
                    $depth--;
                }
                $offset += strlen($m[0]);
                continue;
            }
            if (preg_match(self::START_TAG, $html, $m, 0, $offset)) {
                $tag = strtolower($m[1]);
                $offset += strlen($m[0]);
                if (in_array($tag, self::RAW_TEXT_ELEMENTS, true)) {
                    $offset = $this->skipRawText($html, $offset, $tag);
                } elseif ($tag === $name && !str_ends_with(rtrim($m[2]), '/')) {
                    $depth++;
                }
                continue;
            }
            $offset++;
        }

        return null;
    }

    /**
     * The offset just past an HTML comment that starts at $offset. `<!-->` and
     * `<!--->` are empty comments, as in a browser.
     *
     * @param string $html
     * @param int $offset
     * @return int
     */
    private function skipComment(string $html, int $offset): int
    {
        foreach (['>', '->'] as $empty) {
            if (substr($html, $offset + 4, strlen($empty)) === $empty) {
                return $offset + 4 + strlen($empty);
            }
        }
        $end = strpos($html, '-->', $offset + 4);

        return $end === false ? strlen($html) : $end + 3;
    }

    /**
     * The offset just past the end tag of a raw-text element whose content
     * starts at $offset, or the end of the markup when it has none.
     *
     * @param string $html
     * @param int $offset
     * @param string $name lowercase element name
     * @return int
     */
    private function skipRawText(string $html, int $offset, string $name): int
    {
        return preg_match('/<\/' . preg_quote($name, '/') . '(?=[\s\/>])[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE, $offset)
            ? $m[0][1] + strlen($m[0][0])
            : strlen($html);
    }
}
