<?php

use Grav\Common\Filesystem\Folder;
use Grav\Common\GPM\Installer;

class InstallerReplaceFileTest extends \PHPUnit\Framework\TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/grav-replace-file-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/package', 0700, true);
        mkdir($this->root . '/site', 0700, true);
        mkdir($this->root . '/repo', 0700, true);
        file_put_contents($this->root . '/package/index.php', 'new');
        file_put_contents($this->root . '/site/index.php', 'old');
        file_put_contents($this->root . '/repo/index.php', 'repo');
    }

    protected function tearDown(): void
    {
        @chmod($this->root . '/site', 0700);
        @chmod($this->root . '/site/index.php', 0600);
        Folder::delete($this->root);
    }

    public function testReplacesAnExistingFileWithoutLeavingATempFile(): void
    {
        self::assertTrue(Installer::replaceFile($this->root . '/package/index.php', $this->root . '/site/index.php'));
        self::assertSame('new', file_get_contents($this->root . '/site/index.php'));
        self::assertSame(['index.php'], $this->siteEntries());
    }

    public function testCreatesAFileTheSiteDoesNotHaveYet(): void
    {
        unlink($this->root . '/site/index.php');
        self::assertTrue(Installer::replaceFile($this->root . '/package/index.php', $this->root . '/site/index.php'));
        self::assertSame('new', file_get_contents($this->root . '/site/index.php'));
    }

    public function testReplacesASymlinkWithoutWritingThroughIt(): void
    {
        unlink($this->root . '/site/index.php');
        symlink($this->root . '/repo/index.php', $this->root . '/site/index.php');

        self::assertTrue(Installer::replaceFile($this->root . '/package/index.php', $this->root . '/site/index.php'));
        self::assertFalse(is_link($this->root . '/site/index.php'));
        self::assertSame('new', file_get_contents($this->root . '/site/index.php'));
        self::assertSame('repo', file_get_contents($this->root . '/repo/index.php'));
    }

    public function testAFailedReplaceKeepsTheOriginal(): void
    {
        chmod($this->root . '/site/index.php', 0400);
        chmod($this->root . '/site', 0500);
        clearstatcache();
        if (is_writable($this->root . '/site')) {
            self::markTestSkipped('The test process can bypass filesystem permissions.');
        }

        self::assertFalse(Installer::replaceFile($this->root . '/package/index.php', $this->root . '/site/index.php'));
        self::assertSame('old', file_get_contents($this->root . '/site/index.php'));
        self::assertSame(['index.php'], $this->siteEntries());
    }

    /**
     * @return string[]
     */
    private function siteEntries(): array
    {
        return array_values(array_diff(scandir($this->root . '/site') ?: [], ['.', '..']));
    }
}
