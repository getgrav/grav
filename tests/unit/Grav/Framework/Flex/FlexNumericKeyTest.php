<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Framework\Flex\Flex;
use Grav\Framework\Flex\FlexDirectory;
use Grav\Framework\Flex\Storage\FolderStorage;

/**
 * PHP stores a key made of digits only, such as "20261", as an integer, and array_merge()
 * renumbers integer keys from 0. Lists of objects which are merged together have to keep
 * such an object under its own key, not under its position among the numeric ones.
 */
class FlexNumericKeyTest extends \PHPUnit\Framework\TestCase
{
    /** @var string */
    protected $folder;

    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        /** @var Grav $grav */
        $grav();

        // Storage resolves a plain folder against GRAV_ROOT.
        $this->folder = 'tmp/flex-numeric-key-' . uniqid('', false);
    }

    protected function tearDown(): void
    {
        Folder::delete(GRAV_ROOT . '/' . $this->folder);
        parent::tearDown();
    }

    public static function patterns(): array
    {
        return [
            'plain' => ['{FOLDER}/{KEY}/{FILE}{EXT}'],
            'prefixed' => ['{FOLDER}/{KEY:2}/{KEY}/{FILE}{EXT}'],
        ];
    }

    /**
     * @dataProvider patterns
     */
    public function testFolderStorageIndexKeepsNumericKeys(string $pattern): void
    {
        $storage = new FolderStorage(['folder' => $this->folder, 'pattern' => $pattern]);
        $storage->replaceRows([
            '20261' => ['title' => 'First'],
            'alice' => ['title' => 'Alice'],
            '20262' => ['title' => 'Second'],
            '31007' => ['title' => 'Third'],
        ]);

        // Build the index from the filesystem.
        $storage = new FolderStorage(['folder' => $this->folder, 'pattern' => $pattern]);
        $index = $storage->getExistingKeys();

        $this->assertSame(['20261', '20262', '31007', 'alice'], array_map('strval', array_keys($index)));
        foreach ($index as $key => $meta) {
            $this->assertSame((string)$key, $meta['storage_key']);
        }
        $this->assertSame('Third', $storage->readRows(['31007' => null])['31007']['title']);
    }

    public function testGetObjectsKeepsNumericKeys(): void
    {
        $keys = ['31007', 'alice', '20261'];
        foreach ($keys as $key) {
            $path = GRAV_ROOT . "/{$this->folder}/{$key}";
            mkdir($path, 0777, true);
            file_put_contents("{$path}/default.md", "---\ntitle: Page\n---\n");
        }

        $flex = new Flex([], []);
        $flex->addDirectory(new FlexDirectory('pages', 'blueprints://flex/pages.yaml', [
            'enabled' => true,
            'data' => ['storage' => ['options' => ['folder' => $this->folder, 'indexed' => false]]],
            'object' => ['cache' => ['index' => ['enabled' => false], 'object' => ['enabled' => false], 'render' => ['enabled' => false]]],
        ]));

        $objects = $flex->getObjects($keys, ['type' => 'pages', 'key_field' => 'storage_key']);

        $this->assertSame($keys, array_map('strval', array_keys($objects)));
        foreach ($objects as $key => $object) {
            $this->assertSame((string)$key, $object->getStorageKey());
        }
    }
}
