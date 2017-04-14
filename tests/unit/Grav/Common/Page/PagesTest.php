<?php

use Codeception\Util\Fixtures;
use Codeception\Util\Stub;
use Grav\Common\Grav;
use Grav\Common\Page\Pages;
use Grav\Common\Page\Page;
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

    /** @var Page $root_page */
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
        $this->assertSame(null, $this->pages->base());
    }

    public function testLastModified()
    {
        $this->assertSame(null, $this->pages->lastModified());
        $this->pages->lastModified('test');
        $this->assertSame('test', $this->pages->lastModified());
    }

    public function testInstances()
    {
        $this->assertTrue(is_array($this->pages->instances()));
        foreach($this->pages->instances() as $instance) {
            $this->assertInstanceOf('Grav\Common\Page\Page', $instance);
        }
    }

    public function testRoutes()
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];

        $this->assertTrue(is_array($this->pages->routes()));
        $this->assertSame($locator->findResource('tests://') . '/fake/simple-site/user/pages/01.home', $this->pages->routes()['/']);
        $this->assertSame($locator->findResource('tests://') . '/fake/simple-site/user/pages/01.home', $this->pages->routes()['/home']);
        $this->assertSame($locator->findResource('tests://') . '/fake/simple-site/user/pages/02.blog', $this->pages->routes()['/blog']);
        $this->assertSame($locator->findResource('tests://') . '/fake/simple-site/user/pages/02.blog/post-one', $this->pages->routes()['/blog/post-one']);
        $this->assertSame($locator->findResource('tests://') . '/fake/simple-site/user/pages/02.blog/post-two', $this->pages->routes()['/blog/post-two']);
        $this->assertSame($locator->findResource('tests://') . '/fake/simple-site/user/pages/03.about', $this->pages->routes()['/about']);
    }

    public function testAddPage()
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];

        $path = $locator->findResource('tests://') . '/fake/single-pages/01.simple-page/default.md';
        $aPage = new Page();
        $aPage->init(new \SplFileInfo($path));

        $this->pages->addPage($aPage, '/new-page');

        $this->assertTrue(in_array('/new-page', array_keys($this->pages->routes())));
        $this->assertSame($locator->findResource('tests://') . '/fake/single-pages/01.simple-page', $this->pages->routes()['/new-page']);
    }

    public function testSort()
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];

        $aPage = $this->pages->dispatch('/blog');
        $subPagesSorted = $this->pages->sort($aPage);

        $this->assertTrue(is_array($subPagesSorted));
        $this->assertTrue(count($subPagesSorted) === 2);

        $this->assertSame($locator->findResource('tests://') . '/fake/simple-site/user/pages/02.blog/post-one', array_keys($subPagesSorted)[0]);
        $this->assertSame($locator->findResource('tests://') . '/fake/simple-site/user/pages/02.blog/post-two', array_keys($subPagesSorted)[1]);

        $this->assertTrue(in_array($locator->findResource('tests://') . '/fake/simple-site/user/pages/02.blog/post-one', array_keys($subPagesSorted)));
        $this->assertTrue(in_array($locator->findResource('tests://') . '/fake/simple-site/user/pages/02.blog/post-two', array_keys($subPagesSorted)));

        $this->assertSame(["slug" => "post-one"], $subPagesSorted[$locator->findResource('tests://') . '/fake/simple-site/user/pages/02.blog/post-one']);
        $this->assertSame(["slug" => "post-two"], $subPagesSorted[$locator->findResource('tests://') . '/fake/simple-site/user/pages/02.blog/post-two']);

        $subPagesSorted = $this->pages->sort($aPage, null, 'desc');

        $this->assertTrue(is_array($subPagesSorted));
        $this->assertTrue(count($subPagesSorted) === 2);

        $this->assertSame($locator->findResource('tests://') . '/fake/simple-site/user/pages/02.blog/post-two', array_keys($subPagesSorted)[0]);
        $this->assertSame($locator->findResource('tests://') . '/fake/simple-site/user/pages/02.blog/post-one', array_keys($subPagesSorted)[1]);

        $this->assertTrue(in_array($locator->findResource('tests://') . '/fake/simple-site/user/pages/02.blog/post-one', array_keys($subPagesSorted)));
        $this->assertTrue(in_array($locator->findResource('tests://') . '/fake/simple-site/user/pages/02.blog/post-two', array_keys($subPagesSorted)));

        $this->assertSame(["slug" => "post-one"], $subPagesSorted[$locator->findResource('tests://') . '/fake/simple-site/user/pages/02.blog/post-one']);
        $this->assertSame(["slug" => "post-two"], $subPagesSorted[$locator->findResource('tests://') . '/fake/simple-site/user/pages/02.blog/post-two']);
    }

    public function testSortCollection()
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];

        $aPage = $this->pages->dispatch('/blog');
        $subPagesSorted = $this->pages->sortCollection($aPage->children(), $aPage->orderBy());

        $this->assertTrue(is_array($subPagesSorted));
        $this->assertTrue(count($subPagesSorted) === 2);

        $this->assertSame($locator->findResource('tests://') . '/fake/simple-site/user/pages/02.blog/post-one', array_keys($subPagesSorted)[0]);
        $this->assertSame($locator->findResource('tests://') . '/fake/simple-site/user/pages/02.blog/post-two', array_keys($subPagesSorted)[1]);

        $this->assertTrue(in_array($locator->findResource('tests://') . '/fake/simple-site/user/pages/02.blog/post-one', array_keys($subPagesSorted)));
        $this->assertTrue(in_array($locator->findResource('tests://') . '/fake/simple-site/user/pages/02.blog/post-two', array_keys($subPagesSorted)));

        $this->assertSame(["slug" => "post-one"], $subPagesSorted[$locator->findResource('tests://') . '/fake/simple-site/user/pages/02.blog/post-one']);
        $this->assertSame(["slug" => "post-two"], $subPagesSorted[$locator->findResource('tests://') . '/fake/simple-site/user/pages/02.blog/post-two']);

        $subPagesSorted = $this->pages->sortCollection($aPage->children(), $aPage->orderBy(), 'desc');

        $this->assertTrue(is_array($subPagesSorted));
        $this->assertTrue(count($subPagesSorted) === 2);

        $this->assertSame($locator->findResource('tests://') . '/fake/simple-site/user/pages/02.blog/post-two', array_keys($subPagesSorted)[0]);
        $this->assertSame($locator->findResource('tests://') . '/fake/simple-site/user/pages/02.blog/post-one', array_keys($subPagesSorted)[1]);

        $this->assertTrue(in_array($locator->findResource('tests://') . '/fake/simple-site/user/pages/02.blog/post-one', array_keys($subPagesSorted)));
        $this->assertTrue(in_array($locator->findResource('tests://') . '/fake/simple-site/user/pages/02.blog/post-two', array_keys($subPagesSorted)));

        $this->assertSame(["slug" => "post-one"], $subPagesSorted[$locator->findResource('tests://') . '/fake/simple-site/user/pages/02.blog/post-one']);
        $this->assertSame(["slug" => "post-two"], $subPagesSorted[$locator->findResource('tests://') . '/fake/simple-site/user/pages/02.blog/post-two']);
    }

    public function testGet()
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];

        //Page existing
        $aPage = $this->pages->get($locator->findResource('tests://') . '/fake/simple-site/user/pages/03.about');
        $this->assertTrue(is_object($aPage));
        $this->assertInstanceOf('Grav\Common\Page\Page', $aPage);

        //Page not existing
        $anotherPage = $this->pages->get($locator->findResource('tests://') . '/fake/simple-site/user/pages/03.non-existing');
        $this->assertFalse(is_object($anotherPage));
        $this->assertNull($anotherPage);
    }

    public function testChildren()
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];

        //Page existing
        $children = $this->pages->children($locator->findResource('tests://') . '/fake/simple-site/user/pages/02.blog');
        $this->assertInstanceOf('Grav\Common\Page\Collection', $children);

        //Page not existing
        $children = $this->pages->children($locator->findResource('tests://') . '/fake/whatever/non-existing');
        $this->assertSame([], $children->toArray());
    }

    public function testDispatch()
    {
        $aPage = $this->pages->dispatch('/blog');
        $this->assertInstanceOf('Grav\Common\Page\Page', $aPage);

        $aPage = $this->pages->dispatch('/about');
        $this->assertInstanceOf('Grav\Common\Page\Page', $aPage);

        $aPage = $this->pages->dispatch('/blog/post-one');
        $this->assertInstanceOf('Grav\Common\Page\Page', $aPage);

        //Page not existing
        $aPage = $this->pages->dispatch('/non-existing');
        $this->assertNull($aPage);
    }

    public function testRoot()
    {
        $root = $this->pages->root();
        $this->assertInstanceOf('Grav\Common\Page\Page', $root);
        $this->assertSame('pages', $root->folder());
    }

    public function testBlueprints()
    {

    }

    public function testAll()
    {
        $this->assertTrue(is_object($this->pages->all()));
        $this->assertTrue(is_array($this->pages->all()->toArray()));
        foreach($this->pages->all() as $page) {
            $this->assertInstanceOf('Grav\Common\Page\Page', $page);
        }
    }

    public function testGetList()
    {
        $list = $this->pages->getList();
        $this->assertTrue(is_array($list));
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
