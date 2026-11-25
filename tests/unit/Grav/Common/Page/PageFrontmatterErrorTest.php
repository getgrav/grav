<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Page;

/**
 * The page shown for a file whose frontmatter cannot be parsed quotes the parser's message, and that
 * message quotes the failing line of the file. It is shown as text, whatever the line holds.
 */
class PageFrontmatterErrorTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav $grav */
    protected $grav;

    /** @var string */
    protected $folder;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $this->folder = sys_get_temp_dir() . '/grav-frontmatter-error-' . bin2hex(random_bytes(4));
        mkdir($this->folder . '/01.broken', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->folder . '/01.broken/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->folder . '/01.broken');
        @rmdir($this->folder);
        parent::tearDown();
    }

    protected function errorPage(string $frontmatter): Page
    {
        $filename = $this->folder . '/01.broken/default.md';
        file_put_contents($filename, "---\n{$frontmatter}\n---\n\nbody\n");

        $page = new Page();
        $page->init(new SplFileInfo($filename));
        $page->header();

        return $page;
    }

    public function testParserMessageIsShownAsText(): void
    {
        $page = $this->errorPage("title: T\nbad: [a, <img src=x onerror=alert(1)> {{ 7 * 191 }} **b**");

        $raw = $page->rawMarkdown();
        self::assertStringContainsString('Error: Invalid Frontmatter', $raw);
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $raw);
        self::assertStringContainsString('&#123;&#123; 7 &#42;', $raw);
        self::assertStringNotContainsString('**<img', $raw);
        // The only unescaped copy of the line is the file text, which stands in a code fence.
        self::assertSame(1, substr_count($raw, '<img src=x onerror=alert(1)>'));
    }
}
