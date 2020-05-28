<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media\Traits;

use Grav\Common\Config\Config;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Language\Language;
use Grav\Common\Utils;
use Grav\Framework\Filesystem\Filesystem;
use Grav\Framework\Form\FormFlashFile;
use Psr\Http\Message\UploadedFileInterface;
use RocketTheme\Toolbox\File\YamlFile;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RuntimeException;

/**
 * Implements media upload and delete functionality.
 */
trait MediaUploadTrait
{
    /**
     * @param UploadedFileInterface $uploadedFile
     * @param string|null $filename
     * @return string $filename
     * @throws RuntimeException
     */
    public function checkUploadedFile(UploadedFileInterface $uploadedFile, string $filename = null): string
    {
        // Check if there is an upload error.
        switch ($uploadedFile->getError()) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                if (!$uploadedFile instanceof FormFlashFile) {
                    throw new RuntimeException($this->translate('PLUGIN_ADMIN.NO_FILES_SENT'), 400);
                }
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new RuntimeException($this->translate('PLUGIN_ADMIN.EXCEEDED_FILESIZE_LIMIT'), 400);
            case UPLOAD_ERR_NO_TMP_DIR:
                throw new RuntimeException($this->translate('PLUGIN_ADMIN.UPLOAD_ERR_NO_TMP_DIR'), 400);
            default:
                throw new RuntimeException($this->translate('PLUGIN_ADMIN.UNKNOWN_ERRORS'), 400);
        }

        if (null === $filename) {
            $filename = $uploadedFile->getClientFilename() ?? '';
        }

        // Check if the filename is allowed.
        if (!Utils::checkFilename($filename)) {
            throw new RuntimeException(
                sprintf($this->translate('PLUGIN_ADMIN.FILEUPLOAD_UNABLE_TO_UPLOAD'), $filename, $this->translate('PLUGIN_ADMIN.BAD_FILENAME')),
                400
            );
        }

        // Check size against the upload limit.
        $grav_limit = Utils::getUploadLimit();
        if ($grav_limit > 0 && $uploadedFile->getSize() > $grav_limit) {
            throw new RuntimeException($this->translate('PLUGIN_ADMIN.EXCEEDED_GRAV_FILESIZE_LIMIT'), 400);
        }

        $this->checkFileExtension($filename);

        return $filename;
    }

    /**
     * Upload file to the media collection.
     *
     * @param UploadedFileInterface $uploadedFile
     * @param string|null $filename
     * @return void
     * @throws RuntimeException
     */
    public function uploadMediaFile(UploadedFileInterface $uploadedFile, string $filename = null): void
    {
        // First check if the file is a valid upload (throws error if not).
        $filename = $this->checkUploadedFile($uploadedFile, $filename);

        $path = $this->getPath();
        if (!$path) {
            throw new RuntimeException($this->translate('PLUGIN_ADMIN.FAILED_TO_MOVE_UPLOADED_FILE'), 400);
        }

        try {
            $this->clearMediaCache();

            $filepath = sprintf('%s/%s', $path, $filename);

            // Create folder.
            $filesystem = Filesystem::getInstance(false);
            Folder::create($filesystem->dirname($filepath));

            // Upload file.
            if ($uploadedFile instanceof FormFlashFile) {
                if ($uploadedFile->getError() === \UPLOAD_ERR_OK) {
                    $uploadedFile->moveTo($filepath);
                } elseif ($filename && !file_exists($filepath) && $pos = strpos($filename, '/')) {
                    // Handle original image if it's the same as the uploaded image.
                    $origpath = sprintf('%s/%s', $path, substr($filename, $pos));
                    if (file_exists($origpath)) {
                        copy($origpath, $filepath);
                    }
                }

                // FormFlashFile may also contain metadata.
                $metadata = $uploadedFile->getMetaData();
                if ($metadata) {
                    $file = YamlFile::instance($filepath . '.meta.yaml');
                    $file->save(['upload' => $metadata]);
                }
            } else {
                $uploadedFile->moveTo($filepath);
            }
        } catch (\Exception $e) {
            throw new RuntimeException($this->translate('PLUGIN_ADMIN.FAILED_TO_MOVE_UPLOADED_FILE'), 400);
        }

        // Finally clear media cache.
        $this->clearCache();
    }

    /**
     * @param string $filename
     * @return void
     */
    public function deleteMediaFile(string $filename): void
    {
        // First check for allowed filename.
        $basename = basename($filename);
        if (!Utils::checkFilename($basename)) {
            throw new RuntimeException($this->translate('PLUGIN_ADMIN.FILE_COULD_NOT_BE_DELETED') . ": {$this->translate('PLUGIN_ADMIN.BAD_FILENAME')}: " . $filename, 400);
        }

        $path = $this->getPath();
        if (!$path) {
            // Nothing to do.
            return;
        }

        $filesystem = Filesystem::getInstance(false);
        $dirname = $filesystem->dirname($filename);
        $dirname = $dirname === '.' ? '' : '/' . $dirname;
        $targetPath = "{$path}/{$dirname}";
        $targetFile = "{$path}/{$filename}";

        $grav = $this->getGrav();

        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];
        if ($locator->isStream($targetFile)) {
            $targetPath = (string)$locator->findResource($targetPath, true, true);
            $targetFile = (string)$locator->findResource($targetFile, true, true);
            $locator->clearCache($targetPath);
            $locator->clearCache($targetFile);
        }

        $fileParts = (array)$filesystem->pathinfo($basename);

        if (!file_exists($targetPath)) {
            return;
        }

        if (file_exists($targetFile)) {
            $result = unlink($targetFile);
            if (!$result) {
                throw new RuntimeException($this->translate('PLUGIN_ADMIN.FILE_COULD_NOT_BE_DELETED') . ': ' . $filename, 500);
            }
        }

        $dir = scandir($targetPath, SCANDIR_SORT_NONE);
        if (false === $dir) {
            throw new RuntimeException('Internal error');
        }

        // Remove associated .meta.yaml files.
        foreach ($dir as $file) {
            $preg_name = preg_quote($fileParts['filename'], '`');
            $preg_ext = preg_quote($fileParts['extension'] ?? '.', '`');
            $preg_filename = preg_quote($basename, '`');

            if (preg_match("`({$preg_name}@\d+x\.{$preg_ext}(?:\.meta\.yaml)?$|{$preg_filename}\.meta\.yaml)$`", $file)) {
                $testPath = $targetPath . '/' . $file;
                if ($locator->isStream($testPath)) {
                    $testPath = (string)$locator->findResource($testPath, true, true);
                    $locator->clearCache($testPath);
                }

                if (is_file($testPath)) {
                    $result = unlink($testPath);
                    if (!$result) {
                        throw new RuntimeException($this->translate('PLUGIN_ADMIN.FILE_COULD_NOT_BE_DELETED') . ': ' . $filename, 500);
                    }
                }
            }
        }

        $this->clearCache();
    }

    /**
     * Check the file extension.
     *
     * @param string $filename
     * @return void
     */
    protected function checkFileExtension(string $filename)
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!$extension || !$this->getConfig()->get("media.types.{$extension}")) {
            // Not a supported type.
            throw new RuntimeException($this->translate('PLUGIN_ADMIN.UNSUPPORTED_FILE_TYPE') . ': ' . $extension, 400);
        }
    }

    protected function clearMediaCache(): void
    {
        $grav = $this->getGrav();
        $path = $this->getPath();

        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];
        if ($locator->isStream($path)) {
            $path = (string)$locator->findResource($path, true, true);
            $locator->clearCache($path);
        }
    }

    protected function translate($string): string
    {
        return $this->getLanguage()->translate($string);
    }

    abstract protected function getPath(): string;

    abstract protected function getGrav(): Grav;

    abstract protected function getConfig(): Config;

    abstract protected function getLanguage(): Language;

    abstract protected function clearCache(): void;
}
