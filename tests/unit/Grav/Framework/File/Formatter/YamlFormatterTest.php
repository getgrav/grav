<?php

use Grav\Framework\File\Formatter\YamlFormatter;

/**
 * Class YamlFormatterTest
 */
class YamlFormatterTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Keys that libyaml (YAML 1.1) reads as booleans and Symfony (YAML 1.2) keeps as text.
     */
    public function booleanLikeKeyProvider(): array
    {
        return [
            'y key' => ["image:\n  x: 1200\n  y: 630\n"],
            'upper YES and NO keys' => ["PLUGIN_ADMIN:\n  YES: Yes\n  NO: No\n"],
            'off key with nested value' => ["options:\n  off:\n    label: Off\n"],
            'no key at the top' => ["no:\n  title: Norsk\n"],
            'key in a sequence item' => ["items:\n  - on: 1\n"],
            'key in a flow mapping' => ["options: { a: 1, on: 2 }\n"],
        ];
    }

    /**
     * @dataProvider booleanLikeKeyProvider
     */
    public function testHasBooleanLikeKey(string $yaml): void
    {
        self::assertTrue(YamlFormatter::hasBooleanLikeKey($yaml));
    }

    /**
     * YAML without a boolean-like key.
     */
    public function noBooleanLikeKeyProvider(): array
    {
        return [
            'quoted y key' => ["image:\n  'y': 630\n  \"n\": 1\n"],
            'boolean-like values' => ["enabled: yes\nmode: off\nlist:\n  - no\n"],
            'words starting with y or n' => ["yaml: 1\nname: never\nnone: ~\nyes_label: Yes\n"],
            'text with a colon' => ["title: 'Say no: never'\n"],
        ];
    }

    /**
     * @dataProvider noBooleanLikeKeyProvider
     */
    public function testHasNoBooleanLikeKey(string $yaml): void
    {
        self::assertFalse(YamlFormatter::hasBooleanLikeKey($yaml));
    }

    public function testDecodeKeepsYKey(): void
    {
        $data = (new YamlFormatter())->decode("image:\n  x: 1200\n  y: 630\n");

        self::assertSame(['image' => ['x' => 1200, 'y' => 630]], $data);
    }
}
