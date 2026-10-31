<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Medium\ImageMedium;
use Grav\Common\Page\Medium\MediumFactory;

/**
 * system.images.max_pixels guards the decode of the source image. A source whose
 * size getimagesize() cannot read (a format Imagick decodes but PHP cannot
 * measure) used to skip the check; it is now refused like an oversized one.
 */
class ImageMediumMaxPixelsTest extends \Codeception\Test\Unit
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

        $this->dir = sys_get_temp_dir() . '/grav-max-pixels-' . uniqid('', true);
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        $this->grav['config']->set('system.images.max_pixels', 25000000);
        array_map('unlink', glob($this->dir . '/*'));
        rmdir($this->dir);
        parent::tearDown();
    }

    private function medium(string $file): ImageMedium
    {
        $medium = MediumFactory::fromFile($file);
        $this->assertInstanceOf(ImageMedium::class, $medium);

        return $medium;
    }

    private function png(string $path, int $width, int $height): void
    {
        $image = imagecreatetruecolor($width, $height);
        imagepng($image, $path);
    }

    public function testSourceAboveLimitIsServedUnprocessed(): void
    {
        $this->png($this->dir . '/big.png', 100, 100);
        $this->grav['config']->set('system.images.max_pixels', 5000);

        $medium = $this->medium($this->dir . '/big.png');

        $this->assertSame($this->dir . '/big.png', $medium->resize(10, 10)->path(false));
    }

    public function testSourceWithUnreadableSizeIsServedUnprocessed(): void
    {
        // A JPEG XL codestream signature: getimagesize() cannot read it.
        file_put_contents($this->dir . '/unknown.png', "\xFF\x0A" . str_repeat("\0", 64));

        $medium = $this->medium($this->dir . '/unknown.png');

        $this->assertSame($this->dir . '/unknown.png', $medium->resize(10, 10)->path(false));
    }

    public function testSourceWithinLimitIsProcessed(): void
    {
        $this->png($this->dir . '/small.png', 100, 100);

        $medium = $this->medium($this->dir . '/small.png');
        $path = $medium->resize(10, 10)->path(false);

        $this->assertNotSame($this->dir . '/small.png', $path);
        $this->assertSame([10, 10], array_slice(getimagesize($path), 0, 2));
    }
}
