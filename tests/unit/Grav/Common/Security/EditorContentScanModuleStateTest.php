<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Page\Page;
use Grav\Common\Security;

/**
 * Class EditorContentScanModuleStateTest
 *
 * The save-time scan of editor content Twig (Security::detectXssInEditorContent)
 * must not depend on whether the page is a module when it is saved. A module
 * runs its body Twig regardless of `process.twig`, and a page becomes a module
 * after the save by an ordinary move or copy to a `_` folder, or by a
 * `slug: _name` header. A body saved with Twig off therefore has to be scanned
 * as though its Twig runs.
 */
class EditorContentScanModuleStateTest extends \PHPUnit\Framework\TestCase
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
        $this->dir = sys_get_temp_dir() . '/grav-scan-module-state-' . uniqid('', true) . '/01.hero';
        Folder::create($this->dir);
    }

    protected function tearDown(): void
    {
        Folder::delete(dirname($this->dir));
        parent::tearDown();
    }

    protected function page(string $frontmatter, string $body): Page
    {
        $file = $this->dir . '/text.md';
        file_put_contents($file, "---\n{$frontmatter}\n---\n{$body}\n");

        $page = new Page();
        $page->init(new \SplFileInfo($file));

        return $page;
    }

    public function testDetectXssInEditorContent_TwigOffPageIsScannedAsAModule(): void
    {
        $body = "<img src=x o{{ 'nerror' ~ '=alert(1)' }}>";
        $page = $this->page("title: Hero\nprocess:\n  twig: false\ntwig_first: true", $body);

        self::assertFalse($page->isModule(), 'Fixture: the page is not a module when it is saved');
        self::assertFalse(Security::willProcessContentTwig($page), 'Fixture: Twig does not run on the page as saved');

        self::assertSame(
            'on_events',
            Security::detectXssInEditorContent($body, $page),
            'A move to a `_` folder makes this body a module that renders its Twig, so the save must scan it'
        );
    }

    public function testDetectXssInEditorContent_TwigOffPageWithAModuleSlugHeaderIsScanned(): void
    {
        $body = "<img src=x o{{ 'nerror' ~ '=alert(1)' }}>";
        $page = $this->page("title: Hero\nslug: _hero\nprocess:\n  twig: false", $body);

        self::assertSame('on_events', Security::detectXssInEditorContent($body, $page));
    }

    public function testDetectXssInEditorContent_CleanTwigOffPageStillSaves(): void
    {
        $body = "Use `{{ page.title }}` to print the title. {{ 'safe' ~ ' text' }}";
        $page = $this->page("title: Docs\nprocess:\n  twig: false", $body);

        self::assertNull(Security::detectXssInEditorContent($body, $page));
    }
}
