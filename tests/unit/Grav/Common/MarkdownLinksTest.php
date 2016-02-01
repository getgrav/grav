<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Uri;
use Grav\Common\Config\Config;
use Grav\Common\Page\Pages;
use Grav\Common\Page\Page;
use Grav\Common\Markdown\Parsedown;


/**
 * Class AssetsTest
 */
class MarkdownTest extends \Codeception\TestCase\Test
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

    static $run = false;

    protected function _before()
    {
        $this->grav = Fixtures::get('grav');
        $this->pages = $this->grav['pages'];
        $this->config = $this->grav['config'];
        $this->uri = $this->grav['uri'];

        if (!self::$run) {
            /** @var UniformResourceLocator $locator */
            $locator = $this->grav['locator'];
            $locator->addPath('page', '', 'tests/fake/nested-site/user/pages', false);
            $this->pages->init();
            self::$run = true;
        }

        $defaults = [
            'extra'            => false,
            'auto_line_breaks' => false,
            'auto_url_links'   => false,
            'escape_markup'    => false,
            'special_chars'    => ['>' => 'gt', '<' => 'lt'],
        ];
        $page = $this->pages->dispatch('/item2/item2-2');
        $this->parsedown = new Parsedown($page, $defaults);
    }

    protected function _after()
    {
    }

    public function testAnchorLinksNoPortRelativeUrls()
    {
        $this->config->set('system.absolute_urls', false);
        $this->uri->initializeWithURL('http://localhost/item2/item-2-2')->init();

        $this->assertSame('<p><a href="/item2/item2-1#foo">Peer Anchor</a></p>',
            $this->parsedown->text('[Peer Anchor](../item2-1#foo)'));
        $this->assertSame('<p><a href="/item2/item2-1/#foo">Peer Anchor 2</a></p>',
            $this->parsedown->text('[Peer Anchor 2](../item2-1/#foo)'));
//        $this->assertSame('<p><a href="#foo">Current Anchor</a></p>',
//            $this->parsedown->text('[Current Anchor](#foo)'));
        $this->assertSame('<p><a href="/#foo">Root Anchor</a></p>',
            $this->parsedown->text('[Root Anchor](/#foo)'));

    }

    public function testAnchorLinksNoPortAbsoluteUrls()
    {
        $this->config->set('system.absolute_urls', true);
        $this->uri->initializeWithURL('http://localhost/item2/item-2-2')->init();

        $this->assertSame('<p><a href="http://localhost/item2/item2-1#foo">Peer Anchor</a></p>',
            $this->parsedown->text('[Peer Anchor](../item2-1#foo)'));
        $this->assertSame('<p><a href="http://localhost/item2/item2-1/#foo">Peer Anchor 2</a></p>',
            $this->parsedown->text('[Peer Anchor 2](../item2-1/#foo)'));
//        $this->assertSame('<p><a href="#foo">Current Anchor</a></p>',
//            $this->parsedown->text('[Current Anchor](#foo)'));
        $this->assertSame('<p><a href="http://localhost/#foo">Root Anchor</a></p>',
            $this->parsedown->text('[Root Anchor](/#foo)'));
    }

    public function testAnchorLinksWithPortAbsoluteUrls()
    {
        $this->config->set('system.absolute_urls', true);
        $this->uri->initializeWithURL('http://localhost:8080/item2/item-2-2')->init();

        $this->assertSame('<p><a href="http://localhost:8080/item2/item2-1#foo">Peer Anchor</a></p>',
            $this->parsedown->text('[Peer Anchor](../item2-1#foo)'));
        $this->assertSame('<p><a href="http://localhost:8080/item2/item2-1/#foo">Peer Anchor 2</a></p>',
            $this->parsedown->text('[Peer Anchor 2](../item2-1/#foo)'));
//        $this->assertSame('<p><a href="http://localhost:8080#foo">Current Anchor</a></p>',
//            $this->parsedown->text('[Current Anchor](#foo)'));
        $this->assertSame('<p><a href="http://localhost:8080/#foo">Root Anchor</a></p>',
            $this->parsedown->text('[Root Anchor](/#foo)'));

    }

    public function testAnchorLinksSubDirRelativeUrls()
    {
        $this->config->set('system.absolute_urls', false);
        $this->uri->initializeWithUrlAndRootPath('http://localhost/subdir/item2/item-2-2', '/subdir')->init();

        $this->assertSame('<p><a href="/subdir/item2/item2-1#foo">Peer Anchor</a></p>',
            $this->parsedown->text('[Peer Anchor](../item2-1#foo)'));
        $this->assertSame('<p><a href="/subdir/item2/item2-1/#foo">Peer Anchor 2</a></p>',
            $this->parsedown->text('[Peer Anchor 2](../item2-1/#foo)'));
//        $this->assertSame('<p><a href="/subdir/#foo">Current Anchor</a></p>',
//            $this->parsedown->text('[Current Anchor](#foo)'));
        $this->assertSame('<p><a href="/subdir/#foo">Root Anchor</a></p>',
            $this->parsedown->text('[Root Anchor](/#foo)'));

    }

    public function testAnchorLinksSubDirAbsoluteUrls()
    {
        $this->config->set('system.absolute_urls', true);
        $this->uri->initializeWithUrlAndRootPath('http://localhost/subdir/item2/item-2-2', '/subdir')->init();

        $this->assertSame('<p><a href="http://localhost/subdir/item2/item2-1#foo">Peer Anchor</a></p>',
            $this->parsedown->text('[Peer Anchor](../item2-1#foo)'));
        $this->assertSame('<p><a href="http://localhost/subdir/item2/item2-1/#foo">Peer Anchor 2</a></p>',
            $this->parsedown->text('[Peer Anchor 2](../item2-1/#foo)'));
//        $this->assertSame('<p><a href="http://localhost/subdir#foo">Current Anchor</a></p>',
//            $this->parsedown->text('[Current Anchor](#foo)'));
        $this->assertSame('<p><a href="http://localhost/subdir/#foo">Root Anchor</a></p>',
            $this->parsedown->text('[Root Anchor](/#foo)'));

    }

    public function testSlugRelativeLinks()
    {
        $this->config->set('system.absolute_urls', false);
        $this->uri->initializeWithURL('http://localhost/item2/item-2-2')->init();

        $this->assertSame('<p><a href="/item2/item2-1">Peer Page</a></p>',
            $this->parsedown->text('[Peer Page](../item2-1)'));
        $this->assertSame('<p><a href="/item2/item2-2/item2-2-1">Down a Level</a></p>',
            $this->parsedown->text('[Down a Level](item2-2-1)'));
        $this->assertSame('<p><a href="/item2">Up a Level</a></p>',
            $this->parsedown->text('[Up a Level](..)'));
//        $this->assertSame('<p><a href="/">Up to Root Level</a></p>',
//            $this->parsedown->text('[Up to Root Level](../..)'));
        $this->assertSame('<p><a href="/item3/item3-3">Up and Down</a></p>',
            $this->parsedown->text('[Up and Down](../../item3/item3-3)'));
        $this->assertSame('<p><a href="/item2/item2-2/item2-2-1?foo=bar">Down a Level with Query</a></p>',
            $this->parsedown->text('[Down a Level with Query](item2-2-1?foo=bar)'));
//        $this->assertSame('<p><a href="/item2?foo=bar">Up a Level with Query</a></p>',
//            $this->parsedown->text('[Up a Level with Query](../?foo=bar)'));
        $this->assertSame('<p><a href="/item3/item3-3?foo=bar">Up and Down with Query</a></p>',
            $this->parsedown->text('[Up and Down with Query](../../item3/item3-3?foo=bar)'));
        $this->assertSame('<p><a href="/item3/item3-3/foo:bar">Up and Down with Param</a></p>',
            $this->parsedown->text('[Up and Down with Param](../../item3/item3-3/foo:bar)'));
        $this->assertSame('<p><a href="/item3/item3-3#foo">Up and Down with Anchor</a></p>',
            $this->parsedown->text('[Up and Down with Anchor](../../item3/item3-3#foo)'));
    }

    public function testSlugRelativeLinksAbsoluteUrls()
    {
        $this->config->set('system.absolute_urls', true);
        $this->uri->initializeWithURL('http://localhost/item2/item-2-2')->init();

        $this->assertSame('<p><a href="http://localhost/item2/item2-1">Peer Page</a></p>',
            $this->parsedown->text('[Peer Page](../item2-1)'));
        $this->assertSame('<p><a href="http://localhost/item2/item2-2/item2-2-1">Down a Level</a></p>',
            $this->parsedown->text('[Down a Level](item2-2-1)'));
        $this->assertSame('<p><a href="http://localhost/item2">Up a Level</a></p>',
            $this->parsedown->text('[Up a Level](..)'));
//        $this->assertSame('<p><a href="http://localhost/">Up to Root Level</a></p>',
//            $this->parsedown->text('[Up to Root Level](../..)'));
        $this->assertSame('<p><a href="http://localhost/item3/item3-3">Up and Down</a></p>',
            $this->parsedown->text('[Up and Down](../../item3/item3-3)'));
        $this->assertSame('<p><a href="http://localhost/item2/item2-2/item2-2-1?foo=bar">Down a Level with Query</a></p>',
            $this->parsedown->text('[Down a Level with Query](item2-2-1?foo=bar)'));
//        $this->assertSame('<p><a href="/item2?foo=bar">Up a Level with Query</a></p>',
//            $this->parsedown->text('[Up a Level with Query](../?foo=bar)'));
        $this->assertSame('<p><a href="http://localhost/item3/item3-3?foo=bar">Up and Down with Query</a></p>',
            $this->parsedown->text('[Up and Down with Query](../../item3/item3-3?foo=bar)'));
        $this->assertSame('<p><a href="http://localhost/item3/item3-3/foo:bar">Up and Down with Param</a></p>',
            $this->parsedown->text('[Up and Down with Param](../../item3/item3-3/foo:bar)'));
        $this->assertSame('<p><a href="http://localhost/item3/item3-3#foo">Up and Down with Anchor</a></p>',
            $this->parsedown->text('[Up and Down with Anchor](../../item3/item3-3#foo)'));
    }

    public function testSlugRelativeLinksSubDir()
    {
        $this->config->set('system.absolute_urls', false);
        $this->uri->initializeWithUrlAndRootPath('http://localhost/subdir/item2/item-2-2', '/subdir')->init();

        $this->assertSame('<p><a href="/subdir/item2/item2-1">Peer Page</a></p>',
            $this->parsedown->text('[Peer Page](../item2-1)'));
        $this->assertSame('<p><a href="/subdir/item2/item2-2/item2-2-1">Down a Level</a></p>',
            $this->parsedown->text('[Down a Level](item2-2-1)'));
        $this->assertSame('<p><a href="/subdir/item2">Up a Level</a></p>',
            $this->parsedown->text('[Up a Level](..)'));
        $this->assertSame('<p><a href="/subdir">Up to Root Level</a></p>',
            $this->parsedown->text('[Up to Root Level](../..)'));
        $this->assertSame('<p><a href="/subdir/item3/item3-3">Up and Down</a></p>',
            $this->parsedown->text('[Up and Down](../../item3/item3-3)'));
        $this->assertSame('<p><a href="/subdir/item2/item2-2/item2-2-1?foo=bar">Down a Level with Query</a></p>',
            $this->parsedown->text('[Down a Level with Query](item2-2-1?foo=bar)'));
//        $this->assertSame('<p><a href="/subdir/item2?foo=bar">Up a Level with Query</a></p>',
//            $this->parsedown->text('[Up a Level with Query](../?foo=bar)'));
        $this->assertSame('<p><a href="/subdir/item3/item3-3?foo=bar">Up and Down with Query</a></p>',
            $this->parsedown->text('[Up and Down with Query](../../item3/item3-3?foo=bar)'));
        $this->assertSame('<p><a href="/subdir/item3/item3-3/foo:bar">Up and Down with Param</a></p>',
            $this->parsedown->text('[Up and Down with Param](../../item3/item3-3/foo:bar)'));
        $this->assertSame('<p><a href="/subdir/item3/item3-3#foo">Up and Down with Anchor</a></p>',
            $this->parsedown->text('[Up and Down with Anchor](../../item3/item3-3#foo)'));
    }

    public function testSlugRelativeLinksSubDirAbsoluteUrls()
    {
        $this->config->set('system.absolute_urls', true);
        $this->uri->initializeWithUrlAndRootPath('http://localhost/subdir/item2/item-2-2', '/subdir')->init();

        $this->assertSame('<p><a href="http://localhost/subdir/item2/item2-1">Peer Page</a></p>',
            $this->parsedown->text('[Peer Page](../item2-1)'));
        $this->assertSame('<p><a href="http://localhost/subdir/item2/item2-2/item2-2-1">Down a Level</a></p>',
            $this->parsedown->text('[Down a Level](item2-2-1)'));
        $this->assertSame('<p><a href="http://localhost/subdir/item2">Up a Level</a></p>',
            $this->parsedown->text('[Up a Level](..)'));
        $this->assertSame('<p><a href="http://localhost/subdir">Up to Root Level</a></p>',
            $this->parsedown->text('[Up to Root Level](../..)'));
        $this->assertSame('<p><a href="http://localhost/subdir/item3/item3-3">Up and Down</a></p>',
            $this->parsedown->text('[Up and Down](../../item3/item3-3)'));
        $this->assertSame('<p><a href="http://localhost/subdir/item2/item2-2/item2-2-1?foo=bar">Down a Level with Query</a></p>',
            $this->parsedown->text('[Down a Level with Query](item2-2-1?foo=bar)'));
//        $this->assertSame('<p><a href="http://localhost/subdir/item2?foo=bar">Up a Level with Query</a></p>',
//            $this->parsedown->text('[Up a Level with Query](../?foo=bar)'));
        $this->assertSame('<p><a href="http://localhost/subdir/item3/item3-3?foo=bar">Up and Down with Query</a></p>',
            $this->parsedown->text('[Up and Down with Query](../../item3/item3-3?foo=bar)'));
        $this->assertSame('<p><a href="http://localhost/subdir/item3/item3-3/foo:bar">Up and Down with Param</a></p>',
            $this->parsedown->text('[Up and Down with Param](../../item3/item3-3/foo:bar)'));
        $this->assertSame('<p><a href="http://localhost/subdir/item3/item3-3#foo">Up and Down with Anchor</a></p>',
            $this->parsedown->text('[Up and Down with Anchor](../../item3/item3-3#foo)'));
    }

    public function testDirectoryRelativeLinks()
    {
        $this->config->set('system.absolute_urls', false);
        $this->uri->initializeWithURL('http://localhost/item2/item-2-2')->init();

        $this->assertSame('<p><a href="/item2/item2-1">Peer Page</a></p>',
            $this->parsedown->text('[Peer Page](../01.item2-1)'));
        $this->assertSame('<p><a href="/item2/item2-2/item2-2-1">Down a Level</a></p>',
            $this->parsedown->text('[Down a Level](01.item2-2-1)'));
        $this->assertSame('<p><a href="/item3/item3-3">Up and Down</a></p>',
            $this->parsedown->text('[Up and Down](../../03.item3/03.item3-3)'));
        $this->assertSame('<p><a href="/item2/item2-2/item2-2-1?foo=bar">Down a Level with Query</a></p>',
            $this->parsedown->text('[Down a Level with Query](01.item2-2-1?foo=bar)'));
        $this->assertSame('<p><a href="/item3/item3-3?foo=bar">Up and Down with Query</a></p>',
            $this->parsedown->text('[Up and Down with Query](../../03.item3/03.item3-3?foo=bar)'));
//        $this->assertSame('<p><a href="/item3/item3-3/foo:bar">Up and Down with Param</a></p>',
//            $this->parsedown->text('[Up and Down with Param](../../03.item3/03.item3-3/foo:bar)'));
        $this->assertSame('<p><a href="/item3/item3-3#foo">Up and Down with Anchor</a></p>',
            $this->parsedown->text('[Up and Down with Anchor](../../03.item3/03.item3-3#foo)'));

    }

    public function testDirectoryAbsoluteLinks()
    {
        $this->config->set('system.absolute_urls', false);
        $this->uri->initializeWithURL('http://localhost/item2/item-2-2')->init();

//        $this->assertSame('<p><a href="/">Root</a></p>',
//            $this->parsedown->text('[Root](/)'));
        $this->assertSame('<p><a href="/item2/item2-1">Peer Page</a></p>',
            $this->parsedown->text('[Peer Page](/item2/item2-1)'));
        $this->assertSame('<p><a href="/item2/item2-2/item2-2-1">Down a Level</a></p>',
            $this->parsedown->text('[Down a Level](/item2/item2-2/item2-2-1)'));
        $this->assertSame('<p><a href="/item2">Up a Level</a></p>',
            $this->parsedown->text('[Up a Level](/item2)'));
        $this->assertSame('<p><a href="/item2?foo=bar">With Query</a></p>',
            $this->parsedown->text('[With Query](/item2?foo=bar)'));
        $this->assertSame('<p><a href="/item2/foo:bar">With Param</a></p>',
            $this->parsedown->text('[With Param](/item2/foo:bar)'));
        $this->assertSame('<p><a href="/item2#foo">With Anchor</a></p>',
            $this->parsedown->text('[With Anchor](/item2#foo)'));

    }

    public function testDirectoryAbsoluteLinksSubDir()
    {
        $this->config->set('system.absolute_urls', false);
        $this->uri->initializeWithUrlAndRootPath('http://localhost/subdir/item2/item-2-2', '/subdir')->init();

        $this->assertSame('<p><a href="/subdir">Root</a></p>',
            $this->parsedown->text('[Root](/)'));
        $this->assertSame('<p><a href="/subdir/item2/item2-1">Peer Page</a></p>',
            $this->parsedown->text('[Peer Page](/item2/item2-1)'));
        $this->assertSame('<p><a href="/subdir/item2/item2-2/item2-2-1">Down a Level</a></p>',
            $this->parsedown->text('[Down a Level](/item2/item2-2/item2-2-1)'));
        $this->assertSame('<p><a href="/subdir/item2">Up a Level</a></p>',
            $this->parsedown->text('[Up a Level](/item2)'));
        $this->assertSame('<p><a href="/subdir/item2?foo=bar">With Query</a></p>',
            $this->parsedown->text('[With Query](/item2?foo=bar)'));
        $this->assertSame('<p><a href="/subdir/item2/foo:bar">With Param</a></p>',
            $this->parsedown->text('[With Param](/item2/foo:bar)'));
        $this->assertSame('<p><a href="/subdir/item2#foo">With Anchor</a></p>',
            $this->parsedown->text('[With Anchor](/item2#foo)'));

    }

    public function testDirectoryAbsoluteLinksSubDirAbsoluteUrl()
    {
        $this->config->set('system.absolute_urls', true);
        $this->uri->initializeWithUrlAndRootPath('http://localhost/subdir/item2/item-2-2', '/subdir')->init();

        $this->assertSame('<p><a href="http://localhost/subdir">Root</a></p>',
            $this->parsedown->text('[Root](/)'));
        $this->assertSame('<p><a href="http://localhost/subdir/item2/item2-1">Peer Page</a></p>',
            $this->parsedown->text('[Peer Page](/item2/item2-1)'));
        $this->assertSame('<p><a href="http://localhost/subdir/item2/item2-2/item2-2-1">Down a Level</a></p>',
            $this->parsedown->text('[Down a Level](/item2/item2-2/item2-2-1)'));
        $this->assertSame('<p><a href="http://localhost/subdir/item2">Up a Level</a></p>',
            $this->parsedown->text('[Up a Level](/item2)'));
        $this->assertSame('<p><a href="http://localhost/subdir/item2?foo=bar">With Query</a></p>',
            $this->parsedown->text('[With Query](/item2?foo=bar)'));
        $this->assertSame('<p><a href="http://localhost/subdir/item2/foo:bar">With Param</a></p>',
            $this->parsedown->text('[With Param](/item2/foo:bar)'));
        $this->assertSame('<p><a href="http://localhost/subdir/item2#foo">With Anchor</a></p>',
            $this->parsedown->text('[With Anchor](/item2#foo)'));
    }

    public function testSpecialProtocols()
    {
        $this->config->set('system.absolute_urls', false);
        $this->uri->initializeWithURL('http://localhost/item2/item-2-2')->init();

        $this->assertSame('<p><a href="mailto:user@domain.com">mailto</a></p>',
            $this->parsedown->text('[mailto](mailto:user@domain.com)'));
        $this->assertSame('<p><a href="xmpp:xyx@domain.com">xmpp</a></p>',
            $this->parsedown->text('[xmpp](xmpp:xyx@domain.com)'));
        $this->assertSame('<p><a href="tel:123-555-12345">tel</a></p>',
            $this->parsedown->text('[tel](tel:123-555-12345)'));
        $this->assertSame('<p><a href="sms:123-555-12345">sms</a></p>',
            $this->parsedown->text('[sms](sms:123-555-12345)'));
    }

    public function testReferenceLinks()
    {
        $this->config->set('system.absolute_urls', false);
        $this->uri->initializeWithURL('http://localhost/item2/item-2-2')->init();

        $sample = '[relative link][r_relative]
                   [r_relative]: ../item2-3#blah';
        $this->assertSame('<p><a href="/item2/item2-3#blah">relative link</a></p>',
            $this->parsedown->text($sample));

        $sample = '[absolute link][r_absolute]
                   [r_absolute]: /item3#blah';
        $this->assertSame('<p><a href="/item3#blah">absolute link</a></p>',
            $this->parsedown->text($sample));

        $sample = '[external link][r_external]
                   [r_external]: http://www.cnn.com';
        $this->assertSame('<p><a href="http://www.cnn.com">external link</a></p>',
            $this->parsedown->text($sample));
    }

    public function testExternalLinks()
    {
        $this->assertSame('<p><a href="http://www.cnn.com">cnn.com</a></p>',
            $this->parsedown->text('[cnn.com](http://www.cnn.com)'));
        $this->assertSame('<p><a href="https://www.google.com">google.com</a></p>',
            $this->parsedown->text('[google.com](https://www.google.com)'));
    }

    public function testAttributeLinks()
    {
        $this->config->set('system.absolute_urls', false);
        $this->uri->initializeWithURL('http://localhost/item2/item-2-2')->init();

        $this->assertSame('<p><a href="/item2/item2-3" class="button">Relative Class</a></p>',
            $this->parsedown->text('[Relative Class](../item2-3?classes=button)'));
        $this->assertSame('<p><a href="/item2/item2-3" id="unique">Relative ID</a></p>',
            $this->parsedown->text('[Relative ID](../item2-3?id=unique)'));
        $this->assertSame('<p><a href="https://github.com/getgrav/grav" class="button big">External</a></p>',
            $this->parsedown->text('[External](https://github.com/getgrav/grav?classes=button,big)'));
        $this->assertSame('<p><a href="/item2/item2-3?id=unique">Relative Noprocess</a></p>',
            $this->parsedown->text('[Relative Noprocess](../item2-3?id=unique&noprocess)'));
        $this->assertSame('<p><a href="/item2/item2-3" target="_blank">Relative Target</a></p>',
            $this->parsedown->text('[Relative Target](../item2-3?target=_blank)'));
        $this->assertSame('<p><a href="/item2/item2-3" rel="nofollow">Relative Rel</a></p>',
            $this->parsedown->text('[Relative Rel](../item2-3?rel=nofollow)'));
        $this->assertSame('<p><a href="/item2/item2-3?foo=bar&baz=qux" rel="nofollow" class="button">Relative Mixed</a></p>',
            $this->parsedown->text('[Relative Mixed](../item2-3?foo=bar&baz=qux&rel=nofollow&class=button)'));
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

}
