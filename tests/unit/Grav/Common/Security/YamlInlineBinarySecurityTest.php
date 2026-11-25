<?php

use Codeception\Util\Fixtures;
use Grav\Common\File\CompiledMarkdownFile;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Yaml;
use Grav\Framework\File\Formatter\MarkdownFormatter;
use Grav\Framework\File\Formatter\YamlFormatter;

/**
 * Class YamlInlineBinarySecurityTest
 *
 * Covers: a string with control characters or invalid UTF-8 below the inline level of the YAML
 * dumper. Symfony writes it as `!!binary <base64>`, and inside a `[...]` list the decoded text is
 * parsed as YAML again, so `["\x01: 1, <<: x"]` came back as a mapping with a merge key and the
 * parser failed with a TypeError on every read of the file. The writers refuse such a list item
 * (and key, which is not read back either) instead, and a file which makes the parser fail with
 * an Error is reported like any other unreadable file.
 */
class YamlInlineBinarySecurityTest extends \PHPUnit\Framework\TestCase
{
    /** @var string */
    protected $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $grav();

        $this->tmp = sys_get_temp_dir() . '/grav-yaml-inline-binary-' . bin2hex(random_bytes(4));
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

    public static function providerBinaryStrings(): array
    {
        return [
            'merge key behind a control character' => [["\x01: 1, <<: x"]],
            'merge key behind invalid UTF-8' => [["\xff: 1, <<: x"]],
            'mapping behind a control character' => [["\x01a: b"]],
            'second item of a list' => [['a', "\x01b: c"]],
            'key of a mapping' => [["\x01a" => 'b']],
        ];
    }

    /**
     * Puts the data below `$depth` keys: it is written in flow style when `$depth` is the inline level.
     *
     * @param array $data
     * @param int $depth
     * @return array
     */
    protected static function nest(array $data, int $depth): array
    {
        for ($i = 0; $i < $depth; $i++) {
            $data = ['b' => $data];
        }

        return $data;
    }

    /**
     * @dataProvider providerBinaryStrings
     */
    public function testCompiledMarkdownFileRefusesBinaryStringInFlowStyle(array $data): void
    {
        $file = CompiledMarkdownFile::instance($this->tmp . '.md');
        try {
            $file->save(['header' => self::nest($data, 20), 'markdown' => 'Content']);
            self::fail('Binary string was written in flow style');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('nested too deep', $e->getMessage());
        } finally {
            $file->free();
        }

        self::assertFileDoesNotExist($this->tmp . '.md');
    }

    /**
     * @dataProvider providerBinaryStrings
     */
    public function testCompiledYamlFileRefusesBinaryStringInFlowStyle(array $data): void
    {
        $file = CompiledYamlFile::instance($this->tmp . '.yaml');
        try {
            $file->save(self::nest($data, 5));
            self::fail('Binary string was written in flow style');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('nested too deep', $e->getMessage());
        } finally {
            $file->free();
        }

        self::assertFileDoesNotExist($this->tmp . '.yaml');
    }

    /**
     * @dataProvider providerBinaryStrings
     */
    public function testFormattersRefuseBinaryStringInFlowStyle(array $data): void
    {
        $encoders = [
            static fn() => (new YamlFormatter())->encode(self::nest($data, 5)),
            static fn() => (new YamlFormatter())->encode(self::nest($data, 2), 2),
            static fn() => (new MarkdownFormatter())->encode(['header' => self::nest($data, 20), 'markdown' => 'Content']),
            static fn() => Yaml::dump(self::nest($data, 5)),
        ];

        foreach ($encoders as $encode) {
            try {
                $encode();
                self::fail('Binary string was encoded in flow style');
            } catch (\RuntimeException $e) {
                self::assertStringContainsString('nested too deep', $e->getMessage());
            }
        }
    }

    /**
     * @dataProvider providerBinaryStrings
     */
    public function testBinaryStringInBlockStyleIsWritten(array $data): void
    {
        // One level above the inline level the string is written in block style, which is read back as it was.
        $yaml = self::nest($data, 4);
        $file = CompiledYamlFile::instance($this->tmp . '.yaml');
        $file->save($yaml);
        $file->free();
        self::assertStringContainsString('!!binary', (string)file_get_contents($this->tmp . '.yaml'));
        self::assertSame($yaml, (new YamlFormatter(['native' => false]))->decode((string)file_get_contents($this->tmp . '.yaml')));

        $header = self::nest($data, 19);
        $file = CompiledMarkdownFile::instance($this->tmp . '.md');
        $file->save(['header' => $header, 'markdown' => 'Content']);
        $file->free();
        self::assertStringContainsString('!!binary', (string)file_get_contents($this->tmp . '.md'));
        self::assertSame($header, CompiledMarkdownFile::instance($this->tmp . '.md')->header());
        CompiledMarkdownFile::instance($this->tmp . '.md')->free();

        self::assertSame($yaml, (new YamlFormatter(['native' => false]))->decode(Yaml::dump($yaml)));
    }

    public function testOtherStringsAreWrittenInFlowStyle(): void
    {
        // Strings which the dumper quotes are not read as YAML, whatever they contain. Neither is a binary
        // string as the value of a key, or a binary list item without `: `.
        $data = self::nest(['x: 1, <<: x', "tab\tand\nnew line", 'ünïcödé', 'a' => '{b: c}', 'k: v' => '[d]', 'b' => "\x01: 1, <<: x"], 5);

        $file = CompiledYamlFile::instance($this->tmp . '.yaml');
        $file->save($data);
        $file->free();
        self::assertSame($data, (new YamlFormatter(['native' => false]))->decode((string)file_get_contents($this->tmp . '.yaml')));

        $list = self::nest(["\x01b", "\xffc:d", 'e'], 5);
        $file = CompiledYamlFile::instance($this->tmp . '.yaml');
        $file->save($list);
        $file->free();
        $yaml = (string)file_get_contents($this->tmp . '.yaml');
        self::assertStringContainsString('[!!binary', $yaml);
        self::assertSame($list, (new YamlFormatter(['native' => false]))->decode($yaml));
    }

    /**
     * @dataProvider providerUnreadableFrontmatter
     */
    public function testParserErrorIsReportedAsUnreadableFile(string $frontmatter): void
    {
        // A file which is already on the disk: a TypeError from the parser must not get past the callers,
        // which handle a page with broken frontmatter as an Exception.
        file_put_contents($this->tmp . '.md', "---\n{$frontmatter}\n---\n\nContent");

        $file = CompiledMarkdownFile::instance($this->tmp . '.md');
        try {
            $file->header();
            self::fail('Frontmatter was read');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('Failed to read', $e->getMessage());
        } finally {
            $file->free();
        }

        // The same while Pages scans the tree, where page files are read without the compiled cache.
        self::assertTrue(CompiledMarkdownFile::beginScan());
        $file = CompiledMarkdownFile::instance($this->tmp . '.md');
        try {
            $file->header();
            self::fail('Frontmatter was read');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('Failed to read', $e->getMessage());
        } finally {
            $file->free();
            CompiledMarkdownFile::endScan();
        }
    }

    public static function providerUnreadableFrontmatter(): array
    {
        return [
            'binary string in a list' => ['a: [!!binary ATogMSwgPDw6IHg=]'],
            'merge key with a scalar' => ['a: { <<: x }'],
        ];
    }
}
