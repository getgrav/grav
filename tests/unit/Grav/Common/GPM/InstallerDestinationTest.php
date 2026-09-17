<?php

use Grav\Common\GPM\Installer;

class InstallerDestinationTest extends \PHPUnit\Framework\TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/grav-package-check-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/plugin/nested', 0700, true);
        file_put_contents($this->root . '/plugin/nested/file.php', '<?php');
        mkdir($this->root . '/bin');
        mkdir($this->root . '/system/config', 0700, true);
        mkdir($this->root . '/user/themes/demo', 0700, true);
        file_put_contents($this->root . '/index.php', '<?php');
        file_put_contents($this->root . '/system/config/system.yaml', '');
        file_put_contents($this->root . '/user/themes/demo/template.twig', 'original');
    }

    protected function tearDown(): void
    {
        @unlink($this->root . '/linked');
        chmod($this->root . '/plugin/nested', 0700);
        chmod($this->root . '/plugin/nested/file.php', 0600);
        chmod($this->root . '/user/themes/demo/template.twig', 0600);
        \Grav\Common\Filesystem\Folder::delete($this->root);
    }

    public function testWritablePackagesAndNewDestinationsAreAccepted(): void
    {
        self::assertNull(Installer::getDestinationIssue($this->root . '/plugin'));
        self::assertNull(Installer::getDestinationIssue($this->root . '/new/child'));
    }

    public function testSymlinkTargetsAreNotTraversedOrChanged(): void
    {
        symlink($this->root . '/plugin', $this->root . '/linked');
        self::assertStringContainsString('Symbolic link', Installer::getDestinationIssue($this->root . '/linked'));
        self::assertSame('<?php', file_get_contents($this->root . '/plugin/nested/file.php'));
    }

    public function testNestedReadOnlyDirectoryBlocksReplacementBeforeDeletion(): void
    {
        chmod($this->root . '/plugin/nested', 0500);
        clearstatcache();
        if (is_writable($this->root . '/plugin/nested')) {
            self::markTestSkipped('The test process can bypass filesystem permissions.');
        }
        self::assertStringContainsString('/plugin/nested', Installer::getDestinationIssue($this->root . '/plugin'));
        self::assertFileExists($this->root . '/plugin/nested/file.php');
    }

    public function testReadOnlyFileCanBeDeletedButCannotBeOverwrittenInPlace(): void
    {
        chmod($this->root . '/plugin/nested/file.php', 0400);
        clearstatcache();
        if (is_writable($this->root . '/plugin/nested/file.php')) {
            self::markTestSkipped('The test process can bypass filesystem permissions.');
        }
        self::assertNull(Installer::getDestinationIssue($this->root . '/plugin'));
        self::assertStringContainsString('File cannot be overwritten', Installer::getDestinationIssue($this->root . '/plugin', true));
    }

    public function testFileCannotBeUsedAsPackageDirectory(): void
    {
        self::assertStringContainsString('Not a directory', Installer::getDestinationIssue($this->root . '/plugin/nested/file.php'));
    }

    public function testThemePathChecksOverwritePermissionsBeforeOpeningArchive(): void
    {
        $file = $this->root . '/user/themes/demo/template.twig';
        chmod($file, 0400);
        clearstatcache();
        if (is_writable($file)) {
            self::markTestSkipped('The test process can bypass filesystem permissions.');
        }
        self::assertFalse(Installer::install('missing.zip', $this->root, ['install_path' => 'user/themes/demo']));
        self::assertStringContainsString('File cannot be overwritten', Installer::lastErrorMsg());
        self::assertSame('original', file_get_contents($file));
    }

    public function testCoreInstallerDoesNotPreflightUnrelatedPackageDirectories(): void
    {
        chmod($this->root . '/plugin/nested', 0500);
        // An already-extracted but missing source stops the core path before changes.
        // Package permission checks must not interfere with core's ignore list.
        self::assertFalse(Installer::install('', $this->root, ['sophisticated' => true], $this->root . '/missing-source'));
        self::assertSame(Installer::INVALID_SOURCE, Installer::lastErrorCode());
    }
}
