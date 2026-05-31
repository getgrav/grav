<?php

/**
 * @package    Grav\Common\Filesystem
 *
 * @copyright  Copyright (c) 2015 - 2025 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Filesystem;

use InvalidArgumentException;
use RuntimeException;
use ZipArchive;
use function extension_loaded;
use function realpath;
use function str_starts_with;
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
    public function extract($destination, callable $status = null)
    {
        $zip = new ZipArchive();
        $archive = $zip->open($this->archive_file);

        if ($archive === true) {
            Folder::create($destination);

            // Validate every entry path before extracting to prevent Zip Slip
            // (path traversal via crafted entry names such as "../../evil.php").
            $destination = rtrim((string) realpath($destination) ?: $destination, '/\\') . DIRECTORY_SEPARATOR;
            for ($i = 0, $count = $zip->count(); $i < $count; $i++) {
                $name = $zip->getNameIndex($i);
                if ($name === false) {
                    continue;
                }
                // Resolve the entry's target path and ensure it stays within $destination.
                $entryPath = realpath($destination . $name);
                if ($entryPath === false) {
                    // realpath() returns false for non-existent paths; build a normalised path instead.
                    $entryPath = $destination . $name;
                }
                // Normalise directory separators before the prefix check.
                $entryPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $entryPath);
                if (!str_starts_with($entryPath, $destination)) {
                    $zip->close();
                    throw new RuntimeException(
                        'ZipArchiver: Zip Slip detected — entry "' . $name . '" would extract outside destination directory.'
                    );
                }
            }

            if (!$zip->extractTo($destination)) {
                throw new RuntimeException('ZipArchiver: ZIP failed to extract ' . $this->archive_file . ' to ' . $destination);
            }

            $zip->close();

            return $this;
        }

        throw new RuntimeException('ZipArchiver: Failed to open ' . $this->archive_file);
    }

    /**
     * @param string $source
     * @param callable|null $status
     * @return $this
     */
    public function compress($source, callable $status = null)
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
            $relativePath = ltrim(substr($filePath, strlen($rootPath)), '/');

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
    public function addEmptyFolders($folders, callable $status = null)
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
