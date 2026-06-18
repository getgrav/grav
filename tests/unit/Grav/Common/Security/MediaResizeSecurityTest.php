<?php

use Grav\Common\Media\Traits\MediaObjectTrait;
use Grav\Common\Media\Traits\StaticResizeTrait;

/**
 * Class MediaResizeSecurityTest
 *
 * Covers: GHSA-ffmg-hfvg-jhg9 (Markdown image `?resize=W,H` reaches
 * `StaticResizeTrait::resize()`, which wrote the caller-controlled width/height
 * straight into `$styleAttributes` as `width: <value>px`). Because the values
 * never passed through `MediaObjectTrait::style()`, a payload such as
 * `resize=100;position:fixed;…,200` broke out of the `width:` declaration and
 * injected extra CSS into the rendered `<img style="…">` — the same stored CSS
 * injection the style()/attribute() advisories closed for the other sinks.
 * Follow-up to GHSA-pmf8-g7c8-7v54 / GHSA-r7fx-8g49-7hhr.
 *
 * The fix coerces width/height to integers before they enter $styleAttributes,
 * and the serialization sink in parsedownElement() now validates keyed
 * declarations as defense in depth.
 *
 * Naming convention: test{Method}_{GHSA_ID}_{description}
 */
class MediaResizeSecurityTest extends \PHPUnit\Framework\TestCase
{
    private function newMedium(): object
    {
        // Lightweight stand-in: any class using both traits works. resize()
        // writes to the trait's protected $styleAttributes; we read it back
        // through reflection. Stub the abstract methods we don't exercise.
        return new class {
            use MediaObjectTrait;
            use StaticResizeTrait;
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
     * The advisory PoC: `resize=100;position:fixed;…,200`. The non-numeric
     * width must be dropped entirely, never stored as a CSS declaration.
     */
    public function testResize_GHSAffmg_RejectsInjectedWidth(): void
    {
        $m = $this->newMedium();
        $m->resize('100;position:fixed;top:0;left:0;width:100vw;height:100vh;background:white;z-index:9999', '200');

        $styles = $this->styles($m);

        self::assertArrayNotHasKey('width', $styles, 'Injected width must not be stored');
        self::assertSame('200px', $styles['height'] ?? null, 'Numeric height should still apply');

        $serialized = implode(';', array_map(
            static fn($k, $v) => "$k: $v",
            array_keys($styles),
            array_values($styles)
        ));
        self::assertStringNotContainsString('position', $serialized, 'No injected positioning primitive');
        self::assertStringNotContainsString('z-index', $serialized, 'No injected stacking primitive');
    }

    /**
     * @dataProvider providerSafeDimensions
     */
    public function testResize_GHSAffmg_AcceptsNumericDimensions($width, $height, array $expected, string $description): void
    {
        $m = $this->newMedium();
        $m->resize($width, $height);

        self::assertSame($expected, $this->styles($m), $description);
    }

    public static function providerSafeDimensions(): array
    {
        return [
            [300, 200, ['width' => '300px', 'height' => '200px'], 'integer dimensions'],
            ['300', '200', ['width' => '300px', 'height' => '200px'], 'numeric-string dimensions'],
            [300, null, ['width' => '300px'], 'width only'],
            [null, 200, ['height' => '200px'], 'height only'],
            ['300.9', null, ['width' => '300px'], 'float string truncated to int'],
        ];
    }

    /**
     * @dataProvider providerUnsafeDimensions
     */
    public function testResize_GHSAffmg_DropsNonNumeric($width, $height, string $description): void
    {
        $m = $this->newMedium();
        $m->resize($width, $height);

        self::assertSame([], $this->styles($m), $description);
    }

    public static function providerUnsafeDimensions(): array
    {
        return [
            ['abc', 'def', 'non-numeric strings'],
            ['100;position:fixed', '100;color:red', 'declaration-breaking payloads'],
            ['100vw', '100vh', 'CSS length units are not bare integers'],
            ['0', '0', 'zero collapses to unset'],
        ];
    }
}
