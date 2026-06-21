<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Media\Traits\MediaObjectTrait;

/**
 * Class MediaAttributeSecurityTest
 *
 * Covers: GHSA-r7fx-8g49-7hhr (Markdown image `?attribute=NAME,VALUE` reaches
 * `MediaObjectTrait::attribute()` which let an editor set arbitrary HTML
 * attribute names — including event handlers — on the rendered <img>).
 *
 * The fix gates the attribute name through an allowlist regex and a small
 * denylist of script-context names. Safe `data-*`/`aria-*`/typical media
 * attributes still pass.
 *
 * Naming convention: test{Method}_{GHSA_ID}_{description}
 */
class MediaAttributeSecurityTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
    }

    private function newMedium(): object
    {
        // Lightweight stand-in: any class that uses MediaObjectTrait works.
        // attribute() reads/writes the trait's own protected $attributes; we
        // expose it through reflection in the assertions. The trait declares
        // a handful of abstract methods we don't exercise — stub them.
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

    private function attrs(object $medium): array
    {
        $r = new ReflectionClass($medium);
        $p = $r->getProperty('attributes');
        $p->setAccessible(true);
        return (array) $p->getValue($medium);
    }

    /**
     * @dataProvider providerGHSAr7fx_DangerousAttributeNames
     */
    public function testAttribute_GHSAr7fx_RejectsDangerousAttributeNames(string $name, string $description): void
    {
        $m = $this->newMedium();
        $m->attribute($name, 'value-that-must-not-stick');

        self::assertArrayNotHasKey($name, $this->attrs($m), "Should not store dangerous attribute: $description");
        self::assertArrayNotHasKey(strtolower($name), $this->attrs($m), "case-insensitive: $description");
    }

    public static function providerGHSAr7fx_DangerousAttributeNames(): array
    {
        return [
            ['onerror', 'GHSA-r7fx PoC: onerror handler'],
            ['onload', 'onload handler'],
            ['onclick', 'onclick handler'],
            ['ONERROR', 'uppercase event handler'],
            ['OnMouseOver', 'mixed-case event handler'],
            ['style', 'inline style (CSS expression risk)'],
            ['xmlns', 'XML namespace'],
            ['srcdoc', 'iframe srcdoc'],
            ['formaction', 'form action override'],
            // Malformed names — must be rejected even before the denylist hits.
            ['bad name', 'whitespace in attribute name'],
            ['bad>tag', 'attribute name with `>` (tag-break attempt)'],
            ['"onerror', 'attribute name with leading quote'],
            ['', 'empty name (already handled by empty() check)'],
            ['1foo', 'attribute name not letter-led'],
        ];
    }

    /**
     * @dataProvider providerGHSAr7fx_SafeAttributeNames
     */
    public function testAttribute_GHSAr7fx_AcceptsSafeAttributeNames(string $name, string $description): void
    {
        $m = $this->newMedium();
        $m->attribute($name, 'safe-value');

        self::assertArrayHasKey($name, $this->attrs($m), "Should accept safe attribute: $description");
        self::assertSame('safe-value', $this->attrs($m)[$name], "value should round-trip: $description");
    }

    public static function providerGHSAr7fx_SafeAttributeNames(): array
    {
        return [
            ['alt', 'alt'],
            ['title', 'title'],
            ['class', 'class'],
            ['id', 'id'],
            ['loading', 'loading'],
            ['decoding', 'decoding'],
            ['width', 'width'],
            ['height', 'height'],
            ['data-foo', 'data-* attribute (common theme use)'],
            ['data-image-id', 'data-* with hyphens'],
            ['aria-label', 'aria-* attribute'],
            ['rel', 'rel'],
            // src/href intentionally allowed — themes legitimately call
            // $image->attribute('src', $signed_url) from PHP.
            ['src', 'src (themes override URLs)'],
            ['href', 'href (link wrappers)'],
        ];
    }
}
