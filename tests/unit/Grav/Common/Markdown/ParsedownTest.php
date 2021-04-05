<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Markdown\Excerpts;
use Grav\Common\Uri;
use Grav\Common\Config\Config;
use Grav\Common\Page\Pages;
use Grav\Common\Markdown\Parsedown;
use Grav\Common\Language\Language;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * Class ParsedownTest
 */
class ParsedownTest extends \Codeception\TestCase\Test
{
    /** @var Parsedown $parsedown */
    protected $parsedown;

    /** @var Grav $grav */
    protected $grav;

    /** @var Pages $pages */
    protected $pages;

    /** @var Config $config */
    protected $config;

    /** @var  Uri $uri */
    protected $uri;

    /** @var  Language $language */
    protected $language;

    protected $old_home;

    protected function _before(): void
    {
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->pages = $this->grav['pages'];
        $this->config = $this->grav['config'];
        $this->uri = $this->grav['uri'];
        $this->language = $this->grav['language'];
        $this->old_home = $this->config->get('system.home.alias');
        $this->config->set('system.home.alias', '/item1');
        $this->config->set('system.absolute_urls', false);
        $this->config->set('system.languages.supported', []);

        unset($this->grav['language']);
        $this->grav['language'] = new Language($this->grav);

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $locator->addPath('page', '', 'tests/fake/nested-site/user/pages', false);
        $this->pages->init();

        $defaults = [
            'markdown' => [
                'extra'            => false,
                'auto_line_breaks' => false,
                'auto_url_links'   => false,
                'escape_markup'    => false,
                'special_chars'    => ['>' => 'gt', '<' => 'lt'],
            ],
            'images' => $this->config->get('system.images', [])
        ];
        $page = $this->pages->find('/item2/item2-2');

        $excerpts = new Excerpts($page, $defaults);
        $this->parsedown = new Parsedown($excerpts);
    }

    protected function _after(): void
    {
        $this->config->set('system.home.alias', $this->old_home);
    }

    public function testImages(): void
    {
        $this->config->set('system.languages.supported', ['fr','en']);
        unset($this->grav['language']);
        $this->grav['language'] = new Language($this->grav);
        $this->uri->initializeWithURL('http://testing.dev/fr/item2/item2-2')->init();

        self::assertSame(
            '<p><img alt="" src="/tests/fake/nested-site/user/pages/02.item2/02.item2-2/sample-image.jpg" /></p>',
            $this->parsedown->text('![](sample-image.jpg)')
        );
        self::assertRegexp(
            '|<p><img alt="" src="\/images\/.*-cache-image.jpe?g\?foo=1" \/><\/p>|',
            $this->parsedown->text('![](cache-image.jpg?cropResize=200,200&foo)')
        );

        $this->uri->initializeWithURL('http://testing.dev/item2/item2-2')->init();

        self::assertSame(
            '<p><img alt="" src="/tests/fake/nested-site/user/pages/02.item2/02.item2-2/sample-image.jpg" /></p>',
            $this->parsedown->text('![](sample-image.jpg)')
        );
        self::assertRegexp(
            '|<p><img alt="" src="\/images\/.*-cache-image.jpe?g\?foo=1" \/><\/p>|',
            $this->parsedown->text('![](cache-image.jpg?cropResize=200,200&foo)')
        );
        self::assertRegexp(
            '|<p><img alt="" src="\/images\/.*-home-cache-image.jpe?g" \/><\/p>|',
            $this->parsedown->text('![](/home-cache-image.jpg?cache)')
        );
        self::assertSame(
            '<p><img src="/item2/item2-2/missing-image.jpg" alt="" /></p>',
            $this->parsedown->text('![](missing-image.jpg)')
        );
        self::assertSame(
            '<p><img src="/home-missing-image.jpg" alt="" /></p>',
            $this->parsedown->text('![](/home-missing-image.jpg)')
        );
        self::assertSame(
            '<p><img src="/home-missing-image.jpg" alt="" /></p>',
            $this->parsedown->text('![](/home-missing-image.jpg)')
        );
        self::assertSame(
            '<p><img src="https://getgrav-grav.netdna-ssl.com/user/pages/media/grav-logo.svg" alt="" /></p>',
            $this->parsedown->text('![](https://getgrav-grav.netdna-ssl.com/user/pages/media/grav-logo.svg)')
        );
    }

    public function testImagesSubDir(): void
    {
        $this->config->set('system.images.cache_all', false);
        $this->uri->initializeWithUrlAndRootPath('http://testing.dev/subdir/item2/item2-2', '/subdir')->init();

        self::assertRegexp(
            '|<p><img alt="" src="\/subdir\/images\/.*-home-cache-image.jpe?g" \/><\/p>|',
            $this->parsedown->text('![](/home-cache-image.jpg?cache)')
        );
        self::assertSame(
            '<p><img alt="" src="/subdir/tests/fake/nested-site/user/pages/02.item2/02.item2-2/sample-image.jpg" /></p>',
            $this->parsedown->text('![](sample-image.jpg)')
        );
        self::assertRegexp(
            '|<p><img alt="" src="\/subdir\/images\/.*-cache-image.jpe?g" \/><\/p>|',
            $this->parsedown->text('![](cache-image.jpg?cache)')
        );
        self::assertSame(
            '<p><img src="/subdir/item2/item2-2/missing-image.jpg" alt="" /></p>',
            $this->parsedown->text('![](missing-image.jpg)')
        );
        self::assertSame(
            '<p><img src="/subdir/home-missing-image.jpg" alt="" /></p>',
            $this->parsedown->text('![](/home-missing-image.jpg)')
        );
    }

    public function testImagesAbsoluteUrls(): void
    {
        $this->config->set('system.absolute_urls', true);
        $this->uri->initializeWithURL('http://testing.dev/item2/item2-2')->init();

        self::assertSame(
            '<p><img alt="" src="http://testing.dev/tests/fake/nested-site/user/pages/02.item2/02.item2-2/sample-image.jpg" /></p>',
            $this->parsedown->text('![](sample-image.jpg)')
        );
        self::assertRegexp(
            '|<p><img alt="" src="http:\/\/testing.dev\/images\/.*-cache-image.jpe?g" \/><\/p>|',
            $this->parsedown->text('![](cache-image.jpg?cache)')
        );
        self::assertRegexp(
            '|<p><img alt="" src="http:\/\/testing.dev\/images\/.*-home-cache-image.jpe?g" \/><\/p>|',
            $this->parsedown->text('![](/home-cache-image.jpg?cache)')
        );
        self::assertSame(
            '<p><img src="http://testing.dev/item2/item2-2/missing-image.jpg" alt="" /></p>',
            $this->parsedown->text('![](missing-image.jpg)')
        );
        self::assertSame(
            '<p><img src="http://testing.dev/home-missing-image.jpg" alt="" /></p>',
            $this->parsedown->text('![](/home-missing-image.jpg)')
        );
    }

    public function testImagesSubDirAbsoluteUrls(): void
    {
        $this->config->set('system.absolute_urls', true);
        $this->uri->initializeWithUrlAndRootPath('http://testing.dev/subdir/item2/item2-2', '/subdir')->init();

        self::assertSame(
            '<p><img alt="" src="http://testing.dev/subdir/tests/fake/nested-site/user/pages/02.item2/02.item2-2/sample-image.jpg" /></p>',
            $this->parsedown->text('![](sample-image.jpg)')
        );
        self::assertRegexp(
            '|<p><img alt="" src="http:\/\/testing.dev\/subdir\/images\/.*-cache-image.jpe?g" \/><\/p>|',
            $this->parsedown->text('![](cache-image.jpg?cache)')
        );
        self::assertRegexp(
            '|<p><img alt="" src="http:\/\/testing.dev\/subdir\/images\/.*-home-cache-image.jpe?g" \/><\/p>|',
            $this->parsedown->text('![](/home-cache-image.jpg?cropResize=200,200)')
        );
        self::assertSame(
            '<p><img src="http://testing.dev/subdir/item2/item2-2/missing-image.jpg" alt="" /></p>',
            $this->parsedown->text('![](missing-image.jpg)')
        );
        self::assertSame(
            '<p><img src="http://testing.dev/subdir/home-missing-image.jpg" alt="" /></p>',
            $this->parsedown->text('![](/home-missing-image.jpg)')
        );
    }

    public function testImagesAttributes(): void
    {
        $this->uri->initializeWithURL('http://testing.dev/item2/item2-2')->init();

        self::assertSame(
            '<p><img title="My Title" alt="" src="/tests/fake/nested-site/user/pages/02.item2/02.item2-2/sample-image.jpg" /></p>',
            $this->parsedown->text('![](sample-image.jpg "My Title")')
        );
        self::assertSame(
            '<p><img alt="" class="foo" src="/tests/fake/nested-site/user/pages/02.item2/02.item2-2/sample-image.jpg" /></p>',
            $this->parsedown->text('![](sample-image.jpg?classes=foo)')
        );
        self::assertSame(
            '<p><img alt="" class="foo bar" src="/tests/fake/nested-site/user/pages/02.item2/02.item2-2/sample-image.jpg" /></p>',
            $this->parsedown->text('![](sample-image.jpg?classes=foo,bar)')
        );
        self::assertSame(
            '<p><img alt="" id="foo" src="/tests/fake/nested-site/user/pages/02.item2/02.item2-2/sample-image.jpg" /></p>',
            $this->parsedown->text('![](sample-image.jpg?id=foo)')
        );
        self::assertSame(
            '<p><img alt="Alt Text" id="foo" src="/tests/fake/nested-site/user/pages/02.item2/02.item2-2/sample-image.jpg" /></p>',
            $this->parsedown->text('![Alt Text](sample-image.jpg?id=foo)')
        );
        self::assertSame(
            '<p><img alt="Alt Text" class="bar" id="foo" src="/tests/fake/nested-site/user/pages/02.item2/02.item2-2/sample-image.jpg" /></p>',
            $this->parsedown->text('![Alt Text](sample-image.jpg?class=bar&id=foo)')
        );
        self::assertSame(
            '<p><img title="My Title" alt="Alt Text" class="bar" id="foo" src="/tests/fake/nested-site/user/pages/02.item2/02.item2-2/sample-image.jpg" /></p>',
            $this->parsedown->text('![Alt Text](sample-image.jpg?class=bar&id=foo "My Title")')
        );
    }

    public function testImagesDefaults(): void
    {
        /**
         * Testing default 'loading'
        */

        $this->setImagesDefaults(['loading' => 'auto']);


        // loading should NOT be added to image by default
        self::assertSame(
            '<p><img alt="" src="/tests/fake/nested-site/user/pages/02.item2/02.item2-2/sample-image.jpg" /></p>',
            $this->parsedown->text('![](sample-image.jpg)')
        );

        // loading="lazy" should be added when default is overridden by ?loading=lazy
        self::assertSame(
            '<p><img loading="lazy" alt="" src="/tests/fake/nested-site/user/pages/02.item2/02.item2-2/sample-image.jpg" /></p>',
            $this->parsedown->text('![](sample-image.jpg?loading=lazy)')
        );

        $this->setImagesDefaults(['loading' => 'lazy']);

        // loading="lazy" should be added by default
        self::assertSame(
            '<p><img loading="lazy" alt="" src="/tests/fake/nested-site/user/pages/02.item2/02.item2-2/sample-image.jpg" /></p>',
            $this->parsedown->text('![](sample-image.jpg)')
        );

        // loading should not be added when default is overridden by ?loading=auto
        self::assertSame(
            '<p><img alt="" src="/tests/fake/nested-site/user/pages/02.item2/02.item2-2/sample-image.jpg" /></p>',
            $this->parsedown->text('![](sample-image.jpg?loading=auto)')
        );

        // loading="eager" should be added when default is overridden by ?loading=eager
        self::assertSame(
            '<p><img loading="eager" alt="" src="/tests/fake/nested-site/user/pages/02.item2/02.item2-2/sample-image.jpg" /></p>',
            $this->parsedown->text('![](sample-image.jpg?loading=eager)')
        );

    }

    public function testCLSAutoSizes(): void
    {
        $this->config->set('system.images.cls.auto_sizes', false);
        $this->uri->initializeWithURL('http://testing.dev/item2/item2-2')->init();

        self::assertSame(
            '<p><img alt="" src="/tests/fake/nested-site/user/pages/02.item2/02.item2-2/sample-image.jpg" /></p>',
            $this->parsedown->text('![](sample-image.jpg)')
        );

        self::assertSame(
            '<p><img height="1" width="1" alt="" src="/tests/fake/nested-site/user/pages/02.item2/02.item2-2/sample-image.jpg" /></p>',
            $this->parsedown->text('![](sample-image.jpg?height=1&width=1)')
        );

        self::assertSame(
            '<p><img alt="" src="/tests/fake/nested-site/user/pages/02.item2/02.item2-2/sample-image.jpg" width="1024" height="768" /></p>',
            $this->parsedown->text('![](sample-image.jpg?autoSizes=true)')
        );

        $this->config->set('system.images.cls.auto_sizes', true);

        self::assertSame(
            '<p><img alt="" src="/tests/fake/nested-site/user/pages/02.item2/02.item2-2/sample-image.jpg" width="1024" height="768" /></p>',
            $this->parsedown->text('![](sample-image.jpg?reset)')
        );

        self::assertSame(
            '<p><img height="1" width="1" alt="" src="/tests/fake/nested-site/user/pages/02.item2/02.item2-2/sample-image.jpg" /></p>',
            $this->parsedown->text('![](sample-image.jpg?height=1&width=1)')
        );

        self::assertSame(
            '<p><img alt="" src="/tests/fake/nested-site/user/pages/02.item2/02.item2-2/sample-image.jpg" /></p>',
            $this->parsedown->text('![](sample-image.jpg?autoSizes=false)')
        );

        self::assertRegExp(
            '/width="400" height="200"/',
            $this->parsedown->text('![](sample-image.jpg?reset&resize=400,200)')
        );

        $this->config->set('system.images.cls.retina_scale', 2);


        self::assertRegExp(
            '/width="400" height="200"/',
            $this->parsedown->text('![](sample-image.jpg?reset&resize=800,400)')
        );

        $this->config->set('system.images.cls.retina_scale', 4);

        self::assertRegExp(
            '/width="200" height="100"/',
            $this->parsedown->text('![](sample-image.jpg?reset&resize=800,400)')
        );

        self::assertRegExp(
            '/width="266" height="133"/',
            $this->parsedown->text('![](sample-image.jpg?reset&resize=800,400&retinaScale=3)')
        );

        $this->config->set('system.images.cls.aspect_ratio', true);

        self::assertRegExp(
            '/style="--aspect-ratio: 800\/400;"/',
            $this->parsedown->text('![](sample-image.jpg?reset&resize=800,400)')
        );

        $this->config->set('system.images.cls.aspect_ratio', false);

        self::assertRegExp(
            '/style="--aspect-ratio: 800\/400;"/',
            $this->parsedown->text('![](sample-image.jpg?reset&resize=800,400&aspectRatio=true)')
        );

    }

    public function testRootImages(): void
    {
        $this->uri->initializeWithURL('http://testing.dev/')->init();

        $defaults = [
            'markdown' => [
                'extra'            => false,
                'auto_line_breaks' => false,
                'auto_url_links'   => false,
                'escape_markup'    => false,
                'special_chars'    => ['>' => 'gt', '<' => 'lt'],
            ],
            'images' => $this->config->get('system.images', [])
        ];
        $page = $this->pages->find('/');
        $excerpts = new Excerpts($page, $defaults);
        $this->parsedown = new Parsedown($excerpts);

        self::assertSame(
            '<p><img alt="" src="/tests/fake/nested-site/user/pages/01.item1/home-sample-image.jpg" /></p>',
            $this->parsedown->text('![](home-sample-image.jpg)')
        );
        self::assertRegexp(
            '|<p><img alt="" src="\/images\/.*-home-cache-image.jpe?g" \/><\/p>|',
            $this->parsedown->text('![](home-cache-image.jpg?cache)')
        );
        self::assertRegexp(
            '|<p><img alt="" src="\/images\/.*-home-cache-image.jpe?g\?foo=1" \/><\/p>|',
            $this->parsedown->text('![](home-cache-image.jpg?cropResize=200,200&foo)')
        );
        self::assertSame(
            '<p><img src="/home-missing-image.jpg" alt="" /></p>',
            $this->parsedown->text('![](/home-missing-image.jpg)')
        );

        $this->config->set('system.languages.supported', ['fr','en']);
        unset($this->grav['language']);
        $this->grav['language'] = new Language($this->grav);
        $this->uri->initializeWithURL('http://testing.dev/fr/item2/item2-2')->init();

        self::assertSame(
            '<p><img alt="" src="/tests/fake/nested-site/user/pages/01.item1/home-sample-image.jpg" /></p>',
            $this->parsedown->text('![](home-sample-image.jpg)')
        );
    }

    public function testRootImagesSubDirAbsoluteUrls(): void
    {
        $this->config->set('system.absolute_urls', true);
        $this->uri->initializeWithUrlAndRootPath('http://testing.dev/subdir/item2/item2-2', '/subdir')->init();

        self::assertSame(
            '<p><img alt="" src="http://testing.dev/subdir/tests/fake/nested-site/user/pages/02.item2/02.item2-2/sample-image.jpg" /></p>',
            $this->parsedown->text('![](sample-image.jpg)')
        );
        self::assertRegexp(
            '|<p><img alt="" src="http:\/\/testing.dev\/subdir\/images\/.*-cache-image.jpe?g" \/><\/p>|',
            $this->parsedown->text('![](cache-image.jpg?cache)')
        );
        self::assertRegexp(
            '|<p><img alt="" src="http:\/\/testing.dev\/subdir\/images\/.*-home-cache-image.jpe?g" \/><\/p>|',
            $this->parsedown->text('![](/home-cache-image.jpg?cropResize=200,200)')
        );
        self::assertSame(
            '<p><img src="http://testing.dev/subdir/item2/item2-2/missing-image.jpg" alt="" /></p>',
            $this->parsedown->text('![](missing-image.jpg)')
        );
        self::assertSame(
            '<p><img src="http://testing.dev/subdir/home-missing-image.jpg" alt="" /></p>',
            $this->parsedown->text('![](/home-missing-image.jpg)')
        );
    }

    public function testRootAbsoluteLinks(): void
    {
        $this->uri->initializeWithURL('http://testing.dev/')->init();

        $defaults = [
            'markdown' => [
                'extra'            => false,
                'auto_line_breaks' => false,
                'auto_url_links'   => false,
                'escape_markup'    => false,
                'special_chars'    => ['>' => 'gt', '<' => 'lt'],
            ],
            'images' => $this->config->get('system.images', [])
        ];
        $page = $this->pages->find('/');
        $excerpts = new Excerpts($page, $defaults);
        $this->parsedown = new Parsedown($excerpts);

        self::assertSame(
            '<p><a href="/item1/item1-3">Down a Level</a></p>',
            $this->parsedown->text('[Down a Level](item1-3)')
        );

        self::assertSame(
            '<p><a href="/item2">Peer Page</a></p>',
            $this->parsedown->text('[Peer Page](../item2)')
        );

        self::assertSame(
            '<p><a href="/?foo=bar">With Query</a></p>',
            $this->parsedown->text('[With Query](?foo=bar)')
        );
        self::assertSame(
            '<p><a href="/foo:bar">With Param</a></p>',
            $this->parsedown->text('[With Param](/foo:bar)')
        );
        self::assertSame(
            '<p><a href="#foo">With Anchor</a></p>',
            $this->parsedown->text('[With Anchor](#foo)')
        );

        $this->config->set('system.languages.supported', ['fr','en']);
        unset($this->grav['language']);
        $this->grav['language'] = new Language($this->grav);
        $this->uri->initializeWithURL('http://testing.dev/fr/item2/item2-2')->init();

        self::assertSame(
            '<p><a href="/fr/item2">Peer Page</a></p>',
            $this->parsedown->text('[Peer Page](../item2)')
        );
        self::assertSame(
            '<p><a href="/fr/item1/item1-3">Down a Level</a></p>',
            $this->parsedown->text('[Down a Level](item1-3)')
        );
        self::assertSame(
            '<p><a href="/fr/?foo=bar">With Query</a></p>',
            $this->parsedown->text('[With Query](?foo=bar)')
        );
        self::assertSame(
            '<p><a href="/fr/foo:bar">With Param</a></p>',
            $this->parsedown->text('[With Param](/foo:bar)')
        );
        self::assertSame(
            '<p><a href="#foo">With Anchor</a></p>',
            $this->parsedown->text('[With Anchor](#foo)')
        );
    }


    public function testAnchorLinksLangRelativeUrls(): void
    {
        $this->config->set('system.languages.supported', ['fr','en']);
        unset($this->grav['language']);
        $this->grav['language'] = new Language($this->grav);
        $this->uri->initializeWithURL('http://testing.dev/fr/item2/item2-2')->init();

        self::assertSame(
            '<p><a href="#foo">Current Anchor</a></p>',
            $this->parsedown->text('[Current Anchor](#foo)')
        );
        self::assertSame(
            '<p><a href="/fr/#foo">Root Anchor</a></p>',
            $this->parsedown->text('[Root Anchor](/#foo)')
        );
        self::assertSame(
            '<p><a href="/fr/item2/item2-1#foo">Peer Anchor</a></p>',
            $this->parsedown->text('[Peer Anchor](../item2-1#foo)')
        );
        self::assertSame(
            '<p><a href="/fr/item2/item2-1#foo">Peer Anchor 2</a></p>',
            $this->parsedown->text('[Peer Anchor 2](../item2-1/#foo)')
        );
    }

    public function testAnchorLinksLangAbsoluteUrls(): void
    {
        $this->config->set('system.absolute_urls', true);
        $this->config->set('system.languages.supported', ['fr','en']);
        unset($this->grav['language']);
        $this->grav['language'] = new Language($this->grav);
        $this->uri->initializeWithURL('http://testing.dev/fr/item2/item2-2')->init();

        self::assertSame(
            '<p><a href="#foo">Current Anchor</a></p>',
            $this->parsedown->text('[Current Anchor](#foo)')
        );
        self::assertSame(
            '<p><a href="http://testing.dev/fr/item2/item2-1#foo">Peer Anchor</a></p>',
            $this->parsedown->text('[Peer Anchor](../item2-1#foo)')
        );
        self::assertSame(
            '<p><a href="http://testing.dev/fr/item2/item2-1#foo">Peer Anchor 2</a></p>',
            $this->parsedown->text('[Peer Anchor 2](../item2-1/#foo)')
        );
        self::assertSame(
            '<p><a href="http://testing.dev/fr/#foo">Root Anchor</a></p>',
            $this->parsedown->text('[Root Anchor](/#foo)')
        );
    }


    public function testExternalLinks(): void
    {
        self::assertSame(
            '<p><a href="http://www.cnn.com">cnn.com</a></p>',
            $this->parsedown->text('[cnn.com](http://www.cnn.com)')
        );
        self::assertSame(
            '<p><a href="https://www.google.com">google.com</a></p>',
            $this->parsedown->text('[google.com](https://www.google.com)')
        );
        self::assertSame(
            '<p><a href="https://github.com/getgrav/grav/issues/new?title=%5Badd-resource%5D%20New%20Plugin%2FTheme&amp;body=Hello%20%2A%2AThere%2A%2A">complex url</a></p>',
            $this->parsedown->text('[complex url](https://github.com/getgrav/grav/issues/new?title=[add-resource]%20New%20Plugin/Theme&body=Hello%20**There**)')
        );
    }

    public function testExternalLinksSubDir(): void
    {
        $this->uri->initializeWithUrlAndRootPath('http://testing.dev/subdir/item2/item2-2', '/subdir')->init();

        self::assertSame(
            '<p><a href="http://www.cnn.com">cnn.com</a></p>',
            $this->parsedown->text('[cnn.com](http://www.cnn.com)')
        );
        self::assertSame(
            '<p><a href="https://www.google.com">google.com</a></p>',
            $this->parsedown->text('[google.com](https://www.google.com)')
        );
    }

    public function testExternalLinksSubDirAbsoluteUrls(): void
    {
        $this->config->set('system.absolute_urls', true);
        $this->uri->initializeWithUrlAndRootPath('http://testing.dev/subdir/item2/item2-2', '/subdir')->init();

        self::assertSame(
            '<p><a href="http://www.cnn.com">cnn.com</a></p>',
            $this->parsedown->text('[cnn.com](http://www.cnn.com)')
        );
        self::assertSame(
            '<p><a href="https://www.google.com">google.com</a></p>',
            $this->parsedown->text('[google.com](https://www.google.com)')
        );
    }

    public function testAnchorLinksRelativeUrls(): void
    {
        $this->uri->initializeWithURL('http://testing.dev/item2/item2-2')->init();

        self::assertSame(
            '<p><a href="#foo">Current Anchor</a></p>',
            $this->parsedown->text('[Current Anchor](#foo)')
        );
        self::assertSame(
            '<p><a href="/#foo">Root Anchor</a></p>',
            $this->parsedown->text('[Root Anchor](/#foo)')
        );
        self::assertSame(
            '<p><a href="/item2/item2-1#foo">Peer Anchor</a></p>',
            $this->parsedown->text('[Peer Anchor](../item2-1#foo)')
        );
        self::assertSame(
            '<p><a href="/item2/item2-1#foo">Peer Anchor 2</a></p>',
            $this->parsedown->text('[Peer Anchor 2](../item2-1/#foo)')
        );
    }

    public function testAnchorLinksAbsoluteUrls(): void
    {
        $this->config->set('system.absolute_urls', true);
        $this->uri->initializeWithURL('http://testing.dev/item2/item2-2')->init();

        self::assertSame(
            '<p><a href="#foo">Current Anchor</a></p>',
            $this->parsedown->text('[Current Anchor](#foo)')
        );
        self::assertSame(
            '<p><a href="http://testing.dev/item2/item2-1#foo">Peer Anchor</a></p>',
            $this->parsedown->text('[Peer Anchor](../item2-1#foo)')
        );
        self::assertSame(
            '<p><a href="http://testing.dev/item2/item2-1#foo">Peer Anchor 2</a></p>',
            $this->parsedown->text('[Peer Anchor 2](../item2-1/#foo)')
        );
        self::assertSame(
            '<p><a href="http://testing.dev/#foo">Root Anchor</a></p>',
            $this->parsedown->text('[Root Anchor](/#foo)')
        );
    }

    public function testAnchorLinksWithPortAbsoluteUrls(): void
    {
        $this->config->set('system.absolute_urls', true);
        $this->uri->initializeWithURL('http://testing.dev:8080/item2/item2-2')->init();

        self::assertSame(
            '<p><a href="http://testing.dev:8080/item2/item2-1#foo">Peer Anchor</a></p>',
            $this->parsedown->text('[Peer Anchor](../item2-1#foo)')
        );
        self::assertSame(
            '<p><a href="http://testing.dev:8080/item2/item2-1#foo">Peer Anchor 2</a></p>',
            $this->parsedown->text('[Peer Anchor 2](../item2-1/#foo)')
        );
        self::assertSame(
            '<p><a href="#foo">Current Anchor</a></p>',
            $this->parsedown->text('[Current Anchor](#foo)')
        );
        self::assertSame(
            '<p><a href="http://testing.dev:8080/#foo">Root Anchor</a></p>',
            $this->parsedown->text('[Root Anchor](/#foo)')
        );
    }

    public function testAnchorLinksSubDirRelativeUrls(): void
    {
        $this->uri->initializeWithUrlAndRootPath('http://testing.dev/subdir/item2/item2-2', '/subdir')->init();

        self::assertSame(
            '<p><a href="/subdir/item2/item2-1#foo">Peer Anchor</a></p>',
            $this->parsedown->text('[Peer Anchor](../item2-1#foo)')
        );
        self::assertSame(
            '<p><a href="/subdir/item2/item2-1#foo">Peer Anchor 2</a></p>',
            $this->parsedown->text('[Peer Anchor 2](../item2-1/#foo)')
        );
        self::assertSame(
            '<p><a href="#foo">Current Anchor</a></p>',
            $this->parsedown->text('[Current Anchor](#foo)')
        );
        self::assertSame(
            '<p><a href="/subdir/#foo">Root Anchor</a></p>',
            $this->parsedown->text('[Root Anchor](/#foo)')
        );
    }

    public function testAnchorLinksSubDirAbsoluteUrls(): void
    {
        $this->config->set('system.absolute_urls', true);
        $this->uri->initializeWithUrlAndRootPath('http://testing.dev/subdir/item2/item2-2', '/subdir')->init();

        self::assertSame(
            '<p><a href="http://testing.dev/subdir/item2/item2-1#foo">Peer Anchor</a></p>',
            $this->parsedown->text('[Peer Anchor](../item2-1#foo)')
        );
        self::assertSame(
            '<p><a href="http://testing.dev/subdir/item2/item2-1#foo">Peer Anchor 2</a></p>',
            $this->parsedown->text('[Peer Anchor 2](../item2-1/#foo)')
        );
        self::assertSame(
            '<p><a href="#foo">Current Anchor</a></p>',
            $this->parsedown->text('[Current Anchor](#foo)')
        );
        self::assertSame(
            '<p><a href="http://testing.dev/subdir/#foo">Root Anchor</a></p>',
            $this->parsedown->text('[Root Anchor](/#foo)')
        );
    }

    public function testSlugRelativeLinks(): void
    {
        $this->uri->initializeWithURL('http://testing.dev/item2/item2-2')->init();

        self::assertSame(
            '<p><a href="/">Up to Root Level</a></p>',
            $this->parsedown->text('[Up to Root Level](../..)')
        );
        self::assertSame(
            '<p><a href="/item2/item2-1">Peer Page</a></p>',
            $this->parsedown->text('[Peer Page](../item2-1)')
        );
        self::assertSame(
            '<p><a href="/item2/item2-2/item2-2-1">Down a Level</a></p>',
            $this->parsedown->text('[Down a Level](item2-2-1)')
        );
        self::assertSame(
            '<p><a href="/item2">Up a Level</a></p>',
            $this->parsedown->text('[Up a Level](..)')
        );
        self::assertSame(
            '<p><a href="/item3/item3-3">Up and Down</a></p>',
            $this->parsedown->text('[Up and Down](../../item3/item3-3)')
        );
        self::assertSame(
            '<p><a href="/item2/item2-2/item2-2-1?foo=bar">Down a Level with Query</a></p>',
            $this->parsedown->text('[Down a Level with Query](item2-2-1?foo=bar)')
        );
        self::assertSame(
            '<p><a href="/item2?foo=bar">Up a Level with Query</a></p>',
            $this->parsedown->text('[Up a Level with Query](../?foo=bar)')
        );
        self::assertSame(
            '<p><a href="/item3/item3-3?foo=bar">Up and Down with Query</a></p>',
            $this->parsedown->text('[Up and Down with Query](../../item3/item3-3?foo=bar)')
        );
        self::assertSame(
            '<p><a href="/item3/item3-3/foo:bar">Up and Down with Param</a></p>',
            $this->parsedown->text('[Up and Down with Param](../../item3/item3-3/foo:bar)')
        );
        self::assertSame(
            '<p><a href="/item3/item3-3#foo">Up and Down with Anchor</a></p>',
            $this->parsedown->text('[Up and Down with Anchor](../../item3/item3-3#foo)')
        );
    }

    public function testSlugRelativeLinksAbsoluteUrls(): void
    {
        $this->config->set('system.absolute_urls', true);
        $this->uri->initializeWithURL('http://testing.dev/item2/item2-2')->init();

        self::assertSame(
            '<p><a href="http://testing.dev/item2/item2-1">Peer Page</a></p>',
            $this->parsedown->text('[Peer Page](../item2-1)')
        );
        self::assertSame(
            '<p><a href="http://testing.dev/item2/item2-2/item2-2-1">Down a Level</a></p>',
            $this->parsedown->text('[Down a Level](item2-2-1)')
        );
        self::assertSame(
            '<p><a href="http://testing.dev/item2">Up a Level</a></p>',
            $this->parsedown->text('[Up a Level](..)')
        );
        self::assertSame(
            '<p><a href="http://testing.dev/">Up to Root Level</a></p>',
            $this->parsedown->text('[Up to Root Level](../..)')
        );
        self::assertSame(
            '<p><a href="http://testing.dev/item3/item3-3">Up and Down</a></p>',
            $this->parsedown->text('[Up and Down](../../item3/item3-3)')
        );
        self::assertSame(
            '<p><a href="http://testing.dev/item2/item2-2/item2-2-1?foo=bar">Down a Level with Query</a></p>',
            $this->parsedown->text('[Down a Level with Query](item2-2-1?foo=bar)')
        );
        self::assertSame(
            '<p><a href="http://testing.dev/item2?foo=bar">Up a Level with Query</a></p>',
            $this->parsedown->text('[Up a Level with Query](../?foo=bar)')
        );
        self::assertSame(
            '<p><a href="http://testing.dev/item3/item3-3?foo=bar">Up and Down with Query</a></p>',
            $this->parsedown->text('[Up and Down with Query](../../item3/item3-3?foo=bar)')
        );
        self::assertSame(
            '<p><a href="http://testing.dev/item3/item3-3/foo:bar">Up and Down with Param</a></p>',
            $this->parsedown->text('[Up and Down with Param](../../item3/item3-3/foo:bar)')
        );
        self::assertSame(
            '<p><a href="http://testing.dev/item3/item3-3#foo">Up and Down with Anchor</a></p>',
            $this->parsedown->text('[Up and Down with Anchor](../../item3/item3-3#foo)')
        );
    }

    public function testSlugRelativeLinksSubDir(): void
    {
        $this->uri->initializeWithUrlAndRootPath('http://testing.dev/subdir/item2/item2-2', '/subdir')->init();

        self::assertSame(
            '<p><a href="/subdir/item2/item2-1">Peer Page</a></p>',
            $this->parsedown->text('[Peer Page](../item2-1)')
        );
        self::assertSame(
            '<p><a href="/subdir/item2/item2-2/item2-2-1">Down a Level</a></p>',
            $this->parsedown->text('[Down a Level](item2-2-1)')
        );
        self::assertSame(
            '<p><a href="/subdir/item2">Up a Level</a></p>',
            $this->parsedown->text('[Up a Level](..)')
        );
        self::assertSame(
            '<p><a href="/subdir">Up to Root Level</a></p>',
            $this->parsedown->text('[Up to Root Level](../..)')
        );
        self::assertSame(
            '<p><a href="/subdir/item3/item3-3">Up and Down</a></p>',
            $this->parsedown->text('[Up and Down](../../item3/item3-3)')
        );
        self::assertSame(
            '<p><a href="/subdir/item2/item2-2/item2-2-1?foo=bar">Down a Level with Query</a></p>',
            $this->parsedown->text('[Down a Level with Query](item2-2-1?foo=bar)')
        );
        self::assertSame(
            '<p><a href="/subdir/item2?foo=bar">Up a Level with Query</a></p>',
            $this->parsedown->text('[Up a Level with Query](../?foo=bar)')
        );
        self::assertSame(
            '<p><a href="/subdir/item3/item3-3?foo=bar">Up and Down with Query</a></p>',
            $this->parsedown->text('[Up and Down with Query](../../item3/item3-3?foo=bar)')
        );
        self::assertSame(
            '<p><a href="/subdir/item3/item3-3/foo:bar">Up and Down with Param</a></p>',
            $this->parsedown->text('[Up and Down with Param](../../item3/item3-3/foo:bar)')
        );
        self::assertSame(
            '<p><a href="/subdir/item3/item3-3#foo">Up and Down with Anchor</a></p>',
            $this->parsedown->text('[Up and Down with Anchor](../../item3/item3-3#foo)')
        );
    }

    public function testSlugRelativeLinksSubDirAbsoluteUrls(): void
    {
        $this->config->set('system.absolute_urls', true);
        $this->uri->initializeWithUrlAndRootPath('http://testing.dev/subdir/item2/item2-2', '/subdir')->init();

        self::assertSame(
            '<p><a href="http://testing.dev/subdir/item2/item2-1">Peer Page</a></p>',
            $this->parsedown->text('[Peer Page](../item2-1)')
        );
        self::assertSame(
            '<p><a href="http://testing.dev/subdir/item2/item2-2/item2-2-1">Down a Level</a></p>',
            $this->parsedown->text('[Down a Level](item2-2-1)')
        );
        self::assertSame(
            '<p><a href="http://testing.dev/subdir/item2">Up a Level</a></p>',
            $this->parsedown->text('[Up a Level](..)')
        );
        self::assertSame(
            '<p><a href="http://testing.dev/subdir">Up to Root Level</a></p>',
            $this->parsedown->text('[Up to Root Level](../..)')
        );
        self::assertSame(
            '<p><a href="http://testing.dev/subdir/item3/item3-3">Up and Down</a></p>',
            $this->parsedown->text('[Up and Down](../../item3/item3-3)')
        );
        self::assertSame(
            '<p><a href="http://testing.dev/subdir/item2/item2-2/item2-2-1?foo=bar">Down a Level with Query</a></p>',
            $this->parsedown->text('[Down a Level with Query](item2-2-1?foo=bar)')
        );
        self::assertSame(
            '<p><a href="http://testing.dev/subdir/item2?foo=bar">Up a Level with Query</a></p>',
            $this->parsedown->text('[Up a Level with Query](../?foo=bar)')
        );
        self::assertSame(
            '<p><a href="http://testing.dev/subdir/item3/item3-3?foo=bar">Up and Down with Query</a></p>',
            $this->parsedown->text('[Up and Down with Query](../../item3/item3-3?foo=bar)')
        );
        self::assertSame(
            '<p><a href="http://testing.dev/subdir/item3/item3-3/foo:bar">Up and Down with Param</a></p>',
            $this->parsedown->text('[Up and Down with Param](../../item3/item3-3/foo:bar)')
        );
        self::assertSame(
            '<p><a href="http://testing.dev/subdir/item3/item3-3#foo">Up and Down with Anchor</a></p>',
            $this->parsedown->text('[Up and Down with Anchor](../../item3/item3-3#foo)')
        );
    }


    public function testDirectoryRelativeLinks(): void
    {
        $this->uri->initializeWithURL('http://testing.dev/item2/item2-2')->init();

        self::assertSame(
            '<p><a href="/item3/item3-3/foo:bar">Up and Down with Param</a></p>',
            $this->parsedown->text('[Up and Down with Param](../../03.item3/03.item3-3/foo:bar)')
        );
        self::assertSame(
            '<p><a href="/item2/item2-1">Peer Page</a></p>',
            $this->parsedown->text('[Peer Page](../01.item2-1)')
        );
        self::assertSame(
            '<p><a href="/item2/item2-2/item2-2-1">Down a Level</a></p>',
            $this->parsedown->text('[Down a Level](01.item2-2-1)')
        );
        self::assertSame(
            '<p><a href="/item3/item3-3">Up and Down</a></p>',
            $this->parsedown->text('[Up and Down](../../03.item3/03.item3-3)')
        );
        self::assertSame(
            '<p><a href="/item2/item2-2/item2-2-1?foo=bar">Down a Level with Query</a></p>',
            $this->parsedown->text('[Down a Level with Query](01.item2-2-1?foo=bar)')
        );
        self::assertSame(
            '<p><a href="/item3/item3-3?foo=bar">Up and Down with Query</a></p>',
            $this->parsedown->text('[Up and Down with Query](../../03.item3/03.item3-3?foo=bar)')
        );
        self::assertSame(
            '<p><a href="/item3/item3-3#foo">Up and Down with Anchor</a></p>',
            $this->parsedown->text('[Up and Down with Anchor](../../03.item3/03.item3-3#foo)')
        );
    }


    public function testAbsoluteLinks(): void
    {
        $this->uri->initializeWithURL('http://testing.dev/item2/item2-2')->init();

        self::assertSame(
            '<p><a href="/">Root</a></p>',
            $this->parsedown->text('[Root](/)')
        );
        self::assertSame(
            '<p><a href="/item2/item2-1">Peer Page</a></p>',
            $this->parsedown->text('[Peer Page](/item2/item2-1)')
        );
        self::assertSame(
            '<p><a href="/item2/item2-2/item2-2-1">Down a Level</a></p>',
            $this->parsedown->text('[Down a Level](/item2/item2-2/item2-2-1)')
        );
        self::assertSame(
            '<p><a href="/item2">Up a Level</a></p>',
            $this->parsedown->text('[Up a Level](/item2)')
        );
        self::assertSame(
            '<p><a href="/item2?foo=bar">With Query</a></p>',
            $this->parsedown->text('[With Query](/item2?foo=bar)')
        );
        self::assertSame(
            '<p><a href="/item2/foo:bar">With Param</a></p>',
            $this->parsedown->text('[With Param](/item2/foo:bar)')
        );
        self::assertSame(
            '<p><a href="/item2#foo">With Anchor</a></p>',
            $this->parsedown->text('[With Anchor](/item2#foo)')
        );
    }

    public function testDirectoryAbsoluteLinksSubDir(): void
    {
        $this->uri->initializeWithUrlAndRootPath('http://testing.dev/subdir/item2/item2-2', '/subdir')->init();

        self::assertSame(
            '<p><a href="/subdir/">Root</a></p>',
            $this->parsedown->text('[Root](/)')
        );
        self::assertSame(
            '<p><a href="/subdir/item2/item2-1">Peer Page</a></p>',
            $this->parsedown->text('[Peer Page](/item2/item2-1)')
        );
        self::assertSame(
            '<p><a href="/subdir/item2/item2-2/item2-2-1">Down a Level</a></p>',
            $this->parsedown->text('[Down a Level](/item2/item2-2/item2-2-1)')
        );
        self::assertSame(
            '<p><a href="/subdir/item2">Up a Level</a></p>',
            $this->parsedown->text('[Up a Level](/item2)')
        );
        self::assertSame(
            '<p><a href="/subdir/item2?foo=bar">With Query</a></p>',
            $this->parsedown->text('[With Query](/item2?foo=bar)')
        );
        self::assertSame(
            '<p><a href="/subdir/item2/foo:bar">With Param</a></p>',
            $this->parsedown->text('[With Param](/item2/foo:bar)')
        );
        self::assertSame(
            '<p><a href="/subdir/item2#foo">With Anchor</a></p>',
            $this->parsedown->text('[With Anchor](/item2#foo)')
        );
    }

    public function testDirectoryAbsoluteLinksSubDirAbsoluteUrl(): void
    {
        $this->config->set('system.absolute_urls', true);
        $this->uri->initializeWithUrlAndRootPath('http://testing.dev/subdir/item2/item2-2', '/subdir')->init();

        self::assertSame(
            '<p><a href="http://testing.dev/subdir/">Root</a></p>',
            $this->parsedown->text('[Root](/)')
        );
        self::assertSame(
            '<p><a href="http://testing.dev/subdir/item2/item2-1">Peer Page</a></p>',
            $this->parsedown->text('[Peer Page](/item2/item2-1)')
        );
        self::assertSame(
            '<p><a href="http://testing.dev/subdir/item2/item2-2/item2-2-1">Down a Level</a></p>',
            $this->parsedown->text('[Down a Level](/item2/item2-2/item2-2-1)')
        );
        self::assertSame(
            '<p><a href="http://testing.dev/subdir/item2">Up a Level</a></p>',
            $this->parsedown->text('[Up a Level](/item2)')
        );
        self::assertSame(
            '<p><a href="http://testing.dev/subdir/item2?foo=bar">With Query</a></p>',
            $this->parsedown->text('[With Query](/item2?foo=bar)')
        );
        self::assertSame(
            '<p><a href="http://testing.dev/subdir/item2/foo:bar">With Param</a></p>',
            $this->parsedown->text('[With Param](/item2/foo:bar)')
        );
        self::assertSame(
            '<p><a href="http://testing.dev/subdir/item2#foo">With Anchor</a></p>',
            $this->parsedown->text('[With Anchor](/item2#foo)')
        );
    }

    public function testSpecialProtocols(): void
    {
        $this->uri->initializeWithURL('http://testing.dev/item2/item2-2')->init();

        self::assertSame(
            '<p><a href="mailto:user@domain.com">mailto</a></p>',
            $this->parsedown->text('[mailto](mailto:user@domain.com)')
        );
        self::assertSame(
            '<p><a href="xmpp:xyx@domain.com">xmpp</a></p>',
            $this->parsedown->text('[xmpp](xmpp:xyx@domain.com)')
        );
        self::assertSame(
            '<p><a href="tel:123-555-12345">tel</a></p>',
            $this->parsedown->text('[tel](tel:123-555-12345)')
        );
        self::assertSame(
            '<p><a href="sms:123-555-12345">sms</a></p>',
            $this->parsedown->text('[sms](sms:123-555-12345)')
        );
        self::assertSame(
            '<p><a href="rdp://ts.example.com">ts.example.com</a></p>',
            $this->parsedown->text('[ts.example.com](rdp://ts.example.com)')
        );
    }

    public function testSpecialProtocolsSubDir(): void
    {
        $this->uri->initializeWithUrlAndRootPath('http://testing.dev/subdir/item2/item2-2', '/subdir')->init();

        self::assertSame(
            '<p><a href="mailto:user@domain.com">mailto</a></p>',
            $this->parsedown->text('[mailto](mailto:user@domain.com)')
        );
        self::assertSame(
            '<p><a href="xmpp:xyx@domain.com">xmpp</a></p>',
            $this->parsedown->text('[xmpp](xmpp:xyx@domain.com)')
        );
        self::assertSame(
            '<p><a href="tel:123-555-12345">tel</a></p>',
            $this->parsedown->text('[tel](tel:123-555-12345)')
        );
        self::assertSame(
            '<p><a href="sms:123-555-12345">sms</a></p>',
            $this->parsedown->text('[sms](sms:123-555-12345)')
        );
        self::assertSame(
            '<p><a href="rdp://ts.example.com">ts.example.com</a></p>',
            $this->parsedown->text('[ts.example.com](rdp://ts.example.com)')
        );
    }

    public function testSpecialProtocolsSubDirAbsoluteUrl(): void
    {
        $this->config->set('system.absolute_urls', true);
        $this->uri->initializeWithUrlAndRootPath('http://testing.dev/subdir/item2/item2-2', '/subdir')->init();

        self::assertSame(
            '<p><a href="mailto:user@domain.com">mailto</a></p>',
            $this->parsedown->text('[mailto](mailto:user@domain.com)')
        );
        self::assertSame(
            '<p><a href="xmpp:xyx@domain.com">xmpp</a></p>',
            $this->parsedown->text('[xmpp](xmpp:xyx@domain.com)')
        );
        self::assertSame(
            '<p><a href="tel:123-555-12345">tel</a></p>',
            $this->parsedown->text('[tel](tel:123-555-12345)')
        );
        self::assertSame(
            '<p><a href="sms:123-555-12345">sms</a></p>',
            $this->parsedown->text('[sms](sms:123-555-12345)')
        );
        self::assertSame(
            '<p><a href="rdp://ts.example.com">ts.example.com</a></p>',
            $this->parsedown->text('[ts.example.com](rdp://ts.example.com)')
        );
    }

    public function testReferenceLinks(): void
    {
        $this->uri->initializeWithURL('http://testing.dev/item2/item2-2')->init();

        $sample = '[relative link][r_relative]
                   [r_relative]: ../item2-3#blah';
        self::assertSame(
            '<p><a href="/item2/item2-3#blah">relative link</a></p>',
            $this->parsedown->text($sample)
        );

        $sample = '[absolute link][r_absolute]
                   [r_absolute]: /item3#blah';
        self::assertSame(
            '<p><a href="/item3#blah">absolute link</a></p>',
            $this->parsedown->text($sample)
        );

        $sample = '[external link][r_external]
                   [r_external]: http://www.cnn.com';
        self::assertSame(
            '<p><a href="http://www.cnn.com">external link</a></p>',
            $this->parsedown->text($sample)
        );
    }

    public function testAttributeLinks(): void
    {
        $this->uri->initializeWithURL('http://testing.dev/item2/item2-2')->init();

        self::assertSame(
            '<p><a href="#something" class="button">Anchor Class</a></p>',
            $this->parsedown->text('[Anchor Class](?classes=button#something)')
        );
        self::assertSame(
            '<p><a href="/item2/item2-3" class="button">Relative Class</a></p>',
            $this->parsedown->text('[Relative Class](../item2-3?classes=button)')
        );
        self::assertSame(
            '<p><a href="/item2/item2-3" id="unique">Relative ID</a></p>',
            $this->parsedown->text('[Relative ID](../item2-3?id=unique)')
        );
        self::assertSame(
            '<p><a href="https://github.com/getgrav/grav" class="button big">External</a></p>',
            $this->parsedown->text('[External](https://github.com/getgrav/grav?classes=button,big)')
        );
        self::assertSame(
            '<p><a href="/item2/item2-3?id=unique">Relative Noprocess</a></p>',
            $this->parsedown->text('[Relative Noprocess](../item2-3?id=unique&noprocess)')
        );
        self::assertSame(
            '<p><a href="/item2/item2-3" target="_blank">Relative Target</a></p>',
            $this->parsedown->text('[Relative Target](../item2-3?target=_blank)')
        );
        self::assertSame(
            '<p><a href="/item2/item2-3" rel="nofollow">Relative Rel</a></p>',
            $this->parsedown->text('[Relative Rel](../item2-3?rel=nofollow)')
        );
        self::assertSame(
            '<p><a href="/item2/item2-3?foo=bar&amp;baz=qux" rel="nofollow" class="button">Relative Mixed</a></p>',
            $this->parsedown->text('[Relative Mixed](../item2-3?foo=bar&baz=qux&rel=nofollow&class=button)')
        );
    }

    public function testInvalidLinks(): void
    {
        $this->uri->initializeWithURL('http://testing.dev/item2/item2-2')->init();

        self::assertSame(
            '<p><a href="/item2/item2-2/no-page">Non Existent Page</a></p>',
            $this->parsedown->text('[Non Existent Page](no-page)')
        );
        self::assertSame(
            '<p><a href="/item2/item2-2/existing-file.zip">Existent File</a></p>',
            $this->parsedown->text('[Existent File](existing-file.zip)')
        );
        self::assertSame(
            '<p><a href="/item2/item2-2/missing-file.zip">Non Existent File</a></p>',
            $this->parsedown->text('[Non Existent File](missing-file.zip)')
        );
    }

    public function testInvalidLinksSubDir(): void
    {
        $this->uri->initializeWithUrlAndRootPath('http://testing.dev/subdir/item2/item2-2', '/subdir')->init();

        self::assertSame(
            '<p><a href="/subdir/item2/item2-2/no-page">Non Existent Page</a></p>',
            $this->parsedown->text('[Non Existent Page](no-page)')
        );
        self::assertSame(
            '<p><a href="/subdir/item2/item2-2/existing-file.zip">Existent File</a></p>',
            $this->parsedown->text('[Existent File](existing-file.zip)')
        );
        self::assertSame(
            '<p><a href="/subdir/item2/item2-2/missing-file.zip">Non Existent File</a></p>',
            $this->parsedown->text('[Non Existent File](missing-file.zip)')
        );
    }

    public function testInvalidLinksSubDirAbsoluteUrl(): void
    {
        $this->config->set('system.absolute_urls', true);
        $this->uri->initializeWithUrlAndRootPath('http://testing.dev/subdir/item2/item2-2', '/subdir')->init();

        self::assertSame(
            '<p><a href="http://testing.dev/subdir/item2/item2-2/no-page">Non Existent Page</a></p>',
            $this->parsedown->text('[Non Existent Page](no-page)')
        );
        self::assertSame(
            '<p><a href="http://testing.dev/subdir/item2/item2-2/existing-file.zip">Existent File</a></p>',
            $this->parsedown->text('[Existent File](existing-file.zip)')
        );
        self::assertSame(
            '<p><a href="http://testing.dev/subdir/item2/item2-2/missing-file.zip">Non Existent File</a></p>',
            $this->parsedown->text('[Non Existent File](missing-file.zip)')
        );
    }


    /**
     * @param $string
     *
     * @return mixed
     */
    private function stripLeadingWhitespace($string)
    {
        return preg_replace('/^\s*(.*)/', '', $string);
    }

    private function setImagesDefaults($defaults) {
        $defaults = [
            'images' => [
                'defaults' => $defaults
            ],
        ];
        $page = $this->pages->find('/item2/item2-2');
        $excerpts = new Excerpts($page, $defaults);
        $this->parsedown = new Parsedown($excerpts);
    }
}
