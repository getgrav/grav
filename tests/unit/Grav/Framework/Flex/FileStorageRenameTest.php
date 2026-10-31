<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Framework\File\Formatter\YamlFormatter;
use Grav\Framework\Flex\Storage\FileStorage;

/**
 * Renaming a row in file storage removes the old file and lets the save write the
 * new one, so it has to refuse a key which belongs to another row, like copying
 * and the folder storage do.
 */
class FileStorageRenameTest extends \PHPUnit\Framework\TestCase
{
    /** @var string */
    protected $root;

    /** @var FileStorage */
    protected $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        /** @var Grav $grav */
        $grav();

        // Storage resolves a plain folder against GRAV_ROOT.
        $folder = 'tmp/file-storage-rename-' . uniqid('', false);
        $this->root = GRAV_ROOT . '/' . $folder;
        mkdir($this->root, 0777, true);
        file_put_contents($this->root . '/first.yaml', "title: First\n");
        file_put_contents($this->root . '/second.yaml', "title: Second\n");

        $this->storage = new FileStorage([
            'folder' => $folder,
            'pattern' => '{FOLDER}/{KEY}{EXT}',
            'formatter' => ['class' => YamlFormatter::class],
            'key' => 'name',
            'case_sensitive' => false,
        ]);
    }

    protected function tearDown(): void
    {
        Folder::delete($this->root);
        parent::tearDown();
    }

    public function testRenameToTakenKeyIsRefused(): void
    {
        try {
            $this->storage->renameRow('first', 'second');
            self::fail('Renaming over an existing row should have been refused');
        } catch (RuntimeException $e) {
            self::assertStringContainsString("key 'second' is already taken", $e->getMessage());
        }

        self::assertStringEqualsFile($this->root . '/first.yaml', "title: First\n");
        self::assertStringEqualsFile($this->root . '/second.yaml', "title: Second\n");
    }

    public function testSavingRowUnderTakenKeyIsRefused(): void
    {
        $row = ['name' => 'second', 'title' => 'Changed', '__META' => ['storage_key' => 'first']];

        try {
            $this->storage->replaceRows(['first' => $row]);
            self::fail('Saving over an existing row should have been refused');
        } catch (RuntimeException $e) {
            self::assertStringContainsString("key 'second' is already taken", $e->getMessage());
        }

        self::assertStringEqualsFile($this->root . '/first.yaml', "title: First\n");
        self::assertStringEqualsFile($this->root . '/second.yaml', "title: Second\n");
    }

    public function testRenameToFreeKeyStillWorks(): void
    {
        $row = ['name' => 'third', 'title' => 'First', '__META' => ['storage_key' => 'first']];
        $this->storage->replaceRows(['first' => $row]);

        self::assertFileDoesNotExist($this->root . '/first.yaml');
        self::assertFileExists($this->root . '/third.yaml');
    }

    public function testRenameWhichOnlyChangesCaseStillWorks(): void
    {
        rename($this->root . '/first.yaml', $this->root . '/First.yaml');

        $row = ['name' => 'First', 'title' => 'First', '__META' => ['storage_key' => 'First']];
        $this->storage->replaceRows(['First' => $row]);

        self::assertFileExists($this->root . '/first.yaml');
        self::assertCount(2, glob($this->root . '/*.yaml'));
    }
}
