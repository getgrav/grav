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
        $this->grav = Fixtures::get('grav');

        $this->pages = $this->grav['pages'];

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];
        $locator->addPath('page', '', 'tests/fake/nested-site/user/pages', false);
        $this->pages->init();

        unset($this->grav['pages']);

        $this->grav['pages'] = $this->pages;

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

    public function testDirectoryRelativeLinks()
    {
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

    public function testMarkdownSpecialProtocols()
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

    public function testMarkdownReferenceLinks()
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

    public function testMarkdownExternalLinks()
    {
        $this->assertSame($this->parsedown->text('[cnn.com](http://www.cnn.com)'),
            '<p><a href="http://www.cnn.com">cnn.com</a></p>');
        $this->assertSame($this->parsedown->text('[google.com](https://www.google.com)'),
            '<p><a href="https://www.google.com">google.com</a></p>');
    }
}
