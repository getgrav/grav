<?php

use Grav\Common\File\CompiledMarkdownFile;

/**
 * A page saved with an empty header reads back with an empty header: a `---` block at the top
 * of the body was checked as content and must not turn into frontmatter.
 */
class CompiledMarkdownFileEmptyHeaderTest extends \PHPUnit\Framework\TestCase
{
    /** @var string */
    protected $filename;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filename = sys_get_temp_dir() . '/grav-md-empty-header-' . bin2hex(random_bytes(4)) . '.md';
    }

    protected function tearDown(): void
    {
        @unlink($this->filename);
        parent::tearDown();
    }

    /**
     * @return array{0: array, 1: string}
     */
    protected function saveAndRead(array $header, string $markdown): array
    {
        $file = CompiledMarkdownFile::instance($this->filename);
        $file->header($header);
        $file->markdown($markdown);
        $file->save();
        $file->free();

        $file = CompiledMarkdownFile::instance($this->filename);
        $result = [$file->header(), $file->markdown()];
        $file->free();

        return $result;
    }

    public static function providerBodies(): array
    {
        return [
            'block' => ["---\ntitle: [a, b]\n---\nhello"],
            'windows line endings' => ["---\r\ntitle: [a, b]\r\n---\r\nhello"],
            'leading whitespace' => [" \n\t---\ntitle: [a, b]\n---\nhello"],
            'byte order mark' => ["\xef\xbb\xbf---\ntitle: [a, b]\n---\nhello"],
        ];
    }

    /**
     * @dataProvider providerBodies
     */
    public function testBodyIsNotReadBackAsHeader(string $body): void
    {
        [$header, $markdown] = $this->saveAndRead([], $body);

        self::assertSame([], $header);
        self::assertStringContainsString("---\ntitle: [a, b]\n---\nhello", $markdown);
    }

    public function testBodyIsNotReadBackAsHeaderDuringScan(): void
    {
        $this->saveAndRead([], "---\ntitle: [a, b]\n---\nhello");

        self::assertTrue(CompiledMarkdownFile::beginScan());
        try {
            $file = CompiledMarkdownFile::instance($this->filename);
            $header = $file->header();
            $file->free();
        } finally {
            CompiledMarkdownFile::endScan();
        }

        self::assertSame([], $header);
    }

    public function testOtherFilesAreWrittenAsBefore(): void
    {
        $this->saveAndRead([], 'hello');
        self::assertSame('hello', file_get_contents($this->filename));

        $this->saveAndRead(['title' => 'Memo'], "---\nhello");
        self::assertSame("---\ntitle: Memo\n---\n\n---\nhello", file_get_contents($this->filename));
    }
}
