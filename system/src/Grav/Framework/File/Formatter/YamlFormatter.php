<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\File\Formatter
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\File\Formatter;

use Grav\Framework\File\Interfaces\FileFormatterInterface;
use Symfony\Component\Yaml\Exception\DumpException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml as YamlParser;
use RocketTheme\Toolbox\Compat\Yaml\Yaml as FallbackYamlParser;

class YamlFormatter extends AbstractFormatter
{
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
     * {@inheritdoc}
     * @see FileFormatterInterface::encode()
     */
    public function encode($data, $inline = null, $indent = null): string
    {
        try {
            return YamlParser::dump(
                $data,
                $inline ? (int) $inline : $this->getInlineOption(),
                $indent ? (int) $indent : $this->getIndentOption(),
                YamlParser::DUMP_EXCEPTION_ON_INVALID_TYPE
            );
        } catch (DumpException $e) {
            throw new \RuntimeException('Encoding YAML failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     * @see FileFormatterInterface::decode()
     */
    public function decode($data): array
    {
        // Try native PECL YAML PHP extension first if available.
        if (\function_exists('yaml_parse') && $this->useNativeDecoder()) {
            // Safely decode YAML.
            $saved = @ini_get('yaml.decode_php');
            @ini_set('yaml.decode_php', '0');
            $decoded = @yaml_parse($data);
            @ini_set('yaml.decode_php', $saved);

            if ($decoded !== false) {
                return (array) $decoded;
            }
        }

        try {
            return (array) YamlParser::parse($data);
        } catch (ParseException $e) {
            if ($this->useCompatibleDecoder()) {
                return (array) FallbackYamlParser::parse($data);
            }

            throw new \RuntimeException('Decoding YAML failed: ' . $e->getMessage(), 0, $e);
        }
    }
}
