<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Flex\Types\Pages\Storage\PageStorage;
use Grav\Common\Grav;
use Grav\Framework\File\Formatter\MarkdownFormatter;
use Grav\Framework\Flex\Storage\FileStorage;
use Grav\Framework\Flex\Storage\FolderStorage;

/**
 * The storage folder of an object is the folder of its data file, whatever
 * characters the key has: a page folder called `blog***` does not live in
 * (or own the media and children of) the folder `blog`.
 */
class FolderStoragePathTest extends \PHPUnit\Framework\TestCase
{
    /** @var string */
    protected $folder;

    /** @var string */
    protected $root;

    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        /** @var Grav $grav */
        $grav();

        // Storage resolves a plain folder against GRAV_ROOT.
        $this->folder = 'tmp/folder-storage-path-' . uniqid('', false);
        $this->root = GRAV_ROOT . '/' . $this->folder;
    }

    protected function tearDown(): void
    {
        Folder::delete($this->root);
        parent::tearDown();
    }

    private function pageStorage(): PageStorage
    {
        return new PageStorage(['folder' => $this->folder, 'formatter' => ['class' => MarkdownFormatter::class]]);
    }

    private function createPages(string ...$names): void
    {
        if (DIRECTORY_SEPARATOR === '\\' && strpbrk(implode('', $names), '*?|:') !== false) {
            self::markTestSkipped('Windows does not allow * in a folder name.');
        }
        foreach ($names as $name) {
            mkdir("{$this->root}/{$name}", 0777, true);
            file_put_contents("{$this->root}/{$name}/default.md", "---\ntitle: {$name}\n---\n");
        }
    }

    public static function keys(): array
    {
        return [['blog'], ['blog***'], ['***'], ['blog***/post'], ['a***b***c'], ['%4$s']];
    }

    /**
     * @dataProvider keys
     */
    public function testStoragePathIsTheFolderOfTheFile(string $key): void
    {
        $storage = new FolderStorage(['folder' => $this->folder]);

        $this->assertSame(dirname($storage->getPathFromKey($key)), $storage->getStoragePath($key));
        $this->assertSame($storage->getStoragePath($key), $storage->getMediaPath($key));
    }

    public function testOtherPatterns(): void
    {
        $prefixed = new FolderStorage(['folder' => $this->folder, 'pattern' => '{FOLDER}/{KEY:2}/{KEY}/{FILE}{EXT}']);
        $this->assertSame("{$this->folder}/bl/blog***", $prefixed->getStoragePath('blog***'));
        $this->assertSame($this->folder, $prefixed->getStoragePath());

        $file = new FileStorage(['folder' => $this->folder]);
        $this->assertSame("{$this->folder}/blog***", $file->getStoragePath('blog***'));
        $this->assertSame($this->folder, $file->getStoragePath());
    }

    public function testDeletingPageKeepsItsNeighbour(): void
    {
        $this->createPages('blog', 'blog***');

        $this->pageStorage()->deleteRows(['blog***' => []]);

        $this->assertFileExists("{$this->root}/blog/default.md");
        $this->assertDirectoryDoesNotExist("{$this->root}/blog***");
    }

    public function testRenamingPageOntoItsNeighbourIsRefused(): void
    {
        $this->createPages('blog', 'blog***');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already taken');
        $this->pageStorage()->renameRow('blog***|default', 'blog|default');
    }

    public function testIndexingPagesEnds(): void
    {
        $this->createPages('***');

        $keys = array_map('strval', array_keys($this->pageStorage()->getExistingKeys()));

        $this->assertSame(['', '***'], $keys);
    }
}
