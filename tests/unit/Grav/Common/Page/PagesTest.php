<?php

use Codeception\Util\Fixtures;
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
        $newPagesLocation = 'tests/fake/simple-site/user/pages/';
        $this->grav['pages']->setPagesLocation($newPagesLocation);

        $this->pages = $this->grav['pages'];
    }

    public function testAll()
    {
        $this->assertTrue(is_object($this->pages->all()));
        $this->assertTrue(is_array($this->pages->all()->toArray()));
        $this->assertInstanceOf('Grav\Common\Page\Page', $this->pages->all()->first());
    }

    public function testGetList()
    {
        $list = $this->pages->getList();

        $this->assertTrue(is_array($list));
        $this->assertSame($list['/'], 'Home');
        $this->assertSame($list['/blog'], 'Blog');
    }
}
