<?php

use Grav\Framework\File\Formatter\MarkdownFormatter;

/**
 * Class MarkdownFormatterTest
 */
class MarkdownFormatterTest extends \PHPUnit\Framework\TestCase
{
    public function testBodyOfHeaderlessFileIsNotDecodedAsHeader(): void
    {
        $formatter = new MarkdownFormatter();
        $body = "---\ntitle: [a, b]\n---\nhello";

        $decoded = $formatter->decode($formatter->encode(['header' => [], 'markdown' => $body]));

        self::assertSame([], $decoded['header']);
        self::assertSame($body, $decoded['markdown']);
    }

    public function testOtherFilesAreEncodedAsBefore(): void
    {
        $formatter = new MarkdownFormatter();

        self::assertSame('hello', $formatter->encode(['header' => [], 'markdown' => 'hello']));
        self::assertSame(
            "---\ntitle: Memo\n---\n\n---\nhello",
            $formatter->encode(['header' => ['title' => 'Memo'], 'markdown' => "---\nhello"])
        );
    }
}
