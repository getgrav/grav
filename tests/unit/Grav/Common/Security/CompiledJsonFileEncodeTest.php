<?php

use Codeception\Util\Fixtures;
use Grav\Common\File\CompiledJsonFile;
use Grav\Common\Filesystem\Folder;
use RocketTheme\Toolbox\File\PhpFile;
use Grav\Framework\File\Formatter\JsonFormatter;
use Grav\Framework\Flex\FlexIndex;
use Grav\Framework\Flex\Interfaces\FlexStorageInterface;
use Grav\Framework\Flex\Storage\SimpleStorage;

/**
 * Class CompiledJsonFileEncodeTest
 *
 * Covers: data which json_encode() refuses (INF, NAN, a string which is not UTF-8, too deep nesting).
 * The failed encode used to be saved as an empty file, so one such value in a new Flex object emptied
 * the JSON file which holds every object of the directory. The save is refused instead, and what is
 * saved can be read back.
 */
class CompiledJsonFileEncodeTest extends \PHPUnit\Framework\TestCase
{
    /** @var string Folder of the file, relative to GRAV_ROOT as Flex storage resolves it. */
    protected $folder;

    /** @var string */
    protected $filename;

    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        $grav();

        $this->folder = 'tmp/json-encode-' . bin2hex(random_bytes(4));
        $this->filename = GRAV_ROOT . "/{$this->folder}/data.json";
        mkdir(GRAV_ROOT . "/{$this->folder}", 0777, true);
    }

    protected function tearDown(): void
    {
        CompiledJsonFile::instance($this->filename)->free();
        Folder::delete(GRAV_ROOT . "/{$this->folder}");
        parent::tearDown();
    }

    protected function save(array $data): void
    {
        $file = CompiledJsonFile::instance($this->filename);
        try {
            $file->save($data);
        } finally {
            $file->free();
        }
    }

    protected function read(): array
    {
        $file = CompiledJsonFile::instance($this->filename);
        $content = $file->content();
        $file->free();

        return $content;
    }

    protected static function nested(int $levels): array
    {
        $value = [1];
        while (--$levels > 0) {
            $value = [$value];
        }

        return $value;
    }

    public static function providerUnencodable(): array
    {
        return [
            'INF' => [INF],
            'NAN' => [NAN],
            'not UTF-8' => ["caf\xe9"],
            // The file adds two levels, which makes 512: json_encode() takes that by default, json_decode() does not.
            'too deep' => [self::nested(510)],
        ];
    }

    /**
     * @dataProvider providerUnencodable
     * @param mixed $value
     */
    public function testFileIsKeptWhenDataCannotBeEncoded($value): void
    {
        $this->save(['first' => ['name' => 'Alice']]);
        $saved = file_get_contents($this->filename);

        try {
            $this->save(['first' => ['name' => 'Alice'], 'second' => ['name' => 'Mallory', 'score' => $value]]);
            self::fail('Data which cannot be encoded was saved');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('Encoding JSON failed', $e->getMessage());
        }

        self::assertStringEqualsFile($this->filename, $saved);
        self::assertSame(['first' => ['name' => 'Alice']], $this->read());
    }

    public function testCompiledCacheIsNotLockedAfterFailedSave(): void
    {
        $this->save(['first' => ['name' => 'Alice']]);

        try {
            $this->save(['first' => ['score' => INF]]);
            self::fail('Data which cannot be encoded was saved');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('Encoding JSON failed', $e->getMessage());
        }

        $cache = PhpFile::instance(CACHE_DIR . 'compiled/files/' . md5($this->filename) . '.json.php');
        self::assertFalse((bool)$cache->locked());
    }

    public function testDeepestSavedDataIsReadBack(): void
    {
        $data = ['first' => ['name' => 'Alice', 'deep' => self::nested(509)]];
        $this->save($data);

        self::assertSame($data, $this->read());
    }

    public function testFlexStorageKeepsRowsWhenNewRowCannotBeEncoded(): void
    {
        $options = ['folder' => "{$this->folder}/data.json", 'formatter' => ['class' => JsonFormatter::class]];

        $storage = new SimpleStorage($options);
        $storage->replaceRows(['alice' => ['name' => 'Alice']]);
        $saved = file_get_contents($this->filename);
        self::assertNotSame('', $saved);

        try {
            $storage->createRows(['mallory' => ['name' => 'Mallory', 'score' => INF]]);
            self::fail('Row which cannot be encoded was saved');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('Encoding JSON failed', $e->getMessage());
        }

        self::assertStringEqualsFile($this->filename, $saved);

        $storage = new SimpleStorage($options);
        self::assertTrue($storage->hasKey('alice'));
        self::assertFalse($storage->hasKey('mallory'));
    }

    public function testIndexWhichCannotBeSavedIsStillUsed(): void
    {
        // The saved index is a cache of the storage: a key which JSON cannot hold must not stop the request.
        $index = ['version' => '1', 'timestamp' => 1, 'count' => 0, 'index' => []];
        $this->save($index);
        $saved = file_get_contents($this->filename);

        $storage = new SimpleStorage(['folder' => "{$this->folder}/rows.json", 'formatter' => ['class' => JsonFormatter::class]]);
        $key = "caf\xe9";
        $entries = [$key => ['storage_key' => $key, 'storage_timestamp' => 1]];

        $indexClass = new class ([]) extends FlexIndex {
            /** @var string */
            public static $indexFilename;

            public static function update(FlexStorageInterface $storage, array $index, array $entries): array
            {
                return static::updateIndexFile($storage, $index, $entries, ['include_missing' => true]);
            }

            protected static function getIndexFile(FlexStorageInterface $storage)
            {
                return CompiledJsonFile::instance(static::$indexFilename);
            }
        };
        $indexClass::$indexFilename = $this->filename;

        $result = $indexClass::update($storage, [], $entries);

        self::assertSame([$key], array_keys($result));
        self::assertStringEqualsFile($this->filename, $saved);
        self::assertFalse((bool)CompiledJsonFile::instance($this->filename)->locked());
    }
}
