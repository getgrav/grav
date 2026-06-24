<?php

/**
 * @package    Grav\Common\Filesystem
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Filesystem;

use Grav\Common\Grav;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;
use function extension_loaded;
use function strlen;

/**
 * Class ZipArchiver
 * @package Grav\Common\Filesystem
 */
class ZipArchiver extends Archiver
{
    /**
     * @param string $destination
     * @param callable|null $status
     * @return $this
     */
    public function extract($destination, ?callable $status = null)
    {
        $zip = new ZipArchive();
        $archive = $zip->open($this->archive_file);

        if ($archive === true) {
            // Validate every entry before creating the destination or extracting
            // anything, so a bad archive leaves nothing on disk. Two guards run
            // in this single pass:
            //
            //  - Zip Slip: reject any entry whose path resolves outside the
            //    destination directory (e.g. "../../evil.php"). CWE-22.
            //  - Decompression bomb: ZipArchive::extractTo applies no limit on
            //    total uncompressed size, entry count, or directory depth, so a
            //    crafted archive can fill the disk / exhaust inodes (CWE-409) or
            //    nest deeply enough to overflow recursive cleanup (CWE-674).
            //    Reject anything over the configured limits, matching the caps
            //    GPM\Installer::unZip() already enforces (GHSA-2vcx-h8p2-9pg9,
            //    GHSA-928x-9mpw-8h56).
            [$maxSize, $maxFiles, $maxDepth] = $this->archiveLimits();
            $numFiles = $zip->count();

            if ($maxFiles > 0 && $numFiles > $maxFiles) {
                $zip->close();
                throw new RuntimeException('ZipArchiver: refused to extract ' . $this->archive_file . '. Archive exceeds the maximum file count (' . $maxFiles . ').');
            }

            $totalSize = 0;
            for ($i = 0; $i < $numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if ($name === false) {
                    continue;
                }

                if (!$this->isSafeEntryPath($name)) {
                    $zip->close();
                    throw new RuntimeException('ZipArchiver: refused to extract ' . $this->archive_file . '. Entry "' . $name . '" would escape the destination directory (Zip Slip).');
                }

                if ($maxDepth > 0) {
                    $depth = count(array_filter(preg_split('#[\\\\/]+#', trim($name, '/\\'))));
                    if ($depth > $maxDepth) {
                        $zip->close();
                        throw new RuntimeException('ZipArchiver: refused to extract ' . $this->archive_file . '. Entry "' . $name . '" exceeds the maximum nesting depth (' . $maxDepth . ').');
                    }
                }

                if ($maxSize > 0) {
                    // Advisory only: statIndex()['size'] is the uncompressed size
                    // declared in the central directory, which the archive author
                    // controls and can forge small (GHSA-8h9x-89f2-m7x3). It gives
                    // an early reject for honest oversized archives, but the real
                    // enforcement happens during streamed extraction below, against
                    // the bytes actually inflated.
                    $stat = $zip->statIndex($i);
                    if (is_array($stat) && isset($stat['size'])) {
                        $totalSize += (int) $stat['size'];
                        if ($totalSize > $maxSize) {
                            $zip->close();
                            throw new RuntimeException('ZipArchiver: refused to extract ' . $this->archive_file . '. Archive exceeds the maximum uncompressed size (' . $maxSize . ' bytes).');
                        }
                    }
                }
            }

            Folder::create($destination);

            if ($maxSize > 0) {
                // Enforce the uncompressed-size cap against bytes actually written,
                // so a forged-small declared size cannot smuggle a bomb past the
                // advisory pre-pass above (GHSA-8h9x-89f2-m7x3).
                $this->extractStreamed($zip, $destination, $numFiles, $maxSize);
            } elseif (!$zip->extractTo($destination)) {
                $zip->close();
                throw new RuntimeException('ZipArchiver: ZIP failed to extract ' . $this->archive_file . ' to ' . $destination);
            }

            $zip->close();

            return $this;
        }

        throw new RuntimeException('ZipArchiver: Failed to open ' . $this->archive_file);
    }

    /**
     * Resolve the uncompressed-size / file-count / nesting-depth caps applied
     * before extraction. Shares the same config keys and defaults as
     * GPM\Installer so both ZIP extraction paths enforce identical limits. A
     * limit of 0 disables that particular check.
     *
     * @return array{0:int,1:int,2:int} [maxUncompressedBytes, maxFiles, maxDepth]
     */
    protected function archiveLimits(): array
    {
        $config = Grav::instance()['config'] ?? null;

        $maxSize  = $config ? (int) $config->get('system.gpm.archive.max_uncompressed_size', 1073741824) : 1073741824; // 1 GiB
        $maxFiles = $config ? (int) $config->get('system.gpm.archive.max_files', 50000) : 50000;
        $maxDepth = $config ? (int) $config->get('system.gpm.archive.max_depth', 48) : 48;

        return [$maxSize, $maxFiles, $maxDepth];
    }

    /**
     * Returns true if a ZIP entry name stays inside the extraction root.
     *
     * Resolves "." and ".." lexically (no filesystem access, so it also covers
     * entries that do not exist on disk yet) and rejects absolute paths and
     * Windows drive letters.
     *
     * @param string $name
     * @return bool
     */
    protected function isSafeEntryPath(string $name): bool
    {
        $name = str_replace('\\', '/', $name);

        // Absolute paths and Windows drive letters never belong in an archive entry.
        if (str_starts_with($name, '/') || preg_match('#^[a-zA-Z]:#', $name)) {
            return false;
        }

        // Walk the path segments, tracking depth below the extraction root. If any
        // prefix dips below zero, the entry has climbed out of the destination.
        $depth = 0;
        foreach (explode('/', $name) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                if (--$depth < 0) {
                    return false;
                }
                continue;
            }
            $depth++;
        }

        return true;
    }

    /**
     * Extract every entry through a counting stream, aborting the moment the
     * cumulative inflated size exceeds $maxSize. Unlike ZipArchive::extractTo,
     * this enforces the cap against bytes actually written rather than the
     * attacker-controlled sizes declared in the central directory, so a forged
     * archive cannot fill the disk (GHSA-8h9x-89f2-m7x3). Entry paths were
     * validated in the caller's pre-pass, so they are safe to write here.
     *
     * @param ZipArchive $zip
     * @param string $destination
     * @param int $numFiles
     * @param int $maxSize
     * @return void
     */
    protected function extractStreamed(ZipArchive $zip, string $destination, int $numFiles, int $maxSize): void
    {
        $written = 0;
        $chunkSize = 262144; // 256 KiB

        for ($i = 0; $i < $numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) {
                continue;
            }

            $relative = str_replace('\\', '/', $name);
            $target = $destination . '/' . $relative;

            // Directory entry: create it and move on.
            if (str_ends_with($relative, '/')) {
                Folder::create($target);
                continue;
            }

            $dir = dirname($target);
            if (!is_dir($dir)) {
                Folder::create($dir);
            }

            $stream = $zip->getStream($name);
            if ($stream === false) {
                $this->abortExtraction($zip, $destination, 'ZipArchiver: failed to read entry "' . $name . '" from ' . $this->archive_file . '.');
            }

            $out = fopen($target, 'wb');
            if ($out === false) {
                fclose($stream);
                $this->abortExtraction($zip, $destination, 'ZipArchiver: failed to write "' . $target . '".');
            }

            while (!feof($stream)) {
                $buffer = fread($stream, $chunkSize);
                if ($buffer === false) {
                    break;
                }

                $written += strlen($buffer);
                if ($written > $maxSize) {
                    fclose($out);
                    fclose($stream);
                    $this->abortExtraction($zip, $destination, 'ZipArchiver: refused to extract ' . $this->archive_file . '. Archive exceeds the maximum uncompressed size (' . $maxSize . ' bytes).');
                }

                if ($buffer !== '' && fwrite($out, $buffer) === false) {
                    fclose($out);
                    fclose($stream);
                    $this->abortExtraction($zip, $destination, 'ZipArchiver: failed to write "' . $target . '".');
                }
            }

            fclose($out);
            fclose($stream);
        }
    }

    /**
     * Close the archive, remove everything extracted so far, and throw. Keeps a
     * rejected archive from leaving partial output on disk.
     *
     * @param ZipArchive $zip
     * @param string $destination
     * @param string $message
     * @return never
     */
    protected function abortExtraction(ZipArchive $zip, string $destination, string $message): void
    {
        $zip->close();
        Folder::delete($destination);

        throw new RuntimeException($message);
    }

    /**
     * @param string $source
     * @param callable|null $status
     * @return $this
     */
    public function compress($source, ?callable $status = null)
    {
        if (!extension_loaded('zip')) {
            throw new InvalidArgumentException('ZipArchiver: Zip PHP module not installed...');
        }

        // Get real path for our folder
        $rootPath = realpath($source);
        if (!$rootPath) {
            throw new InvalidArgumentException('ZipArchiver: ' . $source . ' cannot be found...');
        }

        $zip = new ZipArchive();
        $result = $zip->open($this->archive_file, ZipArchive::CREATE);
        if ($result !== true) {
            $error = 'unknown error';
            if ($result === ZipArchive::ER_NOENT) {
                $error = 'file does not exist';
            } elseif ($result === ZipArchive::ER_EXISTS) {
                $error = 'file already exists';
            } elseif ($result === ZipArchive::ER_OPEN) {
                $error = 'cannot open file';
            } elseif ($result === ZipArchive::ER_READ) {
                $error = 'read error';
            } elseif ($result === ZipArchive::ER_SEEK) {
                $error = 'seek error';
            }
            throw new InvalidArgumentException('ZipArchiver: ' . $this->archive_file . ' cannot be created: ' . $error);
        }

        $files = $this->getArchiveFiles($rootPath);

        $status && $status([
            'type' => 'count',
            'steps' => iterator_count($files),
        ]);

        foreach ($files as $file) {
            $filePath = $file->getPathname();
            $relativePath = ltrim(substr((string) $filePath, strlen($rootPath)), '/');

            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($filePath, $relativePath);
            }

            $status && $status([
                'type' => 'progress',
            ]);
        }

        $status && $status([
            'type' => 'message',
            'message' => 'Compressing...'
        ]);

        $zip->close();

        return $this;
    }

    /**
     * @param array $folders
     * @param callable|null $status
     * @return $this
     */
    public function addEmptyFolders($folders, ?callable $status = null)
    {
        if (!extension_loaded('zip')) {
            throw new InvalidArgumentException('ZipArchiver: Zip PHP module not installed...');
        }

        $zip = new ZipArchive();
        $result = $zip->open($this->archive_file);
        if ($result !== true) {
            $error = 'unknown error';
            if ($result === ZipArchive::ER_NOENT) {
                $error = 'file does not exist';
            } elseif ($result === ZipArchive::ER_EXISTS) {
                $error = 'file already exists';
            } elseif ($result === ZipArchive::ER_OPEN) {
                $error = 'cannot open file';
            } elseif ($result === ZipArchive::ER_READ) {
                $error = 'read error';
            } elseif ($result === ZipArchive::ER_SEEK) {
                $error = 'seek error';
            }
            throw new InvalidArgumentException('ZipArchiver: ' . $this->archive_file . ' cannot be opened: ' . $error);
        }

        $status && $status([
            'type' => 'message',
            'message' => 'Adding empty folders...'
        ]);

        foreach ($folders as $folder) {
            if ($zip->addEmptyDir($folder) === false) {
                $status && $status([
                    'type' => 'message',
                    'message' => 'Warning: Could not add empty directory: ' . $folder
                ]);
            }
            $status && $status([
                'type' => 'progress',
            ]);
        }

        $zip->close();

        return $this;
    }
}
