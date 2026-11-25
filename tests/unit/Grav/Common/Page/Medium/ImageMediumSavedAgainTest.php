<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Page\Media;
use Grav\Common\Page\Medium\ImageMedium;
use Grav\Common\Page\Medium\MediumFactory;

/**
 * `url(false)` and `path(false)` save an image and leave it open, so it can be
 * given more actions and saved again. The cached file was named once, on the
 * first save, and the second save went to that file: found it older than a
 * watermark queued since, removed it and wrote another picture there. Every
 * page showing the first image then showed the second.
 */
class ImageMediumSavedAgainTest extends \Codeception\Test\Unit
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

        // A blue 800x200 source in both formats, its retina file and a solid red stamp.
        $this->dir = sys_get_temp_dir() . '/grav-saved-again-' . uniqid('', true);
        mkdir($this->dir);
        $this->image('source.png', 800, 200, [0, 0, 255]);
        $this->image('source.jpg', 800, 200, [0, 0, 255]);
        $this->image('source@2x.png', 1600, 400, [0, 0, 255]);
        $this->image('stamp.png', 800, 200, [255, 0, 0]);

        $config = $this->grav['config'];
        $config->set('system.images.watermark.image', $this->dir . '/stamp.png');
        $config->set('system.images.watermark.scale', 100);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*'));
        rmdir($this->dir);
        parent::tearDown();
    }

    /**
     * Written a minute ago: a cached file is kept while it is not older than
     * the files it was made from, and the tests move those times.
     */
    private function image(string $name, int $width, int $height, array $rgb): void
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, ...$rgb));
        str_ends_with($name, '.jpg') ? imagejpeg($image, $this->dir . '/' . $name) : imagepng($image, $this->dir . '/' . $name);
        touch($this->dir . '/' . $name, time() - 60);
    }

    private function medium(string $name = 'source.png'): ImageMedium
    {
        $medium = MediumFactory::fromFile($this->dir . '/' . $name);
        $this->assertInstanceOf(ImageMedium::class, $medium);

        return $medium;
    }

    /**
     * The size of a processed image and the colour in its middle.
     */
    private function look(string $path): array
    {
        $image = imagecreatefromstring(file_get_contents($path));
        $color = imagecolorat($image, intdiv(imagesx($image), 2), intdiv(imagesy($image), 2));

        return [imagesx($image), imagesy($image), sprintf('%06x', $color & 0xffffff)];
    }

    public function testWatermarkQueuedAfterASaveLeavesTheSavedFileAlone(): void
    {
        // What another page shows. It has been in the image cache for a while,
        // and the stamp is newer than it, as one uploaded later is.
        $shown = $this->medium()->resize(400, 100)->path();
        $this->assertSame([400, 100, '0000ff'], $this->look($shown));
        touch($shown, time() - 30);
        touch($this->dir . '/stamp.png');
        clearstatcache();

        $medium = $this->medium()->resize(400, 100);
        $this->assertSame($shown, $medium->path(false));

        $stamped = $medium->forceResize(400, 100)->watermark()->path(false);

        $this->assertNotSame($shown, $stamped);
        $this->assertSame([400, 100, 'ff0000'], $this->look($stamped));
        $this->assertSame($stamped, $medium->path());
        $this->assertSame([400, 100, '0000ff'], $this->look($shown));
    }

    public function testActionsQueuedAfterASaveAreAppliedToTheSource(): void
    {
        $medium = $this->medium()->rotate(90);
        $turned = $medium->path(false);
        $this->assertSame([200, 800, '0000ff'], $this->look($turned));

        // Both turns from the source, not one more of the file saved above.
        $turnedAgain = $medium->rotate(90)->path(false);

        $this->assertNotSame($turned, $turnedAgain);
        $this->assertSame([800, 200, '0000ff'], $this->look($turnedAgain));
        $this->assertSame([200, 800, '0000ff'], $this->look($turned));
    }

    public function testQualityChangedAfterASaveIsNotTheSavedFile(): void
    {
        $medium = $this->medium('source.jpg')->resize(400, 100);
        $saved = $medium->path(false);

        $this->assertNotSame($saved, $medium->quality(10)->path(false));
    }

    /**
     * An image tag saves the image for `src` and again for `srcset`, a link
     * once more for `href`. Every save queues the default filters, the debug
     * overlay and, for a JPEG, the progressive flag.
     *
     * @dataProvider providerSavedTwice
     */
    public function testSavingAgainWithNothingQueuedKeepsTheFile(string $name, array $settings): void
    {
        $medium = $this->medium($name);
        foreach ($settings as $key => $value) {
            $medium->set($key, $value);
        }

        $saved = $medium->resize(400, 100)->path(false);

        $this->assertSame($saved, $medium->path(false));
        $this->assertSame($saved, $medium->path());
    }

    public static function providerSavedTwice(): array
    {
        return [
            'png' => ['source.png', []],
            'progressive jpeg' => ['source.jpg', []],
            'default filters' => ['source.png', ['image.filters.default' => [['brightness', -10]]]],
            'debug overlay' => ['source.png', ['debug' => true]],
        ];
    }

    public function testSrcsetNamesTheFileOfSrc(): void
    {
        $medium = (new Media($this->dir))['source.png'];
        $this->assertInstanceOf(ImageMedium::class, $medium);
        $medium->set('image.filters.default', [['brightness', -10]]);

        $src = $medium->resize(400, 100)->url(false);
        $srcset = $medium->srcset(false);

        $this->assertStringEndsWith(', ' . $src . ' ' . $medium->get('width') . 'w', $srcset);
        $this->assertSame($srcset, $medium->srcset(false));
        $this->assertSame($src, $medium->url());
    }
}
