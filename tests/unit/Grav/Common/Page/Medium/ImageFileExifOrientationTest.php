<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Page\Medium\ImageFile;


class ImageFileExifOrientationTest extends \Codeception\Test\Unit
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

        $this->dir = sys_get_temp_dir() . '/grav-exif-orientation-' . uniqid('', true);
        Folder::create($this->dir);
    }

    protected function tearDown(): void
    {
        Folder::delete($this->dir);
        parent::tearDown();
    }

    private static function picture(): string
    {
        ob_start();
        imagejpeg(imagecreatetruecolor(40, 30));

        return (string) ob_get_clean();
    }

    /**
     * A TIFF header and a first image directory: $entries, the orientation, one more entry.
     */
    private static function tiff(string $order, int $type, int $orientation, string $entries = ''): string
    {
        [$short, $long] = $order === 'II' ? ['v', 'V'] : ['n', 'N'];
        $value = $type === 3 ? pack($short . $short, $orientation, 0) : pack($long, $orientation);
        $entries .= pack($short . $short . $long, 0x0112, $type, 1) . $value
            . pack($short . $short . $long . $long, 0x0128, 3, 1, 0);

        return $order . pack($short . $long . $short, 42, 8, strlen($entries) / 12) . $entries . pack($long, 0);
    }

    private static function segment(int $marker, string $data): string
    {
        return "\xFF" . chr($marker) . pack('n', strlen($data) + 2) . $data;
    }

    private static function jpeg(string $header): string
    {
        return "\xFF\xD8" . $header . substr(self::picture(), 2);
    }

    private function file(string $name, string $bytes): string
    {
        $file = $this->dir . '/' . $name;
        file_put_contents($file, $bytes);

        return $file;
    }

    public static function orientedFiles(): array
    {
        $files = [];
        foreach (['II', 'MM'] as $order) {
            foreach ([3 => 'SHORT', 4 => 'LONG'] as $type => $name) {
                foreach ([3, 6] as $orientation) {
                    $tiff = self::tiff($order, $type, $orientation);
                    $files["JPEG, {$order}, {$name}, {$orientation}"] = [self::jpeg(self::segment(0xE1, "Exif\0\0" . $tiff)), $orientation];
                    $files["TIFF, {$order}, {$name}, {$orientation}"] = [$tiff, $orientation];
                }
            }
        }

        $exif = self::segment(0xE1, "Exif\0\0" . self::tiff('MM', 3, 6));
        $files['behind JFIF'] = [self::jpeg(self::segment(0xE0, "JFIF\0\1\1\0\0\1\0\1\0\0") . $exif), 6];
        $files['behind XMP'] = [self::jpeg(self::segment(0xE1, "http://ns.adobe.com/xap/1.0/\0<x/>") . $exif), 6];
        $files['behind a comment and fill bytes'] = [self::jpeg(self::segment(0xFE, 'hello') . "\xFF\xFF" . $exif), 6];

        return $files;
    }

    /**
     * @dataProvider orientedFiles
     */
    public function testOrientationIsWhatExifReadDataFinds(string $bytes, int $orientation): void
    {
        $file = $this->file('photo.jpg', $bytes);

        self::assertSame($orientation, ImageFile::exifOrientation($file));
        if (function_exists('exif_read_data')) {
            self::assertSame($orientation, @exif_read_data($file)['Orientation']);
        }
    }

    public static function filesWithoutOrientation(): array
    {
        $exif = self::segment(0xE1, "Exif\0\0" . self::tiff('II', 3, 6));

        return [
            'empty' => [''],
            'no Exif segment' => [self::picture()],
            'Exif behind the image data' => [substr(self::picture(), 0, -2) . $exif . "\xFF\xD9"],
            'cut inside the Exif segment' => [substr(self::jpeg($exif), 0, 20)],
            'directory outside the file' => [self::jpeg(self::segment(0xE1, "Exif\0\0II*\0" . pack('V', 0x7FFFFFFF)))],
            'directory without entries' => [self::jpeg(self::segment(0xE1, "Exif\0\0II*\0" . pack('Vv', 8, 0)))],
            'not a TIFF header' => [self::jpeg(self::segment(0xE1, "Exif\0\0" . substr_replace(self::tiff('II', 3, 6), 'XX', 0, 2)))],
            'two values' => [self::jpeg(self::segment(0xE1, "Exif\0\0II*\0" . pack('VvvvVvvV', 8, 1, 0x0112, 3, 2, 6, 6, 0)))],
        ];
    }

    /**
     * @dataProvider filesWithoutOrientation
     */
    public function testFileWithoutOrientation(string $bytes): void
    {
        self::assertNull(ImageFile::exifOrientation($this->file('photo.jpg', $bytes)));
    }

    public static function expensiveFiles(): array
    {
        // Fifty entries that all have the same 30000 numbers as their value, in a file of 64 KB: 37 MB to
        // exif_read_data(), and 730 MB with the thousand entries that fit as well.
        $tiff = self::tiff('II', 3, 6, implode('', array_map(static fn ($tag) => pack('vvVV', $tag, 3, 30000, 0), range(0xA500, 0xA531))));
        $tiff = str_pad($tiff, 65000, 'A');

        return [
            'JPEG, entries sharing one long value' => [self::jpeg(self::segment(0xE1, "Exif\0\0" . $tiff))],
            'TIFF, entries sharing one long value' => [$tiff],
            // As many segments as fit into 1 MB, 25 MB to exif_read_data().
            'JPEG, empty comments' => [self::jpeg(str_repeat("\xFF\xFE\x00\x02", 250000) . self::segment(0xE1, "Exif\0\0" . self::tiff('II', 3, 6)))],
        ];
    }

    /**
     * @dataProvider expensiveFiles
     */
    public function testFixOrientationDoesNotKeepTheMetadataInMemory(string $bytes): void
    {
        if (!extension_loaded('exif')) {
            $this->markTestSkipped('Needs the exif extension');
        }
        $file = $this->file('photo.jpg', $bytes);

        $before = memory_get_usage();
        memory_reset_peak_usage();
        $image = ImageFile::open($file)->fixOrientation();
        $used = memory_get_peak_usage() - $before;

        self::assertLessThan(4 * 1024 * 1024, $used);
        self::assertInstanceOf(ImageFile::class, $image);
        self::assertSame(serialize([['applyExifOrientation', [6]]]), $image->serializeOperations());
    }
}
