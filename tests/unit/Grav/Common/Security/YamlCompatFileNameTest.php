<?php

use Grav\Common\File\CompiledMarkdownFile;
use Grav\Common\Yaml;
use Grav\Framework\File\Formatter\MarkdownFormatter;
use Grav\Framework\File\Formatter\YamlFormatter;

/**
 * Class YamlCompatFileNameTest
 *
 * The compatibility YAML parser (a copy of Symfony 2.8) is the fallback when the current parser
 * rejects a string. Its static Yaml::parse() reads a string without a line break as a file when
 * a file of that name exists. Core only ever decodes YAML text, never a file name, so the
 * fallback and one-line page frontmatter always parse the string itself.
 */
class YamlCompatFileNameTest extends \PHPUnit\Framework\TestCase
{
    /** @var string A folder whose name makes the current parser throw, so the fallback runs. */
    private $folder;

    /** @var string */
    private $secret;

    protected function setUp(): void
    {
        parent::setUp();

        if (DIRECTORY_SEPARATOR === '\\') {
            self::markTestSkipped('The folder name has to read as YAML (y: @z), and Windows does not allow : in a folder name.');
        }

        $this->folder = sys_get_temp_dir() . '/grav-yaml-compat-' . bin2hex(random_bytes(4)) . '/y: @z';
        $this->secret = $this->folder . '/secret.yaml';
        mkdir($this->folder, 0777, true);
        file_put_contents($this->secret, "hashed_password: canary\n");
    }

    protected function tearDown(): void
    {
        if ($this->folder === null) {
            parent::tearDown();

            return;
        }

        CompiledMarkdownFile::endScan();
        @unlink($this->secret);
        foreach (glob($this->folder . '/page*.md') ?: [] as $page) {
            @unlink($page);
        }
        @rmdir($this->folder);
        @rmdir(dirname($this->folder));

        parent::tearDown();
    }

    public function testFormatterDoesNotOpenAFileName(): void
    {
        self::assertArrayNotHasKey('hashed_password', (new YamlFormatter())->decode($this->secret));
        self::assertArrayNotHasKey('hashed_password', Yaml::parse($this->secret));
    }

    public function testFormatterStillFallsBackToTheCompatibilityParser(): void
    {
        self::assertSame(['title' => '@name'], (new YamlFormatter())->decode('title: @name'));
    }

    public function testFlexPageFrontmatterDoesNotOpenAFileName(): void
    {
        $decoded = (new MarkdownFormatter())->decode("---\n{$this->secret}\n---\nbody\n");

        self::assertArrayNotHasKey('hashed_password', $decoded['header']);
    }

    /**
     * @dataProvider providerPageFiles
     */
    public function testPageFrontmatterDoesNotOpenAFileName(string $before, string $eol, bool $scan): void
    {
        $content = $this->readPage("{$before}---{$eol}{$this->secret}{$eol}---{$eol}{$eol}body{$eol}", $scan);

        self::assertArrayNotHasKey('hashed_password', $content['header']);
        self::assertSame($this->secret, $content['frontmatter']);
        self::assertSame("body\n", $content['markdown']);
    }

    public static function providerPageFiles(): array
    {
        return [
            'plain' => ['', "\n", false],
            'pages scan' => ['', "\n", true],
            'windows line endings' => ['', "\r\n", false],
            'old mac line endings' => ['', "\r", false],
            'byte order mark' => ["\xef\xbb\xbf", "\n", false],
            'leading whitespace' => [" \t\n\x0b\0", "\n", false],
        ];
    }

    public function testOneLineFrontmatterIsReadAsBefore(): void
    {
        $expected = ['header' => ['title' => 'Home'], 'frontmatter' => 'title: Home', 'markdown' => "# Hi\n"];
        self::assertSame($expected, $this->readPage("---\ntitle: Home\n---\n# Hi\n"));

        // Only the compatibility parser accepts this one.
        $expected = ['header' => ['title' => '@name'], 'frontmatter' => 'title: @name', 'markdown' => ''];
        self::assertSame($expected, $this->readPage("---\ntitle: @name\n---"));

        // A multi-line frontmatter and a file without one are untouched.
        $expected = ['header' => ['a' => 1, 'b' => 2], 'frontmatter' => "a: 1\nb: 2", 'markdown' => "text\n"];
        self::assertSame($expected, $this->readPage("---\na: 1\nb: 2\n---\n\ntext\n"));
        $expected = ['header' => [], 'frontmatter' => '', 'markdown' => "just text\n"];
        self::assertSame($expected, $this->readPage("just text\n"));
    }

    private function readPage(string $raw, bool $scan = false): array
    {
        // A new file each time: the compiled cache of a page file is keyed by its time and size.
        static $n = 0;
        $filename = $this->folder . '/page' . ++$n . '.md';
        file_put_contents($filename, $raw);
        if ($scan) {
            CompiledMarkdownFile::beginScan();
        }

        $file = CompiledMarkdownFile::instance($filename);
        $content = ['header' => $file->header(), 'frontmatter' => $file->frontmatter(), 'markdown' => $file->markdown()];
        $file->free();

        return $content;
    }
}
