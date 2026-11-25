<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Flex\Types\Generic\GenericCollection;
use Grav\Common\Flex\Types\Generic\GenericIndex;
use Grav\Common\Flex\Types\Generic\GenericObject;
use Grav\Common\Grav;
use Grav\Framework\File\Formatter\JsonFormatter;
use Grav\Framework\File\Formatter\YamlFormatter;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Flex\Interfaces\FlexObjectInterface;
use Grav\Framework\Flex\Storage\FileStorage;
use Grav\Framework\Flex\Storage\FolderStorage;
use Grav\Framework\Flex\Storage\SimpleStorage;

/**
 * Saving a new object must not replace an existing one. A Flex form creates an empty
 * object and fills it from the submission, and the storage names the row after
 * `storage_key` or its key field, so being allowed to create objects would otherwise
 * be enough to overwrite any object whose key is known.
 */
class FlexObjectOverwriteSecurityTest extends \PHPUnit\Framework\TestCase
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
        $this->folder = 'tmp/flex-object-overwrite-' . uniqid('', false);
        Folder::create(GRAV_ROOT . '/' . $this->folder);

        $blueprint = "title: Notes\ntype: flex-objects\nform:\n  validation: loose\n  fields:\n"
            . "    name:\n      type: text\n    title:\n      type: text\n";
        file_put_contents(GRAV_ROOT . '/' . $this->folder . '/notes.yaml', $blueprint);
    }

    protected function tearDown(): void
    {
        Folder::delete(GRAV_ROOT . '/' . $this->folder);
        parent::tearDown();
    }

    public static function storages(): array
    {
        return [
            'simple' => [SimpleStorage::class, JsonFormatter::class, '/notes.json', []],
            'file' => [FileStorage::class, YamlFormatter::class, '/notes', []],
            'folder' => [FolderStorage::class, YamlFormatter::class, '/notes', []],
        ];
    }

    public static function keyFieldStorages(): array
    {
        return [
            'simple' => [SimpleStorage::class, JsonFormatter::class, '/notes.json', ['key' => 'name'], 'first'],
            'file' => [FileStorage::class, YamlFormatter::class, '/notes', ['key' => 'name'], 'first'],
            'file, case insensitive' => [FileStorage::class, YamlFormatter::class, '/notes', ['key' => 'name', 'case_sensitive' => false], 'FIRST'],
        ];
    }

    public static function allStorages(): array
    {
        $list = self::storages();
        foreach (self::keyFieldStorages() as $name => $args) {
            $list["{$name}, key field"] = array_slice($args, 0, 4);
        }

        return $list;
    }

    private function directory(string $class, string $formatter, string $path, array $options): FlexDirectory
    {
        $storage = [
            'class' => $class,
            'options' => ['formatter' => ['class' => $formatter], 'folder' => $this->folder . $path] + $options,
        ];
        $data = [
            'object' => GenericObject::class,
            'collection' => GenericCollection::class,
            'index' => GenericIndex::class,
            'storage' => $storage,
        ];

        return new FlexDirectory('notes', GRAV_ROOT . '/' . $this->folder . '/notes.yaml', ['enabled' => true, 'data' => $data]);
    }

    private function existing(FlexDirectory $directory): FlexObjectInterface
    {
        $object = $directory->createObject(['name' => 'first', 'title' => 'First'], '');
        $object->save();
        self::assertNotSame('', $object->getStorageKey());

        return $object;
    }

    private function stored(FlexDirectory $directory, string $key): ?array
    {
        $row = $directory->getStorage()->readRows([$key => null])[$key] ?? null;
        unset($row['__META']);

        return $row;
    }

    private function assertSaveIsRefused(FlexObjectInterface $object): void
    {
        try {
            $object->save();
            self::fail('A new object was saved over an existing one');
        } catch (RuntimeException $e) {
            self::assertSame('Cannot create new object (Already exists)', $e->getMessage());
        }
    }

    /**
     * @dataProvider storages
     */
    public function testSubmittedStorageKeyIsIgnored(string $class, string $formatter, string $path, array $options): void
    {
        $directory = $this->directory($class, $formatter, $path, $options);
        $key = $this->existing($directory)->getStorageKey();
        $before = $this->stored($directory, $key);

        // What a Flex form does with a create request.
        $object = $directory->createObject([], '');
        $object->update(['name' => 'second', 'title' => 'Second', 'storage_key' => " {$key} "]);
        $object->save();

        self::assertNotSame($key, $object->getStorageKey());
        self::assertSame($before, $this->stored($directory, $key));
        self::assertSame('Second', $this->stored($directory, $object->getStorageKey())['title']);
    }

    /**
     * @dataProvider storages
     */
    public function testStorageKeyInNewObjectDataCannotReplaceObject(string $class, string $formatter, string $path, array $options): void
    {
        $directory = $this->directory($class, $formatter, $path, $options);
        $key = $this->existing($directory)->getStorageKey();
        $before = $this->stored($directory, $key);

        $object = $directory->createObject(['name' => 'second', 'title' => 'Replaced', 'storage_key' => $key], '');

        $this->assertSaveIsRefused($object);
        self::assertSame($before, $this->stored($directory, $key));
    }

    /**
     * The storage can name the rows after a field of the object, which a new object is free to fill.
     *
     * @dataProvider keyFieldStorages
     */
    public function testKeyFieldOfNewObjectCannotReplaceObject(string $class, string $formatter, string $path, array $options, string $taken): void
    {
        $directory = $this->directory($class, $formatter, $path, $options);
        $key = $this->existing($directory)->getStorageKey();
        self::assertSame('first', $key);
        $before = $this->stored($directory, $key);

        $object = $directory->createObject([], '');
        $object->update(['name' => $taken, 'title' => 'Replaced']);

        $this->assertSaveIsRefused($object);
        self::assertSame($before, $this->stored($directory, $key));
    }

    /**
     * @dataProvider allStorages
     */
    public function testNewAndExistingObjectsAreStillSaved(string $class, string $formatter, string $path, array $options): void
    {
        $directory = $this->directory($class, $formatter, $path, $options);
        $first = $this->existing($directory);
        $key = $first->getStorageKey();

        // A new object, which can be saved again.
        $second = $directory->createObject([], '');
        $second->update(['name' => 'second', 'title' => 'Second']);
        $second->save();
        $second->update(['title' => 'Second, again']);
        $second->save();
        self::assertNotSame($key, $second->getStorageKey());
        self::assertSame('Second, again', $this->stored($directory, $second->getStorageKey())['title']);

        // A new object under a key of its own choosing.
        $third = $directory->createObject(['name' => 'third', 'title' => 'Third', 'storage_key' => 'third'], '');
        $third->save();
        self::assertSame('third', $third->getStorageKey());

        // An object which was loaded from the storage.
        $loaded = $directory->getObject($key, 'storage_key');
        self::assertInstanceOf(FlexObjectInterface::class, $loaded);
        $loaded->update(['title' => 'Changed']);
        $loaded->save();
        self::assertSame('Changed', $this->stored($directory, $key)['title']);
        self::assertSame('Third', $this->stored($directory, 'third')['title']);

        // A submitted storage key does not rename an object, the key field does.
        $loaded->update(['title' => 'Changed', 'storage_key' => 'ignored']);
        $loaded->save();
        self::assertSame($key, $loaded->getStorageKey());
        self::assertFalse($directory->getStorage()->hasKey('ignored'));
        if (!isset($options['key'])) {
            return;
        }

        // An object which was loaded from the storage and is renamed to a free key.
        $loaded->update(['name' => 'renamed']);
        $loaded->save();
        self::assertSame('renamed', $loaded->getStorageKey());
        self::assertFalse($directory->getStorage()->hasKey($key));
        self::assertSame('Changed', $this->stored($directory, 'renamed')['title']);
    }
}
