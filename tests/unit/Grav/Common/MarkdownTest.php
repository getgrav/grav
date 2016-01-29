<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;

/**
 * Class AssetsTest
 */
class MarkdownTest extends \Codeception\TestCase\Test
{
    /** @var Parsedown $parsedown */
    protected $parsedown;

    /** @var Grav $grav */
    protected $grav;

    protected function _before()
    {
        $this->grav = Fixtures::get('grav');

        $defaults = [
            'extra'            => false,
            'auto_line_breaks' => false,
            'auto_url_links'   => false,
            'escape_markup'    => false,
            'special_chars'    => ['>' => 'gt', '<' => 'lt'],
        ];
        $page = new \Grav\Common\Page\Page();

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
                   [r_relative]: ../03.assets#blah';
        $this->assertSame($this->parsedown->text($sample), '<p><a href="../03.assets#blah">relative link</a></p>');

        $sample = '[absolute link][r_absolute]
                   [r_absolute]: /blog/focus-and-blur#blah';
        $this->assertSame($this->parsedown->text($sample),
            '<p><a href="/blog/focus-and-blur#blah">absolute link</a></p>');

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
