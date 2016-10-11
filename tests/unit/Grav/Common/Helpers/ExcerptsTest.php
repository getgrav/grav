<?php

use Codeception\Util\Fixtures;
use Grav\Common\Helpers\Excerpts;
use Grav\Common\Grav;
use Grav\Common\Uri;
use Grav\Common\Config\Config;
use Grav\Common\Page\Pages;
use Grav\Common\Language\Language;

/**
 * Class ExcerptsTest
 */
class ExcerptsTest extends \Codeception\TestCase\Test
{
    /** @var Parsedown $parsedown */
    protected $parsedown;

    /** @var Grav $grav */
    protected $grav;

    /** @var Page $page */
    protected $page;

    /** @var Pages $pages */
    protected $pages;

    /** @var Config $config */
    protected $config;

    /** @var  Uri $uri */
    protected $uri;

    /** @var  Language $language */
    protected $language;

    protected $old_home;

    protected function _before()
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
            'extra'            => false,
            'auto_line_breaks' => false,
            'auto_url_links'   => false,
            'escape_markup'    => false,
            'special_chars'    => ['>' => 'gt', '<' => 'lt'],
        ];
        $this->page = $this->pages->dispatch('/item2/item2-2');
        $this->uri->initializeWithURL('http://testing.dev/item2/item2-2')->init();
    }

    protected function _after()
    {
        $this->config->set('system.home.alias', $this->old_home);
    }


    public function testProcessImageHtml()
    {
        $this->assertRegexp('|<img alt="Sample Image" src="\/images\/.*-sample-image.jpe?g\" data-src="sample-image\.jpg\?cropZoom=300,300" \/>|',
            Excerpts::processImageHtml('<img src="sample-image.jpg?cropZoom=300,300" alt="Sample Image" />', $this->page));
        $this->assertRegexp('|<img alt="Sample Image" class="foo" src="\/images\/.*-sample-image.jpe?g\" data-src="sample-image\.jpg\?classes=foo" \/>|',
            Excerpts::processImageHtml('<img src="sample-image.jpg?classes=foo" alt="Sample Image" />', $this->page));
    }

}
