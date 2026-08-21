<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Assets;

/**
 * Class AssetsTest
 */
class AssetsTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav $grav */
    protected $grav;

    /** @var Assets $assets */
    protected $assets;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->assets = $this->grav['assets'];

        // The pipeline writes minified output to a deterministic UID file in
        // GRAV_ROOT/assets and re-uses it on subsequent runs. If a previous
        // run hit a transient remote-fetch failure (e.g. flaky network when
        // pulling Google Fonts CSS), that partial result is baked in and the
        // testInlinePipeline assertion fails until the cache is cleared by
        // hand. Sweep the pipeline cache for each test to keep runs hermetic.
        $assetsDir = GRAV_ROOT . '/assets';
        if (is_dir($assetsDir)) {
            foreach (glob($assetsDir . '/*.{css,js}', GLOB_BRACE) ?: [] as $file) {
                @unlink($file);
            }
        }
    }

    protected function tearDown(): void
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
        self::assertMatchesRegularExpression('#<link href=\"\/assets\/(.*).css\" type=\"text\/css\" rel=\"stylesheet\">#', $css);

        //Add a core Grav CSS file, which is found. Pipeline will now return a file
        $this->assets->add('/system/assets/debugger/phpdebugbar', null, true);
        $css = $this->assets->css();
        self::assertMatchesRegularExpression('#<link href=\"\/assets\/(.*).css\" type=\"text\/css\" rel=\"stylesheet\">#', $css);
    }

    public function testPipelineWithTimestamp(): void
    {
        $this->assets->reset();
        $this->assets->setTimestamp('foo');
        $this->assets->setCssPipeline(true);

        //Add a core Grav CSS file, which is found. Pipeline will now return a file
        $this->assets->add('/system/assets/debugger.css', null, true);
        $css = $this->assets->css();
        self::assertMatchesRegularExpression('#<link href=\"\/assets\/(.*).css\?foo\" type=\"text\/css\" rel=\"stylesheet\">#', $css);
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
        self::assertStringContainsString('div.phpdebugbar', $css);
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

    public function testPerFileTimestamps(): void
    {
        $fileA = GRAV_ROOT . '/tests/unit/data/assets/timestamp-a.css';
        $fileB = GRAV_ROOT . '/tests/unit/data/assets/timestamp-b.css';
        $fileJs = GRAV_ROOT . '/tests/unit/data/assets/timestamp-a.js';

        $origMtimeA = filemtime($fileA);
        $origMtimeB = filemtime($fileB);
        $origMtimeJs = filemtime($fileJs);

        try {
            // The token the enable_asset_timestamp flag produces on its own. Only
            // this value is eligible to be replaced by a per-file mtime, so the
            // assertions below use it rather than pinning one via setTimestamp().
            $globalKey = $this->grav['cache']->getKey();

            // Distinct, known mtimes for each fixture (not "now") so the assertions
            // don't depend on filesystem timing/precision.
            $mtimeA = time() - 3600;
            $mtimeB = time() - 60;
            touch($fileA, $mtimeA);
            touch($fileB, $mtimeB);
            clearstatcache(true, $fileA);
            clearstatcache(true, $fileB);

            // Exercise the real enable_asset_timestamp config flag (the actual
            // trigger for #4049), not just a manually set token.
            $this->assets->reset();
            $this->grav['config']->set('system.assets.enable_asset_timestamp', true);
            $this->assets->config(['enable_asset_timestamp' => true]);

            // Local, non-pipelined assets: each gets its own filemtime-derived
            // token, not the shared global cache key.
            $this->assets->addCss('/tests/unit/data/assets/timestamp-a.css');
            $css = $this->assets->css();
            self::assertSame(
                '<link href="/tests/unit/data/assets/timestamp-a.css?' . dechex($mtimeA) . '" type="text/css" rel="stylesheet">' . PHP_EOL,
                $css
            );

            $this->assets->reset();
            $this->grav['config']->set('system.assets.enable_asset_timestamp', true);
            $this->assets->config(['enable_asset_timestamp' => true]);
            $this->assets->addCss('/tests/unit/data/assets/timestamp-b.css');
            $css = $this->assets->css();
            self::assertSame(
                '<link href="/tests/unit/data/assets/timestamp-b.css?' . dechex($mtimeB) . '" type="text/css" rel="stylesheet">' . PHP_EOL,
                $css
            );

            // Touching one file changes only its own token.
            $mtimeANew = $mtimeA + 1800;
            touch($fileA, $mtimeANew);
            clearstatcache(true, $fileA);

            $this->assets->reset();
            $this->grav['config']->set('system.assets.enable_asset_timestamp', true);
            $this->assets->config(['enable_asset_timestamp' => true]);
            $this->assets->addCss('/tests/unit/data/assets/timestamp-a.css');
            $css = $this->assets->css();
            self::assertSame(
                '<link href="/tests/unit/data/assets/timestamp-a.css?' . dechex($mtimeANew) . '" type="text/css" rel="stylesheet">' . PHP_EOL,
                $css
            );

            // Stream-based local asset (e.g. theme://): goes through
            // $locator->findResource() rather than GRAV_WEBROOT concatenation,
            // and must be rewritten to its own mtime too.
            $this->assets->reset();
            $this->grav['config']->set('system.assets.enable_asset_timestamp', true);
            $this->assets->config(['enable_asset_timestamp' => true]);
            $this->assets->addCss('tests://unit/data/assets/timestamp-a.css');
            $css = $this->assets->css();
            self::assertSame(
                '<link href="/tests/unit/data/assets/timestamp-a.css?' . dechex($mtimeANew) . '" type="text/css" rel="stylesheet">' . PHP_EOL,
                $css
            );

            // Local JS asset: addJs() goes through a different concrete asset
            // class than addCss(), but must be rewritten the same way.
            $mtimeJs = time() - 120;
            touch($fileJs, $mtimeJs);
            clearstatcache(true, $fileJs);

            $this->assets->reset();
            $this->grav['config']->set('system.assets.enable_asset_timestamp', true);
            $this->assets->config(['enable_asset_timestamp' => true]);
            $this->assets->addJs('/tests/unit/data/assets/timestamp-a.js');
            $js = $this->assets->js();
            self::assertSame(
                '<script src="/tests/unit/data/assets/timestamp-a.js?' . dechex($mtimeJs) . '"></script>' . PHP_EOL,
                $js
            );

            // Remote assets have no local file to stat, so they keep the global
            // cache key untouched.
            $this->assets->reset();
            $this->grav['config']->set('system.assets.enable_asset_timestamp', true);
            $this->assets->config(['enable_asset_timestamp' => true]);
            $this->assets->addCss('http://somesite.com/test.css');
            $css = $this->assets->css();
            self::assertSame('<link href="http://somesite.com/test.css?' . $globalKey . '" type="text/css" rel="stylesheet">' . PHP_EOL, $css);

            // Pipelined assets: the *rendered* query string keeps using the
            // global cache key (Pipeline::$timestamp, set from Assets::render()),
            // regardless of the per-asset mtime rewrite above.
            $this->assets->reset();
            $this->grav['config']->set('system.assets.enable_asset_timestamp', true);
            $this->assets->config(['enable_asset_timestamp' => true]);
            $this->assets->setCssPipeline(true);
            $this->assets->addCss('/tests/unit/data/assets/timestamp-a.css');
            $css = $this->assets->css();
            self::assertMatchesRegularExpression('#<link href="/assets/(.*)\.css\?' . preg_quote($globalKey, '#') . '" type="text/css" rel="stylesheet">#', $css);
        } finally {
            touch($fileA, $origMtimeA);
            touch($fileB, $origMtimeB);
            touch($fileJs, $origMtimeJs);
            clearstatcache(true, $fileA);
            clearstatcache(true, $fileB);
            clearstatcache(true, $fileJs);
        }
    }

    public function testManualTimestampPreservedWithoutConfigFlag(): void
    {
        // enable_asset_timestamp defaults to off. A caller-supplied token set
        // via the public Assets::setTimestamp() API must not be silently
        // overridden by the per-file mtime rewrite, since that rewrite is only
        // meant to fire for the enable_asset_timestamp config path.
        $this->assets->reset();
        $this->assets->setTimestamp('v1.2.3');
        $this->assets->addCss('/tests/unit/data/assets/timestamp-a.css');
        $css = $this->assets->css();
        self::assertSame(
            '<link href="/tests/unit/data/assets/timestamp-a.css?v1.2.3" type="text/css" rel="stylesheet">' . PHP_EOL,
            $css
        );
    }

    public function testManualTimestampWinsOverConfigFlag(): void
    {
        // With enable_asset_timestamp ON, a caller-supplied token still wins.
        // setTimestamp() is how a CI/git deploy pins one release token across a
        // fleet of servers; the per-file rewrite only ever replaces the token the
        // flag generated for itself, so turning the flag on cannot silently strip
        // an explicit choice with no way to opt back out.
        $this->assets->reset();
        $this->grav['config']->set('system.assets.enable_asset_timestamp', true);
        $this->assets->config(['enable_asset_timestamp' => true]);
        $this->assets->setTimestamp('v1.2.3');
        $this->assets->addCss('/tests/unit/data/assets/timestamp-a.css');
        $css = $this->assets->css();
        self::assertSame(
            '<link href="/tests/unit/data/assets/timestamp-a.css?v1.2.3" type="text/css" rel="stylesheet">' . PHP_EOL,
            $css
        );
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
