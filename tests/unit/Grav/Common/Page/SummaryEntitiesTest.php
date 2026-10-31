<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Flex\Types\Pages\PageObject;
use Grav\Common\Grav;
use Grav\Common\Helpers\Truncator;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Page;
use Grav\Framework\Flex\Flex;

/**
 * Class SummaryEntitiesTest
 *
 * Page::summary() and PageContentTrait::summary() return HTML, cut from the
 * rendered content, and themes print it raw (`{{ page.summary|raw }}`). The
 * summary must stay the same HTML as the content, only shorter: entities that
 * keep text apart from markup (`&lt;`, `&gt;`, `&amp;`, `&quot;`) stay as the
 * content has them, and only the non-ASCII characters the truncator turns into
 * entities come back as characters.
 *
 * Each case runs with a body shorter and longer than the summary size, as only
 * the latter goes through the truncator's DOM round trip.
 */
class SummaryEntitiesTest extends \PHPUnit\Framework\TestCase
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

        $this->folder = sys_get_temp_dir() . '/grav-summary-' . uniqid('', true);
        Folder::create($this->folder);
    }

    protected function tearDown(): void
    {
        Folder::delete($this->folder);
        parent::tearDown();
    }

    public static function pages(): array
    {
        // Takes the body past site.summary.size (300), so that summary() truncates.
        $filler = "\n\n" . str_repeat('Grav is a flat-file CMS. ', 20);

        return [
            'Page' => ['page', ''],
            'Page, truncated' => ['page', $filler],
            'Flex page' => ['flexPage', ''],
            'Flex page, truncated' => ['flexPage', $filler]
        ];
    }

    /**
     * @dataProvider pages
     */
    public function testEscapedTextStaysEscaped(string $type, string $filler): void
    {
        $summary = $this->{$type}('A tag: &lt;script&gt;alert(1)&lt;/script&gt; and `<img src=x onerror=alert(1)>`' . $filler)->summary();

        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $summary);
        self::assertStringContainsString('<code>&lt;img src=x onerror=alert(1)&gt;</code>', $summary);
    }

    /**
     * Escaped text with stray end tags inside it: libxml drops the end tags,
     * and the summary must not turn the escaped text that is left into a tag.
     *
     * @dataProvider pages
     */
    public function testStrayEndTagInsideEscapedTextDoesNotMakeATag(string $type, string $filler): void
    {
        $summary = $this->{$type}('&lt;scr</i>ipt&gt;alert(1)&lt;/scr</i>ipt&gt;' . $filler)->summary();

        self::assertStringNotContainsString('<script', $summary);
    }

    /**
     * @dataProvider pages
     */
    public function testTagfilterIsNotUndone(string $type, string $filler): void
    {
        $summary = $this->{$type}('Filtered: <script>alert(1)</script>' . $filler)->summary();

        self::assertStringNotContainsString('<script', $summary);
    }

    /**
     * @dataProvider pages
     */
    public function testQuoteStaysInsideItsAttribute(string $type, string $filler): void
    {
        $summary = $this->{$type}('<a href="/x" title="t&quot; onmouseover=&quot;alert(1)">link</a>' . $filler)->summary();

        $link = Truncator::htmlToDomDocument($summary)->getElementsByTagName('a')->item(0);

        self::assertSame('t" onmouseover="alert(1)', $link->getAttribute('title'));
        self::assertFalse($link->hasAttribute('onmouseover'));
    }

    /**
     * What the decode is there for: non-ASCII characters come back as characters.
     *
     * @dataProvider pages
     */
    public function testNonAsciiCharactersAreNotEntities(string $type, string $filler): void
    {
        $summary = $this->{$type}('ïntro … 中 😀' . $filler)->summary();

        self::assertStringStartsWith('<p>ïntro … 中 😀</p>', $summary);
    }

    protected function page(string $content): PageInterface
    {
        $file = $this->folder . '/default.md';
        file_put_contents($file, "---\ntitle: Summary\ncache_enable: false\nprocess:\n  twig: false\n---\n" . $content);

        $page = new Page();
        $page->init(new \SplFileInfo($file));

        return $page;
    }

    protected function flexPage(string $content): PageInterface
    {
        $flex = new Flex([], ['object' => $this->grav['config']->get('system.flex', [])]);
        $flex->addDirectoryType('pages', 'blueprints://flex/pages.yaml', ['enabled' => true]);

        return new PageObject(
            ['header' => ['title' => 'Summary', 'cache_enable' => false, 'process' => ['twig' => false]], 'markdown' => $content],
            'summary',
            $flex->getDirectory('pages')
        );
    }
}
