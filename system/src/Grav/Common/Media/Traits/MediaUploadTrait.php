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
use Grav\Common\Grav;
use Grav\Common\Language\Language;
use Grav\Common\Page\Medium\Medium;
use Grav\Common\Page\Medium\MediumFactory;
use Grav\Common\Utils;
use Grav\Framework\Filesystem\Filesystem;
use Grav\Framework\Form\FormFlashFile;
use Grav\Framework\Mime\MimeTypes;
use Psr\Http\Message\UploadedFileInterface;
use RocketTheme\Toolbox\File\YamlFile;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RuntimeException;
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
                $size = Utils::prettySize(Utils::getUploadLimit());

                throw new RuntimeException($this->translate('GRAV.MEDIA.UPLOAD_ERR_SIZE', $size), 400);
            case UPLOAD_ERR_PARTIAL:
                throw new RuntimeException($this->translate('GRAV.MEDIA.UPLOAD_ERR_PARTIAL'), 400);
            case UPLOAD_ERR_NO_FILE:
                if (!$uploadedFile instanceof FormFlashFile) {
                    throw new RuntimeException($this->translate('GRAV.MEDIA.UPLOAD_ERR_NO_FILE'), 400);
                }
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                throw new RuntimeException($this->translate('GRAV.MEDIA.UPLOAD_ERR_NO_TMP_DIR'), 400);
            case UPLOAD_ERR_CANT_WRITE:
                throw new RuntimeException($this->translate('GRAV.MEDIA.UPLOAD_ERR_CANT_WRITE'), 400);
            case UPLOAD_ERR_EXTENSION:
                throw new RuntimeException($this->translate('GRAV.MEDIA.UPLOAD_ERR_EXTENSION'), 400);
            default:
                throw new RuntimeException($this->translate('GRAV.MEDIA.UPLOAD_ERR_UNKNOWN'), 400);
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
     * @param string|null $filename
     * @param array|null $settings
     * @return string
     */
    public function checkFileMetadata(array $metadata, string $filename = null, array $settings = null): string
    {
        if (null === $filename) {
            // If no filename is given, use the filename from the uploaded file.
            $filename = $metadata['filename'] ?? '';
        }

        $extension = Utils::pathinfo($filename, PATHINFO_EXTENSION);

        // Generate random filename if asked for.
        $settings = $this->getUploadSettings($settings);
        if ($settings['random_name']) {
            $filename = mb_strtolower(Utils::generateRandomString(15) . '.' . $extension);
        }

        // Destination is always needed (but it can be set in defaults).
        $path = $this->getDestination($settings);

        // Handle conflicting filename if needed.
        if ($settings['avoid_overwriting'] && $this->fileExists($filename, $path)) {
            $filename = date('YmdHis') . '-' . $filename;
        }

        // Check if the filename is allowed.
        if (!Utils::checkFilename($filename)) {
            throw new RuntimeException($this->translate('GRAV.MEDIA.BAD_FILENAME', $filename), 400);
        }

        // Check if the file extension is allowed.
        $extension = mb_strtolower($extension);
        if (!$extension || !$this->getConfig()->get("media.types.{$extension}")) {
            throw new RuntimeException($this->translate('GRAV.MEDIA.BAD_FILE_EXTENSION', $filename), 400);
        }

        // Check for maximum file size.
        $sizeLimit = $settings['filesize'];
        if ($sizeLimit > 0 && $metadata['size'] > $sizeLimit) {
            $size = Utils::prettySize($sizeLimit);

            throw new RuntimeException($this->translate('GRAV.MEDIA.EXCEEDED_GRAV_FILESIZE_LIMIT', $filename, $size), 400);
        }

        $grav = $this->getGrav();

        // Handle Accepted file types. Accept can only be mime types (image/png | image/*) or file extensions (.pdf | .jpg)
        // Do not trust mime type sent by the browser.

        /** @var MimeTypes $mimeChecker */
        $mimeChecker = $grav['mime'];
        $mime = $metadata['mime'] ?? $mimeChecker->getMimeType($extension);
        $validExtensions = $mimeChecker->getExtensions($mime);
        if (!in_array($extension, $validExtensions, true)) {
            throw new RuntimeException($this->translate('GRAV.MEDIA.MIMETYPE_MISMATCH', $mime, $filename), 400);
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
                    $errors[] = $this->translate('GRAV.MEDIA.BAD_MIMETYPE', $mime, $filename);
                } else {
                    $accepted = true;
                    break;
                }
            } else {
                $match = preg_match('#' . $find . '$#', $filename);
                if (!$match) {
                    $errors[] = $this->translate('GRAV.MEDIA.BAD_FILE_EXTENSION', $filename);
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
        try {
            // Add the defaults to the settings.
            if (!$filename) {
                throw new RuntimeException($this->translate('GRAV.MEDIA.BAD_FILENAME', '(N/A)'), 400);
            }

            $this->clearCache();

            $filesystem = Filesystem::getInstance(false);

            // Calculate path without the retina scaling factor.
            $basename = $filesystem->basename($filename);
            $pathname = $filesystem->pathname($filename);

            // Get name for the uploaded file.
            [$base, $ext,,] = $this->getFileParts($basename);
            $name = "{$pathname}{$base}.{$ext}";

            $path = $this->getDestination($settings);

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

            // Update media index.
            if (method_exists($this, 'updateIndex')) {
                $this->updateIndex();
            }

        } catch (Exception $e) {
            throw new RuntimeException($this->translate('GRAV.MEDIA.UPLOAD_ERR_FAILED_TO_MOVE') . $e->getMessage(), 400);
        } finally {
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
        try {
            // First check for allowed filename.
            if (!Utils::checkFilename($filename)) {
                throw new RuntimeException($this->translate('GRAV.MEDIA.BAD_FILENAME', $filename), 400);
            }

            // Get base name of the file.
            [$base, $ext,,] = $this->getFileParts($filename);
            $name = "{$base}.{$ext}";

            // Remove file and all the associated metadata.
            $this->clearCache();

            $path = $this->getDestination($settings);

            $this->doRemove($name, $path);

            // Update media index.
            if (method_exists($this, 'updateIndex')) {
                $this->updateIndex([$filename => null]);
            }
            $this->hide($filename);
            $this->clearCache();
        } catch (Exception $e) {
            throw new RuntimeException($this->translate('GRAV.MEDIA.FILE_COULD_NOT_BE_DELETED', $e->getMessage()), $e->getCode(), $e);
        }
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
        try {
            $filesystem = Filesystem::getInstance(false);

            $this->clearCache();

            // Get base name of the file.
            $pathname = $filesystem->pathname($from);

            // Remove @2x, @3x and .meta.yaml
            [$base, $ext,,] = $this->getFileParts($filesystem->basename($from));
            $from = "{$pathname}{$base}.{$ext}";

            [$base, $ext,,] = $this->getFileParts($filesystem->basename($to));
            $to = "{$pathname}{$base}.{$ext}";

            $path = $this->getDestination($settings);

            $this->doRename($from, $to, $path);

            // Update media index.
            if (method_exists($this, 'updateIndex')) {
                $this->updateIndex();
            }

            $this->clearCache();
        } catch (Exception $e) {
            throw new RuntimeException($this->translate('GRAV.MEDIA.FILE_COULD_NOT_BE_RENAMED', $e->getMessage()), $e->getCode(), $e);
        }
    }

    /**
     * Get upload settings.
     *
     * @param array|null $settings Form field specific settings (override).
     * @return array
     */
    public function getUploadSettings(?array $settings = null): array
    {
        $settings = null !== $settings ? $settings + $this->_upload_defaults : $this->_upload_defaults;
        if ($settings['filesize']) {
            $settings['filesize'] *= 1048576;
        } else {
            $settings['filesize'] = Utils::getUploadLimit();
        }

        return $settings;
    }

    /**
     * @param array|null $settings
     * @return string
     */
    protected function getDestination(?array $settings = null): string
    {
        $settings = $this->getUploadSettings($settings);
        $path = $settings['destination'] ?? $this->getPath();
        if (!$path) {
            throw new RuntimeException($this->translate('GRAV.MEDIA.BAD_DESTINATION'), 400);
        }

        return $path;
    }

    /**
     * Internal logic to move uploaded file.
     *
     * @param UploadedFileInterface $uploadedFile
     * @param string $filename
     * @param string $path
     */
    abstract protected function doMoveUploadedFile(UploadedFileInterface $uploadedFile, string $filename, string $path): void;

    /**
     * Internal logic to copy file.
     *
     * @param string $src
     * @param string $dst
     * @param string $path
     */
    abstract protected function doCopy(string $src, string $dst, string $path): void;

    /**
     * Internal logic to rename file.
     *
     * @param string $from
     * @param string $to
     * @param string $path
     */
    abstract protected function doRename(string $from, string $to, string $path): void;

    /**
     * Internal logic to remove file.
     *
     * @param string $filename
     * @param string $path
     */
    abstract protected function doRemove(string $filename, string $path): void;

    /**
     * @param string $filename
     * @param string $path
     */
    abstract protected function doSanitizeSvg(string $filename, string $path): void;

    /**
     * @param array $metadata
     * @param string $filename
     * @param string $path
     */
    protected function doSaveMetadata(array $metadata, string $filename, string $path): void
    {
        $filepath = sprintf('%s/%s', $path, $filename);

        // Do not use streams internally.
        $locator = $this->getLocator();
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

        // Do not use streams internally.
        $locator = $this->getLocator();
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
     * @param mixed ...$args
     * @return string
     */
    protected function translate(string $string, ...$args): string
    {
        array_unshift($args, $string);

        return $this->getLanguage()->translate($args);
    }

    abstract protected function getPath(): ?string;

    abstract protected function getGrav(): Grav;

    abstract protected function getLocator(): UniformResourceLocator;

    abstract protected function getConfig(): Config;

    abstract protected function getLanguage(): Language;

    abstract protected function clearCache(): void;
}
