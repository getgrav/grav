<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Markdown\Element;
use Grav\Common\Markdown\BlockResult;
use Grav\Common\Markdown\Parsedown;
use Grav\Common\Markdown\Extension\AbstractMarkdownExtension;
use Grav\Common\Markdown\Extension\BlockContinuableInterface;
use Grav\Common\Markdown\Extension\BlockHandlerInterface;
use Grav\Common\Markdown\Extension\InlineHandlerInterface;
use Grav\Common\Markdown\Extension\MarkdownExtensionRegistry;
use Grav\Common\Page\Markdown\Excerpts;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Class MarkdownExtensionTest
 *
 * Exercises the formal extension API (registry + handler objects + Element
 * builder) and proves the legacy closure-injection path still works unchanged.
 */
class MarkdownExtensionTest extends \PHPUnit\Framework\TestCase
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

    protected function parser(): Parsedown
    {
        $defaults = ['markdown' => ['extra' => false], 'images' => []];
        $page = $this->grav['pages']->find('/item2/item2-2');

        return new Parsedown(new Excerpts($page, $defaults));
    }

    /**
     * A continuable block registered through the new API renders correctly,
     * including its multi-line body and nested inline markdown.
     */
    public function testNewApiContinuableBlock(): void
    {
        $parsedown = $this->parser();
        $extension = new class extends AbstractMarkdownExtension implements BlockHandlerInterface, BlockContinuableInterface {
            public function getName(): string { return 'note'; }
            public function register(MarkdownExtensionRegistry $r): void {
                $r->registerBlock('Note', '@', $this, ['index' => 0]);
            }
            public function block(array $line, ?array $block = null): ?array {
                if (preg_match('/^@note\s*$/', $line['text'])) {
                    return BlockResult::fromElement(Element::div()->addClass('note')->setRawLines([]))
                        ->set('note', true)->toArray();
                }
                return null;
            }
            public function blockContinue(array $line, array $block): ?array {
                if (isset($block['interrupted']) || empty($block['note'])) {
                    return null;
                }
                $block['element']['text'][] = $line['body'];
                return $block;
            }
        };

        (new MarkdownExtensionRegistry($parsedown))->add($extension);

        $html = $parsedown->text("@note\nhello **world**\nsecond line");
        self::assertStringContainsString('<div class="note">', $html);
        self::assertStringContainsString('<strong>world</strong>', $html);
        self::assertStringContainsString('second line', $html);
    }

    /**
     * An inline element registered through the new API renders correctly.
     */
    public function testNewApiInline(): void
    {
        $parsedown = $this->parser();
        $extension = new class extends AbstractMarkdownExtension implements InlineHandlerInterface {
            public function getName(): string { return 'mark'; }
            public function register(MarkdownExtensionRegistry $r): void {
                $r->registerInline('Cite', '@', $this);
            }
            public function inline(array $excerpt): ?array {
                if (preg_match('/^@@(?=\S)(.+?)@@/', $excerpt['text'], $m)) {
                    return ['extent' => strlen($m[0]), 'element' => Element::create('cite')->setInlineText($m[1])->toArray()];
                }
                return null;
            }
        };

        (new MarkdownExtensionRegistry($parsedown))->add($extension);

        self::assertSame('<p>a <cite>b c</cite> d</p>', $parsedown->text('a @@b c@@ d'));
    }

    /**
     * A disabled extension is not registered.
     */
    public function testDisabledExtensionIsSkipped(): void
    {
        $parsedown = $this->parser();
        $extension = new class extends AbstractMarkdownExtension implements InlineHandlerInterface {
            public function getName(): string { return 'mark'; }
            public function isEnabled(): bool { return false; }
            public function register(MarkdownExtensionRegistry $r): void {
                $r->registerInline('Cite', '@', $this);
            }
            public function inline(array $excerpt): ?array {
                return ['extent' => 2, 'element' => ['name' => 'cite']];
            }
        };

        (new MarkdownExtensionRegistry($parsedown))->add($extension);

        self::assertSame('<p>a @@b@@ d</p>', $parsedown->text('a @@b@@ d'));
    }

    /**
     * Backward compatibility: the legacy closure-injection path (assign a
     * closure to $markdown->blockX + addBlockType) still works after the
     * __call routing changes.
     */
    public function testLegacyClosureBlockStillWorks(): void
    {
        $parsedown = $this->parser();

        $parsedown->blockLegacy = function ($line) {
            if (preg_match('/^%legacy\s*$/', $line['text'])) {
                return [
                    'legacy' => true,
                    'element' => ['name' => 'div', 'handler' => 'lines', 'attributes' => ['class' => 'legacy'], 'text' => []],
                ];
            }
            return null;
        };
        $parsedown->blockLegacyContinue = function ($line, array $block) {
            if (isset($block['interrupted']) || empty($block['legacy'])) {
                return null;
            }
            $block['element']['text'][] = $line['body'];
            return $block;
        };
        $parsedown->addBlockType('%', 'Legacy', true, false, 0);

        $html = $parsedown->text("%legacy\nkeep **this**");
        self::assertStringContainsString('<div class="legacy">', $html);
        self::assertStringContainsString('<strong>this</strong>', $html);
    }

    /**
     * The new handler-object path and the legacy closure path coexist in one
     * parser without interfering.
     */
    public function testNewApiAndLegacyCoexist(): void
    {
        $parsedown = $this->parser();

        // New API inline
        $inline = new class extends AbstractMarkdownExtension implements InlineHandlerInterface {
            public function getName(): string { return 'mark'; }
            public function register(MarkdownExtensionRegistry $r): void { $r->registerInline('Cite', '@', $this); }
            public function inline(array $excerpt): ?array {
                if (preg_match('/^@@(?=\S)(.+?)@@/', $excerpt['text'], $m)) {
                    return ['extent' => strlen($m[0]), 'element' => Element::create('cite')->setInlineText($m[1])->toArray()];
                }
                return null;
            }
        };
        (new MarkdownExtensionRegistry($parsedown))->add($inline);

        // Legacy inline via closure
        $parsedown->inlineLegacyIns = function ($excerpt) {
            if (preg_match('/^\+\+(?=\S)(.+?)\+\+/', $excerpt['text'], $m)) {
                return ['extent' => strlen($m[0]), 'element' => ['name' => 'ins', 'text' => $m[1], 'handler' => 'line']];
            }
            return null;
        };
        $parsedown->addInlineType('+', 'LegacyIns');

        self::assertSame('<p><cite>new</cite> and <ins>old</ins></p>', $parsedown->text('@@new@@ and ++old++'));
    }
}
