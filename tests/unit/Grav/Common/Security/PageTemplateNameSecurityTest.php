<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Page\Page;
use Grav\Common\Twig\Sandbox\GravSourcePolicy;

/**
 * Class PageTemplateNameSecurityTest
 *
 * The Twig loader chain also holds the editor-authored strings processPage()
 * and processString() register (`@Page:<folder>`, `@Var:<string>`). Those are
 * rendered on filtered variables only. A template name an editor can choose (the
 * `template` header, the Email plugin's `template` parameter) must not get them
 * rendered on the raw ones.
 */
class PageTemplateNameSecurityTest extends \PHPUnit\Framework\TestCase
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
        $this->grav['config']->set('system.cache.redis.password', 'SECRET-REDIS');
        $this->grav['twig']->init();

        $this->dir = sys_get_temp_dir() . '/grav-page-template-name-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        Folder::delete($this->dir);
        parent::tearDown();
    }

    protected function page(string $folder, string $template): Page
    {
        Folder::create($this->dir . '/' . $folder);
        $file = $this->dir . '/' . $folder . '/default.md';
        file_put_contents($file, "---\ntitle: Notes\ntemplate: '{$template}'\n---\n{{ system.cache.redis.password }}\n");

        $page = new Page();
        $page->init(new SplFileInfo($file));

        return $page;
    }

    public function testSandboxedNamesAreTheSourcePolicyOnes(): void
    {
        self::assertTrue(GravSourcePolicy::isSandboxedName('@Page:/site/user/pages/notes.html.twig'));
        self::assertTrue(GravSourcePolicy::isSandboxedName('@Var:{{ system }}'));
        self::assertTrue(GravSourcePolicy::isSandboxedName('@EmailVar:subject'));
        self::assertFalse(GravSourcePolicy::isSandboxedName('@Template:{{ system }}'));
        self::assertFalse(GravSourcePolicy::isSandboxedName('@admin/partials/page.html.twig'));
        self::assertFalse(GravSourcePolicy::isSandboxedName('default.html.twig'));
        self::assertFalse(GravSourcePolicy::isSandboxedName(''));
    }

    public function testPageBodyIsNotAPageTemplate(): void
    {
        $twig = $this->grav['twig'];
        $page = $this->page('notes.html.twig', '@Page:' . $this->dir . '/notes');

        $twig->processPage($page, $page->rawMarkdown());
        self::assertTrue($twig->twig()->getLoader()->exists('@Page:' . $this->dir . '/notes.html.twig'));

        self::assertSame('default.html.twig', $twig->getPageTwigTemplate($page));
    }

    public function testPageBodyIsNotAPageTemplateInAnotherFormat(): void
    {
        $twig = $this->grav['twig'];
        $page = $this->page('notes.html.twig', '@Page:' . $this->dir . '/notes');

        $twig->processPage($page, $page->rawMarkdown());

        $format = 'json';
        self::assertStringStartsNotWith('@', $twig->getPageTwigTemplate($page, $format));
    }

    public function testModuleBodyIsNotAModuleTemplate(): void
    {
        $twig = $this->grav['twig'];
        $page = $this->page('_notes.html.twig', '@Page:' . $this->dir . '/_notes');
        self::assertTrue($page->isModule());

        $twig->processPage($page, $page->rawMarkdown(), false);
        self::assertTrue($twig->twig()->getLoader()->exists('@Page:' . $this->dir . '/_notes.html.twig'));

        self::assertSame('modular/default.html.twig', $twig->getPageTwigTemplate($page));
    }

    public function testStringTemplateIsNotAPageTemplate(): void
    {
        $twig = $this->grav['twig'];
        $twig->processString('{{ system.cache.redis.password }}.html.twig');

        $page = $this->page('notes', '@Var:{{ system.cache.redis.password }}');

        self::assertSame('default.html.twig', $twig->getPageTwigTemplate($page));
    }

    public function testRegisteredTemplateIsStillAPageTemplate(): void
    {
        $twig = $this->grav['twig'];
        $twig->setTemplate('plugin-page.html.twig', 'from a plugin');

        self::assertSame('plugin-page.html.twig', $twig->getPageTwigTemplate($this->page('notes', 'plugin-page')));
    }

    public function testProcessTemplateFiltersVariablesForStringTemplate(): void
    {
        $twig = $this->grav['twig'];
        $string = '[{{ system.cache.redis.password }}][{{ own }}]';

        self::assertSame('[][]', $twig->processString($string));
        self::assertSame('[][mine]', $twig->processTemplate('@Var:' . $string, ['own' => 'mine']));
    }

    public function testProcessTemplateKeepsVariablesForTrustedTemplate(): void
    {
        $twig = $this->grav['twig'];
        $twig->setTemplate('plugin-part.html.twig', '[{{ system.cache.redis.password }}]');

        self::assertSame('[SECRET-REDIS]', $twig->processTemplate('plugin-part.html.twig'));
    }
}
