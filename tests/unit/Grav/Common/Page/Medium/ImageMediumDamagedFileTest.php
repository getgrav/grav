<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Helpers\Exif;
use Grav\Common\Page\Media;
use Grav\Common\Page\Medium\ImageMedium;
use Grav\Common\Twig\Extension\FilesystemExtension;
use Grav\Common\Twig\Extension\GravExtension;


class ImageMediumDamagedFileTest extends \Codeception\Test\Unit
{
    /** @var Grav */
    protected $grav;

    /** @var string */
    protected $folder;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $this->folder = sys_get_temp_dir() . '/grav-damaged-media-' . uniqid('', true);
        Folder::create($this->folder);

        // What Errors::resetHandlers() installs, without Whoops.
        set_error_handler(static function ($level, $message, $file = '', $line = 0) {
            if (!($level & error_reporting())) {
                return false;
            }

            throw new ErrorException($message, 0, $level, $file, $line);
        });
    }

    protected function tearDown(): void
    {
        restore_error_handler();
        Folder::delete($this->folder);
        parent::tearDown();
    }

    private static function jpeg(): string
    {
        ob_start();
        imagejpeg(imagecreatetruecolor(40, 30));

        return (string)ob_get_clean();
    }


    private static function jpegWithExtraneousBytes(): string
    {
        $jpeg = self::jpeg();

        return substr($jpeg, 0, 20) . "\x01\x00\x02" . substr($jpeg, 20);
    }


    private static function jpegWithUnexpectedExif(): string
    {
        $tiff = "II\x2a\x00" . pack('V', 8)
            . pack('v', 1) . pack('vvVV', 0x8769, 4, 1, 26) . pack('V', 0)
            . pack('v', 1) . pack('vvVV', 0x920A, 5, 2, 44) . pack('V', 0)
            . pack('VVVV', 50, 1, 60, 1);
        $app1 = "Exif\0\0" . $tiff;

        return "\xff\xd8\xff\xe1" . pack('n', strlen($app1) + 2) . $app1 . substr(self::jpeg(), 2);
    }

    public function damagedProvider(): array
    {
        return [
            'shorter than a signature' => ['x.jpg', 'hello'],
            'shorter than a signature, png' => ['x.png', 'hello'],
            'shorter than a signature, webp' => ['x.webp', 'hello'],
            'shorter than a signature, retina only' => ['x@2x.jpg', 'hello'],
            'extraneous bytes before a marker' => ['x.jpg', self::jpegWithExtraneousBytes()],
            'JPEG 2000 without a codestream' => ['x.jpg', "\x00\x00\x00\x0cjP  \x0d\x0a\x87\x0a" . str_repeat("\0", 30)],
        ];
    }

    /**
     * @dataProvider damagedProvider
     */
    public function testMediaCollectionSurvivesADamagedFile(string $filename, string $content): void
    {
        file_put_contents($this->folder . '/good.jpg', self::jpeg());
        file_put_contents($this->folder . '/' . $filename, $content);

        $media = new Media($this->folder);

        $this->assertInstanceOf(ImageMedium::class, $media['x.' . pathinfo($filename, PATHINFO_EXTENSION)]);
        $this->assertSame(40, $media['good.jpg']->get('width'));
        $this->assertSame(30, $media['good.jpg']->get('height'));
    }

    public function testFixOrientationIgnoresADamagedFile(): void
    {
        $this->grav['config']->set('system.images.auto_fix_orientation', true);
        file_put_contents($this->folder . '/x.jpg', 'hello');

        $this->assertIsString((new Media($this->folder))['x.jpg']->cropResize(10, 10)->html());
    }

    public function testAutoSizesAreLeftOutWhenTheSizeIsUnknown(): void
    {
        $this->grav['config']->set('system.images.cls.auto_sizes', true);
        file_put_contents($this->folder . '/good.jpg', self::jpeg());
        file_put_contents($this->folder . '/x.jpg', 'hello');

        $media = new Media($this->folder);

        $this->assertStringContainsString('width="40" height="30"', $media['good.jpg']->autoSizes('true')->html());
        $this->assertStringNotContainsString('width=', $media['x.jpg']->autoSizes('true')->html());
    }

    public function exifProvider(): array
    {
        return [
            'extraneous bytes before a marker' => [self::jpegWithExtraneousBytes()],
            'Exif value of an unexpected type' => [self::jpegWithUnexpectedExif()],
        ];
    }

    /**
     * @dataProvider exifProvider
     */
    public function testExifReaderFailureDoesNotEscape(string $content): void
    {
        if (!function_exists('exif_read_data')) {
            $this->markTestSkipped('Needs the exif extension.');
        }

        $this->grav['config']->set('system.media.auto_metadata_exif', true);
        $this->grav['exif'] = new Exif();
        file_put_contents($this->folder . '/x.jpg', $content);

        $this->assertInstanceOf(ImageMedium::class, (new Media($this->folder))['x.jpg']);
        $this->assertNull((new GravExtension())->exifFunc($this->folder . '/x.jpg'));
    }

    public function testTwigGetimagesizeReturnsFalseForADamagedFile(): void
    {
        file_put_contents($this->folder . '/x.jpg', 'hello');

        $this->assertFalse((new FilesystemExtension())->getimagesize($this->folder . '/x.jpg'));
    }
}
