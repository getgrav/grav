<?php

use Grav\Common\Page\Medium\ImageMedium;

/**
 * Covers ImageMedium::urlResizeCanvas(), which Grav::fallbackUrl() uses to hold
 * URL image actions (`image.jpg?forceResize=...`) to system.images.max_pixels.
 *
 * The ceiling used to multiply the two query numbers and skip the check when
 * either was missing, so `forceResize=46000` (one dimension, derived square),
 * `forceResize=46000%` and `zoomCrop=46000,1` (enlarges to cover before it
 * crops) all reached a 46000x46000 GD canvas on a 100x100 source.
 */
class ImageMediumUrlResizeCanvasTest extends \Codeception\Test\Unit
{
    private const MAX = 25000000;

    public function testSingleDimensionForceResizeIsMeasuredAsTheDerivedCanvas(): void
    {
        [$pixels, $w, $h] = ImageMedium::urlResizeCanvas('forceResize', ['46000'], 100, 100);
        self::assertSame([46000.0, 46000.0], [$w, $h]);
        self::assertGreaterThan(self::MAX, $pixels);

        [$pixels] = ImageMedium::urlResizeCanvas('forceResize', ['46000'], 1600, 900);
        self::assertGreaterThan(self::MAX, $pixels);

        [$pixels, $w, $h] = ImageMedium::urlResizeCanvas('forceResize', ['0', '46000'], 100, 100);
        self::assertSame([46000.0, 46000.0], [$w, $h]);
        self::assertGreaterThan(self::MAX, $pixels);
    }

    public function testPercentageIsMeasuredAgainstTheSource(): void
    {
        [$pixels, $w, $h] = ImageMedium::urlResizeCanvas('forceResize', ['46000%'], 100, 100);
        self::assertSame([46000.0, 46000.0], [$w, $h]);
        self::assertGreaterThan(self::MAX, $pixels);

        [$pixels] = ImageMedium::urlResizeCanvas('resize', ['50%'], 1600, 900);
        self::assertSame(800.0 * 450.0, $pixels);
    }

    public function testZoomCropIsMeasuredAtItsCoverCanvas(): void
    {
        // 46000x1 is only 46000 pixels, but zoomCrop() first enlarges a square
        // source to 46000x46000 to cover the box.
        [$pixels, $w, $h] = ImageMedium::urlResizeCanvas('zoomCrop', ['46000', '1'], 100, 100);
        self::assertGreaterThan(self::MAX, $pixels);
        self::assertSame([46000.0, 1.0], [$w, $h]);

        [$pixels] = ImageMedium::urlResizeCanvas('zoomCrop', ['1', '46000'], 1600, 900);
        self::assertGreaterThan(self::MAX, $pixels);
    }

    public function testNonNumericDimensionsAreRefused(): void
    {
        self::assertNull(ImageMedium::urlResizeCanvas('forceResize', ['-46000'], 100, 100));
        self::assertNull(ImageMedium::urlResizeCanvas('forceResize', ['', '46000'], 100, 100));
        self::assertNull(ImageMedium::urlResizeCanvas('forceResize', ['4.6e4'], 100, 100));
        self::assertNull(ImageMedium::urlResizeCanvas('forceResize', ['46000', 'x'], 100, 100));
        self::assertNull(ImageMedium::urlResizeCanvas('crop', ['0', '0', '46000abc', '1'], 100, 100));
    }

    public function testUnknownSourceSizeIsRefused(): void
    {
        self::assertNull(ImageMedium::urlResizeCanvas('resize', ['600', '400'], 0, 0));
    }

    public function testOrdinaryRequestsStayUnderTheCeiling(): void
    {
        self::assertSame([240000.0, 600.0, 400.0], ImageMedium::urlResizeCanvas('resize', ['600', '400'], 1600, 900));
        self::assertSame([180000.0, 600.0, 300.0], ImageMedium::urlResizeCanvas('cropResize', ['600', '300'], 1600, 900));
        self::assertSame([225000.0, 300.0, 750.0], ImageMedium::urlResizeCanvas('crop', ['10', '10', '300', '750'], 1600, 900));
        self::assertSame([25000000.0, 5000.0, 5000.0], ImageMedium::urlResizeCanvas('forceResize', ['5000', '5000'], 100, 100));

        // One dimension keeps the aspect ratio: 600 wide on 1600x900 is 600x338.
        self::assertSame([202800.0, 600.0, 338.0], ImageMedium::urlResizeCanvas('resize', ['600'], 1600, 900));

        // A 300x300 zoomCrop of 1600x900 covers at 533x300 before cropping.
        [$pixels] = ImageMedium::urlResizeCanvas('zoomCrop', ['300', '300'], 1600, 900);
        self::assertLessThan(200000, $pixels);
    }
}
