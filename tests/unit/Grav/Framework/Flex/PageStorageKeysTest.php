<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Flex\Types\Pages\PageObject;
use Grav\Common\Flex\Types\Pages\Storage\PageStorage;
use Grav\Common\Grav;
use Grav\Framework\Flex\Flex;

/**
 * A page's folder, template and language are parts of its file path, so the
 * page storage refuses values that are not plain names instead of writing the
 * file somewhere else.
 */
class PageStorageKeysTest extends \PHPUnit\Framework\TestCase
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
        $folder = 'tmp/page-storage-keys-' . uniqid('', false);
        $this->root = GRAV_ROOT . '/' . $folder;
        mkdir($this->root . '/pages/01.blog', 0777, true);
        $this->storage = new PageStorage([
            'folder' => $folder . '/pages',
            'formatter' => ['class' => \Grav\Framework\File\Formatter\MarkdownFormatter::class],
        ]);
    }

    protected function tearDown(): void
    {
        Folder::delete($this->root);
        parent::tearDown();
    }

    private function save(array $row): void
    {
        $method = new ReflectionMethod(PageStorage::class, 'saveRow');
        $method->invoke($this->storage, 'new', $row + ['header' => ['title' => 'Test'], 'markdown' => 'Body']);
    }

    public static function invalidRows(): array
    {
        return [
            'template climbing out' => [['parent_key' => '01.blog', 'folder' => 'post', 'template' => '../../../outside']],
            'template with a separator' => [['parent_key' => '01.blog', 'folder' => 'post', 'template' => 'sub/default']],
            'folder climbing out' => [['parent_key' => '01.blog', 'folder' => '..', 'template' => 'default']],
            'folder with a separator' => [['parent_key' => '01.blog', 'folder' => '../post', 'template' => 'default']],
            'parent with a dot segment' => [['parent_key' => '01.blog/..', 'folder' => 'post', 'template' => 'default']],
            'language with a separator' => [['parent_key' => '01.blog', 'folder' => 'post', 'template' => 'default', 'lang' => 'en/../../x']],
        ];
    }

    /**
     * @dataProvider invalidRows
     */
    public function testRefusesKeysThatAreNotNames(array $row): void
    {
        try {
            $this->save($row);
            $this->fail('The row was saved');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString("Invalid page", $e->getMessage());
        }

        $written = array_filter(
            iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS))),
            static fn (SplFileInfo $file): bool => $file->isFile()
        );
        $this->assertSame([], array_keys($written));
        $this->assertSame([], glob(GRAV_ROOT . '/tmp/outside*.md') ?: []);
    }

    public static function invalidStorageKeys(): array
    {
        return [
            'parent folder' => ['../outside'],
            'parent folder below a page' => ['01.blog/../../outside'],
            'dot segment' => ['01.blog/.'],
            'backslash' => ['01.blog\\..\\..\\outside'],
        ];
    }

    /**
     * The storage key of an existing page becomes its folder, its media folder
     * and its file, so it is held to the same rule wherever it is turned into a path.
     *
     * @dataProvider invalidStorageKeys
     */
    public function testRefusesStorageKeysWithDotSegments(string $key): void
    {
        mkdir($this->root . '/outside');
        touch($this->root . '/outside/default.md');

        $calls = [
            'hasKey' => [$key],
            'getStoragePath' => [$key],
            'getMediaPath' => [$key],
            'getPathFromKey' => [$key],
            'readRaw' => [$key],
            'readFrontmatter' => [$key],
            'readRows' => [[$key => null]],
            'deleteRows' => [[$key => []]],
            'copyRow' => [$key, '01.blog/copy'],
            'renameRow' => [$key, '01.blog/moved'],
        ];
        foreach ($calls as $method => $args) {
            try {
                $this->storage->{$method}(...$args);
                $this->fail("{$method}() accepted the key");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('Invalid page storage key', $e->getMessage());
            }
        }

        $this->assertFileExists($this->root . '/outside/default.md');
        $this->assertSame([], glob($this->root . '/pages/01.blog/*') ?: []);
    }

    /**
     * The part after `|` names the page file inside the page folder.
     */
    public function testRefusesAFilePartThatIsNotAName(): void
    {
        mkdir($this->root . '/outside');
        touch($this->root . '/outside/default.md');

        foreach (['01.blog|../../outside/default', '01.blog|sub/default', '01.blog|sub\\default'] as $key) {
            $calls = [
                'getStoragePath' => [$key],
                'getPathFromKey' => [$key],
                'readRaw' => [$key],
                'readFrontmatter' => [$key],
                'readRows' => [[$key => null]],
            ];
            foreach ($calls as $method => $args) {
                try {
                    $this->storage->{$method}(...$args);
                    $this->fail("{$method}() accepted the key");
                } catch (RuntimeException $e) {
                    $this->assertStringContainsString('Invalid page storage key', $e->getMessage());
                }
            }
        }
    }

    public function testAcceptsTheKeysOfExistingPages(): void
    {
        mkdir($this->root . '/pages/01.blog/my..post');
        touch($this->root . '/pages/01.blog/my..post/default.en.md');

        $this->assertTrue($this->storage->hasKey('01.blog'));
        $this->assertTrue($this->storage->hasKey('/01.blog/my..post'));
        $this->assertTrue($this->storage->hasKey('01.blog/my..post|default.en'));
        $this->assertStringEndsWith('/pages/01.blog/my..post', $this->storage->getMediaPath('01.blog/my..post'));
        $this->assertStringEndsWith('/pages/01.blog/my..post/default.en.md', $this->storage->getPathFromKey('01.blog/my..post|default.en'));
        $this->assertSame(['', '01.blog', '01.blog/my..post'], array_keys($this->storage->getExistingKeys()));
    }

    /**
     * The storage key of a page follows from its parent, order and folder, never
     * from submitted data.
     */
    public function testUpdateIgnoresASubmittedStorageKey(): void
    {
        $flex = new Flex([], ['object' => Grav::instance()['config']->get('system.flex', [])]);
        $flex->addDirectoryType('pages', 'blueprints://flex/pages.yaml', ['enabled' => true]);

        $meta = ['key' => 'blog', 'storage_key' => '01.blog', 'template' => 'default'];
        $page = new PageObject(['__META' => $meta, 'header' => ['title' => 'Blog'], 'markdown' => 'Body'], 'blog', $flex->getDirectory('pages'));
        $page->update(['storage_key' => '02.other', 'header' => ['title' => 'Changed']]);

        $this->assertSame('01.blog', $page->getStorageKey());
        $this->assertSame('Changed', $page->getNestedProperty('header.title'));
    }

    public function testSavesAPlainPage(): void
    {
        $this->save(['parent_key' => '01.blog', 'folder' => 'post', 'template' => 'default']);

        // `default.md`, or `default.en.md` when the default language is written out.
        $this->assertNotEmpty(glob($this->root . '/pages/01.blog/post/default*.md'));
    }
}
