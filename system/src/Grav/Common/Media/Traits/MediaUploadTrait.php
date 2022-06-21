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
use Grav\Framework\Form\FormFlashFile;
use Grav\Framework\Mime\MimeTypes;
use Psr\Http\Message\UploadedFileInterface;
use RocketTheme\Toolbox\File\YamlFile;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RuntimeException;
use function in_array;

/**
 * Implements media upload and delete functionality for media collection.
 */
trait MediaUploadTrait
{
    private array $_upload_defaults = [
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
     * @param string $filename
     * @return void
     * @throws RuntimeException
     */
    public function checkFilename(string $filename): void
    {
        if (!Utils::checkFilename($filename)) {
            throw new RuntimeException($this->translate('GRAV.MEDIA.BAD_FILENAME', $filename), 400);
        }
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

        // Handle conflicting filename if needed.
        if ($settings['avoid_overwriting'] && $this->fileExists($filename)) {
            $filename = date('YmdHis') . '-' . $filename;
        }

        // Check if the filename is allowed.
        $this->checkFilename($filename);

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
     *   $filename = $media->checkUploadedFile($uploadedFile, $filename);
     *   $media->copyUploadedFile($uploadedFile, $filename);
     *
     * @param UploadedFileInterface $uploadedFile
     * @param string $filename
     * @return void
     * @throws RuntimeException
     */
    public function copyUploadedFile(UploadedFileInterface $uploadedFile, string $filename): void
    {
        try {
            // Check if the filename is allowed.
            $this->checkFilename($filename);

            // Calculate path without the retina scaling factor.
            $name = $this->getBasename($filename);

            $this->clearCache();

            // Upload file.
            if ($uploadedFile instanceof FormFlashFile) {
                // FormFlashFile needs some additional logic.
                if ($uploadedFile->getError() === \UPLOAD_ERR_OK) {
                    // Move uploaded file.
                    $this->doMoveUploadedFile($uploadedFile, $filename);
                }
                // TODO: Add retina image support back?
                /*
                elseif (strpos($filename, 'original/') === 0 && !$this->fileExists($filename, $path) && $this->fileExists($basename, $path)) {
                    // Original image support: override original image if it's the same as the uploaded image.
                    $this->doCopy($basename, $filename, $path);
                }
                */

                // FormFlashFile may also contain metadata.
                $metadata = $uploadedFile->getMetaData();
                if ($metadata) {
                    // TODO: This overrides metadata if used with multiple retina image sizes.
                    $this->doSaveMetadata(['upload' => $metadata], $name);
                }
            } else {
                // Not a FormFlashFile.
                $this->doMoveUploadedFile($uploadedFile, $filename);
            }

            // Post-processing: Special content sanitization for SVG.
            $mime = Utils::getMimeByFilename($filename);
            if (Utils::contains($mime, 'svg', false)) {
                $this->doSanitizeSvg($filename);
            }

            // Add the new file into the media.
            // TODO: This overrides existing media sizes if used with multiple retina image sizes.
            $this->doAddUploadedMedium($name, $filename);

            // Update media index.
            if (method_exists($this, 'updateIndex')) {
                $this->updateIndex();
            }

        } catch (Exception $e) {
            throw new RuntimeException($this->translate('GRAV.MEDIA.UPLOAD_ERR_FAILED_TO_MOVE', $e->getMessage()), 400);
        } finally {
            $this->clearCache();
        }
    }

    /**
     * Delete real file from the media collection.
     *
     * @param string $filename
     * @return void
     * @throws RuntimeException
     */
    public function deleteFile(string $filename): void
    {
        try {
            // Check if the filename is allowed.
            $this->checkFilename($filename);

            // Get base name of the file.
            $name = $this->getBasename($filename);

            // Remove file and all the associated metadata.
            $this->clearCache();

            $this->doRemove($name);

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
     */
    public function renameFile(string $from, string $to): void
    {
        try {
            // Check if the filename is allowed.
            $this->checkFilename($from);
            $this->checkFilename($to);

            $this->clearCache();

            // Remove @2x, @3x and .meta.yaml
            $from = $this->getBasename($from);
            $to = $this->getBasename($to);

            $this->doRename($from, $to);

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
     * Internal logic to move uploaded file.
     *
     * @param UploadedFileInterface $uploadedFile
     * @param string $filename
     */
    abstract protected function doMoveUploadedFile(UploadedFileInterface $uploadedFile, string $filename): void;

    /**
     * Internal logic to copy file.
     *
     * @param string $src
     * @param string $dst
     */
    abstract protected function doCopy(string $src, string $dst): void;

    /**
     * Internal logic to rename file.
     *
     * @param string $from
     * @param string $to
     */
    abstract protected function doRename(string $from, string $to): void;

    /**
     * Internal logic to remove file.
     *
     * @param string $filename
     */
    abstract protected function doRemove(string $filename): void;

    /**
     * @param string $filename
     */
    abstract protected function doSanitizeSvg(string $filename): void;

    /**
     * @param array $metadata
     * @param string $filename
     */
    protected function doSaveMetadata(array $metadata, string $filename): void
    {
        $filepath = $this->getPath($filename . '.meta.yaml');
        $file = YamlFile::instance($filepath);
        $file->save($metadata);
    }

    /**
     * @param string $filename
     */
    protected function doRemoveMetadata(string $filename): void
    {
        $filepath = $this->getPath($filename . '.meta.yaml');
        $file = YamlFile::instance($filepath);
        if ($file->exists()) {
            $file->delete();
        }
    }

    /**
     * @param string $name
     * @param string $filename
     */
    protected function doAddUploadedMedium(string $name, string $filename): void
    {
        $filepath = $this->getRealPath($filename);
        $medium = $this->createFromFile($filepath);
        $this->add($name, $medium);
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

    abstract protected function getRealPath(string $filename, array $info = null): ?string;

    abstract protected function getGrav(): Grav;

    abstract protected function getLocator(): UniformResourceLocator;

    abstract protected function getConfig(): Config;

    abstract protected function getLanguage(): Language;

    abstract protected function clearCache(): void;
}
