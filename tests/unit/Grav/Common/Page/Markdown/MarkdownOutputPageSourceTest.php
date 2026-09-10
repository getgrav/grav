<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Markdown\MarkdownOutput;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Class MarkdownOutputPageSourceTest
 *
 * The Markdown comes from the page as the theme renders it, reduced to its
 * main content region, so template-driven pages (listings, shops) read as
 * they display.
 */
class MarkdownOutputPageSourceTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var MarkdownOutput */
    protected $output;

    /** @var bool|null */
    protected $previousGate;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $this->previousGate = $this->grav['config']->get('security.twig_content.process_enabled');
        $this->grav['config']->set('security.twig_content.process_enabled', true);
        $this->grav['config']->set('system.languages.supported', []);
        $this->grav['config']->set('system.pages.markdown_output', [
            'enabled' => true,
            'frontmatter' => false,
            'links' => false,
            'absolute_urls' => true,
        ]);
        $this->grav['language']->setLanguages([]);
        $this->grav['language']->init();
        $this->grav['uri']->initializeWithUrl('http://localhost/markdown-first')->init();

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $locator->addPath('theme', '', 'tests/fake/twig-first-site/user/themes/testing', false);
        $locator->addPath('page', '', 'tests/fake/twig-first-site/user/pages', false);
        $this->grav['pages']->init();
        $this->grav['twig']->init();

        $this->output = new MarkdownOutput($this->grav);
    }

    protected function tearDown(): void
    {
        $this->grav['config']->set('security.twig_content.process_enabled', $this->previousGate);
        parent::tearDown();
    }

    public function testPageSourceConvertsTheMainRegionOfTheThemedPage(): void
    {
        $markdown = $this->output->body($this->page('/markdown-first'));

        // The theme's own heading, once, and the page content.
        self::assertSame(1, preg_match_all('/^# Markdown First$/m', $markdown));
        self::assertStringContainsString('**yes!** and quoted', $markdown);
        // Template-driven content that page.content() never sees.
        self::assertStringContainsString('### Board', $markdown);
        self::assertStringContainsString('A board.', $markdown);
        self::assertStringContainsString('[Board](http://localhost/shop/board)', $markdown);
        // Chrome is gone.
        foreach (['Home', 'About', 'Related links', 'Copyright footer', 'search'] as $chrome) {
            self::assertStringNotContainsString($chrome, $markdown);
        }
    }

    public function testPageSourceRestoresTheMarkdownFormatOnThePage(): void
    {
        $page = $this->page('/markdown-first');
        $page->templateFormat('md');

        $this->output->body($page);

        self::assertSame('md', $page->templateFormat());
    }

    public function testMainRegionPrefersMainThenRoleThenSingleArticleThenBody(): void
    {
        $out = $this->output;

        self::assertSame('<p>M</p>', trim($out->mainRegion('<body><nav>n</nav><main><p>M</p></main><footer>f</footer></body>')));
        self::assertSame('<p>R</p>', trim($out->mainRegion('<body><div role="main"><p>R</p></div><footer>f</footer></body>')));
        self::assertSame('<p>A</p>', trim($out->mainRegion('<body><header>h</header><article><p>A</p></article><footer>f</footer></body>')));

        // Two articles are a listing, not the content; fall back to the body without its chrome.
        $body = $out->mainRegion('<body><header><nav>n</nav></header><article><p>A1</p></article><article><p>A2</p></article><footer>f</footer></body>');
        self::assertStringContainsString('<p>A1</p>', $body);
        self::assertStringContainsString('<p>A2</p>', $body);
        self::assertStringNotContainsString('<nav>', $body);
        self::assertStringNotContainsString('<footer>', $body);

        // A header inside an article is content, not chrome (kept, as a plain block).
        self::assertStringContainsString('<h1>T</h1>', $out->mainRegion('<body><main><article><header><h1>T</h1></header></article></main></body>'));
    }

    public function testMainRegionDropsNavigationAsidesAndHiddenNodes(): void
    {
        $region = $this->output->mainRegion('<main><nav>n</nav><p>keep</p><aside>side</aside><div aria-hidden="true">x</div><div role="search">s</div></main>');

        self::assertSame('<p>keep</p>', trim($region));
    }

    public function testBlockLinksBecomeBlocksFollowedByATitledLink(): void
    {
        $markdown = $this->output->convert($this->output->mainRegion(
            '<main><a href="/p/one"><img src="/one.jpg" alt=""><h3>One</h3><p>First card.</p></a></main>'
        ));

        self::assertStringContainsString("### One", $markdown);
        self::assertStringContainsString('First card.', $markdown);
        self::assertStringContainsString('[One](http://localhost/p/one)', $markdown);
        self::assertStringNotContainsString('[![]', $markdown);
    }

    public function testHeadingAfterInlineTextStartsItsOwnLine(): void
    {
        $markdown = $this->output->convert($this->output->mainRegion(
            '<main><div><span class="date">8th Jul 2026</span> <h1>Community</h1><p>Body.</p></div></main>'
        ));

        self::assertMatchesRegularExpression('/^# Community$/m', $markdown);
        self::assertStringContainsString('8th Jul 2026', $markdown);
    }

    public function testSectioningElementsKeepTheirLineBreaks(): void
    {
        // quark2's blog item: a <header> holding the title and date, then the content.
        $markdown = $this->output->convert($this->output->mainRegion(
            '<main><article><header><h1><a href="/blog/post">Post</a></h1><span class="blog-date"><time>8th Jul 2026</time></span></header>'
            . '<div class="e-content"><h1 id="post">Post</h1><p>Body.</p></div></article></main>'
        ));

        self::assertMatchesRegularExpression('/^8th Jul 2026$/m', $markdown);
        self::assertMatchesRegularExpression('/^# Post$/m', $markdown);
    }

    protected function page(string $route): PageInterface
    {
        $page = $this->grav['pages']->find($route);
        self::assertInstanceOf(PageInterface::class, $page, "Fake page {$route} not found");

        return $page;
    }
}
