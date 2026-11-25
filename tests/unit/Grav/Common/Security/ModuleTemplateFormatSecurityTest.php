<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Page\Page;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Class ModuleTemplateFormatSecurityTest
 *
 * A module is printed into the document that is being rendered, so that
 * document's format picks the module's template. The module's own format (its
 * `template_format` and `append_url_extension` headers, the extension of the
 * URL) must not: with `template: default` it selected core's unescaped
 * `default.md.twig`, and with `template: error` the Error plugin's
 * `error.json.twig`, for output that lands in an HTML page.
 */
class ModuleTemplateFormatSecurityTest extends \PHPUnit\Framework\TestCase
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
        $this->grav['config']->set('system.languages.supported', []);
        $this->grav['language']->setLanguages([]);
        $this->grav['language']->init();
        $this->grav['uri']->initializeWithUrl('http://localhost/item1')->init();

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $locator->addPath('theme', '', 'tests/fake/twig-first-site/user/themes/testing', false);
        $locator->addPath('page', '', 'tests/fake/nested-site/user/pages', false);
        $this->grav['pages']->init();
        $this->grav['twig']->init();

        $this->dir = sys_get_temp_dir() . '/grav-module-template-format-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        Folder::delete($this->dir);
        parent::tearDown();
    }

    protected function page(string $folder, string $header): Page
    {
        Folder::create($this->dir . '/' . $folder);
        $file = $this->dir . '/' . $folder . '/notes.md';
        file_put_contents($file, "---\ntitle: Notes\n{$header}\n---\nBody\n");

        $page = new Page();
        $page->init(new SplFileInfo($file));

        return $page;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function moduleFormats(): array
    {
        return [
            'template_format md' => ["template: default\ntemplate_format: md"],
            'append_url_extension md' => ["template: default\nappend_url_extension: '.md'"],
            'template_format json' => ["template: module-format\ntemplate_format: json"],
            'append_url_extension json' => ["template: module-format\nappend_url_extension: '.json'"],
        ];
    }

    /**
     * @dataProvider moduleFormats
     */
    public function testModuleHeaderDoesNotPickTheTemplateFormat(string $header): void
    {
        $twig = $this->grav['twig'];
        $twig->setTemplate('module-format.json.twig', 'json');
        $twig->setTemplate('module-format.html.twig', 'html');

        $module = $this->page('_notes', $header);
        self::assertTrue($module->isModule());

        self::assertStringEndsWith('.html.twig', $twig->getPageTwigTemplate($module));
    }

    public function testModuleDoesNotTakeItsFormatFromTheUrl(): void
    {
        $twig = $this->grav['twig'];
        $twig->setTemplate('module-format.json.twig', 'json');
        $twig->setTemplate('module-format.html.twig', 'html');
        $this->grav['uri']->initializeWithUrl('http://localhost/item1.json')->init();

        $module = $this->page('_notes', 'template: module-format');
        self::assertSame('json', $module->templateFormat());

        self::assertSame('module-format.html.twig', $twig->getPageTwigTemplate($module));
    }

    public function testPageKeepsItsOwnFormat(): void
    {
        $twig = $this->grav['twig'];

        self::assertSame('default.md.twig', $twig->getPageTwigTemplate($this->page('notes', "template: default\ntemplate_format: md")));
    }

    public function testModuleRendersInTheFormatOfTheDocument(): void
    {
        $twig = $this->grav['twig'];
        $twig->setTemplate('module-format.json.twig', 'json');
        $twig->setTemplate('module-format.html.twig', 'html');
        // Stand-ins for a theme's modular page templates: they print their module.
        $twig->setTemplate('document.json.twig', '{{ module.content|raw }}');
        $twig->setTemplate('document.html.twig', '{{ module.content|raw }}');

        foreach (['json', 'html'] as $format) {
            $module = $this->page('_notes-' . $format, "template: module-format\ntemplate_format: json\ncache_enable: false");
            unset($this->grav['page']);
            $this->grav['page'] = $this->page('document-' . $format, "template: document\ncache_enable: false");

            self::assertSame($format, $twig->processSite($format, ['module' => $module]));
            // Only while that document renders.
            self::assertSame('module-format.html.twig', $twig->getPageTwigTemplate($module));
        }
    }
}
