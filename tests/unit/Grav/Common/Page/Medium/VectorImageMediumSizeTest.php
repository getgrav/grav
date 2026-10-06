<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Medium\VectorImageMedium;

/**
 * Covers how VectorImageMedium reads an SVG's size.
 *
 * getgrav/grav#4347: the viewBox fallback took min-y and width (indexes 1 and
 * 2) instead of width and height (2 and 3), a comma-separated viewBox was not
 * accepted, and a relative `width="100%"` was cast to 100 pixels. The size now
 * comes from `width`/`height` only when both are plain pixel lengths, and from
 * the viewBox otherwise.
 */
class VectorImageMediumSizeTest extends \Codeception\Test\Unit
{
    /** @var Grav */
    protected $grav;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
    }

    private function medium(string $name): VectorImageMedium
    {
        $filepath = GRAV_ROOT . '/tests/fake/svg-size/' . $name . '.svg';
        $this->assertFileExists($filepath);

        return new VectorImageMedium([
            'type' => 'vector',
            'mime' => 'image/svg+xml',
            'filepath' => $filepath,
            'filename' => $name . '.svg',
            'basename' => $name,
            'extension' => 'svg',
            'path' => dirname($filepath),
        ]);
    }

    /**
     * @dataProvider sizeProvider
     */
    public function testReadsTheSize(string $fixture, ?int $width, ?int $height): void
    {
        $medium = $this->medium($fixture);

        $this->assertSame($width, $medium->get('width'));
        $this->assertSame($height, $medium->get('height'));
    }

    public function sizeProvider(): array
    {
        return [
            // viewBox fallback: width and height are the 3rd and 4th numbers.
            'viewBox only' => ['viewbox-only', 120, 40],
            'viewBox with a non-zero min-y' => ['viewbox-offset', 200, 100],
            'viewBox separated by commas' => ['viewbox-comma', 120, 40],
            'viewBox with mixed separators and padding' => ['viewbox-mixed-separators', 120, 40],
            'viewBox with decimals' => ['viewbox-decimals', 24, 16],
            'viewBox that is not numeric' => ['viewbox-malformed', null, null],
            'viewBox with no area' => ['viewbox-zero-size', null, null],

            // width/height attributes win when both are plain pixel lengths.
            'plain width and height beat the viewBox' => ['attrs-plain', 300, 150],
            'px width and height' => ['attrs-px', 300, 150],

            // Anything else is not a pixel size, so the viewBox is used instead.
            'percent width and height with a viewBox' => ['attrs-percent-with-viewbox', 120, 40],
            'percent width and height without a viewBox' => ['attrs-percent-no-viewbox', null, null],
            'em width and height with a viewBox' => ['attrs-em-with-viewbox', 120, 40],
            'percent width with a pixel height' => ['attrs-mixed-percent-width', 120, 40],
            'physical units with a viewBox' => ['attrs-physical-units', 400, 200],

            'no size information at all' => ['no-size', null, null],
        ];
    }

    public function testSizeFromItemsIsNotOverridden(): void
    {
        $filepath = GRAV_ROOT . '/tests/fake/svg-size/viewbox-only.svg';

        $medium = new VectorImageMedium([
            'type' => 'vector',
            'mime' => 'image/svg+xml',
            'filepath' => $filepath,
            'filename' => 'viewbox-only.svg',
            'basename' => 'viewbox-only',
            'extension' => 'svg',
            'path' => dirname($filepath),
            'width' => 64,
            'height' => 32,
        ]);

        $this->assertSame(64, $medium->get('width'));
        $this->assertSame(32, $medium->get('height'));
    }
}
