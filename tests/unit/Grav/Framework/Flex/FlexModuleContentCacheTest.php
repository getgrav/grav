<?php

use Codeception\Util\Fixtures;
use Grav\Common\Flex\Types\Pages\PageObject;
use Grav\Common\Grav;
use Grav\Framework\Cache\Adapter\MemoryCache;
use Grav\Framework\Flex\Flex;
use Grav\Framework\Flex\FlexDirectory;

/**
 * A module's body Twig answers for the current visitor, and the Flex page render cache has no
 * visitor dimension, so a module stores its markdown and re-runs the Twig on every request,
 * the same as a regular page. Mirrors Page::content().
 */
class FlexModuleContentCacheTest extends \PHPUnit\Framework\TestCase
{
    /** @var FlexDirectory */
    protected $directory;

    /** @var array */
    protected $saved = [];

    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        /** @var Grav $grav */
        $grav = $grav();
        foreach (['system.cache.enabled', 'system.pages.never_cache_twig', 'system.pages.twig_first', 'security.twig_content.process_enabled'] as $name) {
            $this->saved[$name] = $grav['config']->get($name);
        }
        $grav['config']->set('system.cache.enabled', true);
        $grav['config']->set('system.pages.never_cache_twig', false);
        $grav['config']->set('system.pages.twig_first', false);

        $flex = new Flex([], ['object' => $grav['config']->get('system.flex', [])]);
        $flex->addDirectoryType('pages', 'blueprints://flex/pages.yaml', ['enabled' => true]);
        $this->directory = $flex->getDirectory('pages');
    }

    protected function tearDown(): void
    {
        foreach ($this->saved as $name => $value) {
            Grav::instance()['config']->set($name, $value);
        }
        parent::tearDown();
    }

    public function testModuleRenderIsNotStored(): void
    {
        $module = $this->page('landing/_widget', true);
        $module->content();

        $stored = $module->storedRender();
        self::assertIsArray($stored);
        self::assertStringNotContainsString('RENDERED', (string) $stored['content'], 'A module stores its markdown, not its Twig output');
        self::assertStringContainsString('{{ isajaxrequest() }}', (string) $stored['content']);
        self::assertSame(1, $module->renders);
    }

    public function testRegularPageRenderIsNotStored(): void
    {
        $page = $this->page('landing/regular', false);
        $page->header(['title' => 'Regular', 'process' => ['twig' => true]]);
        Grav::instance()['config']->set('security.twig_content.process_enabled', true);

        $page->content();

        $stored = $page->storedRender();
        self::assertIsArray($stored);
        self::assertStringNotContainsString('RENDERED', (string) $stored['content']);
    }

    private function page(string $key, bool $module): object
    {
        $page = new class(['header' => ['title' => 'Widget'], 'markdown' => 'Hi {{ isajaxrequest() }}'], $key, $this->directory) extends PageObject {
            /** @var int */
            public $renders = 0;

            /** @var MemoryCache|null */
            private static $renderCache;

            public function getCache(?string $namespace = null)
            {
                if ($namespace !== 'render') {
                    return parent::getCache($namespace);
                }

                return self::$renderCache ??= new MemoryCache('test-render');
            }

            protected function processTwig($content): string
            {
                $this->renders++;

                return 'RENDERED ' . $content;
            }

            public function storedRender()
            {
                return $this->getCache('render')->get(md5($this->getCacheKey() . '-content'));
            }
        };
        $page->modularTwig($module);

        return $page;
    }
}
