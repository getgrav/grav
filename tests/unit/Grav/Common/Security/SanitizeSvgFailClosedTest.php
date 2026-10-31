<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Page\Media;
use Grav\Common\Security;
use Nyholm\Psr7\UploadedFile;


class SanitizeSvgFailClosedTest extends \PHPUnit\Framework\TestCase
{
    private const DRAWING = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(document.domain)"><text>\'</text></svg>';

    private const THROWING = "<!DOCTYPE svg [<!-- ' -->]>" . self::DRAWING . '<!--]-->';

    /** @var Grav */
    protected $grav;
    /** @var string */
    protected $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->tmp = sys_get_temp_dir() . '/grav-sanitize-svg-' . bin2hex(random_bytes(4));
        Folder::create($this->tmp);
    }

    protected function tearDown(): void
    {
        Folder::delete($this->tmp);
        @unlink($this->quarantined());
        @rmdir(dirname($this->quarantined()));
        parent::tearDown();
    }

    private function quarantined(): string
    {
        return (string)$this->grav['locator']->findResource('log://quarantine/fail-closed-test.svg', true, true);
    }

    public function testSanitizeSVG_CleansTheFileInPlace(): void
    {
        $file = "{$this->tmp}/fail-closed-test.svg";
        file_put_contents($file, self::DRAWING);

        Security::sanitizeSVG($file);

        self::assertStringContainsString('<svg', (string)file_get_contents($file));
        self::assertStringNotContainsString('onload', (string)file_get_contents($file));
        self::assertFileDoesNotExist($this->quarantined());
    }

    public function testSanitizeSVG_QuarantinesTheFileWhenTheSanitizerThrows(): void
    {
        $file = "{$this->tmp}/fail-closed-test.svg";
        file_put_contents($file, self::THROWING);

        try {
            Security::sanitizeSVG($file);
            self::fail('An SVG which cannot be sanitized has to be reported');
        } catch (Exception $e) {
            self::assertStringContainsString('quarantine', $e->getMessage());
        }

        self::assertFileDoesNotExist($file);
        self::assertStringEqualsFile($this->quarantined(), self::THROWING);
    }

    public function testCopyUploadedFile_DoesNotKeepTheFileWhenTheSanitizerThrows(): void
    {
        $source = "{$this->tmp}/upload.tmp";
        file_put_contents($source, self::THROWING);

        $media = new Media("{$this->tmp}/media");
        try {
            $media->copyUploadedFile(
                new UploadedFile($source, strlen(self::THROWING), UPLOAD_ERR_OK, 'fail-closed-test.svg'),
                'fail-closed-test.svg'
            );
            self::fail('An SVG which cannot be sanitized has to be reported');
        } catch (RuntimeException $e) {
            self::assertSame(400, $e->getCode());
        }

        self::assertFileDoesNotExist("{$this->tmp}/media/fail-closed-test.svg");
        self::assertArrayNotHasKey('fail-closed-test.svg', $media->all());
    }
}
