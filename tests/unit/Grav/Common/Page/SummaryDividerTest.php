<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Flex\Types\Pages\PageObject;
use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Page;
use Grav\Framework\Flex\Flex;

/**
 * Class SummaryDividerTest
 *
 * The rendered summary divider (`<p>===</p>`) is cut out of the page content
 * after Markdown has run. It is only removed where Markdown puts it: as a block
 * of its own, between tags or at either end of the content. Inside raw HTML,
 * such as the middle of a tag name or attribute, it is left as written, so the
 * text on both sides is never joined into a different tag.
 *
 * Both Page::content() and the Flex PageContentTrait::processContent() copy
 * are covered.
 */
class SummaryDividerTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var string */
    protected $folder;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $this->folder = sys_get_temp_dir() . '/grav-divider-' . uniqid('', true);
        Folder::create($this->folder);
    }

    protected function tearDown(): void
    {
        Folder::delete($this->folder);
        parent::tearDown();
    }

    public static function pageTypes(): array
    {
        return ['Page' => ['page'], 'Flex page' => ['flexPage']];
    }

    /**
     * @dataProvider pageTypes
     */
    public function testDividerBetweenBlocksIsRemoved(string $type): void
    {
        $page = $this->{$type}("ïntro\n\n===\n\nrest");

        self::assertSame("<p>ïntro</p>\n\n<p>rest</p>", $page->content());
        self::assertSame('<p>ïntro</p>', trim($page->summary()));
    }

    /**
     * @dataProvider pageTypes
     */
    public function testEveryDividerIsRemoved(string $type): void
    {
        $page = $this->{$type}("===\n\n===\n\nintro\n\n===\n\nrest\n\n===");

        self::assertStringNotContainsString('===', $page->content());
        self::assertSame('', trim($page->summary()));
    }

    /**
     * @dataProvider pageTypes
     */
    public function testDividerInsideATagNameIsKept(string $type): void
    {
        $content = $this->{$type}('<section><di<p>===</p>v>text</di<p>===</p>v></section>')->content();

        self::assertStringNotContainsString('<div>', $content);
        self::assertStringNotContainsString('</div>', $content);
    }

    /**
     * @dataProvider pageTypes
     */
    public function testDividerInsideAnAttributeIsKept(string $type): void
    {
        $content = $this->{$type}("<section>\n<div ti<p>===</p>tle=x>x</div>\n</section>")->content();
        self::assertStringNotContainsString('title=', $content);

        // On lines of its own the attribute name would still be joined to what follows.
        $content = $this->{$type}("<section>\n<div title\n<p>===</p>\n=x>x</div>\n</section>")->content();
        self::assertDoesNotMatchRegularExpression('/title\s*=/', $content);
    }

    /**
     * @dataProvider pageTypes
     */
    public function testRealDividerDoesNotUnlockTheOthers(string $type): void
    {
        $page = $this->{$type}("intro\n\n===\n\n<section><p>===</p><p>===</p><di<p>===</p>v>text</section>");

        self::assertStringNotContainsString('<div>', $page->content());
        self::assertSame('<p>intro</p>', trim($page->summary()));
    }

    protected function page(string $content): PageInterface
    {
        $file = $this->folder . '/default.md';
        file_put_contents($file, "---\ntitle: Divider\ncache_enable: false\nprocess:\n  twig: false\n---\n" . $content);

        $page = new Page();
        $page->init(new \SplFileInfo($file));

        return $page;
    }

    protected function flexPage(string $content): PageInterface
    {
        $flex = new Flex([], ['object' => $this->grav['config']->get('system.flex', [])]);
        $flex->addDirectoryType('pages', 'blueprints://flex/pages.yaml', ['enabled' => true]);

        return new PageObject(
            ['header' => ['title' => 'Divider', 'cache_enable' => false, 'process' => ['twig' => false]], 'markdown' => $content],
            'divider',
            $flex->getDirectory('pages')
        );
    }
}
