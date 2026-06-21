<?php

/**
 * @package    Grav\Common\Markdown
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Markdown;

use Grav\Common\Page\Markdown\Excerpts;
use Grav\Common\Page\Interfaces\PageInterface;
use function call_user_func_array;
use function in_array;
use function strlen;

/**
 * Trait ParsedownGravTrait
 * @package Grav\Common\Markdown
 */
trait ParsedownGravTrait
{
    /** @var array */
    public $completable_blocks = [];
    /** @var array */
    public $continuable_blocks = [];
    public $plugins = [];
    /** @var array<string,object> Block handler objects keyed by StudlyCase tag (extension API). */
    public $block_handlers = [];
    /** @var array<string,object> Inline handler objects keyed by StudlyCase tag (extension API). */
    public $inline_handlers = [];

    /** @var Excerpts */
    protected $excerpts;
    /** @var array */
    protected $special_chars;
    /** @var string */
    protected $twig_link_regex = '/\!*\[(?:.*)\]\((\{([\{%#])\s*(.*?)\s*(?:\2|\})\})\)/';
    /** @var bool Render `- [ ]` / `- [x]` list items as checkboxes (GFM task lists). */
    protected $gfm_task_lists = true;
    /** @var bool Escape the GFM "disallowed raw HTML" tag denylist in output (tagfilter). */
    protected $gfm_tagfilter = true;
    /** @var bool Autolink bare `www.` URLs and email addresses (GFM extended autolinks). */
    protected $gfm_autolinks = true;
    /** @var bool Non-GFM table extension: an empty cell merges into the cell on its left (colspan). */
    protected $table_colspan = false;
    /** @var bool Non-GFM table extension: a table may start with the divider row (no header). */
    protected $table_headerless = false;
    /** @var bool Non-GFM table extension: a `[Caption]` line after a table becomes a `<caption>`. */
    protected $table_captions = false;
    /** @var bool Non-GFM table extension: a `{.class #id}` line after a table sets attributes on `<table>`. */
    protected $table_attributes = false;
    /** @var bool Non-GFM table extension: a row ending in `\` continues into the next line (multi-line cells). */
    protected $table_multiline = false;

    /**
     * Initialization function to setup key variables needed by the MarkdownGravLinkTrait
     *
     * @param PageInterface|Excerpts|null $excerpts
     * @param array|null $defaults
     * @return void
     */
    protected function init($excerpts = null, $defaults = null)
    {
        if (!$excerpts || $excerpts instanceof PageInterface) {
            // Deprecated in Grav 1.6.10
            if ($defaults) {
                $defaults = ['markdown' => $defaults];
            }
            $this->excerpts = new Excerpts($excerpts, $defaults);
            user_error(self::class . '::' . __FUNCTION__ . '($page, $defaults) is deprecated since Grav 1.6.10, use ->init(new ' . Excerpts::class . '($page, [\'markdown\' => $defaults])) instead.', E_USER_DEPRECATED);
        } else {
            $this->excerpts = $excerpts;
        }

        $this->BlockTypes['{'][] = 'TwigTag';
        $this->special_chars = ['>' => 'gt', '<' => 'lt', '"' => 'quot'];

        $defaults = $this->excerpts->getConfig();

        if (isset($defaults['markdown']['auto_line_breaks'])) {
            $this->setBreaksEnabled($defaults['markdown']['auto_line_breaks']);
        }
        if (isset($defaults['markdown']['auto_url_links'])) {
            $this->setUrlsLinked($defaults['markdown']['auto_url_links']);
        }
        if (isset($defaults['markdown']['escape_markup'])) {
                $this->setMarkupEscaped($defaults['markdown']['escape_markup']);
        }
        if (isset($defaults['markdown']['special_chars'])) {
            $this->setSpecialChars($defaults['markdown']['special_chars']);
        }

        // GitHub Flavored Markdown extensions (on by default).
        $gfm = $defaults['markdown']['gfm'] ?? [];
        $this->gfm_task_lists = (bool)($gfm['task_lists'] ?? true);
        $this->gfm_tagfilter = (bool)($gfm['tagfilter'] ?? true);
        $this->gfm_autolinks = (bool)($gfm['autolinks'] ?? true);
        $tables = $defaults['markdown']['tables'] ?? [];
        $this->table_colspan = (bool)($tables['colspan'] ?? false);
        $this->table_headerless = (bool)($tables['headerless'] ?? false);
        $this->table_captions = (bool)($tables['captions'] ?? false);
        $this->table_attributes = (bool)($tables['attributes'] ?? false);
        $this->table_multiline = (bool)($tables['multiline'] ?? false);
        if ($gfm['marks'] ?? true) {
            // Subscript shares the `~` marker with strikethrough; register it
            // after so `~~strike~~` is matched first and a single `~sub~` falls through.
            $this->addInlineType('=', 'Highlight');
            $this->addInlineType('~', 'Subscript');
            $this->addInlineType('^', 'Superscript');
        }

        $this->excerpts->fireInitializedEvent($this);
    }

    /**
     * @return Excerpts
     */
    public function getExcerpts()
    {
        return $this->excerpts;
    }

    /**
     * Be able to define a new Block type or override an existing one
     *
     * @param string $type
     * @param string $tag
     * @param bool $continuable
     * @param bool $completable
     * @param int|null $index
     * @return void
     */
    public function addBlockType($type, $tag, $continuable = false, $completable = false, $index = null)
    {
        $block = &$this->unmarkedBlockTypes;
        if ($type) {
            if (!isset($this->BlockTypes[$type])) {
                $this->BlockTypes[$type] = [];
            }
            $block = &$this->BlockTypes[$type];
        }

        if (null === $index) {
            $block[] = $tag;
        } else {
            array_splice($block, $index, 0, [$tag]);
        }

        if ($continuable) {
            $this->continuable_blocks[] = $tag;
        }
        if ($completable) {
            $this->completable_blocks[] = $tag;
        }
    }

    /**
     * Be able to define a new Inline type or override an existing one
     *
     * @param string $type
     * @param string $tag
     * @param int|null $index
     * @return void
     */
    public function addInlineType($type, $tag, $index = null)
    {
        if (null === $index || !isset($this->InlineTypes[$type])) {
            $this->InlineTypes[$type] [] = $tag;
        } else {
            array_splice($this->InlineTypes[$type], $index, 0, [$tag]);
        }

        if (!str_contains($this->inlineMarkerList, $type)) {
            $this->inlineMarkerList .= $type;
        }
    }

    /**
     * Register a block handler object for the extension API. The engine's
     * block{Tag} / block{Tag}Continue / block{Tag}Complete dispatch is routed
     * to this handler via __call().
     *
     * @param string $tag
     * @param object $handler
     * @return void
     */
    public function setBlockHandler($tag, $handler)
    {
        $this->block_handlers[$tag] = $handler;
    }

    /**
     * Register an inline handler object for the extension API. The engine's
     * inline{Tag} dispatch is routed to this handler via __call().
     *
     * @param string $tag
     * @param object $handler
     * @return void
     */
    public function setInlineHandler($tag, $handler)
    {
        $this->inline_handlers[$tag] = $handler;
    }

    /**
     * Overrides the default behavior to allow for plugin-provided blocks to be continuable
     *
     * @param string $Type
     * @return bool
     */
    protected function isBlockContinuable($Type)
    {
        $continuable = in_array($Type, $this->continuable_blocks, true)
            || method_exists($this, 'block' . $Type . 'Continue')
            || (isset($this->block_handlers[$Type]) && method_exists($this->block_handlers[$Type], 'blockContinue'));

        return $continuable;
    }

    /**
     *  Overrides the default behavior to allow for plugin-provided blocks to be completable
     *
     * @param string $Type
     * @return bool
     */
    protected function isBlockCompletable($Type)
    {
        $completable = in_array($Type, $this->completable_blocks, true)
            || method_exists($this, 'block' . $Type . 'Complete')
            || (isset($this->block_handlers[$Type]) && method_exists($this->block_handlers[$Type], 'blockComplete'));

        return $completable;
    }


    /**
     * Make the element function publicly accessible, Medium uses this to render from Twig
     *
     * @param  array $Element
     * @return string markup
     */
    public function elementToHtml(array $Element)
    {
        return $this->element($Element);
    }

    /**
     * Setter for special chars
     *
     * @param array $special_chars
     * @return $this
     */
    public function setSpecialChars($special_chars)
    {
        $this->special_chars = $special_chars;

        return $this;
    }

    /**
     * Ensure Twig tags are treated as block level items with no <p></p> tags
     *
     * @param array $line
     * @return array|null
     */
    protected function blockTwigTag($line)
    {
        if (preg_match('/(?:{{|{%|{#)(.*)(?:}}|%}|#})/', (string) $line['body'], $matches)) {
            return ['markup' => $line['body']];
        }

        return null;
    }

    /**
     * @param array $excerpt
     * @return array|null
     */
    protected function inlineSpecialCharacter($excerpt)
    {
        if ($excerpt['text'][0] === '&' && !preg_match('/^&#?\w+;/', (string) $excerpt['text'])) {
            return [
                'markup' => '&amp;',
                'extent' => 1,
            ];
        }

        if (isset($this->special_chars[$excerpt['text'][0]])) {
            return [
                'markup' => '&' . $this->special_chars[$excerpt['text'][0]] . ';',
                'extent' => 1,
            ];
        }

        return null;
    }

    /**
     * @param array $excerpt
     * @return array
     */
    protected function inlineImage($excerpt)
    {
        if (preg_match($this->twig_link_regex, (string) $excerpt['text'], $matches)) {
            $excerpt['text'] = str_replace($matches[1], '/', $excerpt['text']);
            $excerpt = parent::inlineImage($excerpt);
            $excerpt['element']['attributes']['src'] = $matches[1];
            $excerpt['extent'] = $excerpt['extent'] + strlen($matches[1]) - 1;

            return $excerpt;
        }

        $excerpt['type'] = 'image';
        $excerpt = parent::inlineImage($excerpt);

        // if this is an image process it
        if (isset($excerpt['element']['attributes']['src'])) {
            $excerpt = $this->excerpts->processImageExcerpt($excerpt);
        }

        return $excerpt;
    }

    /**
     * @param array $excerpt
     * @return array
     */
    protected function inlineLink($excerpt)
    {
        $type = $excerpt['type'] ?? 'link';

        // do some trickery to get around Parsedown requirement for valid URL if its Twig in there
        if (preg_match($this->twig_link_regex, (string) $excerpt['text'], $matches)) {
            $excerpt['text'] = str_replace($matches[1], '/', $excerpt['text']);
            $excerpt = parent::inlineLink($excerpt);
            $excerpt['element']['attributes']['href'] = $matches[1];
            $excerpt['extent'] = $excerpt['extent'] + strlen($matches[1]) - 1;

            return $excerpt;
        }

        $excerpt = parent::inlineLink($excerpt);

        // if this is a link
        if (isset($excerpt['element']['attributes']['href'])) {
            $excerpt = $this->excerpts->processLinkExcerpt($excerpt, $type);
        }

        return $excerpt;
    }

    /**
     * Inline `==highlight==` to a <mark> element (GFM-adjacent extended syntax).
     *
     * @param array $excerpt
     * @return array|null
     */
    protected function inlineHighlight($excerpt)
    {
        if (preg_match('/^==(?=\S)(.+?)==/s', (string) $excerpt['text'], $matches)) {
            return [
                'extent' => strlen($matches[0]),
                'element' => Element::create('mark')->setInlineText($matches[1])->toArray(),
            ];
        }

        return null;
    }

    /**
     * Inline `~subscript~` to a <sub> element. Shares the `~` marker with
     * strikethrough, which is registered first and matched first, so only a
     * single-tilde span reaches here.
     *
     * @param array $excerpt
     * @return array|null
     */
    protected function inlineSubscript($excerpt)
    {
        if (preg_match('/^~(?!~)([^~\s]+)~/', (string) $excerpt['text'], $matches)) {
            return [
                'extent' => strlen($matches[0]),
                'element' => Element::create('sub')->setInlineText($matches[1])->toArray(),
            ];
        }

        return null;
    }

    /**
     * Inline `^superscript^` to a <sup> element.
     *
     * @param array $excerpt
     * @return array|null
     */
    protected function inlineSuperscript($excerpt)
    {
        if (preg_match('/^\^([^\^\s]+)\^/', (string) $excerpt['text'], $matches)) {
            return [
                'extent' => strlen($matches[0]),
                'element' => Element::create('sup')->setInlineText($matches[1])->toArray(),
            ];
        }

        return null;
    }

    /**
     * Apply GFM output post-passes (task lists, tagfilter) after rendering.
     *
     * @param string $text
     * @return string
     */
    #[\ReturnTypeWillChange]
    public function text($text)
    {
        $markup = parent::text($text);

        if ($this->gfm_task_lists) {
            $markup = $this->renderTaskLists($markup);
        }
        if ($this->gfm_tagfilter) {
            $markup = $this->filterDisallowedRawHtml($markup);
        }

        return $markup;
    }

    /**
     * Turn `- [ ]` / `- [x]` list items into disabled checkboxes (GFM task
     * lists). Runs on rendered list HTML; fenced/indented code escapes `<`, so
     * a `<li>` here is always a real list item.
     *
     * @param string $markup
     * @return string
     */
    protected function renderTaskLists($markup)
    {
        // The pattern requires a literal "[" (after a "<li>"); skip when absent.
        if (strpos((string) $markup, '[') === false) {
            return (string) $markup;
        }

        return preg_replace_callback(
            '/<li>(\s*(?:<p>)?)\[([ xX])\]\s+/',
            static function ($m) {
                $checked = strtolower($m[2]) === 'x' ? ' checked=""' : '';
                return '<li class="task-list-item">' . $m[1] . '<input type="checkbox" disabled=""' . $checked . ' /> ';
            },
            (string) $markup
        );
    }

    /**
     * Escape the leading `<` of GFM's disallowed-raw-HTML tag denylist so those
     * tags render as inert text instead of active markup (tagfilter extension).
     * Broad XSS protection remains the Security layer's responsibility.
     *
     * @param string $markup
     * @return string
     */
    protected function filterDisallowedRawHtml($markup)
    {
        return preg_replace(
            '#<(/?(?:title|textarea|style|xmp|iframe|noembed|noframes|script|plaintext)\b)#i',
            '&lt;$1',
            (string) $markup
        );
    }

    /**
     * GFM extended autolinks: turn bare `www.` URLs and email addresses into
     * links. Runs on unmarked text only (segments outside other inlines), so it
     * never relinks inside an existing link, code span, or raw HTML.
     *
     * @param string $text
     * @return string
     */
    protected function unmarkedText($text)
    {
        $text = parent::unmarkedText($text);

        if ($this->gfm_autolinks) {
            $text = $this->autolinkExtended((string) $text);
        }

        return $text;
    }

    /**
     * @param string $text
     * @return string
     */
    protected function autolinkExtended($text)
    {
        // Cheap gate: the pattern can only match text containing "www." or "@".
        // unmarkedText() runs once per text fragment, so skipping the regex for
        // the common case (neither present) is a large win on real documents.
        if (strpos($text, '@') === false && stripos($text, 'www.') === false) {
            return $text;
        }

        return preg_replace_callback(
            '~(?<![\w@./])(www\.[\w-]+(?:\.[\w-]+)+(?:[/?#][^\s<]*)?|[\w.+-]+@[\w-]+(?:\.[\w-]+)+)~i',
            function ($matches) {
                [$token, $trailing] = $this->trimAutolinkTrailing($matches[1]);
                if (stripos($token, 'www.') === 0) {
                    return '<a href="http://' . $token . '">' . $token . '</a>' . $trailing;
                }

                return '<a href="mailto:' . $token . '">' . $token . '</a>' . $trailing;
            },
            (string) $text
        );
    }

    /**
     * Strip trailing punctuation (and unbalanced closing parens) from an
     * autolink token, matching GFM behavior. Returns [linked, trailing].
     *
     * @param string $token
     * @return array
     */
    protected function trimAutolinkTrailing($token)
    {
        $trailing = '';
        while ($token !== '' && strpbrk($token[strlen($token) - 1], '?!.,:*_~') !== false) {
            $trailing = $token[strlen($token) - 1] . $trailing;
            $token = substr($token, 0, -1);
        }
        while ($token !== '' && $token[strlen($token) - 1] === ')'
            && substr_count($token, ')') > substr_count($token, '(')) {
            $trailing = ')' . $trailing;
            $token = substr($token, 0, -1);
        }

        return [$token, $trailing];
    }

    /**
     * Header-less tables (opt-in, non-GFM): a table that starts directly with
     * the alignment/divider row, with no header row above it. Emits a
     * `<tbody>`-only table. The standard GFM header+divider table is handled by
     * the parent unchanged; this only kicks in when the parent declines.
     *
     * @param array      $Line
     * @param array|null $Block
     * @return array|null
     */
    protected function blockTable($Line, ?array $Block = null)
    {
        $table = parent::blockTable($Line, $Block);
        if ($table !== null || !$this->table_headerless) {
            return $table;
        }

        // Only when there is no live header paragraph to consume above the
        // divider (start of document, after a blank line, or a different block).
        // A live paragraph above is left intact rather than split.
        if (isset($Block) && !isset($Block['type']) && !isset($Block['interrupted'])) {
            return null;
        }

        $text = (string)$Line['text'];
        if (!str_contains($text, '|') || rtrim($text, ' -:|') !== '') {
            return null;
        }

        // Placeholder <thead> keeps the parent's blockTableContinue() happy (it
        // appends rows to index 1); blockTableComplete() strips it afterwards.
        return [
            'alignments' => $this->parseTableAlignments($text),
            'headerless' => true,
            'element' => [
                'name' => 'table',
                'handler' => 'elements',
                'text' => [
                    ['name' => 'thead', 'handler' => 'elements', 'text' => []],
                    ['name' => 'tbody', 'handler' => 'elements', 'text' => []],
                ],
            ],
        ];
    }

    /**
     * Captures two opt-in trailing lines that immediately follow a table and
     * attaches them to the block (blockTableComplete() renders them):
     *  - a `[Caption]` line  -> `<caption>`
     *  - a `{.class #id}` (or kramdown `{:.class}`) line -> attributes on `<table>`
     * Everything else defers to the parent row parser.
     *
     * @param array $Line
     * @param array $Block
     * @return array|null
     */
    protected function blockTableContinue($Line, array $Block)
    {
        if (!isset($Block['interrupted'])) {
            $trimmed = trim((string)$Line['text']);

            if ($this->table_captions && !isset($Block['caption'])
                && $trimmed !== '' && $trimmed[0] === '['
                && preg_match('/^\[(.+?)\](?:\[.+?\])?$/', $trimmed, $m)) {
                $Block['caption'] = $m[1];

                return $Block;
            }

            if ($this->table_attributes && !isset($Block['table_attributes'])
                && $trimmed !== '' && $trimmed[0] === '{') {
                $attributes = $this->parseTableAttributes($trimmed);
                if ($attributes !== null) {
                    $Block['table_attributes'] = $attributes;

                    return $Block;
                }
            }

            if ($this->table_multiline) {
                $continues = $trimmed !== '' && str_ends_with($trimmed, '\\');

                // A previous row armed continuation: merge this line's cells
                // column-wise into the last row instead of starting a new one.
                if (!empty($Block['table_continue'])) {
                    $cells = $this->parseTableRowCells($continues ? rtrim(substr($trimmed, 0, -1)) : $trimmed);
                    $Block = $this->mergeTableContinuation($Block, $cells);
                    $Block['table_continue'] = $continues;

                    return $Block;
                }

                // A normal row that ends in `\` opts into continuation: let the
                // parent build the row from the de-backslashed line, then arm it.
                if ($continues && str_contains($trimmed, '|')) {
                    $LineStripped = $Line;
                    $LineStripped['text'] = rtrim(substr(rtrim((string)$Line['text']), 0, -1));
                    $result = parent::blockTableContinue($LineStripped, $Block);
                    if ($result !== null) {
                        $result['table_continue'] = true;

                        return $result;
                    }
                }
            }
        }

        return parent::blockTableContinue($Line, $Block);
    }

    /**
     * Split a table row into its trimmed cell strings (mirrors the parent's
     * cell-extraction regex so continuation rows tokenize identically).
     *
     * @param string $text
     * @return array
     */
    private function parseTableRowCells(string $text)
    {
        $row = trim(trim($text), '|');
        preg_match_all('/(?:(\\\\[|])|[^|`]|`[^`]+`|`)+/', $row, $matches);

        return array_map('trim', $matches[0]);
    }

    /**
     * Append a continuation row's cells onto the last body row, joining each
     * non-empty cell to the one above it with a `<br>` (multi-line cells).
     *
     * @param array $Block
     * @param array $cells
     * @return array
     */
    private function mergeTableContinuation(array $Block, array $cells)
    {
        // Rows live in the tbody section (index 1 in the parent's layout).
        if (!isset($Block['element']['text'][1]['text']) || !is_array($Block['element']['text'][1]['text'])) {
            return $Block;
        }
        $rows = &$Block['element']['text'][1]['text'];
        $last = count($rows) - 1;
        if ($last < 0 || !isset($rows[$last]['text']) || !is_array($rows[$last]['text'])) {
            unset($rows);

            return $Block;
        }

        $tds = &$rows[$last]['text'];
        foreach ($cells as $i => $cell) {
            if ($cell === '' || !isset($tds[$i])) {
                continue;
            }
            $existing = (string)($tds[$i]['text'] ?? '');
            $tds[$i]['text'] = $existing === '' ? $cell : $existing . '<br>' . $cell;
        }
        unset($tds, $rows);

        return $Block;
    }

    /**
     * Finalize a parsed table: apply opt-in colspan, drop the placeholder
     * header of a header-less table, and inject a captured caption as the first
     * child (HTML requires `<caption>` to precede the rows). Standard GFM tables
     * with all extensions off pass through untouched.
     *
     * @param array $Block
     * @return array
     */
    protected function blockTableComplete(array $Block)
    {
        if (!isset($Block['element']['text']) || !is_array($Block['element']['text'])) {
            return $Block;
        }

        // Colspan: an empty cell merges into the cell on its left (incrementing
        // its colspan), the MultiMarkdown convention.
        if ($this->table_colspan) {
            foreach ($Block['element']['text'] as &$section) {
                if (!isset($section['text']) || !is_array($section['text'])) {
                    continue;
                }
                foreach ($section['text'] as &$row) {
                    if (isset($row['text']) && is_array($row['text'])) {
                        $row['text'] = $this->mergeColspanCells($row['text']);
                    }
                }
                unset($row);
            }
            unset($section);
        }

        // Header-less: remove the empty placeholder <thead> created in blockTable().
        if (!empty($Block['headerless'])) {
            $Block['element']['text'] = array_values(array_filter(
                $Block['element']['text'],
                static fn($section) => ($section['name'] ?? null) !== 'thead'
            ));
        }

        // Caption: prepend as the first child of the table.
        if (isset($Block['caption']) && $Block['caption'] !== '') {
            array_unshift($Block['element']['text'], [
                'name' => 'caption',
                'handler' => 'line',
                'text' => $Block['caption'],
            ]);
        }

        // Attributes: apply captured class/id to the <table> element itself.
        if (isset($Block['table_attributes']) && is_array($Block['table_attributes'])) {
            $attributes = $Block['element']['attributes'] ?? [];
            if (isset($Block['table_attributes']['class'])) {
                $existing = isset($attributes['class']) ? $attributes['class'] . ' ' : '';
                $attributes['class'] = $existing . $Block['table_attributes']['class'];
            }
            if (isset($Block['table_attributes']['id'])) {
                $attributes['id'] = $Block['table_attributes']['id'];
            }
            if ($attributes !== []) {
                $Block['element']['attributes'] = $attributes;
            }
        }

        return $Block;
    }

    /**
     * Parse a trailing `{.class #id}` / `{:.class #id}` line into a safe
     * attribute set for a `<table>`. Accepts the `.class` / `#id` shortcuts and
     * explicit `class="..."` / `id="..."` only (kramdown's leading colon is
     * optional); any other token rejects the whole line so literal `{...}`
     * content (Twig tags, prose) is never swallowed. Returns null when the line
     * is not a pure, recognised attribute block.
     *
     * @param string $line
     * @return array|null
     */
    private function parseTableAttributes(string $line)
    {
        if (!preg_match('/^\{:?\s*(.*?)\s*\}$/', $line, $m) || $m[1] === '') {
            return null;
        }
        $body = $m[1];

        // A valid token is `.name`, `#name`, or class="..."/id="..." (either quote).
        $token = '([.#][^\s"\'.#]+)|((?:class|id)\s*=\s*(?:"[^"]*"|\'[^\']*\'))';

        // If anything other than valid tokens / whitespace remains, this is not
        // an attribute line — defer so the content is rendered as-is.
        if (trim(preg_replace('/' . $token . '/', '', $body)) !== '') {
            return null;
        }

        preg_match_all('/' . $token . '/', $body, $tokens, PREG_SET_ORDER);

        $classes = [];
        $attributes = [];
        foreach ($tokens as $t) {
            $tok = $t[0];
            if ($tok[0] === '.') {
                $classes[] = substr($tok, 1);
            } elseif ($tok[0] === '#') {
                $attributes['id'] = substr($tok, 1);
            } else {
                [$key, $value] = explode('=', $tok, 2);
                $value = trim(trim($value), '"\'');
                if (trim($key) === 'class') {
                    $classes = array_merge($classes, preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY));
                } else {
                    $attributes['id'] = $value;
                }
            }
        }

        if ($classes !== []) {
            $attributes['class'] = implode(' ', $classes);
        }

        return $attributes !== [] ? $attributes : null;
    }

    /**
     * Parse a table divider row into a per-column alignment list (mirrors the
     * parent's inline logic so header-less tables align identically).
     *
     * @param string $divider
     * @return array
     */
    private function parseTableAlignments(string $divider)
    {
        $alignments = [];
        $divider = trim(trim($divider), '|');

        foreach (explode('|', $divider) as $cell) {
            $cell = trim($cell);
            if ($cell === '') {
                continue;
            }

            $alignment = null;
            if ($cell[0] === ':') {
                $alignment = 'left';
            }
            if (str_ends_with($cell, ':')) {
                $alignment = $alignment === 'left' ? 'center' : 'right';
            }

            $alignments[] = $alignment;
        }

        return $alignments;
    }

    /**
     * Merge empty cells into the preceding cell as colspan.
     *
     * @param array $cells
     * @return array
     */
    protected function mergeColspanCells(array $cells)
    {
        $merged = [];
        foreach ($cells as $cell) {
            $isEmpty = trim((string)($cell['text'] ?? '')) === '';
            if ($isEmpty && $merged !== []) {
                $last = count($merged) - 1;
                $span = (int)($merged[$last]['attributes']['colspan'] ?? 1) + 1;
                $merged[$last]['attributes']['colspan'] = (string)$span;
                continue;
            }
            $merged[] = $cell;
        }

        return $merged;
    }

    /**
     * For extending this class via plugins
     *
     * @param string $method
     * @param array $args
     * @return mixed|null
     */
    #[\ReturnTypeWillChange]
    public function __call($method, $args)
    {
        // 1. Legacy closure path (highest priority — must not be shadowed by the routing below).
        if (isset($this->plugins[$method]) === true) {
            return call_user_func_array($this->plugins[$method], $args);
        }

        // 2. Extension API: route the engine's block/inline dispatch to a registered handler object.
        if (str_starts_with($method, 'block')) {
            foreach (['Complete', 'Continue'] as $suffix) {
                if (str_ends_with($method, $suffix)) {
                    $handler = $this->block_handlers[substr($method, 5, -strlen($suffix))] ?? null;
                    if ($handler !== null) {
                        $fn = 'block' . $suffix;
                        return method_exists($handler, $fn) ? $handler->{$fn}(...$args) : null;
                    }
                }
            }
            $handler = $this->block_handlers[substr($method, 5)] ?? null;
            if ($handler !== null) {
                return method_exists($handler, 'block') ? $handler->block(...$args) : null;
            }
        } elseif (str_starts_with($method, 'inline')) {
            $handler = $this->inline_handlers[substr($method, 6)] ?? null;
            if ($handler !== null) {
                return method_exists($handler, 'inline') ? $handler->inline(...$args) : null;
            }
        }

        // 3. Legacy dynamic-property fallback.
        if (isset($this->{$method}) === true) {
            return call_user_func_array($this->{$method}, $args);
        }

        return null;
    }

    public function __set($name, $value)
    {
        if (is_callable($value)) {
            $this->plugins[$name] = $value;
        }

    }


}
