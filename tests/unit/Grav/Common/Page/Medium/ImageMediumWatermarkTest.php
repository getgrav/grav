<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Medium\ImageMedium;
use Grav\Common\Page\Medium\MediumFactory;

/**
 * getgrav/grav#4322: the watermark was sized and placed from the image's size
 * before any resize, so `resize(...)->watermark()` stamped it in the wrong
 * place or off the image, the position config was never read, and each
 * derivative got the full-size image's coordinates.
 */
class ImageMediumWatermarkTest extends \Codeception\Test\Unit
{
    /** @var Grav */
    protected $grav;

    /** @var string */
    protected $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        // A white 1000x600 source and a solid red 100x50 stamp.
        $this->dir = sys_get_temp_dir() . '/grav-watermark-' . uniqid('', true);
        mkdir($this->dir);
        $this->png($this->dir . '/source.png', 1000, 600, [255, 255, 255]);
        $this->png($this->dir . '/stamp.png', 100, 50, [255, 0, 0]);

        $config = $this->grav['config'];
        $config->set('system.images.watermark.image', $this->dir . '/stamp.png');
        $config->set('system.images.watermark.scale', 33);
        $config->set('system.images.watermark.position_x', 'center');
        $config->set('system.images.watermark.position_y', 'center');
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*'));
        rmdir($this->dir);
        parent::tearDown();
    }

    private function png(string $path, int $width, int $height, array $rgb): void
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, ...$rgb));
        imagepng($image, $path);
    }

    private function medium(): ImageMedium
    {
        $medium = MediumFactory::fromFile($this->dir . '/source.png');
        $this->assertInstanceOf(ImageMedium::class, $medium);

        return $medium;
    }

    /**
     * The canvas size and the box around the red stamp in a processed image.
     */
    private function stamp(ImageMedium $medium): array
    {
        $image = imagecreatefromstring(file_get_contents($medium->path(false)));
        $width = imagesx($image);
        $height = imagesy($image);
        $box = [PHP_INT_MAX, PHP_INT_MAX, -1, -1];

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $color = imagecolorat($image, $x, $y);
                if (($color >> 16 & 255) > 200 && ($color >> 8 & 255) < 80 && ($color & 255) < 80) {
                    $box = [min($box[0], $x), min($box[1], $y), max($box[2], $x), max($box[3], $y)];
                }
            }
        }

        return [$width, $height, $box];
    }

    public function testWatermarkIsCentredOnTheImage(): void
    {
        $this->assertSame([1000, 600, [450, 275, 549, 324]], $this->stamp($this->medium()->watermark()));
    }

    public function testWatermarkFollowsAResizeBeforeIt(): void
    {
        $this->assertSame([500, 300, [200, 125, 299, 174]], $this->stamp($this->medium()->resize(500, 300)->watermark()));
    }

    public function testPositionComesFromConfig(): void
    {
        $this->grav['config']->set('system.images.watermark.position_x', 'right');
        $this->grav['config']->set('system.images.watermark.position_y', 'bottom');

        $this->assertSame([1000, 600, [900, 550, 999, 599]], $this->stamp($this->medium()->watermark()));
        $this->assertSame([1000, 600, [900, 0, 999, 49]], $this->stamp($this->medium()->watermark('1', 'top')));
    }

    public function testBareWatermarkAndUnknownPositionUseTheDefaults(): void
    {
        $this->assertSame([1000, 600, [450, 275, 549, 324]], $this->stamp($this->medium()->watermark('')));
        $this->assertSame([1000, 600, [450, 275, 549, 324]], $this->stamp($this->medium()->watermark('1', 'nowhere')));
    }

    public function testEachDerivativeIsStampedAtItsOwnSize(): void
    {
        foreach ([$this->medium()->derivatives([400])->watermark(), $this->medium()->watermark()->derivatives([400])] as $medium) {
            $alternatives = $medium->getAlternatives();
            [$width, $height, $box] = $this->stamp($alternatives[400]);

            $this->assertSame([400, 240], [$width, $height]);
            $this->assertSame([150, 95, 249, 144], $box);
        }
    }
}
