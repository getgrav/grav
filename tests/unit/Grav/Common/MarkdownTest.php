<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
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

    protected function _before()
    {
        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $this->pages = $this->grav['pages'];

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $locator->addPath('page', '', 'tests/fake/nested-site/user/pages', false);
        $this->pages->init();

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

    /**
     * @param $string
     *
     * @return mixed
     */
    public function stripLeadingWhitespace($string)
    {
        return preg_replace('/^\s*(.*)/', '', $string);
    }

    public function testAnchorLinks()
    {
        $this->assertSame($this->parsedown->text('[Peer Anchor](../item2-1#foo)'),
            '<p><a href="/item2/item2-1#foo">Peer Anchor</a></p>');
        $this->assertSame($this->parsedown->text('[Peer Anchor 2](../item2-1/#foo)'),
            '<p><a href="/item2/item2-1/#foo">Peer Anchor 2</a></p>');
//        $this->assertSame($this->parsedown->text('[Current Anchor](#foo)'),
//            '<p><a href="#foo">Current Anchor</a></p>');
        $this->assertSame($this->parsedown->text('[Root Anchor](/#foo)'),
            '<p><a href="/#foo">Root Anchor</a></p>');

    }

    public function testSlugRelativeLinks()
    {
        $this->assertSame($this->parsedown->text('[Peer Page](../item2-1)'),
            '<p><a href="/item2/item2-1">Peer Page</a></p>');
        $this->assertSame($this->parsedown->text('[Down a Level](item2-2-1)'),
            '<p><a href="/item2/item2-2/item2-2-1">Down a Level</a></p>');
        $this->assertSame($this->parsedown->text('[Up a Level](..)'),
            '<p><a href="/item2">Up a Level</a></p>');
        $this->assertSame($this->parsedown->text('[Up and Down](../../item3/item3-3)'),
            '<p><a href="/item3/item3-3">Up and Down</a></p>');
        $this->assertSame($this->parsedown->text('[Down a Level with Query](item2-2-1?foo=bar)'),
            '<p><a href="/item2/item2-2/item2-2-1?foo=bar">Down a Level with Query</a></p>');
//        $this->assertSame($this->parsedown->text('[Up a Level with Query](../?foo=bar)'),
//            '<p><a href="/item2?foo=bar">Up a Level with Query</a></p>');
        $this->assertSame($this->parsedown->text('[Up and Down with Query](../../item3/item3-3?foo=bar)'),
        '<p><a href="/item3/item3-3?foo=bar">Up and Down with Query</a></p>');
        $this->assertSame($this->parsedown->text('[Up and Down with Param](../../item3/item3-3/foo:bar)'),
            '<p><a href="/item3/item3-3/foo:bar">Up and Down with Param</a></p>');
        $this->assertSame($this->parsedown->text('[Up and Down with Anchor](../../item3/item3-3#foo)'),
            '<p><a href="/item3/item3-3#foo">Up and Down with Anchor</a></p>');
    }

    public function testDirectoryRelativeLinks()
    {
        $this->assertSame($this->parsedown->text('[Peer Page](../01.item2-1)'),
            '<p><a href="/item2/item2-1">Peer Page</a></p>');
        $this->assertSame($this->parsedown->text('[Down a Level](01.item2-2-1)'),
            '<p><a href="/item2/item2-2/item2-2-1">Down a Level</a></p>');
        $this->assertSame($this->parsedown->text('[Up and Down](../../03.item3/03.item3-3)'),
            '<p><a href="/item3/item3-3">Up and Down</a></p>');
        $this->assertSame($this->parsedown->text('[Down a Level with Query](01.item2-2-1?foo=bar)'),
            '<p><a href="/item2/item2-2/item2-2-1?foo=bar">Down a Level with Query</a></p>');
        $this->assertSame($this->parsedown->text('[Up and Down with Query](../../03.item3/03.item3-3?foo=bar)'),
            '<p><a href="/item3/item3-3?foo=bar">Up and Down with Query</a></p>');
//        $this->assertSame($this->parsedown->text('[Up and Down with Param](../../03.item3/03.item3-3/foo:bar)'),
//            '<p><a href="/item3/item3-3/foo:bar">Up and Down with Param</a></p>');
        $this->assertSame($this->parsedown->text('[Up and Down with Anchor](../../03.item3/03.item3-3#foo)'),
            '<p><a href="/item3/item3-3#foo">Up and Down with Anchor</a></p>');
    }

    public function testDirectoryAbsoluteLinks()
    {
        $this->assertSame($this->parsedown->text('[Peer Page](/item2/item2-1)'),
            '<p><a href="/item2/item2-1">Peer Page</a></p>');
        $this->assertSame($this->parsedown->text('[Down a Level](/item2/item2-2/item2-2-1)'),
            '<p><a href="/item2/item2-2/item2-2-1">Down a Level</a></p>');
        $this->assertSame($this->parsedown->text('[Up a Level](/item2)'),
            '<p><a href="/item2">Up a Level</a></p>');
        $this->assertSame($this->parsedown->text('[With Query](/item2?foo=bar)'),
            '<p><a href="/item2?foo=bar">With Query</a></p>');
        $this->assertSame($this->parsedown->text('[With Param](/item2/foo:bar)'),
            '<p><a href="/item2/foo:bar">With Param</a></p>');
        $this->assertSame($this->parsedown->text('[With Anchor](/item2#foo)'),
            '<p><a href="/item2#foo">With Anchor</a></p>');
    }

    public function testSpecialProtocols()
    {
        $this->assertSame($this->parsedown->text('[mailto](mailto:user@domain.com)'),
            '<p><a href="mailto:user@domain.com">mailto</a></p>');
        $this->assertSame($this->parsedown->text('[xmpp](xmpp:xyx@domain.com)'),
            '<p><a href="xmpp:xyx@domain.com">xmpp</a></p>');
        $this->assertSame($this->parsedown->text('[tel](tel:123-555-12345)'),
            '<p><a href="tel:123-555-12345">tel</a></p>');
        $this->assertSame($this->parsedown->text('[sms](sms:123-555-12345)'),
            '<p><a href="sms:123-555-12345">sms</a></p>');
    }

    public function testReferenceLinks()
    {
        $sample = '[relative link][r_relative]
                   [r_relative]: ../item2-3#blah';
        $this->assertSame($this->parsedown->text($sample), '<p><a href="/item2/item2-3#blah">relative link</a></p>');

        $sample = '[absolute link][r_absolute]
                   [r_absolute]: /item3#blah';
        $this->assertSame($this->parsedown->text($sample),
            '<p><a href="/item3#blah">absolute link</a></p>');

        $sample = '[external link][r_external]
                   [r_external]: http://www.cnn.com';
        $this->assertSame($this->parsedown->text($sample), '<p><a href="http://www.cnn.com">external link</a></p>');
    }

    public function testExternalLinks()
    {
        $this->assertSame($this->parsedown->text('[cnn.com](http://www.cnn.com)'),
            '<p><a href="http://www.cnn.com">cnn.com</a></p>');
        $this->assertSame($this->parsedown->text('[google.com](https://www.google.com)'),
            '<p><a href="https://www.google.com">google.com</a></p>');
    }
}
