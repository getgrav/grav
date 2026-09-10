<?php

use Codeception\Util\Fixtures;
use Grav\Common\Cache;
use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Class PageContentTwigOrderTest
 *
 * `twig_first` must keep working now that every content-Twig page runs its
 * Twig on every request (GHSA-pp89-h475-7gj6). The fix for that advisory
 * routed all such pages through the never-cache branch of Page::content(),
 * which always ran Markdown before Twig, so a Twig-first page had its Twig
 * tags Markdown-processed first: `{{ 'x' }}` inside an image URL came out as
 * `{{ &#039;x&#039; }}` and Twig stopped on the `&`.
 */
class PageContentTwigOrderTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var Cache */
    protected $cache;

    /** @var bool|null */
    protected $previousGate;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $this->previousGate = $this->grav['config']->get('security.twig_content.process_enabled');
        $this->grav['config']->set('security.twig_content.process_enabled', true);
        $this->grav['config']->set('system.cache.enabled', true);
        $this->grav['config']->set('system.languages.supported', []);

        // An in-memory page cache, so the tests can see what content() stores.
        $this->cache = new class($this->grav) extends Cache {
            /** @var array<string, mixed> */
            public $store = [];

            public function fetch($id)
            {
                return $this->store[$id] ?? false;
            }

            public function save($id, $data, $lifetime = null)
            {
                $this->store[$id] = $data;
            }
        };
        unset($this->grav['cache']);
        $this->grav['cache'] = $this->cache;
        $this->grav['language']->setLanguages([]);
        $this->grav['language']->init();

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $locator->addPath('theme', '', 'tests/fake/twig-first-site/user/themes/testing', false);
        $locator->addPath('page', '', 'tests/fake/twig-first-site/user/pages', false);
        $this->grav['pages']->init();
        $this->grav['twig']->init();
    }

    protected function tearDown(): void
    {
        $this->grav['config']->set('security.twig_content.process_enabled', $this->previousGate);
        parent::tearDown();
    }

    public function testTwigFirstPageRunsTwigBeforeMarkdown(): void
    {
        $content = $this->page('/twig-first')->content();

        self::assertStringContainsString('<img src="https://example.com/cat.png" alt="Cat"', $content);
        self::assertStringContainsString('Sum: 42', $content);
        self::assertStringNotContainsString('{{', $content);
    }

    public function testMarkdownFirstPageKeepsTwigTagsIntactThroughMarkdown(): void
    {
        $content = $this->page('/markdown-first')->content();

        self::assertStringContainsString('<strong>yes!</strong> and quoted', $content);
        self::assertStringContainsString('quality=10&amp;cropZoom=100,100', $content);
        self::assertStringNotContainsString('{{', $content);
    }

    /**
     * The advisory's guarantee: nothing Twig produced reaches the shared page
     * cache. A Twig-first page has no Twig-free stage, so it stores nothing; a
     * Markdown-first page stores its Markdown with the Twig tags still in it.
     */
    public function testTwigOutputNeverReachesThePageCache(): void
    {
        $twigFirst = $this->page('/twig-first');
        $twigFirst->content();
        self::assertArrayNotHasKey($this->cacheId($twigFirst), $this->cache->store);

        $markdownFirst = $this->page('/markdown-first');
        $markdownFirst->content();
        $cached = $this->cache->store[$this->cacheId($markdownFirst)] ?? null;
        self::assertIsArray($cached);
        self::assertStringContainsString("{{ 'yes' ~ '!' }}", $cached['content']);
        self::assertStringNotContainsString('yes!', $cached['content']);
    }

    /**
     * A stale entry from before the fix (Markdown run over the raw Twig) must
     * not be served to a Twig-first page.
     */
    public function testTwigFirstPageIgnoresAStaleCacheEntry(): void
    {
        $page = $this->page('/twig-first');
        $this->cache->store[$this->cacheId($page)] = ['content' => '<p>{{ &#039;stale&#039; }}</p>', 'content_meta' => null];

        $content = $page->content();

        self::assertStringContainsString('<img src="https://example.com/cat.png"', $content);
        self::assertStringNotContainsString('stale', $content);
    }

    protected function cacheId(PageInterface $page): string
    {
        $method = new ReflectionMethod($page, 'getPageContentCacheKey');
        $method->setAccessible(true);

        return md5('page' . $method->invoke($page, $this->grav['config']));
    }

    protected function page(string $route): PageInterface
    {
        $page = $this->grav['pages']->find($route);
        self::assertInstanceOf(PageInterface::class, $page, "Fake page {$route} not found");

        return $page;
    }
}
