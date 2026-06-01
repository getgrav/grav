<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Markdown\Parsedown;
use Grav\Common\Page\Markdown\Excerpts;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Class TableExtensionsTest
 *
 * Covers the opt-in (non-GFM) table extensions.
 */
class TableExtensionsTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav $grav */
    protected $grav;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->grav['config']->set('system.languages.supported', []);

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $locator->addPath('page', '', 'tests/fake/nested-site/user/pages', false);
        $this->grav['pages']->init();
    }

    protected function parser(array $tables = []): Parsedown
    {
        $markdown = ['extra' => false];
        if ($tables !== []) {
            $markdown['tables'] = $tables;
        }
        $page = $this->grav['pages']->find('/item2/item2-2');

        return new Parsedown(new Excerpts($page, ['markdown' => $markdown, 'images' => []]));
    }

    protected function table(): string
    {
        return "| a | b | c |\n| --- | --- | --- |\n| 1 |  | 3 |";
    }

    /**
     * By default, an empty cell stays an empty cell (standard GFM).
     */
    public function testEmptyCellByDefault(): void
    {
        $html = $this->parser()->text($this->table());
        self::assertStringContainsString('<td></td>', $html);
        self::assertStringNotContainsString('colspan', $html);
    }

    /**
     * With colspan enabled, an empty cell merges into the cell on its left.
     */
    public function testColspanMergesEmptyCell(): void
    {
        $html = $this->parser(['colspan' => true])->text($this->table());
        self::assertStringContainsString('colspan="2"', $html);
        self::assertStringNotContainsString('<td></td>', $html);
    }

    /**
     * Three empty cells after a value produce a colspan of 4.
     */
    public function testColspanAccumulates(): void
    {
        $html = $this->parser(['colspan' => true])->text("| a | b | c | d |\n| - | - | - | - |\n| x |  |  |  |");
        self::assertStringContainsString('colspan="4"', $html);
    }

    /**
     * A normal table is unaffected when colspan is on but there are no empty cells.
     */
    public function testNormalTableUnaffectedWithColspanOn(): void
    {
        $on = $this->parser(['colspan' => true])->text("| a | b |\n| - | - |\n| 1 | 2 |");
        $off = $this->parser()->text("| a | b |\n| - | - |\n| 1 | 2 |");
        self::assertSame($off, $on);
    }

    /**
     * By default a divider-first block is not treated as a table.
     */
    public function testHeaderlessDisabledByDefault(): void
    {
        $html = $this->parser()->text("| - | - |\n| 1 | 2 |");
        self::assertStringNotContainsString('<table>', $html);
    }

    /**
     * With the feature on, a divider-first block renders as a tbody-only table.
     */
    public function testHeaderlessRendersTbodyOnly(): void
    {
        $html = $this->parser(['headerless' => true])->text("| - | - |\n| 1 | 2 |");
        self::assertStringContainsString('<table>', $html);
        self::assertStringContainsString('<tbody>', $html);
        self::assertStringContainsString('<td>1</td>', $html);
        self::assertStringNotContainsString('<thead>', $html);
        self::assertStringNotContainsString('<th>', $html);
    }

    /**
     * Header-less tables still honour per-column alignment from the divider.
     */
    public function testHeaderlessKeepsAlignment(): void
    {
        $html = $this->parser(['headerless' => true])->text("| :-- | --: |\n| a | b |");
        self::assertStringContainsString('text-align: left;', $html);
        self::assertStringContainsString('text-align: right;', $html);
    }

    /**
     * A live paragraph directly above a divider is left intact, not split into a table.
     */
    public function testHeaderlessLeavesProseAbove(): void
    {
        $html = $this->parser(['headerless' => true])->text("Hello\n| - | - |");
        self::assertStringNotContainsString('<table>', $html);
        self::assertStringContainsString('Hello', $html);
    }

    /**
     * By default a trailing `[Caption]` line is not a caption.
     */
    public function testCaptionDisabledByDefault(): void
    {
        $html = $this->parser()->text("| a | b |\n| - | - |\n| 1 | 2 |\n[My caption]");
        self::assertStringNotContainsString('<caption>', $html);
        self::assertStringContainsString('<table>', $html);
    }

    /**
     * With the feature on, a trailing `[Caption]` line becomes a <caption> first child.
     */
    public function testCaptionRendered(): void
    {
        $html = $this->parser(['captions' => true])->text("| a | b |\n| - | - |\n| 1 | 2 |\n[My caption]");
        self::assertStringContainsString('<caption>My caption</caption>', $html);
        self::assertLessThan(strpos($html, '<thead>'), strpos($html, '<caption>'));
    }

    /**
     * Caption text is parsed as inline markdown.
     */
    public function testCaptionParsesInline(): void
    {
        $html = $this->parser(['captions' => true])->text("| a |\n| - |\n| 1 |\n[A **bold** caption]");
        self::assertStringContainsString('<caption>A <strong>bold</strong> caption</caption>', $html);
    }

    /**
     * Header-less and captions compose: caption renders above a thead-less table.
     */
    public function testHeaderlessWithCaption(): void
    {
        $html = $this->parser(['headerless' => true, 'captions' => true])
            ->text("| - | - |\n| 1 | 2 |\n[Cap]");
        self::assertStringContainsString('<caption>Cap</caption>', $html);
        self::assertStringContainsString('<tbody>', $html);
        self::assertStringNotContainsString('<thead>', $html);
        self::assertLessThan(strpos($html, '<tbody>'), strpos($html, '<caption>'));
    }

    protected function basicTable(): string
    {
        return "| a | b |\n| - | - |\n| 1 | 2 |";
    }

    /**
     * By default a trailing `{.class}` line is not consumed as table attributes.
     */
    public function testAttributesDisabledByDefault(): void
    {
        $html = $this->parser()->text($this->basicTable() . "\n{.striped}");
        self::assertStringContainsString('<table>', $html);
        self::assertStringNotContainsString('class="striped"', $html);
    }

    /**
     * The `.class` shortcut sets one or more classes on the <table>.
     */
    public function testAttributesClassShortcut(): void
    {
        $html = $this->parser(['attributes' => true])->text($this->basicTable() . "\n{.striped .responsive}");
        self::assertStringContainsString('<table class="striped responsive">', $html);
    }

    /**
     * The `#id` shortcut sets the id on the <table>.
     */
    public function testAttributesIdShortcut(): void
    {
        $html = $this->parser(['attributes' => true])->text($this->basicTable() . "\n{#sales}");
        self::assertStringContainsString('id="sales"', $html);
    }

    /**
     * The kramdown leading-colon form is also accepted.
     */
    public function testAttributesColonForm(): void
    {
        $html = $this->parser(['attributes' => true])->text($this->basicTable() . "\n{:.striped}");
        self::assertStringContainsString('<table class="striped">', $html);
    }

    /**
     * Explicit class="..." with multiple values is supported.
     */
    public function testAttributesExplicitClass(): void
    {
        $html = $this->parser(['attributes' => true])->text($this->basicTable() . "\n{class=\"foo bar\" #t}");
        self::assertStringContainsString('class="foo bar"', $html);
        self::assertStringContainsString('id="t"', $html);
    }

    /**
     * Arbitrary/dangerous attributes are rejected — the line is left as content.
     */
    public function testAttributesRejectsArbitrary(): void
    {
        $html = $this->parser(['attributes' => true])->text($this->basicTable() . "\n{onclick=\"x\"}");
        self::assertStringNotContainsString('onclick', $this->tableTag($html));
    }

    /**
     * A brace line that is not a pure attribute block is left untouched.
     */
    public function testAttributesIgnoresNonAttributeBraces(): void
    {
        $html = $this->parser(['attributes' => true])->text($this->basicTable() . "\n{ not an attribute }");
        self::assertStringContainsString('<table>', $html);
    }

    /**
     * Captions and attributes compose on the same table.
     */
    public function testAttributesWithCaption(): void
    {
        $html = $this->parser(['attributes' => true, 'captions' => true])
            ->text($this->basicTable() . "\n[Cap]\n{.striped}");
        self::assertStringContainsString('<table class="striped">', $html);
        self::assertStringContainsString('<caption>Cap</caption>', $html);
    }

    /**
     * By default a trailing backslash does not continue a row onto the next line.
     */
    public function testMultilineDisabledByDefault(): void
    {
        $html = $this->parser()->text("| a | b |\n| - | - |\n| 1 | 2 \\\n| 3 | 4 |");
        self::assertStringNotContainsString('<br>', $html);
        self::assertStringContainsString('<td>3</td>', $html);
    }

    /**
     * A normal table is unaffected when multiline is on but no row ends in `\`.
     */
    public function testMultilineNormalTableUnaffected(): void
    {
        $src = "| a | b |\n| - | - |\n| 1 | 2 |";
        $on = $this->parser(['multiline' => true])->text($src);
        $off = $this->parser()->text($src);
        self::assertSame($off, $on);
    }

    /**
     * A row ending in `\` merges the next line's cells into it (joined with <br>).
     */
    public function testMultilineMergesContinuation(): void
    {
        $html = $this->parser(['multiline' => true])
            ->text("| Name | Notes |\n| - | - |\n| Widget | First line \\\n| | second line |");
        self::assertStringContainsString('First line<br>second line', $html);
        self::assertStringNotContainsString('<td></td>', $html);
        self::assertSame(1, substr_count($html, '<td>Widget</td>'));
    }

    /**
     * Continuation accumulates across multiple trailing-backslash rows.
     */
    public function testMultilineAccumulatesAcrossRows(): void
    {
        $html = $this->parser(['multiline' => true])
            ->text("| Step | Detail |\n| - | - |\n| 1 | alpha \\\n| | beta \\\n| | gamma |");
        self::assertStringContainsString('alpha<br>beta<br>gamma', $html);
    }

    /**
     * Every column of a continued row merges, not just one.
     */
    public function testMultilineMergesAllColumns(): void
    {
        $html = $this->parser(['multiline' => true])
            ->text("| A | B |\n| - | - |\n| x1 | y1 \\\n| x2 | y2 |");
        self::assertStringContainsString('x1<br>x2', $html);
        self::assertStringContainsString('y1<br>y2', $html);
    }

    /**
     * Helper: return just the opening <table ...> tag from rendered HTML.
     */
    protected function tableTag(string $html): string
    {
        return preg_match('/<table[^>]*>/', $html, $m) ? $m[0] : '';
    }
}
