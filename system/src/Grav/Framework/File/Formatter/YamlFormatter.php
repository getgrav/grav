<?php
/**
 * @package    Grav\Framework\File\Formatter
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\File\Formatter;

use Symfony\Component\Yaml\Exception\DumpException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml as YamlParser;
use RocketTheme\Toolbox\Compat\Yaml\Yaml as FallbackYamlParser;

class YamlFormatter implements FormatterInterface
{
    /** @var array */
    private $config;

    public function __construct(array $config = [])
    {
        $this->config = $config + [
            'file_extension' => '.yaml',
            'inline' => 5,
            'indent' => 2,
            'native' => true,
            'compat' => true
        ];
    }

    /**
     * @deprecated 1.5 Use $formatter->getDefaultFileExtension() instead.
     */
    public function getFileExtension()
    {
        user_error(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated since Grav 1.5, use getDefaultFileExtension() method instead', E_USER_DEPRECATED);

        return $this->getDefaultFileExtension();
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultFileExtension()
    {
        $extensions = $this->getSupportedFileExtensions();

        return (string) reset($extensions);
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedFileExtensions()
    {
        return (array) $this->config['file_extension'];
    }

    /**
     * {@inheritdoc}
     */
    public function encode($data, $inline = null, $indent = null)
    {
        try {
            return (string) YamlParser::dump(
                $data,
                $inline ? (int) $inline : $this->config['inline'],
                $indent ? (int) $indent : $this->config['indent'],
                YamlParser::DUMP_EXCEPTION_ON_INVALID_TYPE
            );
        } catch (DumpException $e) {
            throw new \RuntimeException('Encoding YAML failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function decode($data)
    {
        // Try native PECL YAML PHP extension first if available.
        if ($this->config['native'] && function_exists('yaml_parse')) {
            // Safely decode YAML.
            $saved = @ini_get('yaml.decode_php');
            @ini_set('yaml.decode_php', 0);
            $decoded = @yaml_parse($data);
            @ini_set('yaml.decode_php', $saved);

            if ($decoded !== false) {
                return (array) $decoded;
            }
        }

        try {
            return (array) YamlParser::parse($data);
        } catch (ParseException $e) {
            if ($this->config['compat']) {
                return (array) FallbackYamlParser::parse($data);
            }

            throw new \RuntimeException('Decoding YAML failed: ' . $e->getMessage(), 0, $e);
        }
    }
}
