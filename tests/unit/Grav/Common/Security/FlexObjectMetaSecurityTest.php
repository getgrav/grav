<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Framework\File\Formatter\YamlFormatter;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Flex\Storage\FileStorage;
use Grav\Framework\Flex\Storage\FolderStorage;

/**
 * `__META` is how the storage layer tells an object where it is stored. Data
 * given to FlexDirectory::createObject() describes a new object and can come
 * from a request, so it must not be able to claim the storage key of another
 * object or of a file outside of the storage folder.
 */
class FlexObjectMetaSecurityTest extends \PHPUnit\Framework\TestCase
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
        $this->folder = 'tmp/flex-object-meta-' . uniqid('', false);
    }

    protected function tearDown(): void
    {
        Folder::delete(GRAV_ROOT . '/' . $this->folder);
        parent::tearDown();
    }

    private function directory(): FlexDirectory
    {
        return new FlexDirectory('user-groups', 'blueprints://flex/user-groups.yaml', ['enabled' => true]);
    }

    public static function validate(): array
    {
        return ['as is' => [false], 'validated' => [true]];
    }

    /**
     * @dataProvider validate
     */
    public function testCreateObjectIgnoresMeta(bool $validate): void
    {
        $data = ['__META' => ['storage_key' => 'admins', 'copy' => true], 'groupname' => 'new'];
        $object = $this->directory()->createObject($data, '', $validate);

        self::assertSame('', $object->getStorageKey());
        self::assertSame([], $object->getMetaData());
        self::assertSame([], $object->prepareStorage()['__META']);
        self::assertSame('new', $object->getProperty('groupname'));
    }

    public function testRowFromTheStorageKeepsMeta(): void
    {
        $row = ['__META' => ['storage_key' => 'admins', 'storage_timestamp' => 1], 'groupname' => 'admins'];
        $object = $this->directory()->createObjectFromStorage($row, 'admins');

        self::assertSame('admins', $object->getStorageKey());
        self::assertSame(1, $object->getTimestamp());
    }

    public static function storages(): array
    {
        return [
            'file, moved' => [FileStorage::class, '/outside.yaml', false],
            'file, copied' => [FileStorage::class, '/outside.yaml', true],
            'folder, moved' => [FolderStorage::class, '/outside/item.yaml', false],
            'folder, copied' => [FolderStorage::class, '/outside/item.yaml', true],
        ];
    }

    /**
     * @dataProvider storages
     */
    public function testStorageRefusesAnOldKeyOutsideOfItsFolder(string $class, string $path, bool $copy): void
    {
        $outside = GRAV_ROOT . '/' . $this->folder . $path;
        Folder::create(dirname($outside));
        file_put_contents($outside, "title: Outside\n");

        $storage = new $class(['formatter' => ['class' => YamlFormatter::class], 'folder' => $this->folder . '/notes']);

        try {
            $meta = ['storage_key' => '../outside', 'copy' => $copy];
            $storage->replaceRows(['../outside' => ['__META' => $meta, 'storage_key' => 'inside', 'title' => 'Inside']]);
            $this->fail('The storage accepted an old key with a path in it');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('Invalid storage key', $e->getMessage());
        }

        self::assertFileExists($outside);
        self::assertFalse($storage->hasKey('inside'));
    }
}
