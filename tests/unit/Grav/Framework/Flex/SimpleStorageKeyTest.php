<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Framework\File\Formatter\JsonFormatter;
use Grav\Framework\Flex\Storage\SimpleStorage;

/**
 * Simple storage names a row after the key field of the saved data, which an update
 * can change. Every row it writes has to stay reachable under its key: hasKey() does
 * not find an empty key or `0`, so a row saved there is lost to reads and deletes and
 * gets replaced by the next row saved the same way. Renaming a row must not touch
 * any other row either, whether the key looks like a number or not.
 */
class SimpleStorageKeyTest extends \PHPUnit\Framework\TestCase
{
    private const ROWS = ['first' => ['title' => 'First'], 'second' => ['title' => 'Second'], 'third' => ['title' => 'Third']];

    /** @var string */
    protected $root;

    /** @var SimpleStorage */
    protected $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        /** @var Grav $grav */
        $grav();

        // Storage resolves a plain folder against GRAV_ROOT.
        $folder = 'tmp/simple-storage-key-' . uniqid('', false);
        $this->root = GRAV_ROOT . '/' . $folder;
        mkdir($this->root, 0777, true);
        file_put_contents($this->root . '/rows.json', json_encode(self::ROWS));

        $this->storage = new SimpleStorage([
            'folder' => $folder . '/rows.json',
            'formatter' => ['class' => JsonFormatter::class],
        ]);
    }

    protected function tearDown(): void
    {
        Folder::delete($this->root);
        parent::tearDown();
    }

    private function stored(): array
    {
        return json_decode(file_get_contents($this->root . '/rows.json'), true);
    }

    public function testRenameToKeyWhichCannotBeFoundIsRefused(): void
    {
        foreach (['', '0', 0, false] as $key) {
            // The way FlexObject::save() hands over an object whose storage key was updated.
            $row = ['storage_key' => $key, 'title' => 'Second', '__META' => ['storage_key' => 'second']];

            try {
                $this->storage->replaceRows(['@@1' => $row]);
                self::fail('Renaming a row to key ' . var_export($key, true) . ' should have been refused');
            } catch (InvalidArgumentException $e) {
                self::assertStringContainsString('Invalid storage key', $e->getMessage());
            }
        }

        self::assertSame(self::ROWS, $this->stored());
        self::assertSame(['first', 'second', 'third'], array_keys($this->storage->getExistingKeys()));
    }

    public function testNewRowWithKeyWhichCannotBeFoundIsRefused(): void
    {
        foreach (['', '0'] as $key) {
            try {
                $this->storage->replaceRows(['@@1' => ['storage_key' => $key, 'title' => 'New']]);
                self::fail('Saving a row under key ' . var_export($key, true) . ' should have been refused');
            } catch (InvalidArgumentException $e) {
                self::assertStringContainsString('Invalid storage key', $e->getMessage());
            }
        }

        self::assertSame(self::ROWS, $this->stored());
    }

    public function testRenameToTakenKeyIsRefused(): void
    {
        $row = ['storage_key' => 'first', 'title' => 'Second', '__META' => ['storage_key' => 'second']];

        try {
            $this->storage->replaceRows(['second' => $row]);
            self::fail('Saving over an existing row should have been refused');
        } catch (RuntimeException $e) {
            self::assertStringContainsString("key 'first' is already taken", $e->getMessage());
        }

        self::assertSame(self::ROWS, $this->stored());
    }

    public function testRenameToFreeKeyStillWorks(): void
    {
        $row = ['storage_key' => 'fourth', 'title' => 'Second', '__META' => ['storage_key' => 'second']];
        $this->storage->replaceRows(['second' => $row]);

        // The row keeps its place in the file.
        self::assertSame(
            ['first' => ['title' => 'First'], 'fourth' => ['title' => 'Second'], 'third' => ['title' => 'Third']],
            $this->stored()
        );
    }

    public function testRenameFromNumericKeyLeavesOtherRowsAlone(): void
    {
        // PHP keeps the key `7` as an integer.
        $row = ['storage_key' => '7', 'title' => 'Second', '__META' => ['storage_key' => 'second']];
        $this->storage->replaceRows(['second' => $row]);

        self::assertTrue($this->storage->hasKey('7'));
        self::assertSame('Second', $this->storage->readRows(['7' => null])['7']['title']);

        $row = ['storage_key' => 'eight', 'title' => 'Second', '__META' => ['storage_key' => '7']];
        $this->storage->replaceRows(['7' => $row]);

        self::assertSame(
            ['first' => ['title' => 'First'], 'eight' => ['title' => 'Second'], 'third' => ['title' => 'Third']],
            $this->stored()
        );
    }

    public function testKeyGivenAsNumberIsSavedAsString(): void
    {
        // A key field other than `storage_key` is not cast before it gets here.
        $row = ['storage_key' => 7, 'title' => 'Second', '__META' => ['storage_key' => 'second']];
        $result = $this->storage->replaceRows(['second' => $row]);

        self::assertSame('7', reset($result)['__META']['storage_key']);
        self::assertSame(['first', 7, 'third'], array_keys($this->stored()));
    }
}
