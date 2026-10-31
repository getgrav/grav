<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;

/**
 * Change detection keeps readable siblings when a descendant cannot be opened.
 */
class FolderWalkPathTooLongTest extends \Codeception\Test\Unit
{
    private $root;
    private $moved;
    private $levels;

    public static function walkers(): array
    {
        return [['lastModifiedFile'], ['lastModifiedFolder'], ['hashAllFiles']];
    }

    /** @dataProvider walkers */
    public function testWalkersContinueAfterAChildCannotBeOpened(string $method): void
    {
        $grav = \Grav\Common\Grav::instance();
        $path = $this->root . '/readable';
        Folder::create($path . '/child');
        file_put_contents($path . '/default.md', 'text');
        touch($path . '/default.md', 1700000000);
        $locator = $this->createMock(\RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator::class);
        $locator->method('isStream')->willReturn(true);
        $locator->method('getRecursiveIterator')->willReturnCallback(static function () use ($path) {
            return new class($path, RecursiveDirectoryIterator::SKIP_DOTS) extends RecursiveDirectoryIterator {
                public function getChildren(): RecursiveDirectoryIterator
                {
                    throw new UnexpectedValueException('Cannot open child directory.');
                }
            };
        });
        unset($grav['locator']);
        $grav['locator'] = static fn() => $locator;

        $before = Folder::$method([$path]);
        if ($method === 'lastModifiedFile') {
            self::assertSame(1700000000, $before);
        } elseif ($method === 'lastModifiedFolder') {
            touch($path . '/child', 2000000000);
            clearstatcache();
            self::assertSame(2000000000, Folder::$method([$path]));
        } else {
            touch($path . '/default.md', 1800000000);
            clearstatcache();
            self::assertNotSame($before, Folder::$method([$path]));
        }
    }

    protected function _before(): void
    {
        $grav = Fixtures::get('grav');
        $grav();
        $this->root = GRAV_ROOT . '/tests/_output/folder-walk-' . bin2hex(random_bytes(4));
        Folder::create($this->root);
    }

    private function createLongTree(): void
    {
        $this->levels = str_repeat('/' . str_repeat('a', 100), (int) ceil(PHP_MAXPATHLEN / 202));

        if (strlen($this->root . '/shelf' . $this->levels . '/box') >= PHP_MAXPATHLEN) {
            $this->markTestSkipped('The platform path limit is too small for this fixture.');
        }
        Folder::create($this->root . '/shelf' . $this->levels);
        Folder::create($this->root . '/box' . $this->levels);
        file_put_contents($this->root . '/shelf/default.md', 'text');
        touch($this->root . '/shelf/default.md', 1700000000);
        Folder::create($this->root . '/readable');
        touch($this->root . '/readable', 2000000000);
        $this->moved = $this->root . '/shelf' . $this->levels . '/box';
        if (!@rename($this->root . '/box', $this->moved)) {
            $this->moved = null;
            $this->markTestSkipped('The filesystem does not permit this directory move.');
        }
        if (@scandir($this->moved . $this->levels) !== false) {
            $this->markTestSkipped('The filesystem can open paths beyond PHP_MAXPATHLEN.');
        }
    }

    protected function _after(): void
    {
        if ($this->moved) {
            rename($this->moved, $this->root . '/box');
        }
        if ($this->root && is_dir($this->root)) {
            Folder::delete($this->root);
        }
    }

    public function testLastModifiedFileKeepsReadableSiblings(): void
    {
        $this->createLongTree();
        self::assertSame(1700000000, Folder::lastModifiedFile([$this->root]));
    }

    public function testLastModifiedFolderKeepsReadableSiblings(): void
    {
        $this->createLongTree();
        self::assertSame(2000000000, Folder::lastModifiedFolder([$this->root]));
    }

    public function testHashAllFilesKeepsReadableSiblings(): void
    {
        $this->createLongTree();
        $before = Folder::hashAllFiles([$this->root]);
        touch($this->root . '/shelf/default.md', 1800000000);
        clearstatcache();
        self::assertNotSame($before, Folder::hashAllFiles([$this->root]));
    }
}
