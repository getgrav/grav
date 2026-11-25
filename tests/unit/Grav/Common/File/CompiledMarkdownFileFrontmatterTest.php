<?php

use Grav\Common\File\CompiledMarkdownFile;
use Grav\Framework\File\Formatter\MarkdownFormatter;

/**
 * A file whose frontmatter can't be matched (too large for the regex engine, or not valid UTF-8) is
 * refused. It must not be read as a file without frontmatter, which served the whole file as content
 * and lost every setting in the header.
 */
class CompiledMarkdownFileFrontmatterTest extends \PHPUnit\Framework\TestCase
{
    /** @var string */
    protected $filename;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filename = sys_get_temp_dir() . '/grav-md-frontmatter-' . bin2hex(random_bytes(4)) . '.md';
    }

    protected function tearDown(): void
    {
        @unlink($this->filename);
        parent::tearDown();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function providerUnreadableFiles(): array
    {
        return [
            'frontmatter over the backtrack limit' => ["---\ntitle: T\nnote: " . str_repeat('a', 1100000) . "\n---\n\nbody"],
            'invalid UTF-8, one line of frontmatter' => ["---\ntitle: T\n---\n\nbody \xff"],
            'invalid UTF-8, several lines of frontmatter' => ["---\ntitle: T\nsummary: S\n---\n\nbody \xff"],
        ];
    }

    /**
     * @dataProvider providerUnreadableFiles
     */
    public function testUnreadableFrontmatterIsRefused(string $contents): void
    {
        file_put_contents($this->filename, $contents);

        $file = CompiledMarkdownFile::instance($this->filename);
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Failed to read the frontmatter');
            $file->header();
        } finally {
            $file->free();
        }
    }

    /**
     * @dataProvider providerUnreadableFiles
     */
    public function testUnreadableFrontmatterIsRefusedDuringScan(string $contents): void
    {
        file_put_contents($this->filename, $contents);

        self::assertTrue(CompiledMarkdownFile::beginScan());
        try {
            $file = CompiledMarkdownFile::instance($this->filename);
            try {
                $this->expectException(RuntimeException::class);
                $this->expectExceptionMessage('Failed to read the frontmatter');
                $file->header();
            } finally {
                $file->free();
            }
        } finally {
            CompiledMarkdownFile::endScan();
        }
    }

    public function testUnreadableFrontmatterIsRefusedByTheFormatter(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to read the frontmatter');

        (new MarkdownFormatter())->decode("---\ntitle: T\nnote: " . str_repeat('a', 1100000) . "\n---\n\nbody");
    }

    public function testReadableFilesStillDecode(): void
    {
        file_put_contents($this->filename, "---\ntitle: T\n---\n\nbody");

        $file = CompiledMarkdownFile::instance($this->filename);
        $result = [$file->header(), trim($file->markdown())];
        $file->free();

        self::assertSame([['title' => 'T'], 'body'], $result);
    }
}
