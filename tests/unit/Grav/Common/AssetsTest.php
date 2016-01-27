<?php

use Codeception\Util\Fixtures;

class AssetsTest extends \Codeception\TestCase\Test
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected function _before()
    {
    }

    protected function _after()
    {
    }

    public function grav()
    {
        $grav = Fixtures::get('grav');
        return $grav;
    }

    public function assets()
    {
        return $this->grav()['assets'];
    }

    public function testAddingAssets()
    {
        $assets = $this->assets();

        //test add()
        $assets->add('test.css');

        $css = $assets->css();
        $this->assertSame($css, '<link href="/test.css" type="text/css" rel="stylesheet" />' . PHP_EOL);

        $array = $assets->getCss();
        $this->assertSame(reset($array), [
            'asset' => '/test.css',
            'priority' => 10,
            'order' => 0,
            'pipeline' => true,
            'group' => 'head'
        ]);

        $assets->add('test.js');
        $js = $assets->js();
        $this->assertSame($js, '<script src="/test.js" type="text/javascript" ></script>' . PHP_EOL);

        $array = $assets->getCss();
        $this->assertSame(reset($array), [
            'asset' => '/test.css',
            'priority' => 10,
            'order' => 0,
            'pipeline' => true,
            'group' => 'head'
        ]);

        //test addCss(). Test adding asset to a separate group
        $assets->reset();
        $assets->addCSS('test.css');
        $css = $assets->css();
        $this->assertSame($css, '<link href="/test.css" type="text/css" rel="stylesheet" />' . PHP_EOL);

        $array = $assets->getCss();
        $this->assertSame(reset($array), [
            'asset' => '/test.css',
            'priority' => 10,
            'order' => 0,
            'pipeline' => true,
            'group' => 'head'
        ]);

        //test addJs()
        $assets->reset();
        $assets->addJs('test.js');
        $js = $assets->js();
        $this->assertSame($js, '<script src="/test.js" type="text/javascript" ></script>' . PHP_EOL);

        $array = $assets->getJs();
        $this->assertSame(reset($array), [
            'asset' => '/test.js',
            'priority' => 10,
            'order' => 0,
            'pipeline' => true,
            'loading' => '',
            'group' => 'head'
        ]);

        //Test CSS Groups
        $assets->reset();
        $assets->addCSS('test.css', null, true, 'footer');
        $css = $assets->css();
        $this->assertEmpty($css);
        $css = $assets->css('footer');
        $this->assertSame($css, '<link href="/test.css" type="text/css" rel="stylesheet" />' . PHP_EOL);

        $array = $assets->getCss();
        $this->assertSame(reset($array), [
            'asset' => '/test.css',
            'priority' => 10,
            'order' => 0,
            'pipeline' => true,
            'group' => 'footer'
        ]);

        //Test JS Groups
        $assets->reset();
        $assets->addJs('test.js', null, true, null, 'footer');
        $js = $assets->js();
        $this->assertEmpty($js);
        $js = $assets->js('footer');
        $this->assertSame($js, '<script src="/test.js" type="text/javascript" ></script>' . PHP_EOL);

        $array = $assets->getJs();
        $this->assertSame(reset($array), [
            'asset' => '/test.js',
            'priority' => 10,
            'order' => 0,
            'pipeline' => true,
            'loading' => '',
            'group' => 'footer'
        ]);

        //Test async / defer
        $assets->reset();
        $assets->addJs('test.js', null, true, 'async', null);
        $js = $assets->js();
        $this->assertSame($js, '<script src="/test.js" type="text/javascript" async></script>' . PHP_EOL);

        $array = $assets->getJs();
        $this->assertSame(reset($array), [
            'asset' => '/test.js',
            'priority' => 10,
            'order' => 0,
            'pipeline' => true,
            'loading' => 'async',
            'group' => 'head'
        ]);

        $assets->reset();
        $assets->addJs('test.js', null, true, 'defer', null);
        $js = $assets->js();
        $this->assertSame($js, '<script src="/test.js" type="text/javascript" defer></script>' . PHP_EOL);

        $array = $assets->getJs();
        $this->assertSame(reset($array), [
            'asset' => '/test.js',
            'priority' => 10,
            'order' => 0,
            'pipeline' => true,
            'loading' => 'defer',
            'group' => 'head'
        ]);
    }

    public function testAddingAssetPropertiesWithArray()
    {
        $assets = $this->assets();

        //Test adding assets with object to define properties
        $assets->reset();
        $assets->addJs('test.js', ['loading' => 'async']);
        $js = $assets->js();
        $this->assertSame($js, '<script src="/test.js" type="text/javascript" async></script>' . PHP_EOL);
        $assets->reset();

    }

    public function testAddingJSAssetPropertiesWithArrayFromCollection()
    {
        $assets = $this->assets();

        //Test adding properties with array
        $assets->reset();
        $assets->addJs('jquery', ['loading' => 'async']);
        $js = $assets->js();
        $this->assertSame($js, '<script src="/system/assets/jquery/jquery-2.x.min.js" type="text/javascript" async></script>' . PHP_EOL);

        //Test priority too
        $assets->reset();
        $assets->addJs('jquery', ['loading' => 'async', 'priority' => 1]);
        $assets->addJs('test.js', ['loading' => 'async', 'priority' => 2]);
        $js = $assets->js();
        $this->assertSame($js, '<script src="/test.js" type="text/javascript" async></script>' . PHP_EOL .
            '<script src="/system/assets/jquery/jquery-2.x.min.js" type="text/javascript" async></script>' . PHP_EOL);

        //Test multiple groups
        $assets->reset();
        $assets->addJs('jquery', ['loading' => 'async', 'priority' => 1, 'group' => 'footer']);
        $assets->addJs('test.js', ['loading' => 'async', 'priority' => 2]);
        $js = $assets->js();
        $this->assertSame($js, '<script src="/test.js" type="text/javascript" async></script>' . PHP_EOL);
        $js = $assets->js('footer');
        $this->assertSame($js, '<script src="/system/assets/jquery/jquery-2.x.min.js" type="text/javascript" async></script>' . PHP_EOL);

        //Test adding array of assets
        //Test priority too
        $assets->reset();
        $assets->addJs(['jquery', 'test.js'], ['loading' => 'async']);
        $js = $assets->js();

        $this->assertSame($js, '<script src="/system/assets/jquery/jquery-2.x.min.js" type="text/javascript" async></script>' . PHP_EOL .
            '<script src="/test.js" type="text/javascript" async></script>' . PHP_EOL);
    }

    public function testAddingCSSAssetPropertiesWithArrayFromCollection()
    {
        $assets = $this->assets();

        $assets->registerCollection('test', ['/system/assets/whoops.css']);

        //Test priority too
        $assets->reset();
        $assets->addCss('test', ['priority' => 1]);
        $assets->addCss('test.css', ['priority' => 2]);
        $css = $assets->css();
        $this->assertSame($css, '<link href="/test.css" type="text/css" rel="stylesheet" />' . PHP_EOL .
            '<link href="/system/assets/whoops.css" type="text/css" rel="stylesheet" />' . PHP_EOL);

        //Test multiple groups
        $assets->reset();
        $assets->addCss('test', ['priority' => 1, 'group' => 'footer']);
        $assets->addCss('test.css', ['priority' => 2]);
        $css = $assets->css();
        $this->assertSame($css, '<link href="/test.css" type="text/css" rel="stylesheet" />' . PHP_EOL);
        $css = $assets->css('footer');
        $this->assertSame($css, '<link href="/system/assets/whoops.css" type="text/css" rel="stylesheet" />' . PHP_EOL);

        //Test adding array of assets
        //Test priority too
        $assets->reset();
        $assets->addCss(['test', 'test.css'], ['loading' => 'async']);
        $css = $assets->css();
        $this->assertSame($css, '<link href="/system/assets/whoops.css" type="text/css" rel="stylesheet" />' . PHP_EOL .
            '<link href="/test.css" type="text/css" rel="stylesheet" />' . PHP_EOL);
    }

    public function testPriorityOfAssets()
    {
        $assets = $this->assets();

        $assets->reset();
        $assets->add('test.css');
        $assets->add('test-after.css');

        $css = $assets->css();
        $this->assertSame($css, '<link href="/test.css" type="text/css" rel="stylesheet" />' . PHP_EOL .
            '<link href="/test-after.css" type="text/css" rel="stylesheet" />' . PHP_EOL);

        //----------------
        $assets->reset();
        $assets->add('test-after.css', 1);
        $assets->add('test.css', 2);

        $css = $assets->css();
        $this->assertSame($css, '<link href="/test.css" type="text/css" rel="stylesheet" />' . PHP_EOL .
            '<link href="/test-after.css" type="text/css" rel="stylesheet" />' . PHP_EOL);

        //----------------
        $assets->reset();
        $assets->add('test-after.css', 1);
        $assets->add('test.css', 2);
        $assets->add('test-before.css', 3);

        $css = $assets->css();
        $this->assertSame($css, '<link href="/test-before.css" type="text/css" rel="stylesheet" />' . PHP_EOL .
            '<link href="/test.css" type="text/css" rel="stylesheet" />' . PHP_EOL .
            '<link href="/test-after.css" type="text/css" rel="stylesheet" />' . PHP_EOL);
    }

    public function testPipeline()
    {
        $assets = $this->assets();

        $assets->reset();

        //File not existing. Pipeline searches for that file without reaching it. Output is empty.
        $assets->add('test.css', null, true);
        $assets->setCssPipeline(true);
        $css = $assets->css();
        $this->assertSame($css, '');

        //Add a core Grav CSS file, which is found. Pipeline will now return a file
        $assets->add('/system/assets/debugger.css', null, true);
        $css = $assets->css();
        $this->assertContains('<link href=', $css);
        $this->assertContains('type="text/css" rel="stylesheet" />', $css);
    }

    public function testAddAsyncJs()
    {
        $assets = $this->assets();

        $assets->reset();
        $assets->addAsyncJs('jquery');
        $js = $assets->js();
        $this->assertSame($js, '<script src="/system/assets/jquery/jquery-2.x.min.js" type="text/javascript" async></script>' . PHP_EOL);
    }

    public function testAddDeferJs()
    {
        $assets = $this->assets();

        $assets->reset();
        $assets->addDeferJs('jquery');
        $js = $assets->js();
        $this->assertSame($js, '<script src="/system/assets/jquery/jquery-2.x.min.js" type="text/javascript" defer></script>' . PHP_EOL);
    }

    public function testAddInlineCss()
    {
        $assets = $this->assets();

        $assets->reset();
        $assets->addInlineCss('body { color: black }');
        $css = $assets->css();
        $this->assertSame($css, PHP_EOL. '<style>' .PHP_EOL . 'body { color: black }' . PHP_EOL.PHP_EOL .'</style>' . PHP_EOL);
    }

    public function testAddInlineJs()
    {
        $assets = $this->assets();

        $assets->reset();
        $assets->addInlineJs('alert("test")');
        $js = $assets->js();
        $this->assertSame($js, PHP_EOL. '<script>' .PHP_EOL . 'alert("test")' . PHP_EOL.PHP_EOL .'</script>' . PHP_EOL);

    public function testGetCollections()
    {
        $assets = $this->assets();

        $this->assertTrue(is_array($assets->getCollections()));
        $this->assertTrue(in_array('jquery', array_keys($assets->getCollections())));
        $this->assertTrue(in_array('system://assets/jquery/jquery-2.x.min.js', $assets->getCollections()));
    }

    public function testExists()
    {
        $assets = $this->assets();

        $this->assertTrue($assets->exists('jquery'));
        $this->assertFalse($assets->exists('another-unexisting-library'));
    }

}
    public function testReset()
    {
        $assets = $this->assets();

        $assets->addInlineJs('alert("test")');
        $assets->reset();
        $this->assertTrue(count($assets->js()) == 0);

        $assets->addAsyncJs('jquery');
        $assets->reset();

        $this->assertTrue(count($assets->js()) == 0);

        $assets->addInlineCss('body { color: black }');
        $assets->reset();

        $this->assertTrue(count($assets->css()) == 0);

        $assets->add('/system/assets/debugger.css', null, true);
        $assets->reset();

        $this->assertTrue(count($assets->css()) == 0);
    }
