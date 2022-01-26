<?php

declare(strict_types=1);

/**
 * @package    Grav\Common\Flex
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Flex\Types\Pages\Storage;

use FilesystemIterator;
use Grav\Common\Debugger;
use Grav\Common\Flex\Types\Pages\PageIndex;
use Grav\Common\Grav;
use Grav\Common\Language\Language;
use Grav\Common\Utils;
use Grav\Framework\Filesystem\Filesystem;
use Grav\Framework\Flex\Storage\FolderStorage;
use RocketTheme\Toolbox\File\MarkdownFile;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RuntimeException;
use SplFileInfo;
use function in_array;
use function is_string;

/**
 * Class GravPageStorage
 * @package Grav\Plugin\FlexObjects\Types\GravPages
 */
class PageStorage extends FolderStorage
{
    /** @var bool */
    protected $ignore_hidden;
    /** @var array */
    protected $ignore_files;
    /** @var array */
    protected $ignore_folders;
    /** @var bool */
    protected $include_default_lang_file_extension;
    /** @var bool */
    protected $recurse;
    /** @var string */
    protected $base_path;

    /** @var int */
    protected $flags;
    /** @var string */
    protected $regex;

    /**
     * @param array $options
     */
    protected function initOptions(array $options): void
    {
        parent::initOptions($options);

        $this->flags = FilesystemIterator::KEY_AS_FILENAME | FilesystemIterator::CURRENT_AS_FILEINFO
            | FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS;

        $grav = Grav::instance();

        $config = $grav['config'];
        $this->ignore_hidden = (bool)$config->get('system.pages.ignore_hidden');
        $this->ignore_files = (array)$config->get('system.pages.ignore_files');
        $this->ignore_folders = (array)$config->get('system.pages.ignore_folders');
        $this->include_default_lang_file_extension = (bool)$config->get('system.languages.include_default_lang_file_extension', true);
        $this->recurse = (bool)($options['recurse'] ?? true);
        $this->regex = '/(\.([\w\d_-]+))?\.md$/D';
    }

    /**
     * @param string $key
     * @param bool $variations
     * @return array
     */
    public function parseKey(string $key, bool $variations = true): array
    {
        if (mb_strpos($key, '|') !== false) {
            [$key, $params] = explode('|', $key, 2);
        } else {
            $params = '';
        }
        $key = ltrim($key, '/');

        $keys = parent::parseKey($key, false) + ['params' => $params];

        if ($variations) {
            $keys += $this->parseParams($key, $params);
        }

        return $keys;
    }

    /**
     * @param string $key
     * @return string
     */
    public function readFrontmatter(string $key): string
    {
        $path = $this->getPathFromKey($key);
        $file = $this->getFile($path);
        try {
            if ($file instanceof MarkdownFile) {
                $frontmatter = $file->frontmatter();
            } else {
                $frontmatter = $file->raw();
            }
        } catch (RuntimeException $e) {
            $frontmatter = 'ERROR: ' . $e->getMessage();
        } finally {
            $file->free();
            unset($file);
        }

        return $frontmatter;
    }

    /**
     * @param string $key
     * @return string
     */
    public function readRaw(string $key): string
    {
        $path = $this->getPathFromKey($key);
        $file = $this->getFile($path);
        try {
            $raw = $file->raw();
        } catch (RuntimeException $e) {
            $raw = 'ERROR: ' . $e->getMessage();
        } finally {
            $file->free();
            unset($file);
        }

        return $raw;
    }

    /**
     * @param array $keys
     * @param bool $includeParams
     * @return string
     */
    public function buildStorageKey(array $keys, bool $includeParams = true): string
    {
        $key = $keys['key'] ?? null;
        if (null === $key) {
            $key = $keys['parent_key'] ?? '';
            if ($key !== '') {
                $key .= '/';
            }
            $order = $keys['order'] ?? null;
            $folder = $keys['folder'] ?? 'undefined';
            $key .= is_numeric($order) ? sprintf('%02d.%s', $order, $folder) : $folder;
        }

        $params = $includeParams ? $this->buildStorageKeyParams($keys) : '';

        return $params ? "{$key}|{$params}" : $key;
    }

    /**
     * @param array $keys
     * @return string
     */
    public function buildStorageKeyParams(array $keys): string
    {
        $params = $keys['template'] ?? '';
        $language = $keys['lang'] ?? '';
        if ($language) {
            $params .= '.' . $language;
        }

        return $params;
    }

    /**
     * @param array $keys
     * @return string
     */
    public function buildFolder(array $keys): string
    {
        return $this->dataFolder . '/' . $this->buildStorageKey($keys, false);
    }

    /**
     * @param array $keys
     * @return string
     */
    public function buildFilename(array $keys): string
    {
        $file = $this->buildStorageKeyParams($keys);

        // Template is optional; if it is missing, we need to have to load the object metadata.
        if ($file && $file[0] === '.') {
            $meta = $this->getObjectMeta($this->buildStorageKey($keys, false));
            $file = ($meta['template'] ?? 'folder') . $file;
        }

        return $file . $this->dataExt;
    }

    /**
     * @param array $keys
     * @return string
     */
    public function buildFilepath(array $keys): string
    {
        $folder = $this->buildFolder($keys);
        $filename = $this->buildFilename($keys);

        return rtrim($folder, '/') !== $folder ? $folder . $filename : $folder . '/' . $filename;
    }

    /**
     * @param array $row
     * @param bool $setDefaultLang
     * @return array
     */
    public function extractKeysFromRow(array $row, bool $setDefaultLang = true): array
    {
        $meta = $row['__META'] ?? null;
        $storageKey = $row['storage_key'] ?? $meta['storage_key']  ?? '';
        $keyMeta = $storageKey !== '' ? $this->extractKeysFromStorageKey($storageKey) : null;
        $parentKey = $row['parent_key'] ?? $meta['parent_key'] ?? $keyMeta['parent_key'] ?? '';
        $order = $row['order'] ?? $meta['order'] ?? $keyMeta['order'] ?? null;
        $folder = $row['folder'] ?? $meta['folder']  ?? $keyMeta['folder'] ?? '';
        $template = $row['template'] ?? $meta['template'] ?? $keyMeta['template'] ?? '';
        $lang = $row['lang'] ?? $meta['lang'] ?? $keyMeta['lang'] ?? '';

        // Handle default language, if it should be saved without language extension.
        if ($setDefaultLang && empty($meta['markdown'][$lang])) {
            $grav = Grav::instance();

            /** @var Language $language */
            $language = $grav['language'];
            $default = $language->getDefault();
            // Make sure that the default language file doesn't exist before overriding it.
            if (empty($meta['markdown'][$default])) {
                if ($this->include_default_lang_file_extension) {
                    if ($lang === '') {
                        $lang = $language->getDefault();
                    }
                } elseif ($lang === $language->getDefault()) {
                    $lang = '';
                }
            }
        }

        $keys = [
            'key' => null,
            'params' => null,
            'parent_key' => $parentKey,
            'order' => is_numeric($order) ? (int)$order : null,
            'folder' => $folder,
            'template' => $template,
            'lang' => $lang
        ];

        $keys['key'] = $this->buildStorageKey($keys, false);
        $keys['params'] = $this->buildStorageKeyParams($keys);

        return $keys;
    }

    /**
     * @param string $key
     * @return array
     */
    public function extractKeysFromStorageKey(string $key): array
    {
        if (mb_strpos($key, '|') !== false) {
            [$key, $params] = explode('|', $key, 2);
            [$template, $language] = mb_strpos($params, '.') !== false ? explode('.', $params, 2) : [$params, ''];
        } else {
            $params = $template = $language = '';
        }
        $objectKey = Utils::basename($key);
        if (preg_match('|^(\d+)\.(.+)$|', $objectKey, $matches)) {
            [, $order, $folder] = $matches;
        } else {
            [$order, $folder] = ['', $objectKey];
        }

        $filesystem = Filesystem::getInstance(false);

        $parentKey = ltrim($filesystem->dirname('/' . $key), '/');

        return [
            'key' => $key,
            'params' => $params,
            'parent_key' => $parentKey,
            'order' => is_numeric($order) ? (int)$order : null,
            'folder' => $folder,
            'template' => $template,
            'lang' => $language
        ];
    }

    /**
     * @param string $key
     * @param string $params
     * @return array
     */
    protected function parseParams(string $key, string $params): array
    {
        if (mb_strpos($params, '.') !== false) {
            [$template, $language] = explode('.', $params, 2);
        } else {
            $template = $params;
            $language = '';
        }

        if ($template === '') {
            $meta = $this->getObjectMeta($key);
            $template = $meta['template'] ?? 'folder';
        }

        return [
            'file' => $template . ($language ? '.' . $language : ''),
            'template' => $template,
            'lang' => $language
        ];
    }

    /**
     * Prepares the row for saving and returns the storage key for the record.
     *
     * @param array $row
     */
    protected function prepareRow(array &$row): void
    {
        // Remove keys used in the filesystem.
        unset($row['parent_key'], $row['order'], $row['folder'], $row['template'], $row['lang']);
    }

    /**
     * @param string $key
     * @return array
     */
    protected function loadRow(string $key): ?array
    {
        $data = parent::loadRow($key);

        // Special case for root page.
        if ($key === '' && null !== $data) {
            $data['root'] = true;
        }

        return $data;
    }

    /**
     * Page storage supports moving and copying the pages and their languages.
     *
     * $row['__META']['copy'] = true       Use this if you want to copy the whole folder, otherwise it will be moved
     * $row['__META']['clone'] = true      Use this if you want to clone the file, otherwise it will be renamed
     *
     * @param string $key
     * @param array $row
     * @return array
     */
    protected function saveRow(string $key, array $row): array
    {
        // Initialize all key-related variables.
        $newKeys = $this->extractKeysFromRow($row);
        $newKey = $this->buildStorageKey($newKeys);
        $newFolder = $this->buildFolder($newKeys);
        $newFilename = $this->buildFilename($newKeys);
        $newFilepath = rtrim($newFolder, '/') !== $newFolder ? $newFolder . $newFilename : $newFolder . '/' . $newFilename;

        try {
            if ($key === '' && empty($row['root'])) {
                throw new RuntimeException('Page has no path');
            }

            $grav = Grav::instance();

            /** @var Debugger $debugger */
            $debugger = $grav['debugger'];
            $debugger->addMessage("Save page: {$newKey}", 'debug');

            // Check if the row already exists.
            $oldKey = $row['__META']['storage_key'] ?? null;
            if (is_string($oldKey)) {
                // Initialize all old key-related variables.
                $oldKeys = $this->extractKeysFromRow(['__META' => $row['__META']], false);
                $oldFolder = $this->buildFolder($oldKeys);
                $oldFilename = $this->buildFilename($oldKeys);

                // Check if folder has changed.
                if ($oldFolder !== $newFolder && file_exists($oldFolder)) {
                    $isCopy = $row['__META']['copy'] ?? false;
                    if ($isCopy) {
                        if (strpos($newFolder, $oldFolder . '/') === 0) {
                            throw new RuntimeException(sprintf('Page /%s cannot be copied to itself', $oldKey));
                        }

                        $this->copyRow($oldKey, $newKey);
                        $debugger->addMessage("Page copied: {$oldFolder} => {$newFolder}", 'debug');
                    } else {
                        if (strpos($newFolder, $oldFolder . '/') === 0) {
                            throw new RuntimeException(sprintf('Page /%s cannot be moved to itself', $oldKey));
                        }

                        $this->renameRow($oldKey, $newKey);
                        $debugger->addMessage("Page moved: {$oldFolder} => {$newFolder}", 'debug');
                    }
                }

                // Check if filename has changed.
                if ($oldFilename !== $newFilename) {
                    // Get instance of the old file (we have already copied/moved it).
                    $oldFilepath = "{$newFolder}/{$oldFilename}";
                    $file = $this->getFile($oldFilepath);

                    // Rename the file if we aren't supposed to clone it.
                    $isClone = $row['__META']['clone'] ?? false;
                    if (!$isClone && $file->exists()) {
                        /** @var UniformResourceLocator $locator */
                        $locator = $grav['locator'];
                        $toPath = $locator->isStream($newFilepath) ? $locator->findResource($newFilepath, true, true) : GRAV_ROOT . "/{$newFilepath}";
                        $success = $file->rename($toPath);
                        if (!$success) {
                            throw new RuntimeException("Changing page template failed: {$oldFilepath} => {$newFilepath}");
                        }
                        $debugger->addMessage("Page template changed: {$oldFilename} => {$newFilename}", 'debug');
                    } else {
                        $file = null;
                        $debugger->addMessage("Page template created: {$newFilename}", 'debug');
                    }
                }
            }

            // Clean up the data to be saved.
            $this->prepareRow($row);
            unset($row['__META'], $row['__ERROR']);

            if (!isset($file)) {
                $file = $this->getFile($newFilepath);
            }

            // Compare existing file content to the new one and save the file only if content has been changed.
            $file->free();
            $oldRaw = $file->raw();
            $file->content($row);
            $newRaw = $file->raw();
            if ($oldRaw !== $newRaw) {
                $file->save($row);
                $debugger->addMessage("Page content saved: {$newFilepath}", 'debug');
            } else {
                $debugger->addMessage('Page content has not been changed, do not update the file', 'debug');
            }
        } catch (RuntimeException $e) {
            $name = isset($file) ? $file->filename() : $newKey;

            throw new RuntimeException(sprintf('Flex saveRow(%s): %s', $name, $e->getMessage()));
        } finally {
            /** @var UniformResourceLocator $locator */
            $locator = Grav::instance()['locator'];
            $locator->clearCache();

            if (isset($file)) {
                $file->free();
                unset($file);
            }
        }

        $row['__META'] = $this->getObjectMeta($newKey, true);

        return $row;
    }

    /**
     * Check if page folder should be deleted.
     *
     * Deleting page can be done either by deleting everything or just a single language.
     * If key contains the language, delete only it, unless it is the last language.
     *
     * @param string $key
     * @return bool
     */
    protected function canDeleteFolder(string $key): bool
    {
        // Return true if there's no language in the key.
        $keys = $this->extractKeysFromStorageKey($key);
        if (!$keys['lang']) {
            return true;
        }

        // Get the main key and reload meta.
        $key = $this->buildStorageKey($keys);
        $meta = $this->getObjectMeta($key, true);

        // Return true if there aren't any markdown files left.
        return empty($meta['markdown'] ?? []);
    }

    /**
     * Get key from the filesystem path.
     *
     * @param  string $path
     * @return string
     */
    protected function getKeyFromPath(string $path): string
    {
        if ($this->base_path) {
            $path = $this->base_path . '/' . $path;
        }

        return $path;
    }

    /**
     * Returns list of all stored keys in [key => timestamp] pairs.
     *
     * @return array
     */
    protected function buildIndex(): array
    {
        $this->clearCache();

        return $this->getIndexMeta();
    }

    /**
     * @param string $key
     * @param bool $reload
     * @return array
     */
    protected function getObjectMeta(string $key, bool $reload = false): array
    {
        $keys = $this->extractKeysFromStorageKey($key);
        $key = $keys['key'];

        if ($reload || !isset($this->meta[$key])) {
            /** @var UniformResourceLocator $locator */
            $locator = Grav::instance()['locator'];
            if (mb_strpos($key, '@@') === false) {
                $path = $this->getStoragePath($key);
                if (is_string($path)) {
                    $path = $locator->isStream($path) ? $locator->findResource($path) : GRAV_ROOT . "/{$path}";
                } else {
                    $path = null;
                }
            } else {
                $path = null;
            }

            $modified = 0;
            $markdown = [];
            $children = [];

            if (is_string($path) && is_dir($path)) {
                $modified = filemtime($path);
                $iterator = new FilesystemIterator($path, $this->flags);

                /** @var SplFileInfo $info */
                foreach ($iterator as $k => $info) {
                    // Ignore all hidden files if set.
                    if ($k === '' || ($this->ignore_hidden && $k[0] === '.')) {
                        continue;
                    }

                    if ($info->isDir()) {
                        // Ignore all folders in ignore list.
                        if ($this->ignore_folders && in_array($k, $this->ignore_folders, true)) {
                            continue;
                        }

                        $children[$k] = false;
                    } else {
                        // Ignore all files in ignore list.
                        if ($this->ignore_files && in_array($k, $this->ignore_files, true)) {
                            continue;
                        }

                        $timestamp = $info->getMTime();

                        // Page is the one that matches to $page_extensions list with the lowest index number.
                        if (preg_match($this->regex, $k, $matches)) {
                            $mark = $matches[2] ?? '';
                            $ext = $matches[1] ?? '';
                            $ext .= $this->dataExt;
                            $markdown[$mark][Utils::basename($k, $ext)] = $timestamp;
                        }

                        $modified = max($modified, $timestamp);
                    }
                }
            }

            $rawRoute = trim(preg_replace(PageIndex::PAGE_ROUTE_REGEX, '/', "/{$key}") ?? '', '/');
            $route = PageIndex::normalizeRoute($rawRoute);

            ksort($markdown, SORT_NATURAL | SORT_FLAG_CASE);
            ksort($children, SORT_NATURAL | SORT_FLAG_CASE);

            $file = array_key_first($markdown[''] ?? (reset($markdown) ?: []));

            $meta = [
                'key' => $route,
                'storage_key' => $key,
                'template' => $file,
                'storage_timestamp' => $modified,
            ];
            if ($markdown) {
                $meta['markdown'] = $markdown;
            }
            if ($children) {
                $meta['children'] = $children;
            }
            $meta['checksum'] = md5(json_encode($meta) ?: '');

            // Cache meta as copy.
            $this->meta[$key] = $meta;
        } else {
            $meta = $this->meta[$key];
        }

        $params = $keys['params'];
        if ($params) {
            $language = $keys['lang'];
            $template = $keys['template'] ?: array_key_first($meta['markdown'][$language]) ?? $meta['template'];
            $meta['exists'] = ($template && !empty($meta['children'])) || isset($meta['markdown'][$language][$template]);
            $meta['storage_key'] .= '|' . $params;
            $meta['template'] = $template;
            $meta['lang'] = $language;
        }

        return $meta;
    }

    /**
     * @return array
     */
    protected function getIndexMeta(): array
    {
        $queue = [''];
        $list = [];
        do {
            $current = array_pop($queue);
            if ($current === null) {
                break;
            }

            $meta = $this->getObjectMeta($current);
            $storage_key = $meta['storage_key'];

            if (!empty($meta['children'])) {
                $prefix = $storage_key . ($storage_key !== '' ? '/' : '');

                foreach ($meta['children'] as $child => $value) {
                    $queue[] = $prefix . $child;
                }
            }

            $list[$storage_key] = $meta;
        } while ($queue);

        ksort($list, SORT_NATURAL | SORT_FLAG_CASE);

        // Update parent timestamps.
        foreach (array_reverse($list) as $storage_key => $meta) {
            if ($storage_key !== '') {
                $filesystem = Filesystem::getInstance(false);

                $storage_key = (string)$storage_key;
                $parentKey = $filesystem->dirname($storage_key);
                if ($parentKey === '.') {
                    $parentKey = '';
                }

                /** @phpstan-var array{'storage_key': string, 'storage_timestamp': int, 'children': array<string, mixed>} $parent */
                $parent = &$list[$parentKey];
                $basename = Utils::basename($storage_key);

                if (isset($parent['children'][$basename])) {
                    $timestamp = $meta['storage_timestamp'];
                    $parent['children'][$basename] = $timestamp;
                    if ($basename && $basename[0] === '_') {
                        $parent['storage_timestamp'] = max($parent['storage_timestamp'], $timestamp);
                    }
                }
            }
        }

        return $list;
    }

    /**
     * @return string
     */
    protected function getNewKey(): string
    {
        throw new RuntimeException('Generating random key is disabled for pages');
    }
}
