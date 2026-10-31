<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Pages;
use Grav\Framework\Flex\Flex;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Route indexing keeps valid routes and ignores values that cannot be routes.
 */
class PagesMalformedRoutesTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var Pages */
    protected $pages;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->pages = $this->grav['pages'];
        $this->grav['config']->set('system.home.alias', '/home');

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $locator->addPath('page', '', 'tests/fake/malformed-routes-site/user/pages', false);
        $this->pages->init();
    }

    public function testEveryPageKeepsItsOwnRoute(): void
    {
        foreach (['/', '/news', '/alias-scalar', '/default-list', '/default-list/child', '/canonical-list', '/slug-list'] as $route) {
            self::assertNotNull($this->pages->find($route, true), $route);
        }

        foreach (array_keys($this->pages->routes()) as $route) {
            self::assertIsString($route);
        }
        self::assertNull($this->pages->find('/nested', true));
        self::assertNull($this->pages->find('/map', true));
    }

    public function testOnlyStringAliasesAreKept(): void
    {
        $page = $this->pages->find('/press', true);

        self::assertNotNull($page);
        self::assertSame('/news', $page->route());
        self::assertSame(['/press'], $page->routeAliases());
    }

    public function testSingleAliasIsReadAsList(): void
    {
        $page = $this->pages->find('/scalar-alias', true);

        self::assertNotNull($page);
        self::assertSame('/scalar-default', $page->route());
        self::assertSame(['/scalar-alias', '/alias-scalar'], $page->routeAliases());
    }

    public function testMalformedValuesFallBackToTheFolderRoute(): void
    {
        self::assertSame('/default-list', $this->pages->find('/default-list', true)->route());
        self::assertSame('/default-list/child', $this->pages->find('/default-list/child', true)->route());
        self::assertSame('/canonical-list', $this->pages->find('/canonical-list', true)->routeCanonical());
        self::assertSame('slug-list', $this->pages->find('/slug-list', true)->slug());
    }

    public function testLegacyAliasSetterAndGetterKeepStrings(): void
    {
        $page = new \Grav\Common\Page\Page();
        self::assertSame(['/press', '/archive'], $page->routeAliases(['/press', ['/nested'], 7, '/archive']));
        self::assertSame(['/press', '/archive'], $page->routeAliases());

        $routes = new ReflectionProperty($page, 'routes');
        $routes->setValue($page, ['aliases' => ['/press', ['/nested'], 7]]);
        self::assertSame(['/press'], $page->routeAliases());
    }

    public function testFlexPageOnlyReturnsStringAliases(): void
    {
        $flex = new Flex([], ['object' => $this->grav['config']->get('system.flex', [])]);
        $flex->addDirectoryType('pages', 'blueprints://flex/pages.yaml', ['enabled' => true]);
        $directory = $flex->getDirectory('pages');
        self::assertNotNull($directory);

        $page = $directory->createObject(['header' => ['routes' => ['aliases' => ['/press', ['/nested'], 7]]]], 'news');
        self::assertSame(['/press'], $page->routeAliases());
        self::assertSame(['/archive'], $page->routeAliases([['/nested'], '/archive', 7]));
        self::assertSame(['/archive'], $page->routeAliases());

        $page = $directory->createObject(['header' => ['routes' => ['default' => ['/nested']]]], 'news');
        self::assertSame([], $page->routeAliases());
    }
}
