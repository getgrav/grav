<?php

use Grav\Framework\File\Formatter\CsvFormatter;

/**
 * Class CsvFormatterTest
 */
class CsvFormatterTest extends \Codeception\TestCase\Test
{
    public function testEncodeWithAssocColumns(): void
    {
        $data = [
            ['col1' => 1, 'col2' => 2, 'col3' => 3],
            ['col1' => 'aaa', 'col2' => 'bbb', 'col3' => 'ccc'],
        ];

        $encoded = (new CsvFormatter())->encode($data);

        $lines = array_filter(explode(PHP_EOL, $encoded));

        self::assertCount(3, $lines);
        self::assertEquals('col1,col2,col3', $lines[0]);
    }

    /**
     * TBD - If indexes are all numeric, what's the purpose
     * of displaying header
     */
    public function testEncodeWithIndexColumns(): void
    {
        $data = [
            [0 => 1, 1 => 2, 2 => 3],
        ];

        $encoded = (new CsvFormatter())->encode($data);

        $lines = array_filter(explode(PHP_EOL, $encoded));

        self::assertCount(2, $lines);
        self::assertEquals('0,1,2', $lines[0]);
    }

    public function testEncodeEmptyData(): void
    {
        $encoded = (new CsvFormatter())->encode([]);
        self::assertEquals('', $encoded);
    }
}
