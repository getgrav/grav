<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Flex
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Flex\Storage;

use Grav\Common\File\CompiledJsonFile;
use Grav\Common\File\CompiledMarkdownFile;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use Grav\Framework\File\Formatter\JsonFormatter;
use Grav\Framework\File\Formatter\MarkdownFormatter;
use Grav\Framework\File\Formatter\YamlFormatter;
use Grav\Framework\File\Interfaces\FileFormatterInterface;
use Grav\Framework\Flex\Interfaces\FlexStorageInterface;
use RocketTheme\Toolbox\File\File;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RuntimeException;

/**
 * Class AbstractFilesystemStorage
 * @package Grav\Framework\Flex\Storage
 */
abstract class AbstractFilesystemStorage implements FlexStorageInterface
{
    /** @var FileFormatterInterface */
    protected $dataFormatter;
    /** @var string */
    protected $keyField = 'storage_key';


    /**
     * {@inheritdoc}
     * @see FlexStorageInterface::hasKey()
     */
    public function hasKeys(array $keys): array
    {
        $list = [];
        foreach ($keys as $key) {
            $list[$key] = $this->hasKey((string)$key);
        }

        return $list;
    }

    /**
     * @return string
     */
    public function getKeyField(): string
    {
        return $this->keyField;
    }

    protected function initDataFormatter($formatter): void
    {
        // Initialize formatter.
        if (!\is_array($formatter)) {
            $formatter = ['class' => $formatter];
        }
        $formatterClassName = $formatter['class'] ?? JsonFormatter::class;
        $formatterOptions = $formatter['options'] ?? [];

        $this->dataFormatter = new $formatterClassName($formatterOptions);
    }

    /**
     * @param string $filename
     * @return null|string
     */
    protected function detectDataFormatter(string $filename): ?string
    {
        if (preg_match('|(\.[a-z0-9]*)$|ui', $filename, $matches)) {
            switch ($matches[1]) {
                case '.json':
                    return JsonFormatter::class;
                case '.yaml':
                    return YamlFormatter::class;
                case '.md':
                    return MarkdownFormatter::class;
            }
        }

        return null;
    }

    /**
     * @param string $filename
     * @return File
     */
    protected function getFile(string $filename)
    {
        $filename = $this->resolvePath($filename);

        switch ($this->dataFormatter->getDefaultFileExtension()) {
            case '.json':
                $file = CompiledJsonFile::instance($filename);
                break;
            case '.yaml':
                $file = CompiledYamlFile::instance($filename);
                break;
            case '.md':
                $file = CompiledMarkdownFile::instance($filename);
                break;
            default:
                throw new RuntimeException('Unknown extension type ' . $this->dataFormatter->getDefaultFileExtension());
        }

        return $file;
    }

    /**
     * @param string $path
     * @return string
     */
    protected function resolvePath(string $path): string
    {
        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];

        if (!$locator->isStream($path)) {
            return $path;
        }

        return (string)($locator->findResource($path) ?: $locator->findResource($path, true, true));
    }

    /**
     * Generates a random, unique key for the row.
     *
     * @return string
     */
    protected function generateKey(): string
    {
        return substr(hash('sha256', random_bytes(32)), 0, 32);
    }

    /**
     * Checks if a key is valid.
     *
     * @param  string $key
     * @return bool
     */
    protected function validateKey(string $key): bool
    {
        return (bool) preg_match('/^[^\\/\\?\\*:;{}\\\\\\n]+$/u', $key);
    }
}
