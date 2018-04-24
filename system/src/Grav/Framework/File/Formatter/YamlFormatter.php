<?php
/**
 * @package    Grav\Framework\Formatter
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Formatter;

use Symfony\Component\Yaml\Exception\DumpException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml as YamlParser;
use RocketTheme\Toolbox\Compat\Yaml\Yaml as FallbackYamlParser;

/**
 * Class YamlFormatter
 * @package Grav\Framework\Formatter
 */
class YamlFormatter implements FormatterInterface
{
    /** @var array */
    private $config;

    public function __construct(array $config = [])
    {
        $this->config = $config + [
            'inline' => 5,
            'indent' => 2,
            'native' => true,
            'compat' => true
        ];
    }

    public function getFileExtension()
    {
        return 'yaml';
    }

    /**
     * {@inheritdoc}
     */
    public function encode($data)
    {
        try {
            return (string) YamlParser::dump(
                $data,
                $this->config['inline'],
                $this->config['indent'],
                YamlParser::DUMP_EXCEPTION_ON_INVALID_TYPE
            );
        } catch (DumpException $e) {
            throw new \RuntimeException($e->getMessage(), 500, $e);
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

            throw new \RuntimeException($e->getMessage(), 500, $e);
        }
    }
}
