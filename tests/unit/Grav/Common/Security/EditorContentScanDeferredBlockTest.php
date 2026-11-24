<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Page\Page;
use Grav\Common\Security;

/**
 * Class EditorContentScanDeferredBlockTest
 *
 * The save-time scan of editor content Twig (Security::detectXssInEditorContent)
 * scans the string the sandboxed render returns, so that string has to be the
 * whole output. A `{% block x deferred %}` opens an output buffer that stays
 * open until its template ends; inside a capture (`set`, `apply`) the capture
 * closed that buffer in place of its own, and the render returned only its tail
 * while the rest went to the client from a buffer nothing scanned. Sandboxed
 * content has no deferred blocks: `deferred` compiles to a plain block there.
 */
class EditorContentScanDeferredBlockTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var string */
    protected $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->grav['locator']->addPath('theme', '', 'tests/fake/twig-first-site/user/themes/testing', false);
        $this->grav['twig']->init();
        $this->dir = sys_get_temp_dir() . '/grav-scan-deferred-block-' . uniqid('', true) . '/notes';
        Folder::create($this->dir);
    }

    protected function tearDown(): void
    {
        Folder::delete(dirname($this->dir));
        parent::tearDown();
    }

    protected function page(string $body): Page
    {
        $file = $this->dir . '/default.md';
        file_put_contents($file, "---\ntitle: Notes\nprocess:\n  twig: true\n---\n{$body}\n");

        $page = new Page();
        $page->init(new \SplFileInfo($file));

        return $page;
    }

    public static function providerCapturedDeferredBlocks(): array
    {
        return [
            'set' => ['{% set a %}{% block a deferred %}{% endblock %}{% endset %}'],
            // A save through the API renders the body twice in one request.
            'two sets' => ['{% set a %}{% block a deferred %}{% endblock %}{% endset %}{% set b %}{% block b deferred %}{% endblock %}{% endset %}'],
            'apply' => ['{% apply upper %}{% block a deferred %}{% endblock %}{% endapply %}'],
        ];
    }

    /**
     * @dataProvider providerCapturedDeferredBlocks
     */
    public function testDetectXssInEditorContent_CapturedDeferredBlockDoesNotHideMarkup(string $capture): void
    {
        $body = "<div>\n<img src=x o{{ 'n' }}error=alert(1)>\n{$capture}\n</div>";
        $page = $this->page($body);

        $level = ob_get_level();
        try {
            $first = Security::detectXssInEditorContent($body, $page);
            $firstLevel = ob_get_level();
            $second = Security::detectXssInEditorContent($body, $page);
            $secondLevel = ob_get_level();
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }

        self::assertSame($level, $firstLevel, 'The content render left an output buffer open');
        self::assertSame('on_events', $first, 'The scan must see the markup in front of the captured block');
        self::assertSame($level, $secondLevel, 'The second content render left an output buffer open');
        self::assertSame('on_events', $second);
    }

    public function testDetectXssInEditorContent_CleanContentWithADeferredBlockStillSaves(): void
    {
        $body = "<p>{% block note deferred %}{{ 'a' ~ 'b' }}{% endblock %}</p>";
        $page = $this->page($body);

        self::assertNull(Security::detectXssInEditorContent($body, $page));
        self::assertSame('<p>ab</p>', $this->grav['twig']->processPage($page, $body));
    }
}
