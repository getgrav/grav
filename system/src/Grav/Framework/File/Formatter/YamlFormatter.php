<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\File\Formatter
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\File\Formatter;

use Grav\Framework\File\Interfaces\FileFormatterInterface;
use RuntimeException;
use Symfony\Component\Yaml\Exception\DumpException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml as YamlParser;
use RocketTheme\Toolbox\Compat\Yaml\Parser as FallbackYamlParser;
use function array_is_list;
use function array_key_exists;
use function function_exists;
use function is_array;
use function is_string;

/**
 * Class YamlFormatter
 * @package Grav\Framework\File\Formatter
 */
class YamlFormatter extends AbstractFormatter
{
    /**
     * YamlFormatter constructor.
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $config += [
            'file_extension' => '.yaml',
            'inline' => 5,
            'indent' => 2,
            'native' => true,
            'compat' => true
        ];

        parent::__construct($config);
    }

    /**
     * @return int
     */
    public function getInlineOption(): int
    {
        return $this->getConfig('inline');
    }

    /**
     * @return int
     */
    public function getIndentOption(): int
    {
        return $this->getConfig('indent');
    }

    /**
     * @return bool
     */
    public function useNativeDecoder(): bool
    {
        return $this->getConfig('native');
    }

    /**
     * @return bool
     */
    public function useCompatibleDecoder(): bool
    {
        return $this->getConfig('compat');
    }

    /**
     * @param array $data
     * @param int|null $inline
     * @param int|null $indent
     * @return string
     * @see FileFormatterInterface::encode()
     */
    public function encode($data, $inline = null, $indent = null): string
    {
        static::assertNoMergeKeys($data);

        $inline = $inline ? (int) $inline : $this->getInlineOption();
        static::assertNoInlineBinary($data, $inline);

        try {
            return YamlParser::dump(
                $data,
                $inline,
                $indent ? (int) $indent : $this->getIndentOption(),
                YamlParser::DUMP_EXCEPTION_ON_INVALID_TYPE
            );
        } catch (DumpException $e) {
            throw new RuntimeException('Encoding YAML failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Make sure that the data does not use `<<` as a key.
     *
     * Symfony YAML decoders read `<<` as the merge key, quoted or not, and move its value one level up:
     * `['<<' => ['access' => ...]]` comes back as `['access' => ...]`. There is no way to write the key so that it
     * is read back as it was, which means that data checked as an array (blueprint `security@` fields, privilege
     * guards) would load differently. Call this before writing YAML with any other encoder.
     *
     * @param mixed $data
     * @return void
     * @throws RuntimeException
     */
    public static function assertNoMergeKeys($data): void
    {
        if (!is_array($data)) {
            return;
        }

        if (array_key_exists('<<', $data)) {
            throw new RuntimeException("Encoding YAML failed: '<<' cannot be used as a key");
        }

        foreach ($data as $value) {
            static::assertNoMergeKeys($value);
        }
    }

    /**
     * Make sure that no binary string is written where it is not read back as it was.
     *
     * Symfony dumps a string with control characters or invalid UTF-8 as `!!binary <base64>`, without quotes. In
     * block style it is read back as it was, but at the `$inline` level the dumper switches to flow style. There an
     * item of a `[...]` list that contains `: ` is parsed as a mapping once more after it has been decoded:
     * `["\x01a: b"]` comes back as `[["\x01a" => 'b']]`, and `["\x01: 1, <<: x"]` as a mapping with a merge key,
     * which makes the parser fail with a TypeError. As a key of a `{...}` mapping the tag is not accepted, so the
     * file cannot be parsed. Other list items, and the value of a key, are read back as they were and stay allowed.
     * Call this with the inline level given to the dumper before writing YAML with any other encoder.
     *
     * @param mixed $data
     * @param int $inline Level where the dumper switches to flow style.
     * @return void
     * @throws RuntimeException
     */
    public static function assertNoInlineBinary($data, int $inline): void
    {
        if (!is_array($data)) {
            return;
        }

        $list = array_is_list($data);
        foreach ($data as $key => $value) {
            $item = $list ? $value : $key;
            if ($inline <= 0 && static::isBinaryString($item) && (!$list || str_contains($item, ': '))) {
                throw new RuntimeException('Encoding YAML failed: a key or list item with control characters or invalid UTF-8 is nested too deep');
            }

            static::assertNoInlineBinary($value, $inline - 1);
        }
    }

    /**
     * Check whether Symfony dumps the value as `!!binary`: same test as its private Inline::isBinaryString().
     *
     * @param mixed $value
     * @return bool
     */
    protected static function isBinaryString($value): bool
    {
        return is_string($value) && (!preg_match('//u', $value) || preg_match('/[^\x00\x07-\x0d\x1B\x20-\xff]/', $value));
    }

    /**
     * Check whether the YAML has a plain `y`, `n`, `yes`, `no`, `on` or `off` key.
     *
     * The yaml extension follows YAML 1.1 and reads those keys as booleans, so `y: 630` comes back as `1 => 630`
     * and the language strings under `YES:` and `NO:` are lost. Symfony follows YAML 1.2 and keeps them as text,
     * which is also how the file loads on every host without the extension, so a file with one of these keys is
     * parsed by Symfony. Quoted keys and false matches (such as `no:` inside a block of text) only cost the faster
     * parser. Only files with such a key change parser, so a hand-written `enabled: no` in any other file still
     * reads as `false` with the extension.
     *
     * @param string $data
     * @return bool
     */
    public static function hasBooleanLikeKey(string $data): bool
    {
        return (bool) preg_match(
            '/(?:^[ \t]*(?:-[ \t]+)*|[{,][ \t]*)(?:[yYnN]|yes|Yes|YES|no|No|NO|on|On|ON|off|Off|OFF)[ \t]*:(?:[ \t]|$)/m',
            $data
        );
    }

    /**
     * {@inheritdoc}
     * @see FileFormatterInterface::decode()
     */
    public function decode($data): array
    {
        // Try native PECL YAML PHP extension first if available.
        if (function_exists('yaml_parse') && $this->useNativeDecoder() && !static::hasBooleanLikeKey((string) $data)) {
            // Safely decode YAML.
            $saved = @ini_get('yaml.decode_php');
            @ini_set('yaml.decode_php', '0');
            $decoded = @yaml_parse($data);
            if ($saved !== false) {
                @ini_set('yaml.decode_php', $saved);
            }

            if ($decoded !== false) {
                return (array) $decoded;
            }
        }

        try {
            return (array) YamlParser::parse($data);
        } catch (ParseException $e) {
            if ($this->useCompatibleDecoder()) {
                // Call the parser itself: the static Compat\Yaml\Yaml::parse() treats a string without a
                // line break as a file name and reads that file when it exists, but $data is always YAML.
                return (array) (new FallbackYamlParser())->parse($data);
            }

            throw new RuntimeException('Decoding YAML failed: ' . $e->getMessage(), 0, $e);
        }
    }
}
