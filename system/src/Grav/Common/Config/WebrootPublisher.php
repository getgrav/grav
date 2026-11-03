<?php

/**
 * @package    Grav\Common\Config
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Config;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * Publishes the static files of system/ that are linked by URL into the webroot.
 *
 * In the public webroot layout system/ is private, so `system://assets/...` and
 * `system://images/...` files (jQuery, the debugger's CSS and JS, the media type icons) can't
 * be served from there. The system stream is `['system', GRAV_ROOT/system]` and the relative
 * entry resolves against GRAV_WEBROOT, so a copy at GRAV_WEBROOT/system/... is found first and
 * gets a working `/system/...` URL, while every other `system://` lookup falls through to the
 * real folder.
 *
 * Only regular files with a static extension under the folders in FOLDERS are copied, never
 * PHP, YAML, Twig or config. A manifest next to the copy lists what was published, so files
 * that left the source are removed and nothing else in the folder is ever touched.
 *
 * Plain PHP on purpose: it also runs from the core upgrade, under the old Grav version.
 */
final class WebrootPublisher
{
    /** @var string[] Folders of system/ that are linked by URL. */
    public const FOLDERS = ['assets', 'images'];

    /** @var string[] File types that are published. */
    public const EXTENSIONS = ['css', 'js', 'map', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'otf', 'eot'];

    /** @var string Manifest file, kept in the published folder. Dotfile and .json, so the shipped server rules deny it. */
    public const MANIFEST = '.published.json';

    /** @var string */
    private $source;
    /** @var string */
    private $target;

    /**
     * @param string $source The real system folder, ie. GRAV_ROOT/system.
     * @param string $target The published copy, ie. GRAV_WEBROOT/system.
     */
    public function __construct(string $source, string $target)
    {
        $this->source = rtrim(str_replace('\\', '/', $source), '/');
        $this->target = rtrim(str_replace('\\', '/', $target), '/');
    }

    /**
     * The publisher of the running site, or null in the single-folder layout.
     *
     * @return self|null
     */
    public static function forSite(): ?self
    {
        if (!InstallLayout::isSplit()) {
            return null;
        }

        $system = \defined('GRAV_SYSTEM_PATH') ? GRAV_SYSTEM_PATH : 'system';

        return new self(InstallLayout::resolve(GRAV_ROOT, $system), GRAV_WEBROOT . '/system');
    }

    public function source(): string
    {
        return $this->source;
    }

    public function target(): string
    {
        return $this->target;
    }

    /**
     * Files to publish, as path relative to the system folder => sha1.
     *
     * @return array<string,string>
     */
    public function sourceFiles(): array
    {
        $files = [];
        foreach (self::FOLDERS as $folder) {
            foreach ($this->walk($this->source . '/' . $folder) as $path) {
                $relative = $folder . substr($path, \strlen($this->source . '/' . $folder));
                if (self::isPublishable($relative) && !is_link($path)) {
                    $files[$relative] = (string) sha1_file($path);
                }
            }
        }
        ksort($files);

        return $files;
    }

    /**
     * Files the last sync published, as relative path => sha1.
     *
     * @return array<string,string>
     */
    public function manifest(): array
    {
        $file = $this->target . '/' . self::MANIFEST;
        if (!is_file($file)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($file), true);
        $files = [];
        foreach ((array) ($data['files'] ?? []) as $relative => $hash) {
            // Never trust a path read from disk to stay inside the published folders.
            if (\is_string($relative) && \is_string($hash) && self::isPublishable($relative)) {
                $files[$relative] = $hash;
            }
        }

        return $files;
    }

    /**
     * What a sync would do. Changes nothing.
     *
     * @return array{copy: string[], update: string[], delete: string[], extra: string[], manifest: bool, total: int}
     *   copy: missing from the copy; update: different from the source; delete: published
     *   earlier but no longer in the source; extra: files in the published folders that
     *   webroot:sync did not put there (left alone); manifest: the manifest needs writing.
     */
    public function plan(): array
    {
        $source = $this->sourceFiles();
        $manifest = $this->manifest();
        $copy = $update = $delete = [];

        foreach ($source as $relative => $hash) {
            $file = $this->target . '/' . $relative;
            if (!is_file($file) || is_link($file)) {
                $copy[] = $relative;
            } elseif (sha1_file($file) !== $hash) {
                $update[] = $relative;
            }
        }

        foreach ($manifest as $relative => $hash) {
            $file = $this->target . '/' . $relative;
            if (!isset($source[$relative]) && (is_file($file) || is_link($file))) {
                $delete[] = $relative;
            }
        }

        return [
            'copy' => $copy,
            'update' => $update,
            'delete' => $delete,
            'extra' => $this->extraFiles($source, $manifest),
            'manifest' => $manifest != $source,
            'total' => \count($source),
        ];
    }

    /**
     * @param array|null $plan Result of plan(), to avoid working it out twice.
     * @return bool True when the copy matches the source.
     */
    public function isInSync(?array $plan = null): bool
    {
        $plan = $plan ?? $this->plan();

        return !$plan['copy'] && !$plan['update'] && !$plan['delete'] && !$plan['manifest'];
    }

    /**
     * Copy new and changed files, remove the ones that left the source, write the manifest.
     *
     * @return array The plan that was applied, see plan().
     */
    public function sync(): array
    {
        if (!is_dir($this->source)) {
            throw new RuntimeException(sprintf('System folder %s not found.', $this->source));
        }

        $plan = $this->plan();
        $source = $this->sourceFiles();

        foreach (array_merge($plan['copy'], $plan['update']) as $relative) {
            $from = $this->source . '/' . $relative;
            $to = $this->target . '/' . $relative;
            $dir = \dirname($to);
            if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new RuntimeException(sprintf('Could not create %s.', $dir));
            }
            if (is_link($to)) {
                @unlink($to);
            }
            // Copy next to the file, then rename over it, so a request never reads half a file.
            $temp = $to . '.tmp-' . getmypid();
            if (!@copy($from, $temp) || !@rename($temp, $to)) {
                @unlink($temp);
                throw new RuntimeException(sprintf('Could not copy %s to %s.', $from, $to));
            }
        }

        foreach ($plan['delete'] as $relative) {
            $this->deleteFile($relative);
        }

        if ($plan['manifest'] || !is_file($this->target . '/' . self::MANIFEST)) {
            if (!is_dir($this->target)) {
                @mkdir($this->target, 0775, true);
            }
            $data = ['grav' => \defined('GRAV_VERSION') ? GRAV_VERSION : null, 'files' => $source];
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
            if (@file_put_contents($this->target . '/' . self::MANIFEST, $json) === false) {
                throw new RuntimeException(sprintf('Could not write %s.', $this->target . '/' . self::MANIFEST));
            }
        }

        return $plan;
    }

    /**
     * Remove the published copy: every file the manifest or the source lists, the manifest,
     * and the folders that are empty after that. Files webroot:sync did not put there stay.
     *
     * @return string[] Files left behind because they were not published by webroot:sync.
     */
    public function remove(): array
    {
        $source = $this->sourceFiles();
        $manifest = $this->manifest();
        $left = $this->extraFiles($source, $manifest);

        foreach (array_keys($manifest + $source) as $relative) {
            $file = $this->target . '/' . $relative;
            if (is_file($file) || is_link($file)) {
                $this->deleteFile($relative);
            }
        }

        @unlink($this->target . '/' . self::MANIFEST);
        foreach (self::FOLDERS as $folder) {
            $this->pruneEmptyFolders($this->target . '/' . $folder);
        }
        @rmdir($this->target);

        return $left;
    }

    /**
     * True for a path inside one of the published folders with a static file type.
     *
     * @param string $relative Path relative to the system folder.
     * @return bool
     */
    public static function isPublishable(string $relative): bool
    {
        $relative = str_replace('\\', '/', $relative);
        if ($relative === '' || $relative[0] === '/' || preg_match('#(^|/)\.\.?(/|$)#', $relative)) {
            return false;
        }

        $folder = strstr($relative, '/', true);
        if ($folder === false || !\in_array($folder, self::FOLDERS, true)) {
            return false;
        }

        return \in_array(strtolower(pathinfo($relative, PATHINFO_EXTENSION)), self::EXTENSIONS, true);
    }

    /**
     * Files in the published copy that neither the source nor the manifest list.
     *
     * @param array<string,string> $source
     * @param array<string,string> $manifest
     * @return string[]
     */
    private function extraFiles(array $source, array $manifest): array
    {
        $extra = [];
        if (!is_dir($this->target)) {
            return $extra;
        }

        foreach ($this->walk($this->target) as $path) {
            $relative = substr($path, \strlen($this->target) + 1);
            if ($relative !== self::MANIFEST && !isset($source[$relative]) && !isset($manifest[$relative])) {
                $extra[] = $relative;
            }
        }
        sort($extra);

        return $extra;
    }

    private function deleteFile(string $relative): void
    {
        $file = $this->target . '/' . $relative;
        @unlink($file);

        // Remove the folders the file leaves empty, up to the published folder.
        $dir = \dirname($file);
        while (\strlen($dir) > \strlen($this->target) && @rmdir($dir)) {
            $dir = \dirname($dir);
        }
    }

    private function pruneEmptyFolders(string $dir): void
    {
        if (!is_dir($dir) || is_link($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $name) {
            if ($name !== '.' && $name !== '..') {
                $this->pruneEmptyFolders($dir . '/' . $name);
            }
        }
        @rmdir($dir);
    }

    /**
     * Files (and symlinks) under a folder. Symlinked folders are not followed.
     *
     * @param string $dir
     * @return string[]
     */
    private function walk(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS),
            RecursiveIteratorIterator::LEAVES_ONLY,
            RecursiveIteratorIterator::CATCH_GET_CHILD
        );
        foreach ($iterator as $file) {
            if ($file->isLink() || $file->isFile()) {
                $files[] = str_replace('\\', '/', $file->getPathname());
            }
        }
        sort($files);

        return $files;
    }
}
