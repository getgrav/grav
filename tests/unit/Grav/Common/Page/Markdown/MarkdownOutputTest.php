<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Markdown\MarkdownOutput;
use Grav\Common\Utils;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Class MarkdownOutputTest
 *
 * Markdown output for agents: `<route>.md` and `Accept: text/markdown`.
 */
class MarkdownOutputTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav $grav */
    protected $grav;

    /** @var MarkdownOutput */
    protected $output;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->grav['config']->set('system.languages.supported', []);
        $this->grav['config']->set('system.pages.markdown_output', [
            'enabled' => true,
            'frontmatter' => true,
            'links' => true,
            'max_links' => 100,
            'absolute_urls' => true,
            'token_header' => true,
        ]);
        $this->grav['language']->setLanguages([]);
        $this->grav['language']->init();
        $this->grav['uri']->initializeWithUrl('http://localhost/item1')->init();
        // No theme in the unit environment, so keep content Twig out of the render.
        $this->grav['config']->set('system.pages.process', ['markdown' => true, 'twig' => false]);
        $this->grav['config']->set('security.twig_content.enabled', false);

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $locator->addPath('theme', '', 'tests/fake/twig-first-site/user/themes/testing', false);
        $locator->addPath('page', '', 'tests/fake/nested-site/user/pages', false);
        $this->grav['pages']->init();
        $this->grav['twig']->init();

        $this->output = new MarkdownOutput($this->grav);
    }

    public function testMdIsAPageTypeAndMimeOnlyWhileEnabled(): void
    {
        self::assertSame('text/markdown', Utils::getMimeByExtension('md'));
        self::assertSame('md', Utils::getExtensionByMime('text/markdown'));
        $types = Utils::getSupportPageTypes();
        self::assertContains('md', $types);
        self::assertSame('md', end($types), 'md goes last so it never wins an ambiguous Accept negotiation');

        $this->grav['config']->set('system.pages.markdown_output.enabled', false);
        self::assertFalse(MarkdownOutput::enabled());
        self::assertNotContains('md', Utils::getSupportPageTypes());
    }

    public function testConvertProducesAtxHeadingsListsAndFencedCode(): void
    {
        $html = '<h2>Install</h2><p>Run <code>composer install</code> first.</p><ul><li>one</li><li>two</li></ul>'
            . '<pre><code class="language-php">if ($a &lt; $b &amp;&amp; $c) {}</code></pre>';

        $markdown = $this->output->convert($html);

        self::assertStringContainsString("## Install\n", $markdown);
        self::assertStringContainsString('Run `composer install` first.', $markdown);
        self::assertStringContainsString("- one\n- two", $markdown);
        self::assertStringContainsString("```php\nif (\$a < \$b && \$c) {}\n```", $markdown);
    }

    public function testConvertKeepsTextCharactersInsteadOfEntities(): void
    {
        $markdown = $this->output->convert('<p>Range &amp; Display &lt;b&gt; caf&eacute;</p>');

        self::assertSame('Range & Display <b> café', $markdown);
    }

    public function testConvertRendersTables(): void
    {
        $markdown = $this->output->convert('<table><thead><tr><th>Name</th><th>Value</th></tr></thead><tbody><tr><td>a</td><td>1</td></tr></tbody></table>');

        self::assertStringContainsString('| Name | Value |', $markdown);
        self::assertStringContainsString('|---|---|', $markdown);
        self::assertStringContainsString('| a | 1 |', $markdown);
    }

    public function testConvertDropsFormsScriptsAndEmptyAnchors(): void
    {
        $html = '<h2>Title<a class="anchor" href="#title"><svg><path d="M0"/></svg></a></h2>'
            . '<form action="/x"><input name="q"><button>Go</button></form>'
            . '<script>alert(1)</script><style>p{}</style><p>Kept.</p>';

        $markdown = $this->output->convert($html);

        self::assertSame("## Title\n\nKept.", $markdown);
    }

    public function testConvertMakesRootRelativeUrlsAbsolute(): void
    {
        $markdown = $this->output->convert('<p><a href="/blog/post">Post</a> <img src="/user/pages/01.home/pic.jpg" alt="Pic"> <a href="#anchor">Same page</a> <a href="//cdn.example.com/x">CDN</a></p>');

        self::assertStringContainsString('[Post](http://localhost/blog/post)', $markdown);
        self::assertStringContainsString('![Pic](http://localhost/user/pages/01.home/pic.jpg)', $markdown);
        self::assertStringContainsString('[Same page](#anchor)', $markdown);
        self::assertStringContainsString('[CDN](//cdn.example.com/x)', $markdown);

        $this->grav['config']->set('system.pages.markdown_output.absolute_urls', false);
        $markdown = $this->output->convert('<p><a href="/blog/post">Post</a></p>');
        self::assertSame('[Post](/blog/post)', $markdown);
    }

    public function testConvertLeavesFencedCodeWhitespaceAlone(): void
    {
        $html = '<ol><li><p>Paste:</p><pre><code>---\ntitle: X\n---\n    # indented heading in code</code></pre></li></ol><div> <h3>Loose heading</h3></div>';

        $markdown = $this->output->convert(str_replace('\n', "\n", $html));

        self::assertStringContainsString("    # indented heading in code", $markdown);
        self::assertMatchesRegularExpression('/^### Loose heading$/m', $markdown);
    }

    public function testConvertSurvivesEmptyInput(): void
    {
        self::assertSame('', $this->output->convert(''));
        self::assertSame('', $this->output->convert("  \n "));
    }

    public function testUrlAppendsMdAndUsesIndexForHome(): void
    {
        $page = $this->page('/item1');
        self::assertSame('http://localhost/item1.md', $this->output->url($page));

        $this->grav['config']->set('system.home.alias', '/item1');
        $this->grav['pages']->init();
        $home = $this->page('/item1');
        self::assertSame('http://localhost/index.md', $this->output->url($home));
    }

    public function testRenderHasFrontmatterBodyAndNavigation(): void
    {
        $page = $this->page('/item1');
        $document = $this->output->render($page);

        self::assertStringStartsWith("---\ntitle: 'Item 1'\n", $document);
        self::assertMatchesRegularExpression('/^url: .*\/item1.?$/m', $document);
        self::assertMatchesRegularExpression('/^markdown: .*\/item1\.md.?$/m', $document);
        self::assertStringContainsString("---\n\n# Item 1\n\nLorem ipsum", $document);
        self::assertStringContainsString("## Navigation\n", $document);
        self::assertStringContainsString('- Next: [Item 2](http://localhost/item2.md)', $document);
        self::assertStringContainsString("- Children:\n  - [Item 1-1](http://localhost/item1/item1-1.md)\n  - [Item 1-2]", $document);
        self::assertStringEndsWith("\n", $document);
    }

    public function testRenderRespectsTheSwitches(): void
    {
        $this->grav['config']->set('system.pages.markdown_output.frontmatter', false);
        $this->grav['config']->set('system.pages.markdown_output.links', false);

        $document = $this->output->render($this->page('/item1'));

        self::assertStringStartsWith("# Item 1\n", $document);
        self::assertStringNotContainsString('## Navigation', $document);
    }

    public function testNavigationCapsChildrenAtMaxLinks(): void
    {
        $this->grav['config']->set('system.pages.markdown_output.max_links', 2);

        $links = $this->output->links($this->page('/item1'));

        self::assertStringContainsString('[Item 1-2]', $links);
        self::assertStringNotContainsString('[Item 1-3]', $links);
        self::assertStringContainsString('1 more pages not listed', $links);
    }

    public function testMiddleChildLinksBothNeighbours(): void
    {
        $links = $this->output->links($this->page('/item1/item1-2'));

        self::assertStringContainsString('- Parent: [Item 1](http://localhost/item1.md)', $links);
        self::assertStringContainsString('- Previous: [Item 1-1](http://localhost/item1/item1-1.md)', $links);
        self::assertStringContainsString('- Next: [Item 1-3](http://localhost/item1/item1-3.md)', $links);
        self::assertStringContainsString("- Children:\n  - [Item 1-2-1](http://localhost/item1/item1-2/item1-2-1.md)", $links);
    }

    public function testEstimateTokens(): void
    {
        self::assertSame(0, MarkdownOutput::estimateTokens(''));
        self::assertSame(1, MarkdownOutput::estimateTokens('abc'));
        self::assertSame(3, MarkdownOutput::estimateTokens(str_repeat('a', 12)));
    }

    protected function page(string $route): PageInterface
    {
        $page = $this->grav['pages']->find($route);
        self::assertInstanceOf(PageInterface::class, $page, "Fake page {$route} not found");

        return $page;
    }
}
