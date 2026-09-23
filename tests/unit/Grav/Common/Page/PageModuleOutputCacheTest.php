<?php

use Codeception\Util\Fixtures;
use Grav\Common\Cache;
use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Pages;
use Grav\Common\User\DataUser\User;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * A module's Twig pass renders its body and its theme template, so its output can depend on the
 * visitor and is never cached by default (GHSA-pp89-h475-7gj6). A modular page can opt in with
 * `cache_modules: true`. These tests pin that the opt-in only ever caches what renders the same
 * for everyone: no module Twig, no forms, no logged-in visitors, no POSTs, one entry per URL, and
 * that a cached module still adds its assets.
 *
 * Each module template prints the `render_mark` Twig variable, which every simulated request
 * changes, so a cached render shows the mark of the request that stored it.
 */
class PageModuleOutputCacheTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var Cache */
    protected $cache;

    /** @var array */
    protected $server;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->server = $_SERVER;

        $config = $this->grav['config'];
        $config->set('system.cache.enabled', true);
        $config->set('system.languages.supported', []);
        $config->set('security.twig_content.process_enabled', false);

        $this->cache = new class($this->grav) extends Cache {
            /** @var array<string, mixed> */
            public $store = [];

            // Stored serialized, as a real cache driver does, so every request gets its own objects.
            public function fetch($id)
            {
                return isset($this->store[$id]) ? unserialize($this->store[$id]) : false;
            }

            public function save($id, $data, $lifetime = null)
            {
                $this->store[$id] = serialize($data);
            }
        };
        unset($this->grav['cache']);
        $this->grav['cache'] = $this->cache;
        $this->grav['language']->setLanguages([]);
        $this->grav['language']->init();

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $locator->addPath('theme', '', 'tests/fake/modular-cache-site/user/themes/testing', false);
        $locator->addPath('page', '', 'tests/fake/modular-cache-site/user/pages', false);
        $this->grav['twig']->init();
        $this->grav['assets']->init();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        parent::tearDown();
    }

    public function testModuleRendersOnceWhenThePageOptsIn(): void
    {
        self::assertStringContainsString('<b>first</b>', $this->render('/landing', 'Intro', 'first'));
        self::assertStringContainsString('<b>first</b>', $this->render('/landing', 'Intro', 'second'), 'The second request got the stored render');
        self::assertStringContainsString('Plain intro text.', $this->render('/landing', 'Intro', 'third'));
    }

    public function testModulesRenderEveryTimeWithoutTheOptIn(): void
    {
        $this->render('/plain', 'Intro', 'first');

        self::assertStringContainsString('<b>second</b>', $this->render('/plain', 'Intro', 'second'));
        self::assertSame([], $this->moduleEntries());
    }

    public function testModuleWithItsOwnTwigRendersEveryTime(): void
    {
        $this->render('/landing', 'Twig', 'first');

        $output = $this->render('/landing', 'Twig', 'second');
        self::assertStringContainsString('<b>second</b>', $output);
        self::assertStringContainsString('Hello Twig.', $output);
        self::assertSame([], $this->moduleEntries());
    }

    public function testFormModuleRendersEveryTime(): void
    {
        $this->render('/landing', 'Signup', 'first');

        self::assertStringContainsString('<b>second</b>', $this->render('/landing', 'Signup', 'second'));
        self::assertSame([], $this->moduleEntries());
    }

    public function testLoggedInVisitorNeitherStoresNorGetsTheStoredRender(): void
    {
        $this->loginAs('editor');
        self::assertStringContainsString('<b>editor</b>', $this->render('/landing', 'Intro', 'editor'));
        self::assertSame([], $this->moduleEntries(), 'A logged-in render is never stored');

        $this->logout();
        self::assertStringContainsString('<b>guest</b>', $this->render('/landing', 'Intro', 'guest'));

        $this->loginAs('editor');
        self::assertStringContainsString('<b>editor-again</b>', $this->render('/landing', 'Intro', 'editor-again'), 'A logged-in visitor never gets the stored render');
    }

    public function testPostRequestRendersEveryTime(): void
    {
        $this->render('/landing', 'Intro', 'first');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        self::assertStringContainsString('<b>posted</b>', $this->render('/landing', 'Intro', 'posted'));
    }

    public function testEveryUrlGetsItsOwnEntry(): void
    {
        $this->render('/landing', 'Intro', 'plain', '/landing');
        self::assertStringContainsString('<b>query</b>', $this->render('/landing', 'Intro', 'query', '/landing?page=2'));
        self::assertStringContainsString('<b>plain</b>', $this->render('/landing', 'Intro', 'again', '/landing'));
        self::assertCount(2, $this->moduleEntries());
    }

    public function testCachedModuleStillAddsItsAssets(): void
    {
        $this->render('/landing', 'Intro', 'first');
        $this->grav['assets']->reset();

        $this->render('/landing', 'Intro', 'second');
        self::assertArrayHasKey(md5('https://example.com/text.css'), $this->grav['assets']->getCss());
    }

    /**
     * Simulate a request for $url and return the rendered module titled $title.
     */
    private function render(string $route, string $title, string $mark, ?string $url = null): string
    {
        $_SERVER['REQUEST_URI'] = $url ?? $route;
        $this->grav['uri']->initializeWithURL('http://localhost' . ($url ?? $route))->init();
        $this->grav['twig']->twig_vars['render_mark'] = $mark;

        $pages = new Pages($this->grav);
        unset($this->grav['pages']);
        $this->grav['pages'] = $pages;
        $pages->init();

        $page = $pages->find($route);
        self::assertInstanceOf(PageInterface::class, $page);
        foreach ($page->collection() as $module) {
            if ($module->title() === $title) {
                return (string)$module->content();
            }
        }

        self::fail("No module {$title} on {$route}");
    }

    /**
     * @return array<string,mixed> Stored module renders.
     */
    private function moduleEntries(): array
    {
        return array_filter(array_map('unserialize', $this->cache->store), static fn($entry) => is_array($entry) && isset($entry['assets'], $entry['time']));
    }

    private function loginAs(string $username): void
    {
        $user = new User(['username' => $username]);
        $user->authenticated = true;
        unset($this->grav['user']);
        $this->grav['user'] = $user;
    }

    private function logout(): void
    {
        unset($this->grav['user']);
    }
}
