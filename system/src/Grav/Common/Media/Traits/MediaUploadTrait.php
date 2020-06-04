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
use Grav\Common\Security;
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
    /** @var array */
    private $_upload_defaults = [
        'self'              => true,        // Whether path is in the media collection path itself.
        'avoid_overwriting' => false,       // Do not override existing files (adds datetime postfix if conflict).
        'random_name'       => false,       // True if name needs to be randomized.
        'accept'            => ['image/*'], // Accepted mime types or file extensions.
        'limit'             => 10,          // Maximum number of files.
        'filesize'          => null,        // Maximum filesize in MB.
        'destination'       => null         // Destination path, if empty, exception is thrown.
    ];

    /**
     * Checks that uploaded file meets the requirements. Returns new filename.
     *
     * @example
     *   $filename = null;  // Override filename if needed (ignored if randomizing filenames).
     *   $settings = ['destination' => 'user://pages/media']; // Settings from the form field.
     *   $filename = $media->checkUploadedFile($uploadedFile, $filename, $settings);
     *   $media->copyUploadedFile($uploadedFile, $filename);
     *
     * @param UploadedFileInterface $uploadedFile
     * @param string|null $filename
     * @param array|null $settings
     * @return string
     * @throws RuntimeException
     */
    public function checkUploadedFile(UploadedFileInterface $uploadedFile, string $filename = null, array $settings = null): string
    {
        // Add the defaults to the settings.
        if (!$settings) {
            $settings = $this->_upload_defaults;
        } else {
            $settings += $this->_upload_defaults;
        }

        // Destination is always needed (but it can be set in defaults).
        if (!isset($settings['destination'])) {
            throw new RuntimeException($this->translate('PLUGIN_ADMIN.DESTINATION_NOT_SPECIFIED'), 400);
        }

        // Check if there is an upload error.
        switch ($uploadedFile->getError()) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new RuntimeException($this->translate('PLUGIN_ADMIN.EXCEEDED_FILESIZE_LIMIT'), 400);
            case UPLOAD_ERR_PARTIAL:
            case UPLOAD_ERR_NO_FILE:
                if (!$uploadedFile instanceof FormFlashFile) {
                    throw new RuntimeException($this->translate('PLUGIN_ADMIN.NO_FILES_SENT'), 400);
                }
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                throw new RuntimeException($this->translate('PLUGIN_ADMIN.UPLOAD_ERR_NO_TMP_DIR'), 400);
            case UPLOAD_ERR_CANT_WRITE:
            case UPLOAD_ERR_EXTENSION:
            default:
                throw new RuntimeException($this->translate('PLUGIN_ADMIN.UNKNOWN_ERRORS'), 400);
        }

        // Decide which filename to use.
        if ($settings['random_name']) {
            // Generate random filename if asked for.
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $filename = Utils::generateRandomString(15) . '.' . $extension;
        } elseif (null === $filename) {
            // If no filename is given, use the filename from the uploaded file.
            $filename = $uploadedFile->getClientFilename() ?? '';
        }

        // Handle conflicting filename if needed.
        if ($settings['avoid_overwriting']) {
            $destination = $settings['destination'];
            if (file_exists("{$destination}/{$filename}")) {
                $filename = date('YmdHis') . '-' . $filename;
            }
        }

        // Check if the filename is allowed.
        if (!Utils::checkFilename($filename)) {
            throw new RuntimeException(
                sprintf($this->translate('PLUGIN_ADMIN.FILEUPLOAD_UNABLE_TO_UPLOAD'), $filename, $this->translate('PLUGIN_ADMIN.BAD_FILENAME')),
                400
            );
        }

        // Check if the file extension is allowed.
        $extension = mb_strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!$extension || !$this->getConfig()->get("media.types.{$extension}")) {
            // Not a supported type.
            throw new RuntimeException($this->translate('PLUGIN_ADMIN.UNSUPPORTED_FILE_TYPE') . ': ' . $extension, 400);
        }

        // Calculate maximum file size (from MB).
        if ($settings['filesize']) {
            $max_filesize = $settings['filesize'] * 1048576;
            if ($uploadedFile->getSize() > $max_filesize) {
                // TODO: use own language string
                throw new RuntimeException($this->translate('PLUGIN_ADMIN.EXCEEDED_GRAV_FILESIZE_LIMIT'), 400);
            }
        }

        // Check size against the Grav upload limit.
        $grav_limit = Utils::getUploadLimit();
        if ($grav_limit > 0 && $uploadedFile->getSize() > $grav_limit) {
            throw new RuntimeException($this->translate('PLUGIN_ADMIN.EXCEEDED_GRAV_FILESIZE_LIMIT'), 400);
        }

        // Handle Accepted file types. Accept can only be mime types (image/png | image/*) or file extensions (.pdf | .jpg)
        $accepted = false;
        $errors = [];
        // Do not trust mime type sent by the browser.
        $mime = Utils::getMimeByFilename($filename);
        foreach ((array)$settings['accept'] as $type) {
            // Force acceptance of any file when star notation
            if ($type === '*') {
                $accepted = true;
                break;
            }

            $isMime = strstr($type, '/');
            $find = str_replace(['.', '*', '+'], ['\.', '.*', '\+'], $type);

            if ($isMime) {
                $match = preg_match('#' . $find . '$#', $mime);
                if (!$match) {
                    // TODO: translate
                    $errors[] = 'The MIME type "' . $mime . '" for the file "' . $filename . '" is not an accepted.';
                } else {
                    $accepted = true;
                    break;
                }
            } else {
                $match = preg_match('#' . $find . '$#', $filename);
                if (!$match) {
                    // TODO: translate
                    $errors[] = 'The File Extension for the file "' . $filename . '" is not an accepted.';
                } else {
                    $accepted = true;
                    break;
                }
            }
        }
        if (!$accepted) {
            throw new RuntimeException(implode('<br />', $errors), 400);
        }

        return $filename;
    }

    /**
     * Copy uploaded file to the media collection.
     *
     * WARNING: Always check uploaded file before copying it!
     *
     * @example
     *   $filename = null;  // Override filename if needed (ignored if randomizing filenames).
     *   $settings = ['destination' => 'user://pages/media']; // Settings from the form field.
     *   $filename = $media->checkUploadedFile($uploadedFile, $filename, $settings);
     *   $media->copyUploadedFile($uploadedFile, $filename);
     *
     * @param UploadedFileInterface $uploadedFile
     * @param string $filename
     * @param array|null $settings
     * @return void
     * @throws RuntimeException
     */
    public function copyUploadedFile(UploadedFileInterface $uploadedFile, string $filename, array $settings = null): void
    {
        // Add the defaults to the settings.
        if (!$settings) {
            $settings = $this->_upload_defaults;
        } else {
            $settings += $this->_upload_defaults;
        }

        $path = $settings['destination'] ?? $this->getPath();
        if (!$path) {
            throw new RuntimeException($this->translate('PLUGIN_ADMIN.FAILED_TO_MOVE_UPLOADED_FILE'), 400);
        }

        /** @var UniformResourceLocator $locator */
        $locator = $this->getGrav()['locator'];

        try {
            // Do not use streams internally.
            if ($locator->isStream($path)) {
                $path = (string)$locator->findResource($path, true, true);
                $locator->clearCache();
            }

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

            // Special content sanitization for SVG.
            $mime = Utils::getMimeByFilename($filename);
            if (Utils::contains($mime, 'svg', false)) {
                Security::sanitizeSVG($filepath);
            }
        } catch (\Exception $e) {
            throw new RuntimeException($this->translate('PLUGIN_ADMIN.FAILED_TO_MOVE_UPLOADED_FILE'), 400);
        }

        // Finally clear media cache.
        $locator->clearCache();
        $this->clearCache();
    }

    /**
     * Delete real file from the media collection.
     *
     * @param string $filename
     * @param array|null $settings
     * @return void
     * @throws RuntimeException
     */
    public function deleteFile(string $filename, array $settings = null): void
    {
        // Add the defaults to the settings.
        if (!$settings) {
            $settings = $this->_upload_defaults;
        } else {
            $settings += $this->_upload_defaults;
        }

        // First check for allowed filename.
        $basename = basename($filename);
        if (!Utils::checkFilename($basename)) {
            throw new RuntimeException($this->translate('PLUGIN_ADMIN.FILE_COULD_NOT_BE_DELETED') . ": {$this->translate('PLUGIN_ADMIN.BAD_FILENAME')}: " . $filename, 400);
        }

        $path = $settings['destination'] ?? $this->getPath();
        if (!$path) {
            // Nothing to do.
            return;
        }

        $filesystem = Filesystem::getInstance(false);
        $dirname = $filesystem->dirname($filename);
        $dirname = $dirname === '.' ? '' : '/' . $dirname;
        $targetPath = sprintf('%s/%s', $path, $dirname);
        $targetFile = sprintf('%s/%s', $path, $filename);

        $grav = $this->getGrav();

        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];

        if ($locator->isStream($targetFile)) {
            $targetPath = (string)$locator->findResource($targetPath, true, true);
            $targetFile = (string)$locator->findResource($targetFile, true, true);
            $locator->clearCache();
        }

        $fileParts = (array)$filesystem->pathinfo($basename);

        // If path doesn't exist, there's nothing to do.
        if (!file_exists($targetPath)) {
            return;
        }

        // Remove media file.
        if (file_exists($targetFile)) {
            $result = unlink($targetFile);
            if (!$result) {
                throw new RuntimeException($this->translate('PLUGIN_ADMIN.FILE_COULD_NOT_BE_DELETED') . ': ' . $filename, 500);
            }
        }

        // Remove associated .meta.yaml files.
        $dir = scandir($targetPath, SCANDIR_SORT_NONE);
        if (false === $dir) {
            throw new RuntimeException('Internal error (M102)');
        }
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

        // Finally clear media cache.
        $this->clearCache();
    }

    /**
     * @param string $string
     * @return string
     */
    protected function translate(string $string): string
    {
        return $this->getLanguage()->translate($string);
    }

    abstract protected function getPath(): ?string;

    abstract protected function getGrav(): Grav;

    abstract protected function getConfig(): Config;

    abstract protected function getLanguage(): Language;

    abstract protected function clearCache(): void;
}
