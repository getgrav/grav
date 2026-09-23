<?php

use Codeception\Util\Fixtures;
use Grav\Common\Assets\Pipeline;

/**
 * Regression for https://github.com/getgrav/grav/issues/2784 -- the CSS
 * pipeline's url() rewriter treated a same-document SVG fragment reference
 * like `url(#linear-gradient)` as a relative file path and mangled it.
 */
class PipelineCssRewriteTest extends \Codeception\Test\Unit
{
    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $grav();
    }

    private function cssRewrite(string $css, string $dir, bool $local): string
    {
        $pipeline = new Pipeline([]);
        $method = new \ReflectionMethod($pipeline, 'cssRewrite');
        $method->setAccessible(true);

        return $method->invoke($pipeline, $css, $dir, $local);
    }

    public function testSvgFragmentUrlIsLeftUntouched(): void
    {
        $css = 'svg { fill: url(#linear-gradient); }';

        $this->assertSame($css, $this->cssRewrite($css, 'assets/css', true));
    }

    public function testRelativeFileUrlIsStillRewritten(): void
    {
        $css = '.bg { background: url(images/bg.png); }';

        $result = $this->cssRewrite($css, 'assets/css', true);

        $this->assertStringNotContainsString('url(images/bg.png)', $result);
        $this->assertStringContainsString('assets/css/images/bg.png', $result);
    }

    public function testUrlsThatAreNotFilePathsAreLeftUntouched(): void
    {
        foreach ([
            'url("#linear-gradient")',
            "url('#linear-gradient')",
            'url( #linear-gradient )',
            'url(about:blank)',
            'url(blob:https://example.com/0f3a)',
            'url(mailto:someone@example.com)',
            "url('data:image/svg+xml;utf8,<svg/>')",
            'url()',
        ] as $url) {
            $css = "a { background: $url; }";

            $this->assertSame($css, $this->cssRewrite($css, 'assets/css', true), $url);
        }
    }

    public function testRelativeFileUrlWithSpacesOrUppercaseIsRewritten(): void
    {
        foreach (['url( images/bg.png )', 'URL(images/bg.png)', 'url( "images/bg.png" )'] as $url) {
            $result = $this->cssRewrite("a { background: $url; }", 'assets/css', true);

            $this->assertStringContainsString('assets/css/images/bg.png', $result, $url);
            $this->assertStringNotContainsString('css/ images', $result, $url);
        }
    }
}
