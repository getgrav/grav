<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Assets;

/**
 * Class AssetsTest
 */
class AssetsTest extends \Codeception\TestCase\Test
{
    /** @var Grav $grav */
    protected $grav;

    /** @var Assets $assets */
    protected $assets;

    protected function _before()
    {
        $this->grav = Fixtures::get('grav');
        $this->assets = $this->grav['assets'];
    }

    protected function _after()
    {
    }

    public function testAddingAssets()
    {
        //test add()
        $this->assets->add('test.css');

        $css = $this->assets->css();
        $this->assertSame('<link href="/test.css" type="text/css" rel="stylesheet" />' . PHP_EOL, $css);

        $array = $this->assets->getCss();
        $this->assertSame([
            'asset'    => '/test.css',
            'priority' => 10,
            'order'    => 0,
            'pipeline' => true,
            'group'    => 'head'
        ], reset($array));

        $this->assets->add('test.js');
        $js = $this->assets->js();
        $this->assertSame('<script src="/test.js" type="text/javascript" ></script>' . PHP_EOL, $js);

        $array = $this->assets->getCss();
        $this->assertSame([
            'asset'    => '/test.css',
            'priority' => 10,
            'order'    => 0,
            'pipeline' => true,
            'group'    => 'head'
        ], reset($array));

        //test addCss(). Test adding asset to a separate group
        $this->assets->reset();
        $this->assets->addCSS('test.css');
        $css = $this->assets->css();
        $this->assertSame('<link href="/test.css" type="text/css" rel="stylesheet" />' . PHP_EOL, $css);

        $array = $this->assets->getCss();
        $this->assertSame([
            'asset'    => '/test.css',
            'priority' => 10,
            'order'    => 0,
            'pipeline' => true,
            'group'    => 'head'
        ], reset($array));

        //test addJs()
        $this->assets->reset();
        $this->assets->addJs('test.js');
        $js = $this->assets->js();
        $this->assertSame('<script src="/test.js" type="text/javascript" ></script>' . PHP_EOL, $js);

        $array = $this->assets->getJs();
        $this->assertSame([
            'asset'    => '/test.js',
            'priority' => 10,
            'order'    => 0,
            'pipeline' => true,
            'loading'  => '',
            'group'    => 'head'
        ], reset($array));

        //Test CSS Groups
        $this->assets->reset();
        $this->assets->addCSS('test.css', null, true, 'footer');
        $css = $this->assets->css();
        $this->assertEmpty($css);
        $css = $this->assets->css('footer');
        $this->assertSame('<link href="/test.css" type="text/css" rel="stylesheet" />' . PHP_EOL, $css);

        $array = $this->assets->getCss();
        $this->assertSame([
            'asset'    => '/test.css',
            'priority' => 10,
            'order'    => 0,
            'pipeline' => true,
            'group'    => 'footer'
        ], reset($array));

        //Test JS Groups
        $this->assets->reset();
        $this->assets->addJs('test.js', null, true, null, 'footer');
        $js = $this->assets->js();
        $this->assertEmpty($js);
        $js = $this->assets->js('footer');
        $this->assertSame('<script src="/test.js" type="text/javascript" ></script>' . PHP_EOL, $js);

        $array = $this->assets->getJs();
        $this->assertSame([
            'asset'    => '/test.js',
            'priority' => 10,
            'order'    => 0,
            'pipeline' => true,
            'loading'  => '',
            'group'    => 'footer'
        ], reset($array));

        //Test async / defer
        $this->assets->reset();
        $this->assets->addJs('test.js', null, true, 'async', null);
        $js = $this->assets->js();
        $this->assertSame('<script src="/test.js" type="text/javascript" async></script>' . PHP_EOL, $js);

        $array = $this->assets->getJs();
        $this->assertSame([
            'asset'    => '/test.js',
            'priority' => 10,
            'order'    => 0,
            'pipeline' => true,
            'loading'  => 'async',
            'group'    => 'head'
        ], reset($array));

        $this->assets->reset();
        $this->assets->addJs('test.js', null, true, 'defer', null);
        $js = $this->assets->js();
        $this->assertSame('<script src="/test.js" type="text/javascript" defer></script>' . PHP_EOL, $js);

        $array = $this->assets->getJs();
        $this->assertSame([
            'asset'    => '/test.js',
            'priority' => 10,
            'order'    => 0,
            'pipeline' => true,
            'loading'  => 'defer',
            'group'    => 'head'
        ], reset($array));
    }

    public function testAddingAssetPropertiesWithArray()
    {
        //Test adding assets with object to define properties
        $this->assets->reset();
        $this->assets->addJs('test.js', ['loading' => 'async']);
        $js = $this->assets->js();
        $this->assertSame('<script src="/test.js" type="text/javascript" async></script>' . PHP_EOL, $js);
        $this->assets->reset();

    }

    public function testAddingJSAssetPropertiesWithArrayFromCollection()
    {
        //Test adding properties with array
        $this->assets->reset();
        $this->assets->addJs('jquery', ['loading' => 'async']);
        $js = $this->assets->js();
        $this->assertSame('<script src="/system/assets/jquery/jquery-2.x.min.js" type="text/javascript" async></script>' . PHP_EOL, $js);

        //Test priority too
        $this->assets->reset();
        $this->assets->addJs('jquery', ['loading' => 'async', 'priority' => 1]);
        $this->assets->addJs('test.js', ['loading' => 'async', 'priority' => 2]);
        $js = $this->assets->js();
        $this->assertSame('<script src="/test.js" type="text/javascript" async></script>' . PHP_EOL .
            '<script src="/system/assets/jquery/jquery-2.x.min.js" type="text/javascript" async></script>' . PHP_EOL, $js);

        //Test multiple groups
        $this->assets->reset();
        $this->assets->addJs('jquery', ['loading' => 'async', 'priority' => 1, 'group' => 'footer']);
        $this->assets->addJs('test.js', ['loading' => 'async', 'priority' => 2]);
        $js = $this->assets->js();
        $this->assertSame('<script src="/test.js" type="text/javascript" async></script>' . PHP_EOL, $js);
        $js = $this->assets->js('footer');
        $this->assertSame('<script src="/system/assets/jquery/jquery-2.x.min.js" type="text/javascript" async></script>' . PHP_EOL, $js);

        //Test adding array of assets
        //Test priority too
        $this->assets->reset();
        $this->assets->addJs(['jquery', 'test.js'], ['loading' => 'async']);
        $js = $this->assets->js();

        $this->assertSame('<script src="/system/assets/jquery/jquery-2.x.min.js" type="text/javascript" async></script>' . PHP_EOL .
            '<script src="/test.js" type="text/javascript" async></script>' . PHP_EOL, $js);
    }

    public function testAddingCSSAssetPropertiesWithArrayFromCollection()
    {
        $this->assets->registerCollection('test', ['/system/assets/whoops.css']);

        //Test priority too
        $this->assets->reset();
        $this->assets->addCss('test', ['priority' => 1]);
        $this->assets->addCss('test.css', ['priority' => 2]);
        $css = $this->assets->css();
        $this->assertSame('<link href="/test.css" type="text/css" rel="stylesheet" />' . PHP_EOL .
            '<link href="/system/assets/whoops.css" type="text/css" rel="stylesheet" />' . PHP_EOL, $css);

        //Test multiple groups
        $this->assets->reset();
        $this->assets->addCss('test', ['priority' => 1, 'group' => 'footer']);
        $this->assets->addCss('test.css', ['priority' => 2]);
        $css = $this->assets->css();
        $this->assertSame('<link href="/test.css" type="text/css" rel="stylesheet" />' . PHP_EOL, $css);
        $css = $this->assets->css('footer');
        $this->assertSame('<link href="/system/assets/whoops.css" type="text/css" rel="stylesheet" />' . PHP_EOL, $css);

        //Test adding array of assets
        //Test priority too
        $this->assets->reset();
        $this->assets->addCss(['test', 'test.css'], ['loading' => 'async']);
        $css = $this->assets->css();
        $this->assertSame('<link href="/system/assets/whoops.css" type="text/css" rel="stylesheet" />' . PHP_EOL .
            '<link href="/test.css" type="text/css" rel="stylesheet" />' . PHP_EOL, $css);
    }

    public function testPriorityOfAssets()
    {
        $this->assets->reset();
        $this->assets->add('test.css');
        $this->assets->add('test-after.css');

        $css = $this->assets->css();
        $this->assertSame('<link href="/test.css" type="text/css" rel="stylesheet" />' . PHP_EOL .
            '<link href="/test-after.css" type="text/css" rel="stylesheet" />' . PHP_EOL, $css);

        //----------------
        $this->assets->reset();
        $this->assets->add('test-after.css', 1);
        $this->assets->add('test.css', 2);

        $css = $this->assets->css();
        $this->assertSame('<link href="/test.css" type="text/css" rel="stylesheet" />' . PHP_EOL .
            '<link href="/test-after.css" type="text/css" rel="stylesheet" />' . PHP_EOL, $css);

        //----------------
        $this->assets->reset();
        $this->assets->add('test-after.css', 1);
        $this->assets->add('test.css', 2);
        $this->assets->add('test-before.css', 3);

        $css = $this->assets->css();
        $this->assertSame('<link href="/test-before.css" type="text/css" rel="stylesheet" />' . PHP_EOL .
            '<link href="/test.css" type="text/css" rel="stylesheet" />' . PHP_EOL .
            '<link href="/test-after.css" type="text/css" rel="stylesheet" />' . PHP_EOL, $css);
    }

    public function testPipeline()
    {
        $this->assets->reset();

        //File not existing. Pipeline searches for that file without reaching it. Output is empty.
        $this->assets->add('test.css', null, true);
        $this->assets->setCssPipeline(true);
        $css = $this->assets->css();
        $this->assertSame('', $css);

        //Add a core Grav CSS file, which is found. Pipeline will now return a file
        $this->assets->add('/system/assets/debugger.css', null, true);
        $css = $this->assets->css();
        $this->assertContains('<link href=', $css);
        $this->assertContains('type="text/css" rel="stylesheet" />', $css);
    }

    public function testAddAsyncJs()
    {
        $this->assets->reset();
        $this->assets->addAsyncJs('jquery');
        $js = $this->assets->js();
        $this->assertSame('<script src="/system/assets/jquery/jquery-2.x.min.js" type="text/javascript" async></script>' . PHP_EOL, $js);
    }

    public function testAddDeferJs()
    {
        $this->assets->reset();
        $this->assets->addDeferJs('jquery');
        $js = $this->assets->js();
        $this->assertSame('<script src="/system/assets/jquery/jquery-2.x.min.js" type="text/javascript" defer></script>' . PHP_EOL, $js);
    }

    public function testAddInlineCss()
    {
        $this->assets->reset();
        $this->assets->addInlineCss('body { color: black }');
        $css = $this->assets->css();
        $this->assertSame(PHP_EOL . '<style>' . PHP_EOL . 'body { color: black }' . PHP_EOL . PHP_EOL . '</style>' . PHP_EOL, $css);
    }

    public function testAddInlineJs()
    {
        $this->assets->reset();
        $this->assets->addInlineJs('alert("test")');
        $js = $this->assets->js();
        $this->assertSame(PHP_EOL . '<script>' . PHP_EOL . 'alert("test")' . PHP_EOL . PHP_EOL . '</script>' . PHP_EOL, $js);
    }

    public function testGetCollections()
    {
        $this->assertTrue(is_array($this->assets->getCollections()));
        $this->assertTrue(in_array('jquery', array_keys($this->assets->getCollections())));
        $this->assertTrue(in_array('system://assets/jquery/jquery-2.x.min.js', $this->assets->getCollections()));
    }

    public function testExists()
    {
        $this->assertTrue($this->assets->exists('jquery'));
        $this->assertFalse($this->assets->exists('another-unexisting-library'));
    }

    public function testRegisterCollection()
    {
        $this->assets->registerCollection('debugger', ['/system/assets/debugger.css']);
        $this->assertTrue($this->assets->exists('debugger'));
        $this->assertTrue(in_array('debugger', array_keys($this->assets->getCollections())));
    }

    public function testReset()
    {
        $this->assets->addInlineJs('alert("test")');
        $this->assets->reset();
        $this->assertTrue(count($this->assets->js()) == 0);

        $this->assets->addAsyncJs('jquery');
        $this->assets->reset();

        $this->assertTrue(count($this->assets->js()) == 0);

        $this->assets->addInlineCss('body { color: black }');
        $this->assets->reset();

        $this->assertTrue(count($this->assets->css()) == 0);

        $this->assets->add('/system/assets/debugger.css', null, true);
        $this->assets->reset();

        $this->assertTrue(count($this->assets->css()) == 0);
    }

    public function testResetJs()
    {
        $this->assets->addInlineJs('alert("test")');
        $this->assets->resetJs();
        $this->assertTrue(count($this->assets->js()) == 0);

        $this->assets->addAsyncJs('jquery');
        $this->assets->resetJs();

        $this->assertTrue(count($this->assets->js()) == 0);
    }

    public function testResetCss()
    {
        $this->assertTrue(count($this->assets->js()) == 0);

        $this->assets->addInlineCss('body { color: black }');
        $this->assets->resetCss();

        $this->assertTrue(count($this->assets->css()) == 0);

        $this->assets->add('/system/assets/debugger.css', null, true);
        $this->assets->resetCss();

        $this->assertTrue(count($this->assets->css()) == 0);
    }

    public function testAddDirCss()
    {
        $this->assets->addDirCss('/system');

        $this->assertTrue(is_array($this->assets->getCss()));
        $this->assertTrue(count($this->assets->getCss()) > 0);
        $this->assertTrue(is_array($this->assets->getJs()));
        $this->assertTrue(count($this->assets->getJs()) == 0);

        $this->assets->reset();
        $this->assets->addDirCss('/system/assets');

        $this->assertTrue(is_array($this->assets->getCss()));
        $this->assertTrue(count($this->assets->getCss()) > 0);
        $this->assertTrue(is_array($this->assets->getJs()));
        $this->assertTrue(count($this->assets->getJs()) == 0);

        $this->assets->reset();
        $this->assets->addDirJs('/system');

        $this->assertTrue(is_array($this->assets->getCss()));
        $this->assertTrue(count($this->assets->getCss()) == 0);
        $this->assertTrue(is_array($this->assets->getJs()));
        $this->assertTrue(count($this->assets->getJs()) > 0);

        $this->assets->reset();
        $this->assets->addDirJs('/system/assets');

        $this->assertTrue(is_array($this->assets->getCss()));
        $this->assertTrue(count($this->assets->getCss()) == 0);
        $this->assertTrue(is_array($this->assets->getJs()));
        $this->assertTrue(count($this->assets->getJs()) > 0);

        $this->assets->reset();
        $this->assets->addDir('/system/assets');

        $this->assertTrue(is_array($this->assets->getCss()));
        $this->assertTrue(count($this->assets->getCss()) > 0);
        $this->assertTrue(is_array($this->assets->getJs()));
        $this->assertTrue(count($this->assets->getJs()) > 0);

        //Use streams
        $this->assets->reset();
        $this->assets->addDir('system://assets');

        $this->assertTrue(is_array($this->assets->getCss()));
        $this->assertTrue(count($this->assets->getCss()) > 0);
        $this->assertTrue(is_array($this->assets->getJs()));
        $this->assertTrue(count($this->assets->getJs()) > 0);

    }
}
