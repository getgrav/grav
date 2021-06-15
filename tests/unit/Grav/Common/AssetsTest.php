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

    protected function _before(): void
    {
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->assets = $this->grav['assets'];
    }

    protected function _after(): void
    {
    }

    public function testAddingAssets(): void
    {
        //test add()
        $this->assets->add('test.css');

        $css = $this->assets->css();
        self::assertSame('<link href="/test.css" type="text/css" rel="stylesheet">' . PHP_EOL, $css);

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
        self::assertJsonStringEqualsJsonString($expected, $actual);

        $this->assets->add('test.js');
        $js = $this->assets->js();
        self::assertSame('<script src="/test.js"></script>' . PHP_EOL, $js);

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
        self::assertJsonStringEqualsJsonString($expected, $actual);

        //test addCss(). Test adding asset to a separate group
        $this->assets->reset();
        $this->assets->addCSS('test.css');
        $css = $this->assets->css();
        self::assertSame('<link href="/test.css" type="text/css" rel="stylesheet">' . PHP_EOL, $css);

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
        self::assertJsonStringEqualsJsonString($expected, $actual);

        //test addCss(). Testing with remote URL
        $this->assets->reset();
        $this->assets->addCSS('http://www.somesite.com/test.css');
        $css = $this->assets->css();
        self::assertSame('<link href="http://www.somesite.com/test.css" type="text/css" rel="stylesheet">' . PHP_EOL, $css);

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
        self::assertJsonStringEqualsJsonString($expected, $actual);

        //test addCss() adding asset to a separate group, and with an alternate rel attribute
        $this->assets->reset();
        $this->assets->addCSS('test.css', ['group' => 'alternate', 'rel' => 'alternate']);
        $css = $this->assets->css('alternate');
        self::assertSame('<link href="/test.css" type="text/css" rel="alternate">' . PHP_EOL, $css);

        //test addJs()
        $this->assets->reset();
        $this->assets->addJs('test.js');
        $js = $this->assets->js();
        self::assertSame('<script src="/test.js"></script>' . PHP_EOL, $js);

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
        self::assertJsonStringEqualsJsonString($expected, $actual);

        //Test CSS Groups
        $this->assets->reset();
        $this->assets->addCSS('test.css', ['group' => 'footer']);
        $css = $this->assets->css();
        self::assertEmpty($css);
        $css = $this->assets->css('footer');
        self::assertSame('<link href="/test.css" type="text/css" rel="stylesheet">' . PHP_EOL, $css);

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
        self::assertJsonStringEqualsJsonString($expected, $actual);

        //Test JS Groups
        $this->assets->reset();
        $this->assets->addJs('test.js', ['group' => 'footer']);
        $js = $this->assets->js();
        self::assertEmpty($js);
        $js = $this->assets->js('footer');
        self::assertSame('<script src="/test.js"></script>' . PHP_EOL, $js);

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
        self::assertJsonStringEqualsJsonString($expected, $actual);

        //Test async / defer
        $this->assets->reset();
        $this->assets->addJs('test.js', ['loading' => 'async']);
        $js = $this->assets->js();
        self::assertSame('<script src="/test.js" async></script>' . PHP_EOL, $js);

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
        self::assertJsonStringEqualsJsonString($expected, $actual);

        $this->assets->reset();
        $this->assets->addJs('test.js', ['loading' => 'defer']);
        $js = $this->assets->js();
        self::assertSame('<script src="/test.js" defer></script>' . PHP_EOL, $js);

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
        self::assertJsonStringEqualsJsonString($expected, $actual);

        //Test inline
        $this->assets->reset();
        $this->assets->setJsPipeline(true);
        $this->assets->addJs('/system/assets/jquery/jquery-3.x.min.js');
        $js = $this->assets->js('head', ['loading' => 'inline']);
        self::assertStringContainsString('"jquery",[],function()', $js);

        $this->assets->reset();
        $this->assets->setCssPipeline(true);
        $this->assets->addCss('/system/assets/debugger/phpdebugbar.css');
        $css = $this->assets->css('head', ['loading' => 'inline']);
        self::assertStringContainsString('div.phpdebugbar', $css);

        $this->assets->reset();
        $this->assets->setCssPipeline(true);
        $this->assets->addCss('https://fonts.googleapis.com/css?family=Roboto');
        $css = $this->assets->css('head', ['loading' => 'inline']);
        self::assertStringContainsString('font-family:\'Roboto\';', $css);

        //Test adding media queries
        $this->assets->reset();
        $this->assets->add('test.css', ['media' => 'only screen and (min-width: 640px)']);
        $css = $this->assets->css();
        self::assertSame('<link href="/test.css" type="text/css" rel="stylesheet" media="only screen and (min-width: 640px)">' . PHP_EOL, $css);
    }

    public function testAddingAssetPropertiesWithArray(): void
    {
        //Test adding assets with object to define properties
        $this->assets->reset();
        $this->assets->addJs('test.js', ['loading' => 'async']);
        $js = $this->assets->js();
        self::assertSame('<script src="/test.js" async></script>' . PHP_EOL, $js);
        $this->assets->reset();
    }

    public function testAddingJSAssetPropertiesWithArrayFromCollection(): void
    {
        //Test adding properties with array
        $this->assets->reset();
        $this->assets->addJs('jquery', ['loading' => 'async']);
        $js = $this->assets->js();
        self::assertSame('<script src="/system/assets/jquery/jquery-3.x.min.js" async></script>' . PHP_EOL, $js);

        //Test priority too
        $this->assets->reset();
        $this->assets->addJs('jquery', ['loading' => 'async', 'priority' => 1]);
        $this->assets->addJs('test.js', ['loading' => 'async', 'priority' => 2]);
        $js = $this->assets->js();
        self::assertSame('<script src="/test.js" async></script>' . PHP_EOL .
            '<script src="/system/assets/jquery/jquery-3.x.min.js" async></script>' . PHP_EOL, $js);

        //Test multiple groups
        $this->assets->reset();
        $this->assets->addJs('jquery', ['loading' => 'async', 'priority' => 1, 'group' => 'footer']);
        $this->assets->addJs('test.js', ['loading' => 'async', 'priority' => 2]);
        $js = $this->assets->js();
        self::assertSame('<script src="/test.js" async></script>' . PHP_EOL, $js);
        $js = $this->assets->js('footer');
        self::assertSame('<script src="/system/assets/jquery/jquery-3.x.min.js" async></script>' . PHP_EOL, $js);

        //Test adding array of assets
        //Test priority too
        $this->assets->reset();
        $this->assets->addJs(['jquery', 'test.js'], ['loading' => 'async']);
        $js = $this->assets->js();

        self::assertSame('<script src="/system/assets/jquery/jquery-3.x.min.js" async></script>' . PHP_EOL .
            '<script src="/test.js" async></script>' . PHP_EOL, $js);
    }

    public function testAddingLegacyFormat(): void
    {
        // regular CSS add
        //test addCss(). Test adding asset to a separate group
        $this->assets->reset();
        $this->assets->addCSS('test.css', 15, true, 'bottom', 'async');
        $css = $this->assets->css('bottom');
        self::assertSame('<link href="/test.css" type="text/css" rel="stylesheet" async>' . PHP_EOL, $css);

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
        self::assertJsonStringEqualsJsonString($expected, $actual);

        $this->assets->reset();
        $this->assets->addJs('test.js', 15, false, 'defer', 'bottom');
        $js = $this->assets->js('bottom');
        self::assertSame('<script src="/test.js" defer></script>' . PHP_EOL, $js);

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
        self::assertJsonStringEqualsJsonString($expected, $actual);


        $this->assets->reset();
        $this->assets->addInlineCss('body { color: black }', 15, 'bottom');
        $css = $this->assets->css('bottom');
        self::assertSame('<style>' . PHP_EOL . 'body { color: black }' . PHP_EOL . '</style>' . PHP_EOL, $css);

        $this->assets->reset();
        $this->assets->addInlineJs('alert("test")', 15, 'bottom', ['id' => 'foo']);
        $js = $this->assets->js('bottom');
        self::assertSame('<script id="foo">' . PHP_EOL . 'alert("test")' . PHP_EOL . '</script>' . PHP_EOL, $js);
    }

    public function testAddingCSSAssetPropertiesWithArrayFromCollection(): void
    {
        $this->assets->registerCollection('test', ['/system/assets/whoops.css']);

        //Test priority too
        $this->assets->reset();
        $this->assets->addCss('test', ['priority' => 1]);
        $this->assets->addCss('test.css', ['priority' => 2]);
        $css = $this->assets->css();
        self::assertSame('<link href="/test.css" type="text/css" rel="stylesheet">' . PHP_EOL .
            '<link href="/system/assets/whoops.css" type="text/css" rel="stylesheet">' . PHP_EOL, $css);

        //Test multiple groups
        $this->assets->reset();
        $this->assets->addCss('test', ['priority' => 1, 'group' => 'footer']);
        $this->assets->addCss('test.css', ['priority' => 2]);
        $css = $this->assets->css();
        self::assertSame('<link href="/test.css" type="text/css" rel="stylesheet">' . PHP_EOL, $css);
        $css = $this->assets->css('footer');
        self::assertSame('<link href="/system/assets/whoops.css" type="text/css" rel="stylesheet">' . PHP_EOL, $css);

        //Test adding array of assets
        //Test priority too
        $this->assets->reset();
        $this->assets->addCss(['test', 'test.css'], ['loading' => 'async']);
        $css = $this->assets->css();
        self::assertSame('<link href="/system/assets/whoops.css" type="text/css" rel="stylesheet" async>' . PHP_EOL .
            '<link href="/test.css" type="text/css" rel="stylesheet" async>' . PHP_EOL, $css);
    }

    public function testAddingAssetPropertiesWithArrayFromCollectionAndParameters(): void
    {
        $this->assets->registerCollection('collection_multi_params', [
            'foo.js' => [ 'defer' => true ],
            'bar.js' => [ 'integrity' => 'sha512-abc123' ],
            'foobar.css' => [ 'defer' => null, 'loading' => null ]
        ]);

        // # Test adding properties with array
        $this->assets->addJs('collection_multi_params', ['loading' => 'async']);
        $js = $this->assets->js();

        // expected output
        $expected = [
            '<script src="/foo.js" async defer="1"></script>',
            '<script src="/bar.js" async integrity="sha512-abc123"></script>',
            '<script src="/foobar.css"></script>',
        ];

        self::assertCount(count($expected), array_filter(explode("\n", $js)));
        self::assertSame(implode("\n", $expected) . PHP_EOL, $js);

        // # Test priority as second argument + render JS should not have any css
        $this->assets->reset();
        $this->assets->add('low_priority.js', 1);
        $this->assets->add('collection_multi_params', 2);
        $js = $this->assets->js();

        // expected output
        $expected = [
            '<script src="/foo.js" defer="1"></script>',
            '<script src="/bar.js" integrity="sha512-abc123"></script>',
            '<script src="/low_priority.js"></script>',
        ];

        self::assertCount(3, array_filter(explode("\n", $js)));
        self::assertSame(implode("\n", $expected) . PHP_EOL, $js);

        // # Test rendering CSS, should not have any JS
        $this->assets->reset();
        $this->assets->add('collection_multi_params', [ 'class' => '__classname' ]);
        $css = $this->assets->css();

        // expected output
        $expected = [
            '<link href="/foobar.css" type="text/css" rel="stylesheet" class="__classname">',
        ];


        self::assertCount(1, array_filter(explode("\n", $css)));
        self::assertSame(implode("\n", $expected) . PHP_EOL, $css);
    }

    public function testPriorityOfAssets(): void
    {
        $this->assets->reset();
        $this->assets->add('test.css');
        $this->assets->add('test-after.css');

        $css = $this->assets->css();
        self::assertSame('<link href="/test.css" type="text/css" rel="stylesheet">' . PHP_EOL .
            '<link href="/test-after.css" type="text/css" rel="stylesheet">' . PHP_EOL, $css);

        //----------------
        $this->assets->reset();
        $this->assets->add('test-after.css', 1);
        $this->assets->add('test.css', 2);

        $css = $this->assets->css();
        self::assertSame('<link href="/test.css" type="text/css" rel="stylesheet">' . PHP_EOL .
            '<link href="/test-after.css" type="text/css" rel="stylesheet">' . PHP_EOL, $css);

        //----------------
        $this->assets->reset();
        $this->assets->add('test-after.css', 1);
        $this->assets->add('test.css', 2);
        $this->assets->add('test-before.css', 3);

        $css = $this->assets->css();
        self::assertSame('<link href="/test-before.css" type="text/css" rel="stylesheet">' . PHP_EOL .
            '<link href="/test.css" type="text/css" rel="stylesheet">' . PHP_EOL .
            '<link href="/test-after.css" type="text/css" rel="stylesheet">' . PHP_EOL, $css);
    }

    public function testPipeline(): void
    {
        $this->assets->reset();

        //File not existing. Pipeline searches for that file without reaching it. Output is empty.
        $this->assets->add('test.css', null, true);
        $this->assets->setCssPipeline(true);
        $css = $this->assets->css();
        self::assertRegExp('#<link href=\"\/assets\/(.*).css\" type=\"text\/css\" rel=\"stylesheet\">#', $css);

        //Add a core Grav CSS file, which is found. Pipeline will now return a file
        $this->assets->add('/system/assets/debugger/phpdebugbar', null, true);
        $css = $this->assets->css();
        self::assertRegExp('#<link href=\"\/assets\/(.*).css\" type=\"text\/css\" rel=\"stylesheet\">#', $css);
    }

    public function testPipelineWithTimestamp(): void
    {
        $this->assets->reset();
        $this->assets->setTimestamp('foo');
        $this->assets->setCssPipeline(true);

        //Add a core Grav CSS file, which is found. Pipeline will now return a file
        $this->assets->add('/system/assets/debugger.css', null, true);
        $css = $this->assets->css();
        self::assertRegExp('#<link href=\"\/assets\/(.*).css\?foo\" type=\"text\/css\" rel=\"stylesheet\">#', $css);
    }

    public function testInline(): void
    {
        $this->assets->reset();

        //File not existing. Pipeline searches for that file without reaching it. Output is empty.
        $this->assets->add('test.css', ['loading' => 'inline']);
        $css = $this->assets->css();
        self::assertSame("<style>\n\n</style>\n", $css);

        $this->assets->reset();
        //Add a core Grav CSS file, which is found. Pipeline will now return its content.
        $this->assets->addCss('https://fonts.googleapis.com/css?family=Roboto', ['loading' => 'inline']);
        $this->assets->addCss('/system/assets/debugger/phpdebugbar.css', ['loading' => 'inline']);
        $css = $this->assets->css();
        self::assertStringContainsString('font-family: \'Roboto\';', $css);
        self::assertStringContainsString('div.phpdebugbar-header', $css);
    }

    public function testInlinePipeline(): void
    {
        $this->assets->reset();
        $this->assets->setCssPipeline(true);

        //File not existing. Pipeline searches for that file without reaching it. Output is empty.
        $this->assets->add('test.css');
        $css = $this->assets->css('head', ['loading' => 'inline']);
        self::assertSame("<style>\n\n</style>\n", $css);

        //Add a core Grav CSS file, which is found. Pipeline will now return its content.
        $this->assets->addCss('https://fonts.googleapis.com/css?family=Roboto', null, true);
        $this->assets->add('/system/assets/debugger/phpdebugbar.css', null, true);
        $css = $this->assets->css('head', ['loading' => 'inline']);
        self::assertStringContainsString('font-family:\'Roboto\';', $css);
        self::assertStringContainsString('div.phpdebugbar', $css);
    }

    public function testAddAsyncJs(): void
    {
        $this->assets->reset();
        $this->assets->addAsyncJs('jquery');
        $js = $this->assets->js();
        self::assertSame('<script src="/system/assets/jquery/jquery-3.x.min.js" async></script>' . PHP_EOL, $js);
    }

    public function testAddDeferJs(): void
    {
        $this->assets->reset();
        $this->assets->addDeferJs('jquery');
        $js = $this->assets->js();
        self::assertSame('<script src="/system/assets/jquery/jquery-3.x.min.js" defer></script>' . PHP_EOL, $js);
    }

    public function testTimestamps(): void
    {
        // local CSS nothing extra
        $this->assets->reset();
        $this->assets->setTimestamp('foo');
        $this->assets->addCSS('test.css');
        $css = $this->assets->css();
        self::assertSame('<link href="/test.css?foo" type="text/css" rel="stylesheet">' . PHP_EOL, $css);

        // local CSS already with param
        $this->assets->reset();
        $this->assets->setTimestamp('foo');
        $this->assets->addCSS('test.css?bar');
        $css = $this->assets->css();
        self::assertSame('<link href="/test.css?bar&foo" type="text/css" rel="stylesheet">' . PHP_EOL, $css);

        // external CSS already
        $this->assets->reset();
        $this->assets->setTimestamp('foo');
        $this->assets->addCSS('http://somesite.com/test.css');
        $css = $this->assets->css();
        self::assertSame('<link href="http://somesite.com/test.css?foo" type="text/css" rel="stylesheet">' . PHP_EOL, $css);

        // external CSS already with param
        $this->assets->reset();
        $this->assets->setTimestamp('foo');
        $this->assets->addCSS('http://somesite.com/test.css?bar');
        $css = $this->assets->css();
        self::assertSame('<link href="http://somesite.com/test.css?bar&foo" type="text/css" rel="stylesheet">' . PHP_EOL, $css);

        // local JS nothing extra
        $this->assets->reset();
        $this->assets->setTimestamp('foo');
        $this->assets->addJs('test.js');
        $css = $this->assets->js();
        self::assertSame('<script src="/test.js?foo"></script>' . PHP_EOL, $css);

        // local JS already with param
        $this->assets->reset();
        $this->assets->setTimestamp('foo');
        $this->assets->addJs('test.js?bar');
        $css = $this->assets->js();
        self::assertSame('<script src="/test.js?bar&foo"></script>' . PHP_EOL, $css);

        // external JS already
        $this->assets->reset();
        $this->assets->setTimestamp('foo');
        $this->assets->addJs('http://somesite.com/test.js');
        $css = $this->assets->js();
        self::assertSame('<script src="http://somesite.com/test.js?foo"></script>' . PHP_EOL, $css);

        // external JS already with param
        $this->assets->reset();
        $this->assets->setTimestamp('foo');
        $this->assets->addJs('http://somesite.com/test.js?bar');
        $css = $this->assets->js();
        self::assertSame('<script src="http://somesite.com/test.js?bar&foo"></script>' . PHP_EOL, $css);
    }

    public function testAddInlineCss(): void
    {
        $this->assets->reset();
        $this->assets->addInlineCss('body { color: black }');
        $css = $this->assets->css();
        self::assertSame('<style>' . PHP_EOL . 'body { color: black }' . PHP_EOL . '</style>' . PHP_EOL, $css);
    }

    public function testAddInlineJs(): void
    {
        $this->assets->reset();
        $this->assets->addInlineJs('alert("test")');
        $js = $this->assets->js();
        self::assertSame('<script>' . PHP_EOL . 'alert("test")' . PHP_EOL . '</script>' . PHP_EOL, $js);
    }

    public function testGetCollections(): void
    {
        self::assertIsArray($this->assets->getCollections());
        self::assertContains('jquery', array_keys($this->assets->getCollections()));
        self::assertContains('system://assets/jquery/jquery-3.x.min.js', $this->assets->getCollections());
    }

    public function testExists(): void
    {
        self::assertTrue($this->assets->exists('jquery'));
        self::assertFalse($this->assets->exists('another-unexisting-library'));
    }

    public function testRegisterCollection(): void
    {
        $this->assets->registerCollection('debugger', ['/system/assets/debugger.css']);
        self::assertTrue($this->assets->exists('debugger'));
        self::assertContains('debugger', array_keys($this->assets->getCollections()));
    }

    public function testRegisterCollectionWithParameters(): void
    {
        $this->assets->registerCollection('collection_multi_params', [
            'foo.js' => [ 'defer' => true ],
            'bar.js' => [ 'integrity' => 'sha512-abc123' ],
            'foobar.css' => [ 'defer' => null ],
        ]);

        self::assertTrue($this->assets->exists('collection_multi_params'));

        $collection = $this->assets->getCollections()['collection_multi_params'];
        self::assertArrayHasKey('foo.js', $collection);
        self::assertArrayHasKey('bar.js', $collection);
        self::assertArrayHasKey('foobar.css', $collection);
        self::assertArrayHasKey('defer', $collection['foo.js']);
        self::assertArrayHasKey('defer', $collection['foobar.css']);

        self::assertNull($collection['foobar.css']['defer']);
        self::assertTrue($collection['foo.js']['defer']);
    }

    public function testReset(): void
    {
        $this->assets->addInlineJs('alert("test")');
        $this->assets->reset();
        self::assertCount(0, (array) $this->assets->getJs());

        $this->assets->addAsyncJs('jquery');
        $this->assets->reset();
        self::assertCount(0, (array) $this->assets->getJs());

        $this->assets->addInlineCss('body { color: black }');
        $this->assets->reset();
        self::assertCount(0, (array) $this->assets->getCss());

        $this->assets->add('/system/assets/debugger.css', null, true);
        $this->assets->reset();
        self::assertCount(0, (array) $this->assets->getCss());
    }

    public function testResetJs(): void
    {
        $this->assets->addInlineJs('alert("test")');
        $this->assets->resetJs();
        self::assertCount(0, (array) $this->assets->getJs());

        $this->assets->addAsyncJs('jquery');
        $this->assets->resetJs();
        self::assertCount(0, (array) $this->assets->getJs());
    }

    public function testResetCss(): void
    {
        $this->assets->addInlineCss('body { color: black }');
        $this->assets->resetCss();
        self::assertCount(0, (array) $this->assets->getCss());

        $this->assets->add('/system/assets/debugger.css', null, true);
        $this->assets->resetCss();
        self::assertCount(0, (array) $this->assets->getCss());
    }

    public function testAddDirCss(): void
    {
        $this->assets->addDirCss('/system');

        self::assertIsArray($this->assets->getCss());
        self::assertGreaterThan(0, (array) $this->assets->getCss());
        self::assertIsArray($this->assets->getJs());
        self::assertCount(0, (array) $this->assets->getJs());

        $this->assets->reset();
        $this->assets->addDirCss('/system/assets');

        self::assertIsArray($this->assets->getCss());
        self::assertGreaterThan(0, (array) $this->assets->getCss());
        self::assertIsArray($this->assets->getJs());
        self::assertCount(0, (array) $this->assets->getJs());

        $this->assets->reset();
        $this->assets->addDirJs('/system');

        self::assertIsArray($this->assets->getCss());
        self::assertCount(0, (array) $this->assets->getCss());
        self::assertIsArray($this->assets->getJs());
        self::assertGreaterThan(0, (array) $this->assets->getJs());

        $this->assets->reset();
        $this->assets->addDirJs('/system/assets');

        self::assertIsArray($this->assets->getCss());
        self::assertCount(0, (array) $this->assets->getCss());
        self::assertIsArray($this->assets->getJs());
        self::assertGreaterThan(0, (array) $this->assets->getJs());

        $this->assets->reset();
        $this->assets->addDir('/system/assets');

        self::assertIsArray($this->assets->getCss());
        self::assertGreaterThan(0, (array) $this->assets->getCss());
        self::assertIsArray($this->assets->getJs());
        self::assertGreaterThan(0, (array) $this->assets->getJs());

        //Use streams
        $this->assets->reset();
        $this->assets->addDir('system://assets');

        self::assertIsArray($this->assets->getCss());
        self::assertGreaterThan(0, (array) $this->assets->getCss());
        self::assertIsArray($this->assets->getJs());
        self::assertGreaterThan(0, (array) $this->assets->getJs());
    }
}
