<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Markdown\BlockResult;
use Grav\Common\Markdown\Element;
use Grav\Common\Markdown\Parsedown;
use Grav\Common\Page\Markdown\Excerpts;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Class ElementBuilderTest
 *
 * Proves the Element builder compiles to the exact array shape Parsedown
 * consumes, and renders byte-identical HTML to hand-built element arrays.
 */
class ElementBuilderTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav $grav */
    protected $grav;

    /** @var Parsedown $parsedown */
    protected $parsedown;

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

        $defaults = [
            'markdown' => [
                'extra'            => false,
                'auto_line_breaks' => false,
                'auto_url_links'   => false,
                'escape_markup'    => false,
                'special_chars'    => ['>' => 'gt', '<' => 'lt'],
            ],
            'images' => []
        ];

        $page = $this->grav['pages']->find('/item2/item2-2');
        $excerpts = new Excerpts($page, $defaults);
        $this->parsedown = new Parsedown($excerpts);
    }

    /**
     * The builder reproduces the exact nested array the github-markdown-alerts
     * plugin hand-builds today — both as an array and as rendered HTML.
     */
    public function testBuilderMatchesHandBuiltAlertStructure(): void
    {
        $hand = [
            'name' => 'div',
            'handler' => 'elements',
            'attributes' => ['class' => 'md-alert md-alert--note', 'dir' => 'auto'],
            'text' => [
                [
                    'name' => 'p',
                    'handler' => 'line',
                    'attributes' => ['class' => 'md-alert__title'],
                    'text' => 'Note',
                ],
                [
                    'name' => 'div',
                    'handler' => 'lines',
                    'attributes' => ['class' => 'md-alert__body'],
                    'text' => ['Hello **world**', 'second line'],
                ],
            ],
        ];

        $built = Element::div()
            ->addClass('md-alert md-alert--note')
            ->attr('dir', 'auto')
            ->setChildren([
                Element::p()->addClass('md-alert__title')->setInlineText('Note'),
                Element::div()->addClass('md-alert__body')->setRawLines(['Hello **world**', 'second line']),
            ])
            ->toArray();

        self::assertSame($hand, $built, 'compiled array must equal the hand-built array');
        self::assertSame(
            $this->parsedown->elementToHtml($hand),
            $this->parsedown->elementToHtml($built),
            'rendered HTML must be byte-identical'
        );
    }

    public function testInlineTextRendersMarkdown(): void
    {
        $built = Element::p()->addClass('x')->setInlineText('a **b** c')->toArray();
        self::assertSame(
            '<p class="x">a <strong>b</strong> c</p>',
            $this->parsedown->elementToHtml($built)
        );
    }

    public function testPlainTextIsEscaped(): void
    {
        $built = Element::span()->setText('a < b & c')->toArray();
        self::assertSame(
            '<span>a &lt; b &amp; c</span>',
            $this->parsedown->elementToHtml($built)
        );
    }

    public function testVoidElementWhenNoContent(): void
    {
        $built = Element::create('hr')->toArray();
        self::assertSame('<hr />', $this->parsedown->elementToHtml($built));
    }

    public function testAddClassMergesTokens(): void
    {
        $built = Element::div()->addClass('a')->addClass('b c')->attr('id', 'x')->toArray();
        self::assertSame(['class' => 'a b c', 'id' => 'x'], $built['attributes']);
    }

    public function testNullAttributeSkippedAtRender(): void
    {
        $built = Element::span()->attr('title', null)->setText('hi')->toArray();
        self::assertArrayHasKey('title', $built['attributes']);
        self::assertSame('<span>hi</span>', $this->parsedown->elementToHtml($built));
    }

    public function testBlockResultWrapsElementWithState(): void
    {
        $el = Element::div()->addClass('alert')->setRawLines([]);
        $result = BlockResult::fromElement($el)->with(['alert' => true, 'type' => 'note'])->toArray();

        self::assertSame(true, $result['alert']);
        self::assertSame('note', $result['type']);
        self::assertSame($el->toArray(), $result['element']);
    }
}
