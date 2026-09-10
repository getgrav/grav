<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Page;

/**
 * Flex render hints (#3538): the HTML comment wrapped around every rendered Flex
 * object/collection is opt-in and must never reach a non-HTML response.
 */
class DebuggerFlexRenderHintsTest extends \Codeception\Test\Unit
{
    /** @var Grav */
    protected $grav;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->grav['debugger']->init();
    }

    protected function tearDown(): void
    {
        $this->grav['debugger']->enabled(false);
        $this->grav['config']->set('system.debugger.flex_render_hints', false);
        parent::tearDown();
    }

    public function testOffByDefaultEvenWithDebuggerOn(): void
    {
        $debugger = $this->grav['debugger'];
        $this->assertFalse($debugger->flexRenderHints());

        $debugger->enabled(true);
        $this->assertFalse($debugger->flexRenderHints(), 'the setting has to be turned on explicitly');
    }

    public function testNeedsBothTheDebuggerAndTheSetting(): void
    {
        $debugger = $this->grav['debugger'];
        $this->grav['config']->set('system.debugger.flex_render_hints', true);
        $this->assertFalse($debugger->flexRenderHints(), 'setting alone is not enough');

        $debugger->enabled(true);
        $this->assertTrue($debugger->flexRenderHints());
    }

    public function testOnlyForHtmlPages(): void
    {
        $debugger = $this->grav['debugger'];
        $debugger->enabled(true);
        $this->grav['config']->set('system.debugger.flex_render_hints', true);

        $page = new Page();
        $this->grav['page'] = $page;

        foreach (['rss', 'atom', 'xml', 'json', 'md'] as $format) {
            $page->templateFormat($format);
            $this->assertFalse($debugger->flexRenderHints(), "no hints in a {$format} response");
        }

        $page->templateFormat('html');
        $this->assertTrue($debugger->flexRenderHints());
    }
}
