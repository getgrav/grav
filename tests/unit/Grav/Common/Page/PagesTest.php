<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Pages;
use Grav\Common\Page\Page;
use Grav\Common\Page\Interfaces\PageInterface;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Class PagesTest
 */
class PagesTest extends \Codeception\TestCase\Test
{
    /** @var Grav $grav */
    protected $grav;

    /** @var Pages $pages */
    protected $pages;

    /** @var PageInterface $root_page */
    protected $root_page;

    protected function _before(): void
    {
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->pages = $this->grav['pages'];
        $this->grav['config']->set('system.home.alias', '/home');

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];

        $locator->addPath('page', '', 'tests/fake/simple-site/user/pages', false);
        $this->pages->init();
    }

    public function testBase(): void
    {
        self::assertSame('', $this->pages->base());
        $this->pages->base('/test');
        self::assertSame('/test', $this->pages->base());
        $this->pages->base('');
        self::assertSame($this->pages->base(), '');
    }

    public function testLastModified(): void
    {
        self::assertNull($this->pages->lastModified());
        $this->pages->lastModified('test');
        self::assertSame('test', $this->pages->lastModified());
    }

    public function testInstances(): void
    {
        self::assertIsArray($this->pages->instances());
        foreach ($this->pages->instances() as $instance) {
            self::assertInstanceOf(PageInterface::class, $instance);
        }
    }

    public function testRoutes(): void
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $folder = $locator->findResource('tests://');

        self::assertIsArray($this->pages->routes());
        self::assertSame($folder . '/fake/simple-site/user/pages/01.home', $this->pages->routes()['/']);
        self::assertSame($folder . '/fake/simple-site/user/pages/01.home', $this->pages->routes()['/home']);
        self::assertSame($folder . '/fake/simple-site/user/pages/02.blog', $this->pages->routes()['/blog']);
        self::assertSame($folder . '/fake/simple-site/user/pages/02.blog/post-one', $this->pages->routes()['/blog/post-one']);
        self::assertSame($folder . '/fake/simple-site/user/pages/02.blog/post-two', $this->pages->routes()['/blog/post-two']);
        self::assertSame($folder . '/fake/simple-site/user/pages/03.about', $this->pages->routes()['/about']);
    }

    public function testAddPage(): void
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $folder = $locator->findResource('tests://');

        $path = $folder . '/fake/single-pages/01.simple-page/default.md';
        $aPage = new Page();
        $aPage->init(new \SplFileInfo($path));

        $this->pages->addPage($aPage, '/new-page');

        self::assertContains('/new-page', array_keys($this->pages->routes()));
        self::assertSame($folder . '/fake/single-pages/01.simple-page', $this->pages->routes()['/new-page']);
    }

    public function testSort(): void
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $folder = $locator->findResource('tests://');

        $aPage = $this->pages->find('/blog');
        $subPagesSorted = $this->pages->sort($aPage);

        self::assertIsArray($subPagesSorted);
        self::assertCount(2, $subPagesSorted);

        self::assertSame($folder . '/fake/simple-site/user/pages/02.blog/post-one', array_keys($subPagesSorted)[0]);
        self::assertSame($folder . '/fake/simple-site/user/pages/02.blog/post-two', array_keys($subPagesSorted)[1]);

        self::assertContains($folder . '/fake/simple-site/user/pages/02.blog/post-one', array_keys($subPagesSorted));
        self::assertContains($folder . '/fake/simple-site/user/pages/02.blog/post-two', array_keys($subPagesSorted));

        self::assertSame(['slug' => 'post-one'], $subPagesSorted[$folder . '/fake/simple-site/user/pages/02.blog/post-one']);
        self::assertSame(['slug' => 'post-two'], $subPagesSorted[$folder . '/fake/simple-site/user/pages/02.blog/post-two']);

        $subPagesSorted = $this->pages->sort($aPage, null, 'desc');

        self::assertIsArray($subPagesSorted);
        self::assertCount(2, $subPagesSorted);

        self::assertSame($folder . '/fake/simple-site/user/pages/02.blog/post-two', array_keys($subPagesSorted)[0]);
        self::assertSame($folder . '/fake/simple-site/user/pages/02.blog/post-one', array_keys($subPagesSorted)[1]);

        self::assertContains($folder . '/fake/simple-site/user/pages/02.blog/post-one', array_keys($subPagesSorted));
        self::assertContains($folder . '/fake/simple-site/user/pages/02.blog/post-two', array_keys($subPagesSorted));

        self::assertSame(['slug' => 'post-one'], $subPagesSorted[$folder . '/fake/simple-site/user/pages/02.blog/post-one']);
        self::assertSame(['slug' => 'post-two'], $subPagesSorted[$folder . '/fake/simple-site/user/pages/02.blog/post-two']);
    }

    public function testSortCollection(): void
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $folder = $locator->findResource('tests://');

        $aPage = $this->pages->find('/blog');
        $subPagesSorted = $this->pages->sortCollection($aPage->children(), $aPage->orderBy());

        self::assertIsArray($subPagesSorted);
        self::assertCount(2, $subPagesSorted);

        self::assertSame($folder . '/fake/simple-site/user/pages/02.blog/post-one', array_keys($subPagesSorted)[0]);
        self::assertSame($folder . '/fake/simple-site/user/pages/02.blog/post-two', array_keys($subPagesSorted)[1]);

        self::assertContains($folder . '/fake/simple-site/user/pages/02.blog/post-one', array_keys($subPagesSorted));
        self::assertContains($folder . '/fake/simple-site/user/pages/02.blog/post-two', array_keys($subPagesSorted));

        self::assertSame(['slug' => 'post-one'], $subPagesSorted[$folder . '/fake/simple-site/user/pages/02.blog/post-one']);
        self::assertSame(['slug' => 'post-two'], $subPagesSorted[$folder . '/fake/simple-site/user/pages/02.blog/post-two']);

        $subPagesSorted = $this->pages->sortCollection($aPage->children(), $aPage->orderBy(), 'desc');

        self::assertIsArray($subPagesSorted);
        self::assertCount(2, $subPagesSorted);

        self::assertSame($folder . '/fake/simple-site/user/pages/02.blog/post-two', array_keys($subPagesSorted)[0]);
        self::assertSame($folder . '/fake/simple-site/user/pages/02.blog/post-one', array_keys($subPagesSorted)[1]);

        self::assertContains($folder . '/fake/simple-site/user/pages/02.blog/post-one', array_keys($subPagesSorted));
        self::assertContains($folder . '/fake/simple-site/user/pages/02.blog/post-two', array_keys($subPagesSorted));

        self::assertSame(['slug' => 'post-one'], $subPagesSorted[$folder . '/fake/simple-site/user/pages/02.blog/post-one']);
        self::assertSame(['slug' => 'post-two'], $subPagesSorted[$folder . '/fake/simple-site/user/pages/02.blog/post-two']);
    }

    public function testGet(): void
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $folder = $locator->findResource('tests://');

        //Page existing
        $aPage = $this->pages->get($folder . '/fake/simple-site/user/pages/03.about');
        self::assertInstanceOf(PageInterface::class, $aPage);

        //Page not existing
        $anotherPage = $this->pages->get($folder . '/fake/simple-site/user/pages/03.non-existing');
        self::assertNull($anotherPage);
    }

    public function testChildren(): void
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $folder = $locator->findResource('tests://');

        //Page existing
        $children = $this->pages->children($folder . '/fake/simple-site/user/pages/02.blog');
        self::assertInstanceOf('Grav\Common\Page\Collection', $children);

        //Page not existing
        $children = $this->pages->children($folder . '/fake/whatever/non-existing');
        self::assertSame([], $children->toArray());
    }

    public function testDispatch(): void
    {
        $aPage = $this->pages->dispatch('/blog');
        self::assertInstanceOf(PageInterface::class, $aPage);

        $aPage = $this->pages->dispatch('/about');
        self::assertInstanceOf(PageInterface::class, $aPage);

        $aPage = $this->pages->dispatch('/blog/post-one');
        self::assertInstanceOf(PageInterface::class, $aPage);

        //Page not existing
        $aPage = $this->pages->dispatch('/non-existing');
        self::assertNull($aPage);
    }

    public function testRoot(): void
    {
        $root = $this->pages->root();
        self::assertInstanceOf(PageInterface::class, $root);
        self::assertSame('pages', $root->folder());
    }

    public function testBlueprints(): void
    {
    }

    public function testAll()
    {
        self::assertIsObject($this->pages->all());
        self::assertIsArray($this->pages->all()->toArray());
        foreach ($this->pages->all() as $page) {
            self::assertInstanceOf(PageInterface::class, $page);
        }
    }

    public function testGetList(): void
    {
        $list = $this->pages->getList();
        self::assertIsArray($list);
        self::assertSame('&mdash;-&rtrif; Home', $list['/']);
        self::assertSame('&mdash;-&rtrif; Blog', $list['/blog']);
    }

    public function testTranslatedLanguages(): void
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $folder = $locator->findResource('tests://');

        $page = $this->pages->get($folder . '/fake/simple-site/user/pages/04.page-translated');
        $this->assertInstanceOf(PageInterface::class, $page);
        $translatedLanguages = $page->translatedLanguages();
        $this->assertIsArray($translatedLanguages);
        $this->assertSame(["en" => "/page-translated", "fr" => "/page-translated"], $translatedLanguages);
    }

    public function testLongPathTranslatedLanguages(): void
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $folder = $locator->findResource('tests://');
        $page = $this->pages->get($folder . '/fake/simple-site/user/pages/05.translatedlong/part2');
        $this->assertInstanceOf(PageInterface::class, $page);
        $translatedLanguages = $page->translatedLanguages();
        $this->assertIsArray($translatedLanguages);
        $this->assertSame(["en" => "/translatedlong/part2", "fr" => "/translatedlong/part2"], $translatedLanguages);
    }

    public function testGetTypes(): void
    {
    }

    public function testTypes(): void
    {
    }

    public function testModularTypes(): void
    {
    }

    public function testPageTypes(): void
    {
    }

    public function testAccessLevels(): void
    {
    }

    public function testParents(): void
    {
    }

    public function testParentsRawRoutes(): void
    {
    }

    public function testGetHomeRoute(): void
    {
    }

    public function testResetPages(): void
    {
    }
}
