<?php

use Codeception\Util\Fixtures;
use Grav\Framework\Flex\Pages\Traits\PageLegacyTrait;

class FlexPageVaryTest extends \PHPUnit\Framework\TestCase
{
    public function testHeadersDeclareNegotiatedFormats(): void
    {
        $grav = Fixtures::get('grav')();
        $page = new class {
            use PageLegacyTrait;
            public function templateFormat() { return 'html'; }
            public function cacheControl() { return null; }
            public function expires() { return 60; }
            public function lastModified() { return false; }
            public function eTag() { return false; }
        };
        $grav['config']->set('system.pages.markdown_output.enabled', true);
        foreach (['/plain' => true, '/release-2.2' => true, '/plain.md' => false, '/plain.json' => false] as $path => $negotiated) {
            $grav['uri']->initializeWithUrl('http://example.com' . $path)->init();
            foreach ([false, true] as $encoding) {
                $grav['config']->set('system.pages.vary_accept_encoding', $encoding);
                $expected = implode(', ', array_filter([$encoding ? 'Accept-Encoding' : '', $negotiated ? 'Accept' : '']));
                self::assertSame($expected, $page->httpHeaders()['Vary'] ?? '', $path);
            }
        }
        $grav['uri']->initializeWithUrl('http://example.com/plain')->init();
        $grav['config']->set('system.pages.markdown_output.enabled', false);
        self::assertSame('Accept-Encoding', $page->httpHeaders()['Vary']);
    }
}
