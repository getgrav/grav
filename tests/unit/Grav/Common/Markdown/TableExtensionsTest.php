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
}
