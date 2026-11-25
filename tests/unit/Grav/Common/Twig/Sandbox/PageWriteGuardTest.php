<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Flex\Types\Pages\PageObject;
use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Page;
use Grav\Common\Security;
use Grav\Common\Twig\Sandbox\PageWriteGuard;
use Grav\Common\Twig\Sandbox\SandboxDefaults;
use Grav\Common\Twig\Twig;
use Grav\Framework\Flex\Flex;
use PHPUnit\Framework\TestCase;
use Twig\Sandbox\SecurityNotAllowedMethodError;

/**
 * Page accessors are setters too (`page.title()` reads, `page.title('x')`
 * writes) and the sandbox policy only sees the name of a call. Content may call
 * them with arguments on the page being rendered only: on any other page they
 * are refused, while calls whose arguments only choose what to read keep working.
 */
class PageWriteGuardTest extends TestCase
{
    /** @var Grav */
    private $grav;

    /** @var string */
    private $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $reflection = new ReflectionClass(Security::class);
        foreach (['twigSandboxPolicy', 'twigSandboxPolicyKey'] as $prop) {
            if ($reflection->hasProperty($prop)) {
                $reflection->getProperty($prop)->setValue(null, null);
            }
        }

        $this->grav = Fixtures::get('grav')();
        $this->grav['config']->set('system.home.alias', '/home');
        $this->grav['locator']->addPath('page', '', 'tests/fake/simple-site/user/pages', false);
        $this->grav['locator']->addPath('theme', '', 'tests/fake/twig-first-site/user/themes/testing', false);
        $this->grav['pages']->init();

        $this->dir = sys_get_temp_dir() . '/grav-page-write-guard-' . bin2hex(random_bytes(6));
        Folder::create($this->dir . '/partials');
        file_put_contents($this->dir . '/partials/retitle.html.twig', "{% do other.title('Trusted') %}{{ other.title }}");
    }

    protected function tearDown(): void
    {
        Folder::delete($this->dir);
        parent::tearDown();
    }

    // =========================================================================
    // Every page method that takes arguments is classified
    // =========================================================================

    public function testEveryAllowlistedPageMethodWithParametersIsClassified(): void
    {
        $row = null;
        foreach (SandboxDefaults::methods() as $entry) {
            if ($entry['class'] === PageInterface::class) {
                $row = $entry;
            }
        }
        self::assertNotNull($row, 'The PageInterface row of the method allowlist');

        $checked = 0;
        foreach (array_map('trim', explode(',', $row['methods'])) as $method) {
            foreach ([PageInterface::class, Page::class, PageObject::class] as $class) {
                if (!method_exists($class, $method) || 0 === (new ReflectionMethod($class, $method))->getNumberOfParameters()) {
                    continue;
                }
                $this->assertClassified($method, "{$class}::{$method}() is allowlisted for content and takes arguments");
                $checked++;
            }
        }
        self::assertGreaterThan(20, $checked);
    }

    public function testEveryPageInterfaceMethodWithParametersIsClassified(): void
    {
        foreach ((new ReflectionClass(PageInterface::class))->getMethods() as $method) {
            if ($method->getNumberOfParameters() > 0) {
                $this->assertClassified(strtolower($method->getName()), PageInterface::class . '::' . $method->getName() . '()');
            }
        }
    }

    public function testClassifiedNamesArePageMethods(): void
    {
        foreach (array_merge(PageWriteGuard::SETTER_METHODS, PageWriteGuard::READ_METHODS) as $method) {
            self::assertTrue(method_exists(Page::class, $method) || method_exists(PageObject::class, $method), $method);
            self::assertSame(strtolower($method), $method);
        }
    }

    // =========================================================================
    // Page content renders (Twig::processPage)
    // =========================================================================

    public function testOwnPageSettersKeepWorking(): void
    {
        $about = $this->page('/about');

        self::assertSame('Changed|Changed', $this->process($about, "{% do page.title('Changed') %}{{ page.title }}|{{ page.title() }}"));
        self::assertSame('x', $this->process($about, "{% do page.header({note: 'x'}) %}{{ page.header.note }}"));
        self::assertSame('Changed', $about->title());
    }

    /**
     * @dataProvider crossPageWriteProvider
     */
    public function testCrossPageSettersAreRefused(string $content): void
    {
        $about = $this->page('/about');
        $blog = $this->page('/blog');
        $header = (array) $blog->header();

        $output = $this->process($about, $content);

        self::assertStringStartsWith($content, $output, 'Refused content falls back to its source');
        self::assertSame('Blog', $blog->title());
        self::assertEquals($header, (array) $blog->header());
        foreach ($blog->children() as $child) {
            self::assertNotSame('Hacked', $child->title());
        }
    }

    public static function crossPageWriteProvider(): array
    {
        return [
            'find'                => ["{% do page.find('/blog').title('Hacked') %}ok"],
            'find header'         => ["{% do page.find('/blog').header({access: {site: {login: false}}}) %}ok"],
            'find order'          => ["{% do page.find('/blog').order(5) %}ok"],
            'reassigned page'     => ["{% set page = page.find('/blog') %}{% do page.title('Hacked') %}ok"],
            'parent'              => ["{% do page.find('/blog/post-one').parent().title('Hacked') %}ok"],
            'collection item'     => ["{% for p in page.find('/blog').collection({items: '@self.children'}) %}{% do p.title('Hacked') %}{% endfor %}ok"],
            'children item'       => ["{% for p in page.find('/blog').children %}{% do p.title('Hacked') %}{% endfor %}ok"],
            'attribute()'         => ["{{ attribute(page.find('/blog'), 'title', ['Hacked']) }}ok"],
            'dynamic name'        => ["{% set m = 'ti' ~ 'tle' %}{{ page.find('/blog').(m)('Hacked') }}ok"],
            'mixed case'          => ["{% do page.find('/blog').TITLE('Hacked') %}ok"],
            'theme partial'       => ["{% include 'partials/retitle.html.twig' with {other: page.find('/blog')} %}ok"],
        ];
    }

    public function testCrossPageReadsWithArgumentsKeepWorking(): void
    {
        $this->twig();
        $about = $this->page('/about');
        $blog = $this->page('/blog');

        $content = "{% set b = page.find('/blog') %}"
            . "{{ b.url(true) == blog_url ? 'url' }}|{{ b.summary(5) == blog_summary ? 'summary' }}|{{ b.value('header.title') }}"
            . "|{{ b.find('/about').title }}|{% for c in b.collection({items: '@self.children'}) %}{{ c.title }},{% endfor %}"
            . "|{{ b.adjacentSibling(1) is not null ? 'sibling' }}|{{ b.title }}";
        $content = strtr($content, ['blog_url' => "'" . $blog->url(true) . "'", 'blog_summary' => "'" . $blog->summary(5) . "'"]);

        $titles = '';
        foreach ($blog->collection(['items' => '@self.children']) as $child) {
            $titles .= $child->title() . ',';
        }

        self::assertSame(
            "url|summary|Blog|About|{$titles}|sibling|Blog",
            $this->process($about, $content)
        );
    }

    // =========================================================================
    // The guard itself
    // =========================================================================

    public function testOnlyThePageOnTopOfTheStackMayBeChanged(): void
    {
        $twig = $this->twig();
        $about = $this->page('/about');
        $blog = $this->page('/blog');
        $twig->setTemplate('@Page:guard-stack', "{% do other.title('Nested') %}{{ other.title }}");
        $render = fn () => $twig->twig()->render('@Page:guard-stack', ['page' => $about, 'other' => $blog]);

        // A page rendered from inside another one's content (`other.content`) may change itself.
        self::assertSame('Nested', PageWriteGuard::rendering($about, fn () => PageWriteGuard::rendering($blog, $render)));

        $blog->title('Blog');
        $this->expectException(SecurityNotAllowedMethodError::class);
        PageWriteGuard::rendering($blog, fn () => PageWriteGuard::rendering($about, $render));
    }

    public function testWithNoPageBeingRenderedEveryPageIsRefused(): void
    {
        $about = $this->page('/about');
        $content = "{% do page.title('x') %}ok";

        self::assertStringStartsWith($content, $this->twig()->processString($content, ['page' => $about]));
        self::assertSame('About', $about->title());
    }

    public function testFlexPages(): void
    {
        $twig = $this->twig();
        $flex = new Flex([], ['object' => $this->grav['config']->get('system.flex', [])]);
        $flex->addDirectoryType('pages', 'blueprints://flex/pages.yaml', ['enabled' => true]);
        $flexPage = $flex->getDirectory('pages')->createObject(['header' => ['title' => 'Flex'], 'markdown' => 'Text'], 'flex');
        $about = $this->page('/about');

        $twig->setTemplate('@Page:guard-flex-own', "{% do page.title('Own') %}{{ page.title }}|{{ other.url(true) is not empty ? 'url' }}");
        self::assertSame('Own|url', PageWriteGuard::rendering($flexPage, fn () => $twig->twig()->render('@Page:guard-flex-own', ['page' => $flexPage, 'other' => $about])));

        $twig->setTemplate('@Page:guard-flex-other', "{% do other.header({title: 'Hacked'}) %}");
        try {
            PageWriteGuard::rendering($about, fn () => $twig->twig()->render('@Page:guard-flex-other', ['page' => $about, 'other' => $flexPage]));
            self::fail('A setter on another Flex page must be refused');
        } catch (SecurityNotAllowedMethodError $e) {
            self::assertSame(PageObject::class, $e->getClassName());
            self::assertSame('header', $e->getMethodName());
            self::assertSame('Own', $flexPage->title());
        }
    }

    public function testTrustedTemplateIsUntouched(): void
    {
        $twig = $this->twig();
        $twig->addPath($this->dir);
        $blog = $this->page('/blog');

        self::assertSame('Trusted', PageWriteGuard::rendering($this->page('/about'), fn () => $twig->twig()->render('partials/retitle.html.twig', ['other' => $blog])));
        self::assertSame('Trusted', $blog->title());

        $env = $twig->twig();
        $code = $env->compileSource($env->getLoader()->getSourceContext('partials/retitle.html.twig'));
        self::assertStringNotContainsString('PageWriteGuard', $code);
    }

    public function testNothingChangesWithTheSandboxOff(): void
    {
        $this->grav['config']->set('security.twig_sandbox.enabled', false);
        $blog = $this->page('/blog');

        self::assertSame('ok', $this->process($this->page('/about'), "{% do page.find('/blog').title('Off') %}ok"));
        self::assertSame('Off', $blog->title());
        self::assertFalse($this->grav['twig']->twig()->hasExtension(PageWriteGuard::class));
    }

    // =========================================================================

    private function assertClassified(string $method, string $what): void
    {
        $setter = in_array($method, PageWriteGuard::SETTER_METHODS, true);
        $read = in_array($method, PageWriteGuard::READ_METHODS, true);

        self::assertTrue($setter xor $read, "{$what}: list it in PageWriteGuard::SETTER_METHODS or READ_METHODS");
    }

    private function twig(): Twig
    {
        /** @var Twig $twig */
        $twig = $this->grav['twig'];
        $twig->init();

        return $twig;
    }

    private function page(string $route): PageInterface
    {
        $page = $this->grav['pages']->find($route);
        self::assertInstanceOf(PageInterface::class, $page, $route);

        return $page;
    }

    private function process(PageInterface $page, string $content): string
    {
        $twig = $this->twig();
        $twig->addPath($this->dir);
        $page->process(['twig' => true, 'markdown' => false]);

        return trim((string) $twig->processPage($page, $content));
    }
}
