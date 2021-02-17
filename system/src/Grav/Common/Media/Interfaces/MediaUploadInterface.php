<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media\Interfaces;

use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

/**
 * Implements media upload and delete functionality.
 */
interface MediaUploadInterface
{
    /**
     * Checks that uploaded file meets the requirements. Returns new filename.
     *
     * @example
     *   $filename = null;  // Override filename if needed (ignored if randomizing filenames).
     *   $settings = ['destination' => 'user://pages/media']; // Settings from the form field.
     *   $filename = $media->checkUploadedFile($uploadedFile, $filename, $settings);
     *   $media->copyUploadedFile($uploadedFile, $filename);

     * @param UploadedFileInterface $uploadedFile
     * @param string|null $filename
     * @param array|null $settings
     * @return string
     * @throws RuntimeException
     */
    public function checkUploadedFile(UploadedFileInterface $uploadedFile, string $filename = null, array $settings = null): string;

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
    public function copyUploadedFile(UploadedFileInterface $uploadedFile, string $filename, array $settings = null): void;

    /**
     * Delete real file from the media collection.
     *
     * @param string $filename
     * @param array|null $settings
     * @return void
     */
    public function deleteFile(string $filename, array $settings = null): void;

    /**
     * Rename file inside the media collection.
     *
     * @param string $from
     * @param string $to
     * @param array|null $settings
     */
    public function renameFile(string $from, string $to, array $settings = null): void;
}
