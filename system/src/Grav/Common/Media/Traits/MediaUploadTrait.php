<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media\Traits;

use Exception;
use Grav\Common\Config\Config;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Grav;
use Grav\Common\Language\Language;
use Grav\Common\Page\Medium\Medium;
use Grav\Common\Page\Medium\MediumFactory;
use Grav\Common\Security;
use Grav\Common\Utils;
use Grav\Framework\Filesystem\Filesystem;
use Grav\Framework\Form\FormFlashFile;
use Grav\Framework\Mime\MimeTypes;
use Psr\Http\Message\UploadedFileInterface;
use RocketTheme\Toolbox\File\YamlFile;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RuntimeException;
use function dirname;
use function in_array;

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
     * Create Medium from an uploaded file.
     *
     * @param  UploadedFileInterface $uploadedFile
     * @param  array  $params
     * @return Medium|null
     */
    public function createFromUploadedFile(UploadedFileInterface $uploadedFile, array $params = [])
    {
        return MediumFactory::fromUploadedFile($uploadedFile, $params);
    }

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

        $metadata = [
            'filename' => $uploadedFile->getClientFilename(),
            'mime' => $uploadedFile->getClientMediaType(),
            'size' => $uploadedFile->getSize(),
        ];

        if ($uploadedFile instanceof FormFlashFile) {
            $uploadedFile->checkXss();
        }

        return $this->checkFileMetadata($metadata, $filename, $settings);
    }

    /**
     * Checks that file metadata meets the requirements. Returns new filename.
     *
     * @param array $metadata
     * @param array|null $settings
     * @return string
     * @throws RuntimeException
     */
    public function checkFileMetadata(array $metadata, string $filename = null, array $settings = null): string
    {
        // Add the defaults to the settings.
        $settings = $this->getUploadSettings($settings);

        // Destination is always needed (but it can be set in defaults).
        $self = $settings['self'] ?? false;
        if (!isset($settings['destination']) && $self === false) {
            throw new RuntimeException($this->translate('PLUGIN_ADMIN.DESTINATION_NOT_SPECIFIED'), 400);
        }

        if (null === $filename) {
            // If no filename is given, use the filename from the uploaded file (path is not allowed).
            $folder = '';
            $filename = $metadata['filename'] ?? '';
        } else {
            // If caller sets the filename, we will accept any custom path.
            $folder = dirname($filename);
            if ($folder === '.') {
                $folder = '';
            }
            $filename = Utils::basename($filename);
        }
        $extension = Utils::pathinfo($filename, PATHINFO_EXTENSION);

        // Decide which filename to use.
        if ($settings['random_name']) {
            // Generate random filename if asked for.
            $filename = mb_strtolower(Utils::generateRandomString(15) . '.' . $extension);
        }

        // Handle conflicting filename if needed.
        if ($settings['avoid_overwriting']) {
            $destination = $settings['destination'];
            if ($destination && $this->fileExists($filename, $destination)) {
                $filename = date('YmdHis') . '-' . $filename;
            }
        }
        $filepath = $folder . $filename;

        // Check if the filename is allowed.
        if (!Utils::checkFilename($filename)) {
            throw new RuntimeException(
                sprintf($this->translate('PLUGIN_ADMIN.FILEUPLOAD_UNABLE_TO_UPLOAD'), $filepath, $this->translate('PLUGIN_ADMIN.BAD_FILENAME'))
            );
        }

        // Check if the file extension is allowed.
        $extension = mb_strtolower($extension);
        if (!$extension || !$this->getConfig()->get("media.types.{$extension}")) {
            // Not a supported type.
            throw new RuntimeException($this->translate('PLUGIN_ADMIN.UNSUPPORTED_FILE_TYPE') . ': ' . $extension, 400);
        }

        // Calculate maximum file size (from MB).
        $filesize = $settings['filesize'];
        if ($filesize) {
            $max_filesize = $filesize * 1048576;
            if ($metadata['size'] > $max_filesize) {
                // TODO: use own language string
                throw new RuntimeException($this->translate('PLUGIN_ADMIN.EXCEEDED_GRAV_FILESIZE_LIMIT'), 400);
            }
        } elseif (null === $filesize) {
            // Check size against the Grav upload limit.
            $grav_limit = Utils::getUploadLimit();
            if ($grav_limit > 0 && $metadata['size'] > $grav_limit) {
                throw new RuntimeException($this->translate('PLUGIN_ADMIN.EXCEEDED_GRAV_FILESIZE_LIMIT'), 400);
            }
        }

        $grav = Grav::instance();
        /** @var MimeTypes $mimeChecker */
        $mimeChecker = $grav['mime'];

        // Handle Accepted file types. Accept can only be mime types (image/png | image/*) or file extensions (.pdf | .jpg)
        // Do not trust mime type sent by the browser.
        $mime = $metadata['mime'] ?? $mimeChecker->getMimeType($extension);
        $validExtensions = $mimeChecker->getExtensions($mime);
        if (!in_array($extension, $validExtensions, true)) {
            throw new RuntimeException('The mime type does not match to file extension', 400);
        }

        $accepted = false;
        $errors = [];
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
                    $errors[] = 'The MIME type "' . $mime . '" for the file "' . $filepath . '" is not an accepted.';
                } else {
                    $accepted = true;
                    break;
                }
            } else {
                $match = preg_match('#' . $find . '$#', $filename);
                if (!$match) {
                    // TODO: translate
                    $errors[] = 'The File Extension for the file "' . $filepath . '" is not an accepted.';
                } else {
                    $accepted = true;
                    break;
                }
            }
        }
        if (!$accepted) {
            throw new RuntimeException(implode('<br />', $errors), 400);
        }

        return $filepath;
    }

    /**
     * Copy uploaded file to the media collection.
     *
     * WARNING: Always check uploaded file before copying it!
     *
     * @example
     *   $settings = ['destination' => 'user://pages/media']; // Settings from the form field.
     *   $filename = $media->checkUploadedFile($uploadedFile, $filename, $settings);
     *   $media->copyUploadedFile($uploadedFile, $filename, $settings);
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
        $settings = $this->getUploadSettings($settings);

        $path = $settings['destination'] ?? $this->getPath();
        if (!$path || !$filename) {
            throw new RuntimeException($this->translate('PLUGIN_ADMIN.FAILED_TO_MOVE_UPLOADED_FILE'), 400);
        }

        /** @var UniformResourceLocator $locator */
        $locator = $this->getGrav()['locator'];

        try {
            // Clear locator cache to make sure we have up to date information from the filesystem.
            $locator->clearCache();
            $this->clearCache();

            $filesystem = Filesystem::getInstance(false);

            // Calculate path without the retina scaling factor.
            $basename = $filesystem->basename($filename);
            $pathname = $filesystem->pathname($filename);

            // Get name for the uploaded file.
            [$base, $ext,,] = $this->getFileParts($basename);
            $name = "{$pathname}{$base}.{$ext}";

            // Upload file.
            if ($uploadedFile instanceof FormFlashFile) {
                // FormFlashFile needs some additional logic.
                if ($uploadedFile->getError() === \UPLOAD_ERR_OK) {
                    // Move uploaded file.
                    $this->doMoveUploadedFile($uploadedFile, $filename, $path);
                } elseif (strpos($filename, 'original/') === 0 && !$this->fileExists($filename, $path) && $this->fileExists($basename, $path)) {
                    // Original image support: override original image if it's the same as the uploaded image.
                    $this->doCopy($basename, $filename, $path);
                }

                // FormFlashFile may also contain metadata.
                $metadata = $uploadedFile->getMetaData();
                if ($metadata) {
                    // TODO: This overrides metadata if used with multiple retina image sizes.
                    $this->doSaveMetadata(['upload' => $metadata], $name, $path);
                }
            } else {
                // Not a FormFlashFile.
                $this->doMoveUploadedFile($uploadedFile, $filename, $path);
            }

            // Post-processing: Special content sanitization for SVG.
            $mime = Utils::getMimeByFilename($filename);
            if (Utils::contains($mime, 'svg', false)) {
                $this->doSanitizeSvg($filename, $path);
            }

            // Add the new file into the media.
            // TODO: This overrides existing media sizes if used with multiple retina image sizes.
            $this->doAddUploadedMedium($name, $filename, $path);

        } catch (Exception $e) {
            throw new RuntimeException($this->translate('PLUGIN_ADMIN.FAILED_TO_MOVE_UPLOADED_FILE') . $e->getMessage(), 400);
        } finally {
            // Finally clear media cache.
            $locator->clearCache();
            $this->clearCache();
        }
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
        $settings = $this->getUploadSettings($settings);
        $filesystem = Filesystem::getInstance(false);

        // First check for allowed filename.
        $basename = $filesystem->basename($filename);
        if (!Utils::checkFilename($basename)) {
            throw new RuntimeException($this->translate('PLUGIN_ADMIN.FILE_COULD_NOT_BE_DELETED') . ": {$this->translate('PLUGIN_ADMIN.BAD_FILENAME')}: " . $filename, 400);
        }

        $path = $settings['destination'] ?? $this->getPath();
        if (!$path) {
            return;
        }

        /** @var UniformResourceLocator $locator */
        $locator = $this->getGrav()['locator'];
        $locator->clearCache();

        $pathname = $filesystem->pathname($filename);

        // Get base name of the file.
        [$base, $ext,,] = $this->getFileParts($basename);
        $name = "{$pathname}{$base}.{$ext}";

        // Remove file and all all the associated metadata.
        $this->doRemove($name, $path);

        // Finally clear media cache.
        $locator->clearCache();
        $this->clearCache();
    }

    /**
     * Rename file inside the media collection.
     *
     * @param string $from
     * @param string $to
     * @param array|null $settings
     */
    public function renameFile(string $from, string $to, array $settings = null): void
    {
        // Add the defaults to the settings.
        $settings = $this->getUploadSettings($settings);
        $filesystem = Filesystem::getInstance(false);

        $path = $settings['destination'] ?? $this->getPath();
        if (!$path) {
            // TODO: translate error message
            throw new RuntimeException('Failed to rename file: Bad destination', 400);
        }

        /** @var UniformResourceLocator $locator */
        $locator = $this->getGrav()['locator'];
        $locator->clearCache();

        // Get base name of the file.
        $pathname = $filesystem->pathname($from);

        // Remove @2x, @3x and .meta.yaml
        [$base, $ext,,] = $this->getFileParts($filesystem->basename($from));
        $from = "{$pathname}{$base}.{$ext}";

        [$base, $ext,,] = $this->getFileParts($filesystem->basename($to));
        $to = "{$pathname}{$base}.{$ext}";

        $this->doRename($from, $to, $path);

        // Finally clear media cache.
        $locator->clearCache();
        $this->clearCache();
    }

    /**
     * Internal logic to move uploaded file.
     *
     * @param UploadedFileInterface $uploadedFile
     * @param string $filename
     * @param string $path
     */
    protected function doMoveUploadedFile(UploadedFileInterface $uploadedFile, string $filename, string $path): void
    {
        $filepath = sprintf('%s/%s', $path, $filename);

        /** @var UniformResourceLocator $locator */
        $locator = $this->getGrav()['locator'];

        // Do not use streams internally.
        if ($locator->isStream($filepath)) {
            $filepath = (string)$locator->findResource($filepath, true, true);
        }

        Folder::create(dirname($filepath));

        $uploadedFile->moveTo($filepath);
    }

    /**
     * Get upload settings.
     *
     * @param array|null $settings Form field specific settings (override).
     * @return array
     */
    public function getUploadSettings(?array $settings = null): array
    {
        return null !== $settings ? $settings + $this->_upload_defaults : $this->_upload_defaults;
    }

    /**
     * Internal logic to copy file.
     *
     * @param string $src
     * @param string $dst
     * @param string $path
     */
    protected function doCopy(string $src, string $dst, string $path): void
    {
        $src = sprintf('%s/%s', $path, $src);
        $dst = sprintf('%s/%s', $path, $dst);

        /** @var UniformResourceLocator $locator */
        $locator = $this->getGrav()['locator'];

        // Do not use streams internally.
        if ($locator->isStream($dst)) {
            $dst = (string)$locator->findResource($dst, true, true);
        }

        Folder::create(dirname($dst));

        copy($src, $dst);
    }

    /**
     * Internal logic to rename file.
     *
     * @param string $from
     * @param string $to
     * @param string $path
     */
    protected function doRename(string $from, string $to, string $path): void
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->getGrav()['locator'];

        $fromPath = $path . '/' . $from;
        if ($locator->isStream($fromPath)) {
            $fromPath = $locator->findResource($fromPath, true, true);
        }

        if (!is_file($fromPath)) {
            return;
        }

        $mediaPath = dirname($fromPath);
        $toPath = $mediaPath . '/' . $to;
        if ($locator->isStream($toPath)) {
            $toPath = $locator->findResource($toPath, true, true);
        }

        if (is_file($toPath)) {
            // TODO: translate error message
            throw new RuntimeException(sprintf('File could not be renamed: %s already exists (%s)', $to, $mediaPath), 500);
        }

        $result = rename($fromPath, $toPath);
        if (!$result) {
            // TODO: translate error message
            throw new RuntimeException(sprintf('File could not be renamed: %s -> %s (%s)', $from, $to, $mediaPath), 500);
        }

        // TODO: Add missing logic to handle retina files.
        if (is_file($fromPath . '.meta.yaml')) {
            $result = rename($fromPath . '.meta.yaml', $toPath . '.meta.yaml');
            if (!$result) {
                // TODO: translate error message
                throw new RuntimeException(sprintf('Meta could not be renamed: %s -> %s (%s)', $from, $to, $mediaPath), 500);
            }
        }
    }

    /**
     * Internal logic to remove file.
     *
     * @param string $filename
     * @param string $path
     */
    protected function doRemove(string $filename, string $path): void
    {
        $filesystem = Filesystem::getInstance(false);

        /** @var UniformResourceLocator $locator */
        $locator = $this->getGrav()['locator'];

        // If path doesn't exist, there's nothing to do.
        $pathname = $filesystem->pathname($filename);
        if (!$this->fileExists($pathname, $path)) {
            return;
        }

        $folder = $locator->isStream($path) ? (string)$locator->findResource($path, true, true) : $path;

        // Remove requested media file.
        if ($this->fileExists($filename, $path)) {
            $result = unlink("{$folder}/{$filename}");
            if (!$result) {
                throw new RuntimeException($this->translate('PLUGIN_ADMIN.FILE_COULD_NOT_BE_DELETED') . ': ' . $filename, 500);
            }
        }

        // Remove associated metadata.
        $this->doRemoveMetadata($filename, $path);

        // Remove associated 2x, 3x and their .meta.yaml files.
        $targetPath = rtrim(sprintf('%s/%s', $folder, $pathname), '/');
        $dir = scandir($targetPath, SCANDIR_SORT_NONE);
        if (false === $dir) {
            throw new RuntimeException($this->translate('PLUGIN_ADMIN.FILE_COULD_NOT_BE_DELETED') . ': ' . $filename, 500);
        }

        /** @var UniformResourceLocator $locator */
        $locator = $this->getGrav()['locator'];

        $basename = $filesystem->basename($filename);
        $fileParts = (array)$filesystem->pathinfo($filename);

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

        $this->hide($filename);
    }

    /**
     * @param array $metadata
     * @param string $filename
     * @param string $path
     */
    protected function doSaveMetadata(array $metadata, string $filename, string $path): void
    {
        $filepath = sprintf('%s/%s', $path, $filename);

        /** @var UniformResourceLocator $locator */
        $locator = $this->getGrav()['locator'];

        // Do not use streams internally.
        if ($locator->isStream($filepath)) {
            $filepath = (string)$locator->findResource($filepath, true, true);
        }

        $file = YamlFile::instance($filepath . '.meta.yaml');
        $file->save($metadata);
    }

    /**
     * @param string $filename
     * @param string $path
     */
    protected function doRemoveMetadata(string $filename, string $path): void
    {
        $filepath = sprintf('%s/%s', $path, $filename);

        /** @var UniformResourceLocator $locator */
        $locator = $this->getGrav()['locator'];

        // Do not use streams internally.
        if ($locator->isStream($filepath)) {
            $filepath = (string)$locator->findResource($filepath, true);
            if (!$filepath) {
                return;
            }
        }

        $file = YamlFile::instance($filepath . '.meta.yaml');
        if ($file->exists()) {
            $file->delete();
        }
    }

    /**
     * @param string $filename
     * @param string $path
     */
    protected function doSanitizeSvg(string $filename, string $path): void
    {
        $filepath = sprintf('%s/%s', $path, $filename);

        /** @var UniformResourceLocator $locator */
        $locator = $this->getGrav()['locator'];

        // Do not use streams internally.
        if ($locator->isStream($filepath)) {
            $filepath = (string)$locator->findResource($filepath, true, true);
        }

        Security::sanitizeSVG($filepath);
    }

    /**
     * @param string $name
     * @param string $filename
     * @param string $path
     */
    protected function doAddUploadedMedium(string $name, string $filename, string $path): void
    {
        $filepath = sprintf('%s/%s', $path, $filename);
        $medium = $this->createFromFile($filepath);
        $realpath = $path . '/' . $name;
        $this->add($realpath, $medium);
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
