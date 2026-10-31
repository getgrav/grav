<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Framework\Form\FormFlash;

/**
 * Class FormFlashIndexSizeTest
 *
 * The flash index is written in block style YAML to `index-v2.yaml`, its size
 * and nesting depth are limited, and an `index.yaml` written by an earlier
 * version is not read.
 */
class FormFlashIndexSizeTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;
    /** @var array */
    protected $config = ['session_id' => 'indexsizetest', 'unique_id' => 'abcdef1234567890', 'form_name' => 'test'];

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
    }

    protected function tearDown(): void
    {
        (new FormFlash($this->config))->delete();
        parent::tearDown();
    }

    /**
     * @dataProvider providerData
     */
    public function testSave_DataIsLoadedBackAsItWas(array $data): void
    {
        $flash = new FormFlash($this->config);
        $flash->setData($data);
        $flash->save();

        $flash = new FormFlash($this->config);

        $this->assertTrue($flash->exists());
        $this->assertSame($data, $flash->getData());
    }

    public static function providerData(): array
    {
        return [
            'scalars' => [['s' => "a'b", 'i' => 1, 'f' => 1.0, 't' => true, 'n' => null, 'e' => '', 'u' => 'äö€', 'd' => '1.0']],
            'lists and maps' => [['l' => ['a', 'b'], 'm' => ['a' => 1, 'b' => 2], 'g' => [1 => 'a', 3 => 'b'], 'e' => []]],
            'nested list' => [['x' => ['a' => ['b' => ['c' => ["a'b", "a'b", "a'b"]]]]]],
        ];
    }

    public function testSave_DeeplyNestedListIsWrittenInBlockStyle(): void
    {
        $flash = new FormFlash($this->config);
        $flash->setData(['x' => ['a' => ['b' => ['c' => array_fill(0, 10000, "a'b")]]]]);
        $flash->save();

        $index = (string)file_get_contents($flash->getTmpDir() . '/index-v2.yaml');
        $lines = explode("\n", $index);

        $this->assertGreaterThan(10000, count($lines));
        $this->assertLessThan(100, max(array_map('strlen', $lines)));
        $this->assertCount(10000, (new FormFlash($this->config))->getData()['x']['a']['b']['c']);
    }

    public function testSave_OversizedIndexIsRefused(): void
    {
        $flash = new FormFlash($this->config);
        $flash->setData(['x' => 'small']);
        $flash->save();

        $flash->setData(['x' => str_repeat('a', 1048577)]);
        try {
            $flash->save();
            $this->fail('Oversized index was saved');
        } catch (RuntimeException $e) {
            $this->assertSame('Form flash data is too large', $e->getMessage());
        }

        // Previously saved state is still there.
        $this->assertSame(['x' => 'small'], (new FormFlash($this->config))->getData());
    }

    public function testSave_TooDeeplyNestedDataIsRefused(): void
    {
        $nest = static function (array $data, int $depth): array {
            for ($i = 0; $i < $depth; $i++) {
                $data = ['k' => $data];
            }

            return $data;
        };

        // The index adds one level on top of the data.
        $flash = new FormFlash($this->config);
        $flash->setData($nest(["a'b"], 61));
        $flash->save();

        $this->assertSame($nest(["a'b"], 61), (new FormFlash($this->config))->getData());

        $flash->setData($nest(["a'b"], 64));
        try {
            $flash->save();
            $this->fail('Too deeply nested data was saved');
        } catch (RuntimeException $e) {
            $this->assertSame('Form flash data is nested too deep', $e->getMessage());
        }

        $this->assertSame($nest(["a'b"], 61), (new FormFlash($this->config))->getData());
    }

    public function testConstruct_OversizedIndexIsNotParsed(): void
    {
        $tmpDir = (new FormFlash($this->config))->getTmpDir();
        Folder::create($tmpDir);
        file_put_contents($tmpDir . '/index-v2.yaml', "data:\n  x: " . str_repeat('a', 1048577) . "\n");

        $flash = new FormFlash($this->config);

        $this->assertFalse($flash->exists());
        $this->assertNull($flash->getData());
    }

    /**
     * An index written by an earlier version, under the old file name and in
     * inline style, is not read at all, whatever its size.
     */
    public function testConstruct_LegacyIndexIsNotRead(): void
    {
        $tmpDir = (new FormFlash($this->config))->getTmpDir();
        Folder::create($tmpDir);
        $list = implode(', ', array_fill(0, 20000, "'a''b'"));
        file_put_contents($tmpDir . '/index.yaml', "form: test\nunique_id: abcdef1234567890\ndata:\n  x: { a: { b: { c: [{$list}] } } }\n");

        $start = microtime(true);
        $flash = new FormFlash($this->config);
        $elapsed = microtime(true) - $start;

        $this->assertFalse($flash->exists());
        $this->assertNull($flash->getData());
        $this->assertLessThan(0.1, $elapsed);
    }

    /**
     * A small index under the old name is not read either: pending form state
     * from before the update starts over.
     */
    public function testConstruct_SmallLegacyIndexIsNotRead(): void
    {
        $tmpDir = (new FormFlash($this->config))->getTmpDir();
        Folder::create($tmpDir);
        file_put_contents($tmpDir . '/index.yaml', "form: test\nunique_id: abcdef1234567890\ndata:\n  x: legacy\n");

        $flash = new FormFlash($this->config);

        $this->assertFalse($flash->exists());
        $this->assertNull($flash->getData());
    }
}
