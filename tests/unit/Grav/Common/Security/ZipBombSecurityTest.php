<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Archiver;
use Grav\Common\Filesystem\Folder;
use Grav\Common\GPM\Installer;

/**
 * Class ZipBombSecurityTest
 *
 * Covers: GHSA-2vcx-h8p2-9pg9 (Installer::unZip()) and GHSA-928x-9mpw-8h56
 * (the sibling ZipArchiver::extract() path). Before extraction, both methods
 * now bound the total uncompressed size, entry count, and directory nesting
 * depth, so a crafted archive can no longer fill the disk / exhaust inodes
 * (CWE-409) or nest deeply enough to overflow the recursive cleanup (CWE-674).
 * Because the check runs *before* extractTo(), a rejected archive leaves
 * nothing on disk. Installer::unZip() reports failure via a return value +
 * error code; ZipArchiver::extract() throws RuntimeException.
 *
 * Limits are read from `system.gpm.archive.*`; the tests lower them so the
 * fixtures stay tiny while exercising the same code path.
 *
 * Naming convention: test{Method}_{GHSA_ID}_{description}
 */
class ZipBombSecurityTest extends \PHPUnit\Framework\TestCase
{
    /** @var \Grav\Common\Grav */
    protected $grav;
    /** @var string */
    protected $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $config = $this->grav['config'];
        $config->set('system.gpm.archive.max_uncompressed_size', 1048576); // 1 MiB
        $config->set('system.gpm.archive.max_files', 100);
        $config->set('system.gpm.archive.max_depth', 10);

        $this->tmp = sys_get_temp_dir() . '/grav-zipbomb-' . uniqid('', true);
        @mkdir($this->tmp, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmp)) {
            Folder::delete($this->tmp);
        }
        parent::tearDown();
    }

    private function makeZip(string $name, callable $build): string
    {
        $path = $this->tmp . '/' . $name;
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $build($zip);
        $zip->close();

        return $path;
    }

    private function destFor(string $zip): string
    {
        return $this->tmp . '/out-' . md5($zip);
    }

    public function testUnZip_GHSA2vcx_RejectsOversizedArchive(): void
    {
        $zip = $this->makeZip('bomb.zip', static function (ZipArchive $z) {
            $z->addFromString('bomb.dat', str_repeat('A', 2 * 1024 * 1024)); // 2 MiB > 1 MiB
        });
        $dest = $this->destFor($zip);

        self::assertFalse(Installer::unZip($zip, $dest), 'Oversized archive must be rejected');
        self::assertSame(Installer::ZIP_LIMITS_ERROR, Installer::lastErrorCode());
        self::assertDirectoryDoesNotExist($dest, 'Nothing should land on disk for a rejected archive');
    }

    public function testUnZip_GHSA2vcx_RejectsTooManyEntries(): void
    {
        $zip = $this->makeZip('many.zip', static function (ZipArchive $z) {
            for ($i = 0; $i < 150; $i++) { // 150 > 100
                $z->addFromString("f/{$i}.txt", 'x');
            }
        });
        $dest = $this->destFor($zip);

        self::assertFalse(Installer::unZip($zip, $dest), 'Archive with too many entries must be rejected');
        self::assertSame(Installer::ZIP_LIMITS_ERROR, Installer::lastErrorCode());
        self::assertDirectoryDoesNotExist($dest);
    }

    public function testUnZip_GHSA2vcx_RejectsExcessiveNesting(): void
    {
        $zip = $this->makeZip('deep.zip', static function (ZipArchive $z) {
            $path = 'deep';
            for ($i = 0; $i < 20; $i++) { // 20 levels > 10
                $path .= '/x';
            }
            $z->addFromString($path . '/.keep', '');
        });
        $dest = $this->destFor($zip);

        self::assertFalse(Installer::unZip($zip, $dest), 'Deeply nested archive must be rejected');
        self::assertSame(Installer::ZIP_LIMITS_ERROR, Installer::lastErrorCode());
        self::assertDirectoryDoesNotExist($dest);
    }

    public function testUnZip_GHSA2vcx_AcceptsLegitimatePackage(): void
    {
        $zip = $this->makeZip('ok.zip', static function (ZipArchive $z) {
            $z->addFromString('grav-plugin-demo/demo.php', '<?php');
            $z->addFromString('grav-plugin-demo/blueprints.yaml', 'name: Demo');
            $z->addFromString('grav-plugin-demo/vendor/a/b/c/file.php', '<?php');
        });
        $dest = $this->destFor($zip);

        $result = Installer::unZip($zip, $dest);
        self::assertNotFalse($result, 'A normal package within limits must extract');
        self::assertSame(Installer::OK, Installer::lastErrorCode());
        self::assertFileExists($dest . '/grav-plugin-demo/demo.php');
    }

    public function testExtract_GHSA928x_RejectsOversizedArchive(): void
    {
        $zip = $this->makeZip('za-bomb.zip', static function (ZipArchive $z) {
            $z->addFromString('bomb.dat', str_repeat('A', 2 * 1024 * 1024)); // 2 MiB > 1 MiB
        });
        $dest = $this->destFor($zip);

        try {
            Archiver::create('zip')->setArchive($zip)->extract($dest);
            self::fail('Oversized archive must be rejected');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('uncompressed size', $e->getMessage());
        }
        self::assertDirectoryDoesNotExist($dest, 'Nothing should land on disk for a rejected archive');
    }

    public function testExtract_GHSA928x_RejectsTooManyEntries(): void
    {
        $zip = $this->makeZip('za-many.zip', static function (ZipArchive $z) {
            for ($i = 0; $i < 150; $i++) { // 150 > 100
                $z->addFromString("f/{$i}.txt", 'x');
            }
        });
        $dest = $this->destFor($zip);

        try {
            Archiver::create('zip')->setArchive($zip)->extract($dest);
            self::fail('Archive with too many entries must be rejected');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('file count', $e->getMessage());
        }
        self::assertDirectoryDoesNotExist($dest);
    }

    public function testExtract_GHSA928x_RejectsExcessiveNesting(): void
    {
        $zip = $this->makeZip('za-deep.zip', static function (ZipArchive $z) {
            $path = 'deep';
            for ($i = 0; $i < 20; $i++) { // 20 levels > 10
                $path .= '/x';
            }
            $z->addFromString($path . '/.keep', '');
        });
        $dest = $this->destFor($zip);

        try {
            Archiver::create('zip')->setArchive($zip)->extract($dest);
            self::fail('Deeply nested archive must be rejected');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('nesting depth', $e->getMessage());
        }
        self::assertDirectoryDoesNotExist($dest);
    }

    public function testExtract_GHSA928x_AcceptsLegitimateArchive(): void
    {
        $zip = $this->makeZip('za-ok.zip', static function (ZipArchive $z) {
            $z->addFromString('demo/demo.php', '<?php');
            $z->addFromString('demo/vendor/a/b/c/file.php', '<?php');
        });
        $dest = $this->destFor($zip);

        Archiver::create('zip')->setArchive($zip)->extract($dest);
        self::assertFileExists($dest . '/demo/demo.php', 'A normal archive within limits must extract');
    }
}
