<?php

/**
 * @package    Grav\Common\Filesystem
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Filesystem;

class ZipArchiver extends Archiver
{

    public function extract($destination, callable $status = null)
    {
        $zip = new \ZipArchive();
        $archive = $zip->open($this->archive_file);

        if ($archive === true) {
            Folder::create($destination);

            if (!$zip->extractTo($destination)) {
                throw new \RuntimeException('ZipArchiver: ZIP failed to extract ' . $this->archive_file . ' to ' . $destination);
            }

            $zip->close();
            return $this;
        }

        throw new \RuntimeException('ZipArchiver: Failed to open ' . $this->archive_file);
    }

    public function compress($source, callable $status = null)
    {
        if (!extension_loaded('zip')) {
            throw new \InvalidArgumentException('ZipArchiver: Zip PHP module not installed...');
        }

        if (!file_exists($source)) {
            throw new \InvalidArgumentException('ZipArchiver: ' . $source . ' cannot be found...');
        }

        $zip = new \ZipArchive();
        if (!$zip->open($this->archive_file, \ZipArchive::CREATE)) {
            throw new \InvalidArgumentException('ZipArchiver:' . $this->archive_file . ' cannot be created...');
        }

        // Get real path for our folder
        $rootPath = realpath($source);

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

    public function addEmptyFolders($folders, callable $status = null)
    {
        if (!extension_loaded('zip')) {
            throw new \InvalidArgumentException('ZipArchiver: Zip PHP module not installed...');
        }

        $zip = new \ZipArchive();
        if (!$zip->open($this->archive_file)) {
            throw new \InvalidArgumentException('ZipArchiver: ' . $this->archive_file . ' cannot be opened...');
        }

        $status && $status([
            'type' => 'message',
            'message' => 'Adding empty folders...'
        ]);

        foreach($folders as $folder) {
            $zip->addEmptyDir($folder);
            $status && $status([
                'type' => 'progress',
            ]);
        }

        $zip->close();

        return $this;
    }
}
