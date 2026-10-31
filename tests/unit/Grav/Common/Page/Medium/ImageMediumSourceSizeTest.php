<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Page\Medium\ImageFile;
use Grav\Common\Page\Medium\ImageMedium;
use Grav\Common\Page\Medium\MediumFactory;


class ImageMediumSourceSizeTest extends \Codeception\Test\Unit
{
    /** @var Grav */
    protected $grav;

    /** @var string */
    protected $dir;

    /** @var array */
    protected $images;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $this->dir = sys_get_temp_dir() . '/grav-source-size-' . uniqid('', true);
        Folder::create($this->dir);

        $config = $this->grav['config'];
        $this->images = $config->get('system.images');
        // A hundredth of the default, to keep the pictures small.
        $config->set('system.images.max_pixels', 250000);
    }

    protected function tearDown(): void
    {
        $this->grav['config']->set('system.images', $this->images);
        Folder::delete($this->dir);
        parent::tearDown();
    }

    private static function picture(string $type, int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        ('image' . $type)($image);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    private static function frameHeader(int $width, int $height): string
    {
        return "\xFF\xC0" . pack('nCnnC', 11, 8, $height, $width, 1) . "\x01\x11\x00";
    }

    private static function comment(string $text): string
    {
        return "\xFF\xFE" . pack('n', strlen($text) + 2) . $text;
    }

    private function file(string $name, string $bytes): string
    {
        $file = $this->dir . '/' . $name;
        file_put_contents($file, $bytes);

        return $file;
    }

    private function assertRefused(string $file): void
    {
        $medium = MediumFactory::fromFile($file);
        self::assertInstanceOf(ImageMedium::class, $medium);
        // The original comes back instead of a file from the image cache.
        self::assertSame($file, $medium->resize(10, 10)->path());
    }

    public static function jpegHeaders(): array
    {
        return [
            'nothing' => [''],
            'RST0' => ["\xFF\xD0"],
            // getimagesize() reads the `ff fe` behind RST0 as a length and carries on 0xfffe bytes
            // further, at 0x10002. The text of the second comment starts at 0xf00c.
            'RST0, another frame header for getimagesize()' => [
                "\xFF\xD0" . self::comment(str_repeat(' ', 0xF000)) . self::comment(str_repeat(' ', 0xFF6) . self::frameHeader(20, 20))
            ],
            'TEM' => ["\xFF\x01"],
            'fill bytes' => ["\xFF\xFF\xFF\xD7\xFF"],
            'bytes that are no marker' => ["\x00\x00"],
            'stuffed zero' => ["\xFF\x00"],
        ];
    }

    /**
     * @dataProvider jpegHeaders
     */
    public function testOversizedJpegIsNotOpened(string $afterStartOfImage): void
    {
        $file = $this->file('photo.jpg', substr_replace(self::picture('jpeg', 600, 500), $afterStartOfImage, 2, 0));

        $image = imagecreatefromjpeg($file);
        self::assertSame([600, 500], [imagesx($image), imagesy($image)]);
        self::assertSame([600, 500], ImageFile::decodedSize($file));
        $this->assertRefused($file);
    }

    public static function jpegsWithoutFrameHeader(): array
    {
        return [
            'empty' => [''],
            'no start of image' => [self::frameHeader(20, 20)],
            'start of image only' => ["\xFF\xD8"],
            'second start of image' => ["\xFF\xD8\xFF\xD8" . self::frameHeader(20, 20)],
            'end of image' => ["\xFF\xD8\xFF\xD9" . self::frameHeader(20, 20)],
            'start of scan' => ["\xFF\xD8\xFF\xDA\x00\x08\x01\x01\x00\x00\x3F\x00" . self::frameHeader(20, 20)],
            'segment without a length' => ["\xFF\xD8\xFF\xFE\x00\x00" . self::frameHeader(20, 20)],
            'segment past the end' => ["\xFF\xD8\xFF\xFE\xFF\xFF" . self::frameHeader(20, 20)],
            'cut frame header' => ["\xFF\xD8" . substr(self::frameHeader(20, 20), 0, 8)],
        ];
    }

    /**
     * @dataProvider jpegsWithoutFrameHeader
     */
    public function testJpegWithoutFrameHeaderHasNoSize(string $bytes): void
    {
        self::assertNull(ImageFile::decodedSize($this->file('photo.jpg', $bytes)));
    }

    public function testPngWithChunkBeforeHeaderIsNotOpened(): void
    {
        // An unknown chunk that can be ignored, filled like the header of a 20x20 image.
        $chunk = 'teSt' . pack('NN', 20, 20) . "\x08\x02\x00\x00\x00";
        $chunk = pack('N', 13) . $chunk . pack('N', crc32($chunk));
        $file = $this->file('photo.png', substr_replace(self::picture('png', 600, 500), $chunk, 8, 0));

        self::assertNull(ImageFile::decodedSize($file));
        $this->assertRefused($file);
    }

    public static function bareWebpStreams(): array
    {
        return [
            'nothing to getimagesize()' => [''],
            'XBM to getimagesize()' => ["\n#define photo_width 20\n#define photo_height 20\n"],
        ];
    }

    /**
     * @dataProvider bareWebpStreams
     */
    public function testBareWebpStreamIsNotOpened(string $trailer): void
    {
        // VP8L signature, 14 bits of width - 1, 14 bits of height - 1, and no RIFF container.
        $file = $this->file('photo.webp', "\x2F" . pack('V', 599 | (499 << 14)) . str_repeat("\0", 16) . $trailer);

        self::assertNull(ImageFile::decodedSize($file));
        $this->assertRefused($file);
    }

    public function testBareWebpStreamThatLooksLikeContainerIsNotOpened(): void
    {
        // To libwebp a VP8 frame tag, the start code and 14 bits each of width and height, of which
        // `WE` is 1367. The tag gives the first partition 143946 bytes, which have to be there.
        // To getimagesize() a container, with a VP8L stream of 20x20 in it.
        $bytes = "RIF\x9D\x01\x2A" . pack('v', 600) . 'WEBP' . 'VP8L' . pack('V', 5) . "\x2F" . pack('V', 19 | (19 << 14));
        $file = $this->file('photo.webp', $bytes . str_repeat("\0", 150000));

        self::assertNull(ImageFile::decodedSize($file));
        $this->assertRefused($file);
    }

    public function testFileOfAnotherTypeIsMeasuredAsWhatItIsOpenedAs(): void
    {
        $jpeg = $this->file('jpeg.png', self::picture('jpeg', 600, 500));
        self::assertSame([600, 500], ImageFile::decodedSize($jpeg));

        // No type the adapters open: left to the extension, and that is not what the file is.
        $bmp = $this->file('bmp.gif', self::picture('bmp', 30, 20));
        self::assertNull(ImageFile::decodedSize($bmp));
        $bmp = $this->file('bmp.jpg', self::picture('bmp', 30, 20));
        self::assertNull(ImageFile::decodedSize($bmp));
    }

    public static function types(): array
    {
        return [['jpeg'], ['png'], ['webp']];
    }

    /**
     * @dataProvider types
     */
    public function testOrdinaryImagesAreMeasuredAndProcessedAsBefore(string $type): void
    {
        $file = $this->file('photo.' . $type, self::picture($type, 30, 20));
        self::assertSame([30, 20], ImageFile::decodedSize($file));

        $medium = MediumFactory::fromFile($file);
        self::assertInstanceOf(ImageMedium::class, $medium);
        $path = $medium->resize(15, 10)->path();
        self::assertNotSame($file, $path);
        self::assertSame([15, 10], array_slice(getimagesize($path), 0, 2));
    }

    public function testOtherImagesAreMeasuredAsBefore(): void
    {
        foreach ([
            $this->file('photo.gif', self::picture('gif', 30, 20)),
            GRAV_ROOT . '/tests/fake/nested-site/user/pages/01.item1/home-sample-image.jpg',
            GRAV_ROOT . '/system/images/media/thumb.png',
        ] as $file) {
            self::assertSame(array_slice(getimagesize($file), 0, 2), ImageFile::decodedSize($file));
        }
    }
}
