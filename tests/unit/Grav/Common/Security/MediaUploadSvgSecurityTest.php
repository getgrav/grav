<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Page\Media;
use Grav\Common\Security;
use Grav\Framework\Form\FormFlash;
use Grav\Framework\Form\FormFlashFile;
use Nyholm\Psr7\UploadedFile;


class MediaUploadSvgSecurityTest extends \PHPUnit\Framework\TestCase
{
    private const CONTENT = '<s:svg xmlns:s="http://www.w3.org/2000/svg"><s:script>alert(document.domain)</s:script></s:svg>';
    private const PNG = "\x89PNG\r\n\x1a\n<svg xmlns=\"http://www.w3.org/2000/svg\"/>";

    /** @var Grav */
    protected $grav;
    /** @var string */
    protected $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->tmp = sys_get_temp_dir() . '/grav-media-svg-' . bin2hex(random_bytes(4));
        Folder::create($this->tmp);
    }

    protected function tearDown(): void
    {
        Folder::delete($this->tmp);
        parent::tearDown();
    }

    private function upload(string $filename, string $content): string
    {
        $source = "{$this->tmp}/upload.tmp";
        file_put_contents($source, $content);

        $media = new Media("{$this->tmp}/media");
        $media->copyUploadedFile(new UploadedFile($source, strlen($content), UPLOAD_ERR_OK, $filename), $filename);

        return (string)file_get_contents("{$this->tmp}/media/{$filename}");
    }

    public function testCheckFileMetadata_RejectsDefaultsAsFileType(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(400);

        (new Media("{$this->tmp}/media"))->checkFileMetadata(
            ['filename' => 'drawing.svg.defaults', 'mime' => 'application/octet-stream', 'size' => 96],
            null,
            ['accept' => ['*']]
        );
    }

    /**
     * @dataProvider providerSvgNames
     */
    public function testCopyUploadedFile_SanitizesSvgBehindAnotherExtension(string $filename, string $content): void
    {
        self::assertStringNotContainsStringIgnoringCase('script', str_replace("\0", '', $this->upload($filename, $content)));
    }

    public static function providerSvgNames(): array
    {
        return [
            'last extension' => ['drawing.svg', self::CONTENT],
            'inner extension' => ['drawing.svg.avif', self::CONTENT],
            'inner extension, upper case' => ['drawing.SVG.avif', self::CONTENT],
            'sub-path' => ['original/drawing.svg.avif', self::CONTENT],
            'byte order mark' => ['drawing.svg.avif', "\xEF\xBB\xBF" . self::CONTENT],
            'long whitespace padding' => ['drawing.svg.avif', str_repeat(" \n", 2048) . self::CONTENT],
            'utf-16' => ['drawing.svg.avif', "\xFF\xFE" . mb_convert_encoding(self::CONTENT, 'UTF-16LE', 'UTF-8')],
        ];
    }

    public function testCopyUploadedFile_KeepsRenderedSvg(): void
    {
        self::assertSame(self::PNG, $this->upload('logo.svg.png', self::PNG));
    }

    /**
     * A form upload may carry crop data, which is saved next to the file after the file has been
     * stored. When that save fails, so does the upload, but the file stays in the media folder.
     */
    public function testCopyUploadedFile_SanitizesSvgWhenMetadataCannotBeSaved(): void
    {
        $flash = new FormFlash(['session_id' => 'session', 'unique_id' => 'form', 'folder' => "{$this->tmp}/flash"]);
        Folder::create($flash->getTmpDir());
        file_put_contents("{$flash->getTmpDir()}/upload", self::CONTENT);
        $upload = ['name' => 'drawing.svg', 'type' => 'image/svg+xml', 'tmp_name' => 'upload', 'crop' => ['x' => 0]];

        $media = new Media("{$this->tmp}/media");
        // A folder in the way: the same failure as with a file name too long to fit `.meta.yaml` after it.
        Folder::create("{$this->tmp}/media/drawing.svg.meta.yaml");

        try {
            $media->copyUploadedFile(new FormFlashFile('image/original', $upload, $flash), 'drawing.svg');
            self::fail('Metadata which cannot be saved has to be reported');
        } catch (RuntimeException $e) {
            self::assertSame(400, $e->getCode());
        }

        self::assertFileExists("{$this->tmp}/media/drawing.svg");
        self::assertStringNotContainsStringIgnoringCase('script', (string)file_get_contents("{$this->tmp}/media/drawing.svg"));
    }

    public function testIsSvgFile(): void
    {
        file_put_contents("{$this->tmp}/markup", self::CONTENT);
        file_put_contents("{$this->tmp}/binary", self::PNG);

        self::assertTrue(Security::isSvgFile('a.svg', "{$this->tmp}/binary"));
        self::assertTrue(Security::isSvgFile('a.svg.foo', "{$this->tmp}/markup"));
        self::assertTrue(Security::isSvgFile('a.svg.foo', "{$this->tmp}/missing"));
        self::assertFalse(Security::isSvgFile('a.svg.png', "{$this->tmp}/binary"));
        self::assertFalse(Security::isSvgFile('a.svg/b.xml', "{$this->tmp}/markup"));
        self::assertFalse(Security::isSvgFile('svg', "{$this->tmp}/markup"));
    }
}
