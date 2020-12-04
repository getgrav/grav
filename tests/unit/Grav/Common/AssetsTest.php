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
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
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
        $this->assertSame('<link href="/test.css" type="text/css" rel="stylesheet">' . PHP_EOL, $css);

        $array = $this->assets->getCss();

        /** @var Assets\BaseAsset $item */
        $item = reset($array);
        $actual = json_encode($item);
        $expected = '
        {
           "type":"css",
           "elements":{
              "asset":"\/test.css",
              "asset_type":"css",
              "order":0,
              "group":"head",
              "position":"pipeline",
              "priority":10,
              "attributes":{
                 "type":"text\/css",
                 "rel":"stylesheet"
              },
              "modified":false,
              "query":""
           }
        }';
        $this->assertJsonStringEqualsJsonString($expected, $actual);

        $this->assets->add('test.js');
        $js = $this->assets->js();
        $this->assertSame('<script src="/test.js"></script>' . PHP_EOL, $js);

        $array = $this->assets->getJs();

        /** @var Assets\BaseAsset $item */
        $item = reset($array);
        $actual = json_encode($item);
        $expected = '
        {
           "type":"js",
           "elements":{
              "asset":"\/test.js",
              "asset_type":"js",
              "order":0,
              "group":"head",
              "position":"pipeline",
              "priority":10,
              "attributes":[

              ],
              "modified":false,
              "query":""
           }
        }';
        $this->assertJsonStringEqualsJsonString($expected, $actual);

        //test addCss(). Test adding asset to a separate group
        $this->assets->reset();
        $this->assets->addCSS('test.css');
        $css = $this->assets->css();
        $this->assertSame('<link href="/test.css" type="text/css" rel="stylesheet">' . PHP_EOL, $css);

        $array = $this->assets->getCss();
        /** @var Assets\BaseAsset $item */
        $item = reset($array);
        $actual = json_encode($item);
        $expected = '
        {
           "type":"css",
           "elements":{
              "asset":"\/test.css",
              "asset_type":"css",
              "order":0,
              "group":"head",
              "position":"pipeline",
              "priority":10,
              "attributes":{
                 "type":"text\/css",
                 "rel":"stylesheet"
              },
              "modified":false,
              "query":""
           }
        }';
        $this->assertJsonStringEqualsJsonString($expected, $actual);

        //test addCss(). Testing with remote URL
        $this->assets->reset();
        $this->assets->addCSS('http://www.somesite.com/test.css');
        $css = $this->assets->css();
        $this->assertSame('<link href="http://www.somesite.com/test.css" type="text/css" rel="stylesheet">' . PHP_EOL, $css);

        $array = $this->assets->getCss();
        /** @var Assets\BaseAsset $item */
        $item = reset($array);
        $actual = json_encode($item);
        $expected = '
        {
           "type":"css",
           "elements":{
              "asset":"http:\/\/www.somesite.com\/test.css",
              "asset_type":"css",
              "order":0,
              "group":"head",
              "position":"pipeline",
              "priority":10,
              "attributes":{
                 "type":"text\/css",
                 "rel":"stylesheet"
              },
              "query":""
           }
        }';
        $this->assertJsonStringEqualsJsonString($expected, $actual);

        //test addCss() adding asset to a separate group, and with an alternate rel attribute
        $this->assets->reset();
        $this->assets->addCSS('test.css', ['group' => 'alternate', 'rel' => 'alternate']);
        $css = $this->assets->css('alternate');
        $this->assertSame('<link href="/test.css" type="text/css" rel="alternate">' . PHP_EOL, $css);

        //test addJs()
        $this->assets->reset();
        $this->assets->addJs('test.js');
        $js = $this->assets->js();
        $this->assertSame('<script src="/test.js"></script>' . PHP_EOL, $js);

        $array = $this->assets->getJs();
        /** @var Assets\BaseAsset $item */
        $item = reset($array);
        $actual = json_encode($item);
        $expected = '
        {
           "type":"js",
           "elements":{
              "asset":"\/test.js",
              "asset_type":"js",
              "order":0,
              "group":"head",
              "position":"pipeline",
              "priority":10,
              "attributes":[],
              "modified":false,
              "query":""
           }
        }';
        $this->assertJsonStringEqualsJsonString($expected, $actual);

        //Test CSS Groups
        $this->assets->reset();
        $this->assets->addCSS('test.css', ['group' => 'footer']);
        $css = $this->assets->css();
        $this->assertEmpty($css);
        $css = $this->assets->css('footer');
        $this->assertSame('<link href="/test.css" type="text/css" rel="stylesheet">' . PHP_EOL, $css);

        $array = $this->assets->getCss();
        /** @var Assets\BaseAsset $item */
        $item = reset($array);
        $actual = json_encode($item);
        $expected = '
        {
          "type": "css",
          "elements": {
            "asset": "/test.css",
            "asset_type": "css",
            "order": 0,
            "group": "footer",
            "position": "pipeline",
            "priority": 10,
            "attributes": {
              "type": "text/css",
              "rel": "stylesheet"
            },
            "modified": false,
            "query": ""
          }
        }
        ';
        $this->assertJsonStringEqualsJsonString($expected, $actual);

        //Test JS Groups
        $this->assets->reset();
        $this->assets->addJs('test.js', ['group' => 'footer']);
        $js = $this->assets->js();
        $this->assertEmpty($js);
        $js = $this->assets->js('footer');
        $this->assertSame('<script src="/test.js"></script>' . PHP_EOL, $js);

        $array = $this->assets->getJs();
        /** @var Assets\BaseAsset $item */
        $item = reset($array);
        $actual = json_encode($item);
        $expected = '
        {
          "type": "js",
          "elements": {
            "asset": "/test.js",
            "asset_type": "js",
            "order": 0,
            "group": "footer",
            "position": "pipeline",
            "priority": 10,
            "attributes": [],
            "modified": false,
            "query": ""
          }
        }';
        $this->assertJsonStringEqualsJsonString($expected, $actual);

        //Test async / defer
        $this->assets->reset();
        $this->assets->addJs('test.js', ['loading' => 'async']);
        $js = $this->assets->js();
        $this->assertSame('<script src="/test.js" async></script>' . PHP_EOL, $js);

        $array = $this->assets->getJs();
        /** @var Assets\BaseAsset $item */
        $item = reset($array);
        $actual = json_encode($item);
        $expected = '
        {
          "type": "js",
          "elements": {
            "asset": "/test.js",
            "asset_type": "js",
            "order": 0,
            "group": "head",
            "position": "pipeline",
            "priority": 10,
            "attributes": {
              "loading": "async"
            },
            "modified": false,
            "query": ""
          }
        }';
        $this->assertJsonStringEqualsJsonString($expected, $actual);

        $this->assets->reset();
        $this->assets->addJs('test.js', ['loading' => 'defer']);
        $js = $this->assets->js();
        $this->assertSame('<script src="/test.js" defer></script>' . PHP_EOL, $js);

        $array = $this->assets->getJs();
        /** @var Assets\BaseAsset $item */
        $item = reset($array);
        $actual = json_encode($item);
        $expected = '
        {
          "type": "js",
          "elements": {
            "asset": "/test.js",
            "asset_type": "js",
            "order": 0,
            "group": "head",
            "position": "pipeline",
            "priority": 10,
            "attributes": {
              "loading": "defer"
            },
            "modified": false,
            "query": ""
          }
        }';
        $this->assertJsonStringEqualsJsonString($expected, $actual);

        //Test inline
        $this->assets->reset();
        $this->assets->setJsPipeline(true);
        $this->assets->addJs('/system/assets/jquery/jquery-3.x.min.js');
        $js = $this->assets->js('head', ['loading' => 'inline']);
        $this->assertStringContainsString('"jquery",[],function()', $js);

        $this->assets->reset();
        $this->assets->setCssPipeline(true);
        $this->assets->addCss('/system/assets/debugger/phpdebugbar.css');
        $css = $this->assets->css('head', ['loading' => 'inline']);
        $this->assertStringContainsString('div.phpdebugbar', $css);

        $this->assets->reset();
        $this->assets->setCssPipeline(true);
        $this->assets->addCss('https://fonts.googleapis.com/css?family=Roboto');
        $css = $this->assets->css('head', ['loading' => 'inline']);
        $this->assertStringContainsString('font-family:\'Roboto\';', $css);

        //Test adding media queries
        $this->assets->reset();
        $this->assets->add('test.css', ['media' => 'only screen and (min-width: 640px)']);
        $css = $this->assets->css();
        $this->assertSame('<link href="/test.css" type="text/css" rel="stylesheet" media="only screen and (min-width: 640px)">' . PHP_EOL, $css);
    }

    public function testAddingAssetPropertiesWithArray()
    {
        //Test adding assets with object to define properties
        $this->assets->reset();
        $this->assets->addJs('test.js', ['loading' => 'async']);
        $js = $this->assets->js();
        $this->assertSame('<script src="/test.js" async></script>' . PHP_EOL, $js);
        $this->assets->reset();
    }

    public function testAddingJSAssetPropertiesWithArrayFromCollection()
    {
        //Test adding properties with array
        $this->assets->reset();
        $this->assets->addJs('jquery', ['loading' => 'async']);
        $js = $this->assets->js();
        $this->assertSame('<script src="/system/assets/jquery/jquery-2.x.min.js" async></script>' . PHP_EOL, $js);

        //Test priority too
        $this->assets->reset();
        $this->assets->addJs('jquery', ['loading' => 'async', 'priority' => 1]);
        $this->assets->addJs('test.js', ['loading' => 'async', 'priority' => 2]);
        $js = $this->assets->js();
        $this->assertSame('<script src="/test.js" async></script>' . PHP_EOL .
            '<script src="/system/assets/jquery/jquery-2.x.min.js" async></script>' . PHP_EOL, $js);

        //Test multiple groups
        $this->assets->reset();
        $this->assets->addJs('jquery', ['loading' => 'async', 'priority' => 1, 'group' => 'footer']);
        $this->assets->addJs('test.js', ['loading' => 'async', 'priority' => 2]);
        $js = $this->assets->js();
        $this->assertSame('<script src="/test.js" async></script>' . PHP_EOL, $js);
        $js = $this->assets->js('footer');
        $this->assertSame('<script src="/system/assets/jquery/jquery-2.x.min.js" async></script>' . PHP_EOL, $js);

        //Test adding array of assets
        //Test priority too
        $this->assets->reset();
        $this->assets->addJs(['jquery', 'test.js'], ['loading' => 'async']);
        $js = $this->assets->js();

        $this->assertSame('<script src="/system/assets/jquery/jquery-2.x.min.js" async></script>' . PHP_EOL .
            '<script src="/test.js" async></script>' . PHP_EOL, $js);
    }

    public function testAddingLegacyFormat()
    {
        // regular CSS add
        //test addCss(). Test adding asset to a separate group
        $this->assets->reset();
        $this->assets->addCSS('test.css', 15, true, 'bottom', 'async');
        $css = $this->assets->css('bottom');
        $this->assertSame('<link href="/test.css" type="text/css" rel="stylesheet" async>' . PHP_EOL, $css);

        $array = $this->assets->getCss();
        /** @var Assets\BaseAsset $item */
        $item = reset($array);
        $actual = json_encode($item);
        $expected = '
        {
           "type":"css",
           "elements":{
              "asset":"\/test.css",
              "asset_type":"css",
              "order":0,
              "group":"bottom",
              "position":"pipeline",
              "priority":15,
              "attributes":{
                 "type":"text\/css",
                 "rel":"stylesheet",
                 "loading":"async"
              },
              "modified":false,
              "query":""
           }
        }';
        $this->assertJsonStringEqualsJsonString($expected, $actual);

        $this->assets->reset();
        $this->assets->addJs('test.js', 15, false, 'defer', 'bottom');
        $js = $this->assets->js('bottom');
        $this->assertSame('<script src="/test.js" defer></script>' . PHP_EOL, $js);

        $array = $this->assets->getJs();
        /** @var Assets\BaseAsset $item */
        $item = reset($array);
        $actual = json_encode($item);
        $expected = '
        {
          "type": "js",
          "elements": {
            "asset": "/test.js",
            "asset_type": "js",
            "order": 0,
            "group": "bottom",
            "position": "after",
            "priority": 15,
            "attributes": {
              "loading": "defer"
            },
            "modified": false,
            "query": ""
          }
        }';
        $this->assertJsonStringEqualsJsonString($expected, $actual);


        $this->assets->reset();
        $this->assets->addInlineCss('body { color: black }', 15, 'bottom');
        $css = $this->assets->css('bottom');
        $this->assertSame('<style>' . PHP_EOL . 'body { color: black }' . PHP_EOL . '</style>' . PHP_EOL, $css);

        $this->assets->reset();
        $this->assets->addInlineJs('alert("test")', 15, 'bottom', ['id' => 'foo']);
        $js = $this->assets->js('bottom');
        $this->assertSame('<script id="foo">' . PHP_EOL . 'alert("test")' . PHP_EOL . '</script>' . PHP_EOL, $js);
    }

    public function testAddingCSSAssetPropertiesWithArrayFromCollection()
    {
        $this->assets->registerCollection('test', ['/system/assets/whoops.css']);

        //Test priority too
        $this->assets->reset();
        $this->assets->addCss('test', ['priority' => 1]);
        $this->assets->addCss('test.css', ['priority' => 2]);
        $css = $this->assets->css();
        $this->assertSame('<link href="/test.css" type="text/css" rel="stylesheet">' . PHP_EOL .
            '<link href="/system/assets/whoops.css" type="text/css" rel="stylesheet">' . PHP_EOL, $css);

        //Test multiple groups
        $this->assets->reset();
        $this->assets->addCss('test', ['priority' => 1, 'group' => 'footer']);
        $this->assets->addCss('test.css', ['priority' => 2]);
        $css = $this->assets->css();
        $this->assertSame('<link href="/test.css" type="text/css" rel="stylesheet">' . PHP_EOL, $css);
        $css = $this->assets->css('footer');
        $this->assertSame('<link href="/system/assets/whoops.css" type="text/css" rel="stylesheet">' . PHP_EOL, $css);

        //Test adding array of assets
        //Test priority too
        $this->assets->reset();
        $this->assets->addCss(['test', 'test.css'], ['loading' => 'async']);
        $css = $this->assets->css();
        $this->assertSame('<link href="/system/assets/whoops.css" type="text/css" rel="stylesheet" async>' . PHP_EOL .
            '<link href="/test.css" type="text/css" rel="stylesheet" async>' . PHP_EOL, $css);
    }

    public function testPriorityOfAssets()
    {
        $this->assets->reset();
        $this->assets->add('test.css');
        $this->assets->add('test-after.css');

        $css = $this->assets->css();
        $this->assertSame('<link href="/test.css" type="text/css" rel="stylesheet">' . PHP_EOL .
            '<link href="/test-after.css" type="text/css" rel="stylesheet">' . PHP_EOL, $css);

        //----------------
        $this->assets->reset();
        $this->assets->add('test-after.css', 1);
        $this->assets->add('test.css', 2);

        $css = $this->assets->css();
        $this->assertSame('<link href="/test.css" type="text/css" rel="stylesheet">' . PHP_EOL .
            '<link href="/test-after.css" type="text/css" rel="stylesheet">' . PHP_EOL, $css);

        //----------------
        $this->assets->reset();
        $this->assets->add('test-after.css', 1);
        $this->assets->add('test.css', 2);
        $this->assets->add('test-before.css', 3);

        $css = $this->assets->css();
        $this->assertSame('<link href="/test-before.css" type="text/css" rel="stylesheet">' . PHP_EOL .
            '<link href="/test.css" type="text/css" rel="stylesheet">' . PHP_EOL .
            '<link href="/test-after.css" type="text/css" rel="stylesheet">' . PHP_EOL, $css);
    }

    public function testPipeline()
    {
        $this->assets->reset();

        //File not existing. Pipeline searches for that file without reaching it. Output is empty.
        $this->assets->add('test.css', null, true);
        $this->assets->setCssPipeline(true);
        $css = $this->assets->css();
        $this->assertRegExp('#<link href=\"\/assets\/(.*).css\" type=\"text\/css\" rel=\"stylesheet\">#', $css);

        //Add a core Grav CSS file, which is found. Pipeline will now return a file
        $this->assets->add('/system/assets/debugger/phpdebugbar', null, true);
        $css = $this->assets->css();
        $this->assertRegExp('#<link href=\"\/assets\/(.*).css\" type=\"text\/css\" rel=\"stylesheet\">#', $css);
    }

    public function testPipelineWithTimestamp()
    {
        $this->assets->reset();
        $this->assets->setTimestamp('foo');
        $this->assets->setCssPipeline(true);

        //Add a core Grav CSS file, which is found. Pipeline will now return a file
        $this->assets->add('/system/assets/debugger.css', null, true);
        $css = $this->assets->css();
        $this->assertRegExp('#<link href=\"\/assets\/(.*).css\?foo\" type=\"text\/css\" rel=\"stylesheet\">#', $css);
    }

    public function testInline()
    {
        $this->assets->reset();

        //File not existing. Pipeline searches for that file without reaching it. Output is empty.
        $this->assets->add('test.css', ['loading' => 'inline']);
        $css = $this->assets->css();
        $this->assertSame("<style>\n\n</style>\n", $css);

        $this->assets->reset();
        //Add a core Grav CSS file, which is found. Pipeline will now return its content.
        $this->assets->addCss('https://fonts.googleapis.com/css?family=Roboto', ['loading' => 'inline']);
        $this->assets->addCss('/system/assets/debugger/phpdebugbar.css', ['loading' => 'inline']);
        $css = $this->assets->css();
        $this->assertStringContainsString('font-family: \'Roboto\';', $css);
        $this->assertStringContainsString('div.phpdebugbar-header', $css);
    }

    public function testInlinePipeline()
    {
        $this->assets->reset();
        $this->assets->setCssPipeline(true);

        //File not existing. Pipeline searches for that file without reaching it. Output is empty.
        $this->assets->add('test.css');
        $css = $this->assets->css('head', ['loading' => 'inline']);
        $this->assertSame("<style>\n\n</style>\n", $css);

        //Add a core Grav CSS file, which is found. Pipeline will now return its content.
        $this->assets->addCss('https://fonts.googleapis.com/css?family=Roboto', null, true);
        $this->assets->add('/system/assets/debugger/phpdebugbar.css', null, true);
        $css = $this->assets->css('head', ['loading' => 'inline']);
        $this->assertStringContainsString('font-family:\'Roboto\';', $css);
        $this->assertStringContainsString('div.phpdebugbar', $css);
    }

    public function testAddAsyncJs()
    {
        $this->assets->reset();
        $this->assets->addAsyncJs('jquery');
        $js = $this->assets->js();
        $this->assertSame('<script src="/system/assets/jquery/jquery-2.x.min.js" async></script>' . PHP_EOL, $js);
    }

    public function testAddDeferJs()
    {
        $this->assets->reset();
        $this->assets->addDeferJs('jquery');
        $js = $this->assets->js();
        $this->assertSame('<script src="/system/assets/jquery/jquery-2.x.min.js" defer></script>' . PHP_EOL, $js);
    }

    public function testTimestamps()
    {
        // local CSS nothing extra
        $this->assets->reset();
        $this->assets->setTimestamp('foo');
        $this->assets->addCSS('test.css');
        $css = $this->assets->css();
        $this->assertSame('<link href="/test.css?foo" type="text/css" rel="stylesheet">' . PHP_EOL, $css);

        // local CSS already with param
        $this->assets->reset();
        $this->assets->setTimestamp('foo');
        $this->assets->addCSS('test.css?bar');
        $css = $this->assets->css();
        $this->assertSame('<link href="/test.css?bar&foo" type="text/css" rel="stylesheet">' . PHP_EOL, $css);

        // external CSS already
        $this->assets->reset();
        $this->assets->setTimestamp('foo');
        $this->assets->addCSS('http://somesite.com/test.css');
        $css = $this->assets->css();
        $this->assertSame('<link href="http://somesite.com/test.css?foo" type="text/css" rel="stylesheet">' . PHP_EOL, $css);

        // external CSS already with param
        $this->assets->reset();
        $this->assets->setTimestamp('foo');
        $this->assets->addCSS('http://somesite.com/test.css?bar');
        $css = $this->assets->css();
        $this->assertSame('<link href="http://somesite.com/test.css?bar&foo" type="text/css" rel="stylesheet">' . PHP_EOL, $css);

        // local JS nothing extra
        $this->assets->reset();
        $this->assets->setTimestamp('foo');
        $this->assets->addJs('test.js');
        $css = $this->assets->js();
        $this->assertSame('<script src="/test.js?foo"></script>' . PHP_EOL, $css);

        // local JS already with param
        $this->assets->reset();
        $this->assets->setTimestamp('foo');
        $this->assets->addJs('test.js?bar');
        $css = $this->assets->js();
        $this->assertSame('<script src="/test.js?bar&foo"></script>' . PHP_EOL, $css);

        // external JS already
        $this->assets->reset();
        $this->assets->setTimestamp('foo');
        $this->assets->addJs('http://somesite.com/test.js');
        $css = $this->assets->js();
        $this->assertSame('<script src="http://somesite.com/test.js?foo"></script>' . PHP_EOL, $css);

        // external JS already with param
        $this->assets->reset();
        $this->assets->setTimestamp('foo');
        $this->assets->addJs('http://somesite.com/test.js?bar');
        $css = $this->assets->js();
        $this->assertSame('<script src="http://somesite.com/test.js?bar&foo"></script>' . PHP_EOL, $css);
    }

    public function testAddInlineCss()
    {
        $this->assets->reset();
        $this->assets->addInlineCss('body { color: black }');
        $css = $this->assets->css();
        $this->assertSame('<style>' . PHP_EOL . 'body { color: black }' . PHP_EOL . '</style>' . PHP_EOL, $css);
    }

    public function testAddInlineJs()
    {
        $this->assets->reset();
        $this->assets->addInlineJs('alert("test")');
        $js = $this->assets->js();
        $this->assertSame('<script>' . PHP_EOL . 'alert("test")' . PHP_EOL . '</script>' . PHP_EOL, $js);
    }

    public function testGetCollections()
    {
        $this->assertIsArray($this->assets->getCollections());
        $this->assertContains('jquery', array_keys($this->assets->getCollections()));
        $this->assertContains('system://assets/jquery/jquery-2.x.min.js', $this->assets->getCollections());
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
        $this->assertContains('debugger', array_keys($this->assets->getCollections()));
    }

    public function testReset()
    {
        $this->assets->addInlineJs('alert("test")');
        $this->assets->reset();
        $this->assertCount(0, (array) $this->assets->getJs());

        $this->assets->addAsyncJs('jquery');
        $this->assets->reset();
        $this->assertCount(0, (array) $this->assets->getJs());

        $this->assets->addInlineCss('body { color: black }');
        $this->assets->reset();
        $this->assertCount(0, (array) $this->assets->getCss());

        $this->assets->add('/system/assets/debugger.css', null, true);
        $this->assets->reset();
        $this->assertCount(0, (array) $this->assets->getCss());
    }

    public function testResetJs()
    {
        $this->assets->addInlineJs('alert("test")');
        $this->assets->resetJs();
        $this->assertCount(0, (array) $this->assets->getJs());

        $this->assets->addAsyncJs('jquery');
        $this->assets->resetJs();
        $this->assertCount(0, (array) $this->assets->getJs());
    }

    public function testResetCss()
    {
        $this->assets->addInlineCss('body { color: black }');
        $this->assets->resetCss();
        $this->assertCount(0, (array) $this->assets->getCss());

        $this->assets->add('/system/assets/debugger.css', null, true);
        $this->assets->resetCss();
        $this->assertCount(0, (array) $this->assets->getCss());
    }

    public function testAddDirCss()
    {
        $this->assets->addDirCss('/system');

        $this->assertIsArray($this->assets->getCss());
        $this->assertGreaterThan(0, (array) $this->assets->getCss());
        $this->assertIsArray($this->assets->getJs());
        $this->assertCount(0, (array) $this->assets->getJs());

        $this->assets->reset();
        $this->assets->addDirCss('/system/assets');

        $this->assertIsArray($this->assets->getCss());
        $this->assertGreaterThan(0, (array) $this->assets->getCss());
        $this->assertIsArray($this->assets->getJs());
        $this->assertCount(0, (array) $this->assets->getJs());

        $this->assets->reset();
        $this->assets->addDirJs('/system');

        $this->assertIsArray($this->assets->getCss());
        $this->assertCount(0, (array) $this->assets->getCss());
        $this->assertIsArray($this->assets->getJs());
        $this->assertGreaterThan(0, (array) $this->assets->getJs());

        $this->assets->reset();
        $this->assets->addDirJs('/system/assets');

        $this->assertIsArray($this->assets->getCss());
        $this->assertCount(0, (array) $this->assets->getCss());
        $this->assertIsArray($this->assets->getJs());
        $this->assertGreaterThan(0, (array) $this->assets->getJs());

        $this->assets->reset();
        $this->assets->addDir('/system/assets');

        $this->assertIsArray($this->assets->getCss());
        $this->assertGreaterThan(0, (array) $this->assets->getCss());
        $this->assertIsArray($this->assets->getJs());
        $this->assertGreaterThan(0, (array) $this->assets->getJs());

        //Use streams
        $this->assets->reset();
        $this->assets->addDir('system://assets');

        $this->assertIsArray($this->assets->getCss());
        $this->assertGreaterThan(0, (array) $this->assets->getCss());
        $this->assertIsArray($this->assets->getJs());
        $this->assertGreaterThan(0, (array) $this->assets->getJs());
    }
}
