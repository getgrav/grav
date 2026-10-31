<?php

use Codeception\Util\Fixtures;
use Grav\Common\Data\Blueprint;
use Grav\Common\File\CompiledMarkdownFile;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Yaml;
use Grav\Framework\File\Formatter\MarkdownFormatter;
use Grav\Framework\File\Formatter\YamlFormatter;
use Grav\Framework\Form\FormFlash;

/**
 * Class YamlMergeKeySecurityTest
 *
 * Covers: `<<` used as an array key. The YAML decoders read it as the merge key even when it is
 * quoted, so `['<<' => ['access' => ...]]` would come back from the file as `['access' => ...]`,
 * past every check which was made on the array. The writers refuse the key instead.
 */
class YamlMergeKeySecurityTest extends \PHPUnit\Framework\TestCase
{
    /** @var string */
    protected $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $grav();

        $this->tmp = sys_get_temp_dir() . '/grav-yaml-merge-key-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (['.yaml', '.md'] as $extension) {
            if (is_file($this->tmp . $extension)) {
                unlink($this->tmp . $extension);
            }
        }
        parent::tearDown();
    }

    public static function providerMergeKeys(): array
    {
        return [
            'top level' => [['fullname' => 'Jane', '<<' => ['access' => ['admin' => ['super' => true]]]]],
            'nested' => [['access' => ['admin' => ['<<' => ['super' => true]]]]],
            'inside a list' => [['items' => [['name' => 'a'], ['<<' => ['name' => 'b']]]]],
            'scalar value' => [['<<' => 'value']],
        ];
    }

    /**
     * @dataProvider providerMergeKeys
     */
    public function testCompiledYamlFileRefusesMergeKey(array $data): void
    {
        $file = CompiledYamlFile::instance($this->tmp . '.yaml');
        try {
            $file->save($data);
            self::fail('Merge key was written');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString("'<<'", $e->getMessage());
        } finally {
            $file->free();
        }

        self::assertFileDoesNotExist($this->tmp . '.yaml');
    }

    /**
     * @dataProvider providerMergeKeys
     */
    public function testCompiledMarkdownFileRefusesMergeKey(array $data): void
    {
        $file = CompiledMarkdownFile::instance($this->tmp . '.md');
        try {
            $file->save(['header' => $data, 'markdown' => 'Content']);
            self::fail('Merge key was written');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString("'<<'", $e->getMessage());
        } finally {
            $file->free();
        }

        self::assertFileDoesNotExist($this->tmp . '.md');
    }

    /**
     * @dataProvider providerMergeKeys
     */
    public function testFormattersRefuseMergeKey(array $data): void
    {
        $encoders = [
            static fn() => (new YamlFormatter())->encode($data),
            static fn() => (new MarkdownFormatter())->encode(['header' => $data, 'markdown' => 'Content']),
            static fn() => Yaml::dump($data),
        ];

        foreach ($encoders as $encode) {
            try {
                $encode();
                self::fail('Merge key was encoded');
            } catch (\RuntimeException $e) {
                self::assertStringContainsString("'<<'", $e->getMessage());
            }
        }
    }

    /**
     * @dataProvider providerMergeKeys
     */
    public function testFormFlashRefusesMergeKey(array $data): void
    {
        $flash = new FormFlash(['session_id' => 'yamlmergekey', 'unique_id' => bin2hex(random_bytes(8)), 'form_name' => 'test']);
        $flash->setData($data);
        try {
            $flash->save();
            self::fail('Merge key was written');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString("'<<'", $e->getMessage());
        }

        self::assertFileDoesNotExist($flash->getTmpDir() . '/index.yaml');
    }

    /**
     * @dataProvider providerMergeKeys
     */
    public function testBlueprintFilterDropsMergeKey(array $data): void
    {
        $blueprint = new Blueprint(null, ['form' => ['validation' => 'loose', 'fields' => ['fullname' => ['type' => 'text']]]]);
        $blueprint->load()->init();

        $filtered = $blueprint->filter($data, true, true);

        self::assertStringNotContainsString("'<<'", Yaml::dump($filtered));
    }

    public function testOrdinaryDataIsWritten(): void
    {
        // As a value, or as a part of a longer key, `<<` has no special meaning.
        $data = ['fullname' => 'Jane', 'access' => ['site' => ['login' => true]], 'list' => ['<<'], 'a<<' => '<<', '<<<' => ['b' => 1]];

        $file = CompiledYamlFile::instance($this->tmp . '.yaml');
        $file->save($data);
        $file->free();
        self::assertSame($data, Yaml::parse((string)file_get_contents($this->tmp . '.yaml')));

        $file = CompiledMarkdownFile::instance($this->tmp . '.md');
        $file->save(['header' => $data, 'markdown' => '<<']);
        $file->free();
        self::assertSame($data, (new MarkdownFormatter())->decode((string)file_get_contents($this->tmp . '.md'))['header']);

        self::assertSame($data, Yaml::parse(Yaml::dump($data)));
    }
}
