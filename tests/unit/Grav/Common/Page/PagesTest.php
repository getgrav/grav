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

    protected function _before()
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

    public function testBase()
    {
        $this->assertSame('', $this->pages->base());
        $this->pages->base('/test');
        $this->assertSame('/test', $this->pages->base());
        $this->pages->base('');
        $this->assertSame($this->pages->base(), '');
    }

    public function testLastModified()
    {
        $this->assertNull($this->pages->lastModified());
        $this->pages->lastModified('test');
        $this->assertSame('test', $this->pages->lastModified());
    }

    public function testInstances()
    {
        $this->assertIsArray($this->pages->instances());
        foreach ($this->pages->instances() as $instance) {
            $this->assertInstanceOf(PageInterface::class, $instance);
        }
    }

    public function testRoutes()
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $folder = $locator->findResource('tests://');

        $this->assertIsArray($this->pages->routes());
        $this->assertSame($folder . '/fake/simple-site/user/pages/01.home', $this->pages->routes()['/']);
        $this->assertSame($folder . '/fake/simple-site/user/pages/01.home', $this->pages->routes()['/home']);
        $this->assertSame($folder . '/fake/simple-site/user/pages/02.blog', $this->pages->routes()['/blog']);
        $this->assertSame($folder . '/fake/simple-site/user/pages/02.blog/post-one', $this->pages->routes()['/blog/post-one']);
        $this->assertSame($folder . '/fake/simple-site/user/pages/02.blog/post-two', $this->pages->routes()['/blog/post-two']);
        $this->assertSame($folder . '/fake/simple-site/user/pages/03.about', $this->pages->routes()['/about']);
    }

    public function testAddPage()
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $folder = $locator->findResource('tests://');

        $path = $folder . '/fake/single-pages/01.simple-page/default.md';
        $aPage = new Page();
        $aPage->init(new \SplFileInfo($path));

        $this->pages->addPage($aPage, '/new-page');

        $this->assertContains('/new-page', array_keys($this->pages->routes()));
        $this->assertSame($folder . '/fake/single-pages/01.simple-page', $this->pages->routes()['/new-page']);
    }

    public function testSort()
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $folder = $locator->findResource('tests://');

        $aPage = $this->pages->find('/blog');
        $subPagesSorted = $this->pages->sort($aPage);

        $this->assertIsArray($subPagesSorted);
        $this->assertCount(2, $subPagesSorted);

        $this->assertSame($folder . '/fake/simple-site/user/pages/02.blog/post-one', array_keys($subPagesSorted)[0]);
        $this->assertSame($folder . '/fake/simple-site/user/pages/02.blog/post-two', array_keys($subPagesSorted)[1]);

        $this->assertContains($folder . '/fake/simple-site/user/pages/02.blog/post-one', array_keys($subPagesSorted));
        $this->assertContains($folder . '/fake/simple-site/user/pages/02.blog/post-two', array_keys($subPagesSorted));

        $this->assertSame(['slug' => 'post-one'], $subPagesSorted[$folder . '/fake/simple-site/user/pages/02.blog/post-one']);
        $this->assertSame(['slug' => 'post-two'], $subPagesSorted[$folder . '/fake/simple-site/user/pages/02.blog/post-two']);

        $subPagesSorted = $this->pages->sort($aPage, null, 'desc');

        $this->assertIsArray($subPagesSorted);
        $this->assertCount(2, $subPagesSorted);

        $this->assertSame($folder . '/fake/simple-site/user/pages/02.blog/post-two', array_keys($subPagesSorted)[0]);
        $this->assertSame($folder . '/fake/simple-site/user/pages/02.blog/post-one', array_keys($subPagesSorted)[1]);

        $this->assertContains($folder . '/fake/simple-site/user/pages/02.blog/post-one', array_keys($subPagesSorted));
        $this->assertContains($folder . '/fake/simple-site/user/pages/02.blog/post-two', array_keys($subPagesSorted));

        $this->assertSame(['slug' => 'post-one'], $subPagesSorted[$folder . '/fake/simple-site/user/pages/02.blog/post-one']);
        $this->assertSame(['slug' => 'post-two'], $subPagesSorted[$folder . '/fake/simple-site/user/pages/02.blog/post-two']);
    }

    public function testSortCollection()
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $folder = $locator->findResource('tests://');

        $aPage = $this->pages->find('/blog');
        $subPagesSorted = $this->pages->sortCollection($aPage->children(), $aPage->orderBy());

        $this->assertIsArray($subPagesSorted);
        $this->assertCount(2, $subPagesSorted);

        $this->assertSame($folder . '/fake/simple-site/user/pages/02.blog/post-one', array_keys($subPagesSorted)[0]);
        $this->assertSame($folder . '/fake/simple-site/user/pages/02.blog/post-two', array_keys($subPagesSorted)[1]);

        $this->assertContains($folder . '/fake/simple-site/user/pages/02.blog/post-one', array_keys($subPagesSorted));
        $this->assertContains($folder . '/fake/simple-site/user/pages/02.blog/post-two', array_keys($subPagesSorted));

        $this->assertSame(['slug' => 'post-one'], $subPagesSorted[$folder . '/fake/simple-site/user/pages/02.blog/post-one']);
        $this->assertSame(['slug' => 'post-two'], $subPagesSorted[$folder . '/fake/simple-site/user/pages/02.blog/post-two']);

        $subPagesSorted = $this->pages->sortCollection($aPage->children(), $aPage->orderBy(), 'desc');

        $this->assertIsArray($subPagesSorted);
        $this->assertCount(2, $subPagesSorted);

        $this->assertSame($folder . '/fake/simple-site/user/pages/02.blog/post-two', array_keys($subPagesSorted)[0]);
        $this->assertSame($folder . '/fake/simple-site/user/pages/02.blog/post-one', array_keys($subPagesSorted)[1]);

        $this->assertContains($folder . '/fake/simple-site/user/pages/02.blog/post-one', array_keys($subPagesSorted));
        $this->assertContains($folder . '/fake/simple-site/user/pages/02.blog/post-two', array_keys($subPagesSorted));

        $this->assertSame(['slug' => 'post-one'], $subPagesSorted[$folder . '/fake/simple-site/user/pages/02.blog/post-one']);
        $this->assertSame(['slug' => 'post-two'], $subPagesSorted[$folder . '/fake/simple-site/user/pages/02.blog/post-two']);
    }

    public function testGet()
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $folder = $locator->findResource('tests://');

        //Page existing
        $aPage = $this->pages->get($folder . '/fake/simple-site/user/pages/03.about');
        $this->assertInstanceOf(PageInterface::class, $aPage);

        //Page not existing
        $anotherPage = $this->pages->get($folder . '/fake/simple-site/user/pages/03.non-existing');
        $this->assertNull($anotherPage);
    }

    public function testChildren()
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $folder = $locator->findResource('tests://');

        //Page existing
        $children = $this->pages->children($folder . '/fake/simple-site/user/pages/02.blog');
        $this->assertInstanceOf('Grav\Common\Page\Collection', $children);

        //Page not existing
        $children = $this->pages->children($folder . '/fake/whatever/non-existing');
        $this->assertSame([], $children->toArray());
    }

    public function testDispatch()
    {
        $aPage = $this->pages->dispatch('/blog');
        $this->assertInstanceOf(PageInterface::class, $aPage);

        $aPage = $this->pages->dispatch('/about');
        $this->assertInstanceOf(PageInterface::class, $aPage);

        $aPage = $this->pages->dispatch('/blog/post-one');
        $this->assertInstanceOf(PageInterface::class, $aPage);

        //Page not existing
        $aPage = $this->pages->dispatch('/non-existing');
        $this->assertNull($aPage);
    }

    public function testRoot()
    {
        $root = $this->pages->root();
        $this->assertInstanceOf(PageInterface::class, $root);
        $this->assertSame('pages', $root->folder());
    }

    public function testBlueprints()
    {
    }

    public function testAll()
    {
        $this->assertIsObject($this->pages->all());
        $this->assertIsArray($this->pages->all()->toArray());
        foreach ($this->pages->all() as $page) {
            $this->assertInstanceOf(PageInterface::class, $page);
        }
    }

    public function testGetList()
    {
        $list = $this->pages->getList();
        $this->assertIsArray($list);
        $this->assertSame('&mdash;-&rtrif; Home', $list['/']);
        $this->assertSame('&mdash;-&rtrif; Blog', $list['/blog']);
    }

    public function testGetTypes()
    {
    }

    public function testTypes()
    {
    }

    public function testModularTypes()
    {
    }

    public function testPageTypes()
    {
    }

    public function testAccessLevels()
    {
    }

    public function testParents()
    {
    }

    public function testParentsRawRoutes()
    {
    }

    public function testGetHomeRoute()
    {
    }

    public function testResetPages()
    {
    }
}
