<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Pages;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Raw route conflicts compare the entry registered for that raw route.
 */
class PagesEmptyDefaultRouteTest extends \PHPUnit\Framework\TestCase
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
        $locator->addPath('page', '', 'tests/fake/empty-default-route-site/user/pages', false);
    }

    public static function defaultRoutes(): array
    {
        return [[''], ['0']];
    }

    /** @dataProvider defaultRoutes */
    public function testRawRouteIsAddedWithoutARegularRoute(string $route): void
    {
        // Like Errors::resetHandlers() on a site: a warning is an exception (unless @-silenced).
        set_error_handler(static function (int $errno, string $message, string $file, int $line): bool {
            if (!($errno & error_reporting())) {
                return false;
            }

            throw new ErrorException($message, 0, $errno, $file, $line);
        }, E_WARNING);
        try {
            $this->pages->init();
            if ($route !== '') {
                $page = $this->pages->find('/docs/intro');
                $page->header((object)['routes' => ['default' => $route]]);
                $method = new ReflectionMethod($this->pages, 'buildRoutes');
                $method->invoke($this->pages);
            }
        } finally {
            restore_error_handler();
        }

        self::assertInstanceOf(PageInterface::class, $this->pages->find('/home'));

        $intro = $this->pages->find('/docs/intro');
        self::assertInstanceOf(PageInterface::class, $intro);
        self::assertSame('Intro', $intro->title());
        self::assertSame($route, $intro->route());
        self::assertSame('/docs/intro', $intro->rawRoute());
    }
}
