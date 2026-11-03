<?php

use Grav\Common\Config\InstallLayout;
use Grav\Common\Filesystem\Folder;

/**
 * Layout detection for the optional public webroot layout, where GRAV_ROOT is private and
 * only a subfolder of it is served by the web server.
 */
class InstallLayoutTest extends \PHPUnit\Framework\TestCase
{
    /** @var string */
    private $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/grav-layout-' . bin2hex(random_bytes(6));
        mkdir($this->root);
    }

    protected function tearDown(): void
    {
        Folder::delete($this->root);
    }

    public function testSingleFolderLayoutHasNoSeparateWebroot(): void
    {
        $this->site($this->root);
        // Even with a candidate folder next to it, a root that has index.php is the webroot.
        $this->site($this->root . '/public');

        self::assertNull(InstallLayout::detectWebroot($this->root));
    }

    public function testPublicFolderIsFoundWhenRootHasNoIndex(): void
    {
        $this->site($this->root . '/public');

        self::assertSame($this->root . '/public', InstallLayout::detectWebroot($this->root));
        self::assertSame($this->root . '/public', InstallLayout::detectWebroot($this->root . '/'));
    }

    public function testCandidatesAreTriedInOrder(): void
    {
        $this->site($this->root . '/www');
        $this->site($this->root . '/public_html');

        self::assertSame($this->root . '/public_html', InstallLayout::detectWebroot($this->root));
    }

    public function testCandidateNeedsBothIndexAndUserFolder(): void
    {
        mkdir($this->root . '/public');
        file_put_contents($this->root . '/public/index.php', '<?php');
        mkdir($this->root . '/web/user', 0777, true);
        $this->site($this->root . '/htdocs');

        self::assertSame($this->root . '/htdocs', InstallLayout::detectWebroot($this->root));
    }

    public function testCustomUserFolderIsChecked(): void
    {
        mkdir($this->root . '/public/site', 0777, true);
        file_put_contents($this->root . '/public/index.php', '<?php');

        self::assertNull(InstallLayout::detectWebroot($this->root));
        self::assertSame($this->root . '/public', InstallLayout::detectWebroot($this->root, 'site'));
    }

    public function testNothingFoundReturnsNull(): void
    {
        self::assertNull(InstallLayout::detectWebroot($this->root));
        self::assertNull(InstallLayout::detectWebroot($this->root, 'user', []));
    }

    public function testAbsolutePathDetection(): void
    {
        self::assertTrue(InstallLayout::isAbsolute('/srv/grav/cache'));
        self::assertTrue(InstallLayout::isAbsolute('C:/grav/cache'));
        self::assertTrue(InstallLayout::isAbsolute('c:\\grav\\cache'));
        self::assertFalse(InstallLayout::isAbsolute('cache'));
        self::assertFalse(InstallLayout::isAbsolute('../cache'));
    }

    public function testResolveJoinsOnlyRelativePaths(): void
    {
        self::assertSame('/srv/grav/cache', InstallLayout::resolve('/srv/grav', 'cache'));
        self::assertSame('/srv/grav/cache', InstallLayout::resolve('/srv/grav/', 'cache'));
        self::assertSame('/var/cache/grav', InstallLayout::resolve('/srv/grav', '/var/cache/grav'));
    }

    public function testRootPathIsUnchangedInSingleFolderLayout(): void
    {
        // The test suite runs in the single-folder layout.
        self::assertSame(GRAV_ROOT, GRAV_WEBROOT);
        self::assertFalse(InstallLayout::isSplit());
        self::assertSame('cache', InstallLayout::rootPath('cache'));
        self::assertSame('/var/cache/grav', InstallLayout::rootPath('/var/cache/grav'));
    }

    private function site(string $dir): void
    {
        @mkdir($dir . '/user', 0777, true);
        file_put_contents($dir . '/index.php', '<?php');
    }
}
