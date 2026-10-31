<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Medium\ImageMedium;
use Grav\Common\Page\Medium\MediumFactory;


class ImageMediumUrlActionMaxPixelsTest extends \Codeception\Test\Unit
{

    private const MAX = 250000;

    /** @var Grav */
    protected $grav;

    /** @var string */
    protected $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $this->dir = sys_get_temp_dir() . '/grav-max-pixels-' . uniqid('', true);
        mkdir($this->dir);
        imagepng(imagecreatetruecolor(100, 10), $this->dir . '/banner.png');
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*'));
        rmdir($this->dir);
        parent::tearDown();
    }


    private function produced(array $actions, string $file = 'banner.png'): array
    {
        $medium = MediumFactory::fromFile($this->dir . '/' . $file);
        $this->assertInstanceOf(ImageMedium::class, $medium);
        foreach ($actions as $action => $args) {
            $medium->{$action}(...$args);
        }

        return array_slice(getimagesize($medium->maxPixels(self::MAX)->path()), 0, 2);
    }

    public function testAResizeAfterARotateIsMeasuredAgainstTheTurnedImage(): void
    {
        $this->assertSame([158, 1580], $this->produced(['rotate' => ['90'], 'forceResize' => ['158']]));

        $this->expectException(RangeException::class);
        $this->produced(['rotate' => ['90'], 'forceResize' => ['160']]);
    }

    public function testAResizeIsMeasuredAgainstTheExifOrientedImage(): void
    {
        if (!extension_loaded('exif')) {
            $this->markTestSkipped('Needs the exif extension');
        }
        $this->grav['config']->set('system.images.auto_fix_orientation', true);

        // The banner as a JPEG with orientation 6, a quarter turn.
        ob_start();
        imagejpeg(imagecreatetruecolor(100, 10));
        $jpeg = ob_get_clean();
        $exif = "Exif\0\0II*\0" . pack('VvvvVvvV', 8, 1, 0x0112, 3, 1, 6, 0, 0);
        file_put_contents($this->dir . '/turned.jpg', substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif . substr($jpeg, 2));

        $this->assertSame([158, 1580], $this->produced(['forceResize' => ['158']], 'turned.jpg'));

        $this->expectException(RangeException::class);
        $this->produced(['forceResize' => ['160']], 'turned.jpg');
    }

    /**
     * @dataProvider providerActionsThatDoNotEnlarge
     */
    public function testAResizeIsMeasuredAgainstAnImageThatWasNotEnlarged(string $action): void
    {
        $this->assertSame([1500, 150], $this->produced([$action => ['25000', '10'], 'forceResize' => ['1500']]));

        $this->expectException(RangeException::class);
        $this->produced([$action => ['25000', '10'], 'forceResize' => ['1600']]);
    }

    public static function providerActionsThatDoNotEnlarge(): array
    {
        return [['resize'], ['cropResize']];
    }

    public function testGaussianBlurIsHeldToTheCeiling(): void
    {
        $this->assertSame([100, 10], $this->produced(['gaussianBlur' => ['9']]));

        $this->expectException(RangeException::class);
        $this->produced(['gaussianBlur' => ['11']]);
    }

    public function testRotateIsHeldToTheCeiling(): void
    {
        $this->assertSame([500, 500], $this->produced(['forceResize' => ['500', '500'], 'rotate' => ['90']]));

        $this->expectException(RangeException::class);
        $this->produced(['forceResize' => ['500', '500'], 'rotate' => ['45']]);
    }

    public function testNoCeilingUnlessAsked(): void
    {
        $medium = MediumFactory::fromFile($this->dir . '/banner.png');

        $this->assertSame([160, 1600], array_slice(getimagesize($medium->rotate(90)->forceResize(160)->path()), 0, 2));
    }

    public function testRotateIsMeasuredAsTheBoxAroundTheTurnedImage(): void
    {
        self::assertSame([100000.0, 100.0, 1000.0], ImageMedium::urlActionCanvas('rotate', ['90'], 1000, 100));
        self::assertSame([100000.0, 1000.0, 100.0], ImageMedium::urlActionCanvas('rotate', ['-180'], 1000, 100));
        self::assertSame([7072.0 * 7072.0, 7072.0, 7072.0], ImageMedium::urlActionCanvas('rotate', ['45'], 5000, 5000));

        // A quarter turn swaps the sides a one-dimension resize derives from.
        [, $w, $h] = ImageMedium::urlActionCanvas('rotate', ['90'], 1000, 100);
        [$pixels] = ImageMedium::urlActionCanvas('forceResize', ['1582'], (int) $w, (int) $h);
        self::assertSame(1582.0 * 15820.0, $pixels);

        self::assertNull(ImageMedium::urlActionCanvas('rotate', ['x'], 100, 100));
        self::assertNull(ImageMedium::urlActionCanvas('rotate', [''], 100, 100));
        self::assertNull(ImageMedium::urlActionCanvas('rotate', ['1e999'], 100, 100));
    }

    public function testGaussianBlurIsMeasuredAtItsLargestStep(): void
    {
        self::assertSame([500.0 * 52.0, 1000.0, 100.0], ImageMedium::urlActionCanvas('gaussianBlur', ['3'], 1000, 100));
        self::assertSame(4096.0 * 4096.0, ImageMedium::urlActionCanvas('gaussianBlur', ['13'], 1000, 100)[0]);
        self::assertSame(8192.0 * 8192.0, ImageMedium::urlActionCanvas('gaussianBlur', ['14'], 1000, 100)[0]);
        self::assertGreaterThan(1e30, ImageMedium::urlActionCanvas('gaussianBlur', ['99999999999999999999'], 1000, 100)[0]);

        // One step when no argument is given, as in the GD adapter.
        self::assertSame([500.0 * 50.0, 1000.0, 100.0], ImageMedium::urlActionCanvas('gaussianBlur', [], 1000, 100));

        self::assertNull(ImageMedium::urlActionCanvas('gaussianBlur', ['0'], 100, 100));
        self::assertNull(ImageMedium::urlActionCanvas('gaussianBlur', ['-1'], 100, 100));
        self::assertNull(ImageMedium::urlActionCanvas('gaussianBlur', ['1.5'], 100, 100));
        self::assertNull(ImageMedium::urlActionCanvas('gaussianBlur', [''], 100, 100));
    }

    public function testResizesAreMeasuredAsTheirCanvasAndTheRestAsTheImage(): void
    {
        self::assertSame([46000.0 * 46000.0, 46000.0, 46000.0], ImageMedium::urlActionCanvas('forceResize', ['46000'], 100, 100));
        self::assertNull(ImageMedium::urlActionCanvas('forceResize', ['x'], 100, 100));
        self::assertSame([100000.0, 1000.0, 100.0], ImageMedium::urlActionCanvas('sepia', [''], 1000, 100));
    }

    public function testAPercentageIsMeasuredRoundedUp(): void
    {
        // 5916.07x4225.77 is under 25 million pixels, the 5916x4226 it is rounded to is not.
        self::assertGreaterThan(25000000, ImageMedium::urlActionCanvas('forceResize', ['84515.3%'], 7, 5)[0]);
    }
}
