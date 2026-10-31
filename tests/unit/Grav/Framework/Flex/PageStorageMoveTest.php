<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Flex\Types\Pages\Storage\PageStorage;
use Grav\Common\Grav;

/**
 * A page saved under another folder name is moved there, and the move is
 * refused when that folder belongs to another page. This has to hold whatever
 * the order prefix of the page's current folder looks like: the old folder is
 * the one named by the storage key, not one rebuilt from order and slug.
 */
class PageStorageMoveTest extends \PHPUnit\Framework\TestCase
{
    /** @var string */
    protected $root;

    /** @var PageStorage */
    protected $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        /** @var Grav $grav */
        $grav();

        // Page storage resolves a plain folder against GRAV_ROOT.
        $folder = 'tmp/page-storage-move-' . uniqid('', false);
        $this->root = GRAV_ROOT . '/' . $folder;
        foreach (array_merge(['taken'], array_column(self::folders(), 0)) as $name) {
            mkdir("{$this->root}/{$name}", 0777, true);
            file_put_contents("{$this->root}/{$name}/default.md", "---\ntitle: {$name}\n---\n");
        }
        $this->storage = new PageStorage([
            'folder' => $folder,
            'formatter' => ['class' => \Grav\Framework\File\Formatter\MarkdownFormatter::class],
        ]);
    }

    protected function tearDown(): void
    {
        Folder::delete($this->root);
        parent::tearDown();
    }

    public static function folders(): array
    {
        return [['02.blog'], ['1.blog'], ['00.blog'], ['005.blog'], ['0000007.blog']];
    }

    /**
     * @dataProvider folders
     */
    public function testPageCannotBeSavedOverAnotherPage(string $folder): void
    {
        $row = $this->storage->readRows([$folder => null])[$folder];

        try {
            $this->storage->replaceRows([$folder => ['order' => '', 'folder' => 'taken', 'template' => 'default', 'markdown' => 'Mine'] + $row]);
            $this->fail('The row was saved');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('already taken', $e->getMessage());
        }

        $this->assertStringContainsString('title: taken', file_get_contents("{$this->root}/taken/default.md"));
        $this->assertFileExists("{$this->root}/{$folder}/default.md");
    }

    /**
     * @dataProvider folders
     */
    public function testPageIsMovedToFreeFolder(string $folder): void
    {
        $row = $this->storage->readRows([$folder => null])[$folder];
        $this->storage->replaceRows([$folder => ['order' => '', 'folder' => 'free', 'template' => 'default'] + $row]);

        $this->assertFileExists("{$this->root}/free/default.md");
        $this->assertDirectoryDoesNotExist("{$this->root}/{$folder}");
    }

    /**
     * Saving the content of a page keeps the width of its prefix instead of writing a second folder next to it.
     */
    public function testSaveKeepsPrefixWidth(): void
    {
        $before = glob("{$this->root}/*");

        foreach (['02.blog', '1.blog', '005.blog'] as $folder) {
            $row = $this->storage->readRows([$folder => null])[$folder];
            $saved = $this->storage->replaceRows([$folder => ['markdown' => 'Changed'] + $row])[$folder];

            $this->assertSame($folder, explode('|', $saved['__META']['storage_key'])[0]);
            $this->assertStringContainsString('Changed', file_get_contents("{$this->root}/{$folder}/default.md"));
        }

        $this->assertSame($before, glob("{$this->root}/*"));
    }
}
