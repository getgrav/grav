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
        $this->pages = $this->grav['pages'];
        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $locator->addPath('page', '', 'tests/fake/simple-site/user/pages', false);
        $this->pages->init();
    }

    public function testAll()
    {
        $locator = $this->grav['locator'];
        $locator->resetScheme('page');
        $locator->addPath('page', '', 'tests/fake/simple-site/user/pages', false);
        $this->pages->init();

        $this->assertTrue(is_object($this->pages->all()));
        $this->assertTrue(is_array($this->pages->all()->toArray()));
        $this->assertInstanceOf('Grav\Common\Page\Page', $this->pages->all()->first());
    }

    public function testGetList()
    {
        $list = $this->pages->getList();
        $this->assertTrue(is_array($list));
//        $this->assertSame($list['/home'], 'Home');
//        $this->assertSame($list['/blog'], 'Blog');
    }
}
