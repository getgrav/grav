<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Flex\Types\Pages\Storage\PageStorage;
use Grav\Common\Grav;

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

    public function testSavesAPlainPage(): void
    {
        $this->save(['parent_key' => '01.blog', 'folder' => 'post', 'template' => 'default']);

        // `default.md`, or `default.en.md` when the default language is written out.
        $this->assertNotEmpty(glob($this->root . '/pages/01.blog/post/default*.md'));
    }
}
