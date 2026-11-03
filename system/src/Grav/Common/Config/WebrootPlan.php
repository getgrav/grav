<?php

/**
 * @package    Grav\Common\Config
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Config;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;
use RuntimeException;

/**
 * Everything webroot:split or webroot:join is going to do, worked out up front by
 * WebrootPlanner. The dry run prints this object and the real run applies the same one, so
 * nothing outside the plan is ever moved or deleted.
 */
final class WebrootPlan
{
    public const SPLIT = 'split';
    public const JOIN = 'join';

    /** @var string self::SPLIT or self::JOIN */
    public $action;
    /** @var string The private Grav folder. */
    public $root;
    /** @var string The webroot: the folder to create for a split, the one to empty for a join. */
    public $webroot;
    /** @var array<string,string> Absolute path => where it is moved to. index.php comes last. */
    public $moves = [];
    /** @var array<string,array{from:string,to:string}> Relative symlinks to rewrite, by their path after the move. */
    public $links = [];
    /** @var string[] Top-level names a split leaves in the private folder without knowing what they are. */
    public $unknown = [];
    /** @var string[] Reasons the plan can't be applied. */
    public $errors = [];
    /** @var string[] Things the user has to do or know afterwards. */
    public $notes = [];
    /** @var WebrootPublisher|null Split: publishes system files. Join: removes the published copy. */
    public $publisher;

    public function __construct(string $action, string $root, string $webroot)
    {
        $this->action = $action;
        $this->root = $root;
        $this->webroot = $webroot;
    }

    public function canApply(): bool
    {
        return !$this->errors;
    }

    /**
     * Carry out the plan.
     *
     * @param callable|null $log Called with one line of text per step.
     * @return string[] Problems that did not stop the run (a symlink that could not be rewritten...).
     */
    public function apply(?callable $log = null): array
    {
        if ($this->errors) {
            throw new RuntimeException(implode("\n", $this->errors));
        }

        $log = $log ?? static function (string $line): void {};
        $problems = [];

        if ($this->action === self::SPLIT && !is_dir($this->webroot) && !@mkdir($this->webroot, 0775) && !is_dir($this->webroot)) {
            throw new RuntimeException(sprintf('Could not create %s.', $this->webroot));
        }

        // Maintenance page in both folders while things move: the web server serves one of them.
        $flags = [$this->root . '/.upgrading', $this->webroot . '/.upgrading'];
        foreach ($flags as $flag) {
            @file_put_contents($flag, date('Y-m-d H:i:s'));
        }

        try {
            if ($this->action === self::JOIN && $this->publisher) {
                $left = $this->publisher->remove();
                $log('Removed the published system files from ' . $this->publisher->target());
                if ($left) {
                    $problems[] = sprintf('Left %d file(s) in %s that webroot:sync did not publish.', \count($left), $this->publisher->target());
                }
            }

            foreach ($this->moves as $from => $to) {
                self::move($from, $to);
                $log(sprintf('Moved %s to %s', $from, $to));
            }

            foreach ($this->links as $link => $change) {
                if (self::relink($link, $change['to'])) {
                    $log(sprintf('Rewrote symlink %s: %s -> %s', $link, $change['from'], $change['to']));
                } else {
                    $problems[] = sprintf('Could not rewrite symlink %s to %s.', $link, $change['to']);
                }
            }

            if ($this->action === self::SPLIT && $this->publisher) {
                $result = $this->publisher->sync();
                $log(sprintf('Published %d system file(s) to %s', \count($result['copy']) + \count($result['update']), $this->publisher->target()));
            }
        } finally {
            foreach ($flags as $flag) {
                @unlink($flag);
            }
        }

        if ($this->action === self::JOIN) {
            if (@rmdir($this->webroot)) {
                $log('Removed the empty folder ' . $this->webroot);
            } else {
                $problems[] = sprintf('%s is not empty, so it was left in place.', $this->webroot);
            }
        }

        return $problems;
    }

    /**
     * Move a file, folder or symlink. rename() first, then copy and delete when rename()
     * can't (another filesystem).
     */
    public static function move(string $from, string $to): void
    {
        if (file_exists($to) || is_link($to)) {
            throw new RuntimeException(sprintf('%s already exists.', $to));
        }
        if (@rename($from, $to)) {
            return;
        }

        self::copyTree($from, $to);
        self::deleteTree($from);
    }

    /**
     * Point a symlink somewhere else.
     */
    public static function relink(string $link, string $target): bool
    {
        if (!is_link($link)) {
            return false;
        }
        // A link to a folder is removed with rmdir() on Windows.
        if (!@unlink($link) && !@rmdir($link)) {
            return false;
        }

        return self::symlink($target, $link);
    }

    /**
     * PHP on Windows resolves a relative symlink target against the working directory, not the
     * link's folder, and stores it as an absolute path. Resolve it from the link's folder there.
     */
    private static function symlink(string $target, string $link): bool
    {
        if (\DIRECTORY_SEPARATOR === '\\' && !InstallLayout::isAbsolute($target)) {
            $target = \dirname($link) . '/' . $target;
        }

        return @symlink($target, $link);
    }

    private static function copyTree(string $from, string $to): void
    {
        if (is_link($from)) {
            if (!self::symlink((string) readlink($from), $to)) {
                throw new RuntimeException(sprintf('Could not copy the symlink %s.', $from));
            }

            return;
        }
        if (is_file($from)) {
            if (!@copy($from, $to)) {
                throw new RuntimeException(sprintf('Could not copy %s to %s.', $from, $to));
            }
            @chmod($to, fileperms($from) & 0777);

            return;
        }

        if (!@mkdir($to, fileperms($from) & 0777) && !is_dir($to)) {
            throw new RuntimeException(sprintf('Could not create %s.', $to));
        }
        foreach (scandir($from) ?: [] as $name) {
            if ($name !== '.' && $name !== '..') {
                self::copyTree($from . '/' . $name, $to . '/' . $name);
            }
        }
    }

    private static function deleteTree(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);

            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            // Symlinks are removed, never followed.
            if ($file->isDir() && !$file->isLink()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }
        @rmdir($path);
    }
}
