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
}
