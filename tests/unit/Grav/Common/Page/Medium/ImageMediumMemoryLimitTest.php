<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Medium\ImageFile;
use Grav\Common\Page\Medium\ImageMedium;
use Grav\Common\Page\Medium\MediumFactory;

/**
 * A source within max_pixels can still take more of memory_limit than is left
 * (PHP rounds each GD row up, PNG is read into a buffer first), and zoomCrop()
 * builds a canvas that covers its box. Both are served unprocessed instead of
 * running into a fatal error.
 */
class ImageMediumMemoryLimitTest extends \Codeception\Test\Unit
{
    /** @var Grav */
    protected $grav;

    /** @var string */
    protected $dir;

    /** @var string */
    protected $memoryLimit;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $this->dir = sys_get_temp_dir() . '/grav-memory-limit-' . uniqid('', true);
        mkdir($this->dir);
        $this->memoryLimit = (string) ini_get('memory_limit');
    }

    protected function tearDown(): void
    {
        ini_set('memory_limit', $this->memoryLimit);
        $this->measureGd(null);
        $this->grav['config']->set('system.images.max_pixels', 25000000);
        array_map('unlink', glob($this->dir . '/*'));
        rmdir($this->dir);
        parent::tearDown();
    }

    /**
     * Only PHP's bundled GD allocates from memory_limit; this runs the check whatever GD is linked.
     */
    private function measureGd(?bool $measure): void
    {
        (new ReflectionProperty(ImageFile::class, 'gdUsesMemoryLimit'))->setValue(null, $measure);
    }

    /**
     * Leaves this many bytes of memory_limit for the image.
     */
    private function leave(int $bytes): void
    {
        ini_set('memory_limit', (string) (memory_get_usage(true) + ImageFile::MEMORY_MARGIN + $bytes));
    }

    private function medium(string $file): ImageMedium
    {
        $medium = MediumFactory::fromFile($file);
        $this->assertInstanceOf(ImageMedium::class, $medium);

        return $medium;
    }

    private function png(string $name, int $width, int $height): string
    {
        $path = $this->dir . '/' . $name;
        imagepng(imagecreatetruecolor($width, $height), $path);

        return $path;
    }

    public function testEstimateRoundsRowsLikePhp(): void
    {
        // 4100 bytes a row are two pages.
        $this->assertSame((8192 + 8) * 10, ImageFile::canvasBytes(1025, 10));
        $this->assertSame((4096 + 8) * 10, ImageFile::canvasBytes(1024, 10));
        $this->assertSame((500 + 8) * 10, ImageFile::canvasBytes(100, 10));

        $png = $this->png('tall.png', 1025, 100);
        $canvas = ImageFile::canvasBytes(1025, 100);
        $this->assertSame(2 * $canvas, ImageFile::decodeBytes($png));
        $this->assertNull(ImageFile::decodeBytes($this->dir . '/missing.png'));
    }

    public function testSourceThatDoesNotFitIsServedUnprocessed(): void
    {
        $this->measureGd(true);
        $png = $this->png('poster@2x.png', 1025, 1000);

        // About 16 MB to decode and process, 8 MB left.
        $medium = $this->medium($png);
        $this->leave(8 << 20);
        $path = $medium->resize(100, 100)->path(false);
        ini_set('memory_limit', $this->memoryLimit);
        $this->assertSame($png, $path);

        // The same image with room for it.
        $medium = $this->medium($png);
        $this->leave(64 << 20);
        $path = $medium->resize(100, 100)->path(false);
        ini_set('memory_limit', $this->memoryLimit);
        $this->assertNotSame($png, $path);
        $this->assertSame([100, 100], array_slice(getimagesize($path), 0, 2));
    }

    public function testNothingIsMeasuredWithoutMemoryLimit(): void
    {
        $this->measureGd(true);
        ini_set('memory_limit', '-1');
        $this->assertNull(ImageFile::open($this->png('a.png', 10, 10))->memoryLeft());

        $this->measureGd(false);
        ini_set('memory_limit', '512M');
        $this->assertNull(ImageFile::open($this->png('a.png', 10, 10))->memoryLeft());
    }

    public function testZoomCropIsHeldToMaxPixels(): void
    {
        $this->grav['config']->set('system.images.max_pixels', 100000);

        // Covering 80x42 with a 200x1 image takes an 8400x42 canvas.
        $line = $this->png('line.png', 200, 1);
        $this->assertSame($line, $this->medium($line)->zoomCrop(80, 42)->path(false));

        $square = $this->png('square.png', 200, 200);
        $path = $this->medium($square)->zoomCrop(80, 42)->path(false);
        $this->assertNotSame($square, $path);
        $this->assertSame([80, 42], array_slice(getimagesize($path), 0, 2));
    }

    public function testResizeCanvasThatDoesNotFitIsRefused(): void
    {
        $this->measureGd(true);
        $this->grav['config']->set('system.images.max_pixels', 0);

        // The source is tiny, the canvas covering 800x420 is 840000x420.
        $line = $this->png('line.png', 2000, 1);
        $medium = $this->medium($line);
        $this->leave(64 << 20);
        $path = $medium->zoomCrop(800, 420)->path(false);
        ini_set('memory_limit', $this->memoryLimit);
        $this->assertSame($line, $path);
    }

    public function testFrameIsSkippedWhenTheImageDoesNotFit(): void
    {
        $this->measureGd(true);
        $png = $this->png('framed.png', 1025, 1000);

        $medium = $this->medium($png);
        $this->leave(8 << 20);
        $medium->addFrame(10, '0xff0000');
        ini_set('memory_limit', $this->memoryLimit);
        $this->assertSame($png, $medium->path(false));
    }
}
