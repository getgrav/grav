<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Flex\Types\Generic\GenericCollection;
use Grav\Common\Flex\Types\Generic\GenericIndex;
use Grav\Common\Flex\Types\Generic\GenericObject;
use Grav\Common\Grav;
use Grav\Framework\File\Formatter\YamlFormatter;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use Grav\Framework\Flex\Storage\FileStorage;
use Grav\Framework\Flex\Storage\FolderStorage;

/**
 * The storage key of an object names its row, its folder and its media. A storage with a key field names the row
 * after that field, so a submitted `storage_key` is neither used nor checked by it: the object must not take it
 * either, or it would work with the folder and media of another object (in any directory, with `..`) as its own.
 */
class FlexObjectSubmittedStorageKeyTest extends \PHPUnit\Framework\TestCase
{
    /** @var string */
    protected $folder;

    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        /** @var Grav $grav */
        $grav();

        // Storages resolve a plain folder against GRAV_ROOT.
        $this->folder = 'tmp/flex-object-storage-key-' . uniqid('', false);
        Folder::create(GRAV_ROOT . '/' . $this->folder);

        $blueprint = "title: Notes\ntype: flex-objects\nform:\n  validation: loose\n  fields:\n"
            . "    name:\n      type: text\n    title:\n      type: text\n"
            . "    files:\n      type: file\n      multiple: true\n      destination: 'self@'\n";
        file_put_contents(GRAV_ROOT . '/' . $this->folder . '/blueprint.yaml', $blueprint);
    }

    protected function tearDown(): void
    {
        Folder::delete(GRAV_ROOT . '/' . $this->folder);
        parent::tearDown();
    }

    public static function storages(): array
    {
        return [
            'file' => [FileStorage::class],
            'folder' => [FolderStorage::class],
        ];
    }

    private function directory(string $type, string $class, array $options = []): FlexDirectory
    {
        $storage = [
            'class' => $class,
            'options' => ['formatter' => ['class' => YamlFormatter::class], 'folder' => "{$this->folder}/{$type}"] + $options,
        ];
        $data = [
            'object' => GenericObject::class,
            'collection' => GenericCollection::class,
            'index' => GenericIndex::class,
            'storage' => $storage,
        ];

        return new FlexDirectory($type, GRAV_ROOT . '/' . $this->folder . '/blueprint.yaml', ['enabled' => true, 'data' => $data]);
    }

    private function create(FlexDirectory $directory, string $name): FlexObjectInterface
    {
        $object = $directory->createObject(['name' => $name, 'title' => ucfirst($name)], '');
        $object->save();
        self::assertSame($name, $object->getStorageKey());

        return $object;
    }

    private function stored(FlexDirectory $directory, string $key): ?array
    {
        $row = $directory->getStorage()->readRows([$key => null])[$key] ?? null;
        unset($row['__META']);

        return $row;
    }

    /**
     * Another directory next to the notes, with an object which has a media file.
     */
    private function createContract(string $class): string
    {
        $this->create($this->directory('contracts', $class, ['key' => 'name']), 'acme');

        $file = GRAV_ROOT . "/{$this->folder}/contracts/acme/signed.pdf";
        Folder::create(dirname($file));
        file_put_contents($file, 'signed');

        return $file;
    }

    /**
     * @dataProvider storages
     */
    public function testSubmittedStorageKeyDoesNotNameAnotherDirectory(string $class): void
    {
        $file = $this->createContract($class);
        $notes = $this->directory('notes', $class, ['key' => 'name']);
        $memo = $this->create($notes, 'memo');
        $folder = $memo->getMediaFolder();

        // The files of the field are deleted from the media folder of the object when it is saved.
        $memo->update(['title' => 'Changed', 'storage_key' => '../contracts/acme'], ['files' => ['signed.pdf' => null]]);

        self::assertSame('memo', $memo->getStorageKey());
        self::assertSame($folder, $memo->getMediaFolder());

        $memo->save();

        self::assertFileExists($file);
        self::assertSame('memo', $memo->getStorageKey());
        self::assertSame(['title' => 'Changed', 'name' => 'memo'], $this->stored($notes, 'memo'));
    }

    /**
     * @dataProvider storages
     */
    public function testSubmittedStorageKeyDoesNotNameAnotherObject(string $class): void
    {
        $notes = $this->directory('notes', $class, ['key' => 'name']);
        $other = $this->create($notes, 'other');
        $memo = $this->create($notes, 'memo');
        $folder = $memo->getMediaFolder();

        $memo->update(['title' => 'Changed', 'storage_key' => 'other']);

        self::assertSame('memo', $memo->getStorageKey());
        self::assertSame($folder, $memo->getMediaFolder());
        self::assertNotSame($other->getMediaFolder(), $memo->getMediaFolder());

        $memo->save();

        self::assertSame(['title' => 'Changed', 'name' => 'memo'], $this->stored($notes, 'memo'));
        self::assertSame(['title' => 'Other', 'name' => 'other'], $this->stored($notes, 'other'));
    }

    /**
     * The key field still renames the object.
     *
     * @dataProvider storages
     */
    public function testKeyFieldRenamesObject(string $class): void
    {
        $notes = $this->directory('notes', $class, ['key' => 'name']);
        $memo = $this->create($notes, 'memo');

        $memo->update(['name' => 'renamed', 'storage_key' => 'ignored'])->save();

        self::assertSame('renamed', $memo->getStorageKey());
        self::assertSame(['renamed'], array_map('strval', array_keys($notes->getStorage()->getExistingKeys())));
    }

    /**
     * Without a value in the key field the storage names the row, and a submitted storage key does not.
     *
     * @dataProvider storages
     */
    public function testStorageKeyDoesNotNameObjectWithoutKeyFieldValue(string $class): void
    {
        $notes = $this->directory('notes', $class, ['key' => 'name']);

        $object = $notes->createObject([], '');
        $object->update(['title' => 'New', 'storage_key' => 'chosen'])->save();

        self::assertNotSame('chosen', $object->getStorageKey());
        self::assertFalse($notes->getStorage()->hasKey('chosen'));

        // Taking the value out of the key field does not get a key past the storage either.
        $file = $this->createContract($class);
        $memo = $this->create($notes, 'memo');
        $folder = $memo->getMediaFolder();
        $memo->update(['name' => null, 'storage_key' => '../contracts/acme'], ['files' => ['signed.pdf' => null]]);

        self::assertSame($folder, $memo->getMediaFolder());
        $memo->save();

        self::assertFileExists($file);
    }

    /**
     * In a storage without a key field the object is named by the storage, a submitted storage key is not used.
     *
     * @dataProvider storages
     */
    public function testStorageKeyDoesNotRenameObjectWithoutKeyField(string $class): void
    {
        $file = $this->createContract($class);
        $notes = $this->directory('notes', $class);
        $memo = $notes->createObject(['title' => 'Memo'], '');
        $memo->update(['storage_key' => 'memo'])->save();
        $key = $memo->getStorageKey();
        self::assertNotSame('memo', $key);

        $memo->update(['storage_key' => 'renamed'])->save();

        self::assertSame($key, $memo->getStorageKey());
        self::assertSame([$key], array_map('strval', array_keys($notes->getStorage()->getExistingKeys())));

        $memo->update(['storage_key' => '../contracts/acme'], ['files' => ['signed.pdf' => null]]);
        $folder = $memo->getMediaFolder();
        $memo->save();

        self::assertSame($key, $memo->getStorageKey());
        self::assertSame($folder, $memo->getMediaFolder());
        self::assertFileExists($file);
    }
}
