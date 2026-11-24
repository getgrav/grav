<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Pages;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Class PageRouteClaimTest
 *
 * The `routes` header of a page (`default`, `canonical`, `aliases`) can name any route. It gets
 * the routes nobody has, but it does not take the route a routable page has by its folder or by
 * the slugs of the page and its parents, whichever of the two pages is indexed first. Otherwise
 * whoever may edit one page could replace any page above it in the tree.
 */
class PageRouteClaimTest extends \PHPUnit\Framework\TestCase
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
        $locator->addPath('page', '', 'tests/fake/route-claims-site/user/pages', false);
        $this->pages->init();
    }

    public static function ownRoutes(): array
    {
        return [
            'alias, folder of a parent page' => ['/team', 'Team'],
            'alias, slug of a parent page' => ['/crew', 'Team'],
            'alias, folder of the home page' => ['/home', 'Home'],
            'alias, home page' => ['/', 'Home'],
            'default, folder of a parent page' => ['/legal', 'Legal'],
            'canonical, folder of a parent page' => ['/legal/terms', 'Terms'],
            'slug below the default of a parent page' => ['/crew/contact', 'Contact'],
        ];
    }

    /**
     * @dataProvider ownRoutes
     */
    public function testRoutesHeaderDoesNotTakeTheRouteOfAnotherPage(string $route, string $title): void
    {
        self::assertSame($title, $this->pages->find($route, true)->title());
        self::assertSame($title, $this->pages->dispatch($route)->title());
    }

    public function testRoutesHeaderGetsFreeRoutes(): void
    {
        self::assertSame('Post', $this->pages->find('/free', true)->title());
        self::assertSame('Post', $this->pages->find('/team/blog/post', true)->title());
        self::assertSame('Post', $this->pages->find('/crew/blog/post', true)->title());

        // `routes.default` moves the page and the pages below it, and leaves them their other routes.
        $promo = $this->pages->find('/team/contact-us/promo', true);
        self::assertSame('Promo', $promo->title());
        self::assertSame('/crew', $promo->route());
        self::assertSame('Promo', $this->pages->find('/crew/contact/promo', true)->title());
        $contact = $this->pages->find('/team/contact-us/promo/contact', true);
        self::assertSame('Promo Contact', $contact->title());
        self::assertSame('/crew/contact', $contact->route());
        self::assertSame('Note', $this->pages->find('/legal/terms/note', true)->title());
    }

    public function testRoutesHeaderStillGetsTheRouteOfAPageWhichIsNotRoutable(): void
    {
        // A page with `routable: false`.
        self::assertSame('Archive', $this->pages->find('/archive/current', true)->parent()->title());
        self::assertSame('Current', $this->pages->find('/archive', true)->title());
        self::assertSame('Current', $this->pages->dispatch('/archive')->title());

        // A folder without a page.
        self::assertFalse($this->pages->find('/docs/intro', true)->parent()->isPage());
        self::assertSame('Intro', $this->pages->dispatch('/docs')->title());
    }

    /**
     * Folder and slug routes are both the place of a page in the page tree: between them the page indexed last
     * still has the route. `06.about` has the slug `company` and `07.company` the slug `about`.
     */
    public function testFolderAndSlugRoutesAreEqualClaims(): void
    {
        self::assertSame('Company', $this->pages->find('/about', true)->title());
        self::assertSame('Company', $this->pages->find('/company', true)->title());
    }

    public function testLastPageStillWinsBetweenEqualClaims(): void
    {
        // A page is indexed after its parent.
        $this->pages->find('/legal', true)->routeAliases(['/imprint']);
        $this->pages->find('/legal/terms', true)->routeAliases(['/imprint']);

        $method = new ReflectionMethod($this->pages, 'buildRoutes');
        $method->invoke($this->pages);

        self::assertSame('Terms', $this->pages->find('/imprint', true)->title());
    }
}
