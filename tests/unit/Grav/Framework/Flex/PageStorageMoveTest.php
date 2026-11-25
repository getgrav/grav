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
     * The order prefix is not a part of the route, so a folder which differs from the folder of another page only in
     * its prefix is not free.
     *
     * @dataProvider routes
     */
    public function testPageCannotBeSavedToTheRouteOfAnotherPage(array $to, bool $copy): void
    {
        $before = glob("{$this->root}/*");
        $row = $this->storage->readRows(['taken' => null])['taken'];
        $row['__META']['copy'] = $copy;

        try {
            $this->storage->replaceRows(['taken' => $to + ['template' => 'default', 'markdown' => 'Mine'] + $row]);
            $this->fail('The row was saved');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('already taken', $e->getMessage());
        }

        $this->assertSame($before, glob("{$this->root}/*"));
    }

    public static function routes(): array
    {
        return [
            'move without order' => [['order' => '', 'folder' => 'blog'], false],
            'move with another order' => [['order' => 3, 'folder' => 'blog'], false],
            'move with another case' => [['order' => '', 'folder' => 'Blog'], false],
            'copy without order' => [['order' => '', 'folder' => 'blog'], true],
            'copy with another order' => [['order' => 3, 'folder' => 'taken'], true],
        ];
    }

    /**
     * A page which keeps its route can get a new order, also when another folder already has the same route.
     */
    public function testPageIsReordered(): void
    {
        $row = $this->storage->readRows(['02.blog' => null])['02.blog'];
        $this->storage->replaceRows(['02.blog' => ['order' => 9, 'template' => 'default'] + $row]);

        $this->assertFileExists("{$this->root}/09.blog/default.md");
        $this->assertDirectoryDoesNotExist("{$this->root}/02.blog");

        $row = $this->storage->readRows(['taken' => null])['taken'];
        $this->storage->replaceRows(['taken' => ['order' => 4, 'template' => 'default'] + $row]);

        $this->assertFileExists("{$this->root}/04.taken/default.md");
        $this->assertDirectoryDoesNotExist("{$this->root}/taken");
    }

    /**
     * Under another parent the page is next to other folders: keeping its own name does not make the route free there.
     */
    public function testPageCannotBeMovedToTheRouteOfAPageUnderAnotherParent(): void
    {
        mkdir("{$this->root}/taken/01.blog");
        file_put_contents("{$this->root}/taken/01.blog/default.md", "---\ntitle: taken blog\n---\n");

        $row = $this->storage->readRows(['02.blog' => null])['02.blog'];

        try {
            $this->storage->replaceRows(['02.blog' => ['parent_key' => 'taken', 'order' => '', 'template' => 'default', 'markdown' => 'Mine'] + $row]);
            $this->fail('The row was saved');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('already taken', $e->getMessage());
        }

        $this->assertFileExists("{$this->root}/02.blog/default.md");
        $this->assertSame(['01.blog', 'default.md'], array_values(array_diff(scandir("{$this->root}/taken"), ['.', '..'])));

        $this->storage->replaceRows(['02.blog' => ['parent_key' => 'taken', 'order' => '', 'folder' => 'news', 'template' => 'default'] + $row]);

        $this->assertFileExists("{$this->root}/taken/news/default.md");
        $this->assertDirectoryDoesNotExist("{$this->root}/02.blog");
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

    public static function deletedPages(): array
    {
        // The row was loaded by the key of the page folder or of the page file. Then either the whole folder was
        // deleted, or only the page file (the folder still has media in it).
        return [
            ['02.blog', true, 'default'],
            ['02.blog|default', true, 'default'],
            ['02.blog', false, 'default'],
            ['02.blog|default', false, 'default'],
            // With another template the page file of the new folder would be renamed before it is written.
            ['02.blog|default', true, 'item'],
        ];
    }

    /**
     * A row whose page has been deleted since it was loaded (a form flash keeps such rows) has nothing to move. It is
     * saved as a new page, which cannot take over the folder of another page any more than a moved page can.
     *
     * @dataProvider deletedPages
     */
    public function testRowOfDeletedPageCannotBeSavedOverAnotherPage(string $key, bool $all, string $template): void
    {
        $row = $this->deletePage($key, $all);

        try {
            $this->storage->replaceRows([$key => ['order' => '', 'folder' => 'taken', 'template' => $template, 'markdown' => 'Mine'] + $row]);
            $this->fail('The row was saved');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('already', $e->getMessage());
        }

        $this->assertSame(["{$this->root}/taken/default.md"], glob("{$this->root}/taken/*"));
        $this->assertStringContainsString('title: taken', file_get_contents("{$this->root}/taken/default.md"));
    }

    /**
     * @dataProvider deletedPages
     */
    public function testRowOfDeletedPageIsSavedToFreeFolder(string $key, bool $all, string $template): void
    {
        $row = $this->deletePage($key, $all);
        $this->storage->replaceRows([$key => ['order' => '', 'folder' => 'free', 'template' => $template, 'markdown' => 'Mine'] + $row]);

        $this->assertStringContainsString('Mine', file_get_contents("{$this->root}/free/{$template}.md"));
    }

    /**
     * Load the row of a page and delete the page before the row gets saved.
     */
    protected function deletePage(string $key, bool $all): array
    {
        $folder = explode('|', $key)[0];
        $row = $this->storage->readRows([$key => null])[$key];
        if ($all) {
            Folder::delete("{$this->root}/{$folder}");
        } else {
            unlink("{$this->root}/{$folder}/default.md");
            touch("{$this->root}/{$folder}/image.jpg");
        }
        // The row is saved by a later request, which does not have the page in the storage's cache.
        $this->storage->clearCache();

        return $row;
    }
}
