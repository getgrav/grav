<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
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
     * @phpstan-pure
     */
    public function checkUploadedFile(UploadedFileInterface $uploadedFile, string $filename = null, array $settings = null): string;

    /**
     * Copy uploaded file to the media collection.
     *
     * WARNING: Always check uploaded file before copying it!
     *
     * @example
     *   $filename = null;  // Override filename if needed (ignored if randomizing filenames).
     *   $filename = $media->checkUploadedFile($uploadedFile, $filename);
     *   $media->copyUploadedFile($uploadedFile, $filename);
     *
     * @param UploadedFileInterface $uploadedFile
     * @param string $filename
     * @return void
     * @throws RuntimeException
     * @phpstan-impure
     */
    public function copyUploadedFile(UploadedFileInterface $uploadedFile, string $filename): void;

    /**
     * Delete real file from the media collection.
     *
     * @param string $filename
     * @return void
     * @phpstan-impure
     */
    public function deleteFile(string $filename): void;

    /**
     * Rename file inside the media collection.
     *
     * @param string $from
     * @param string $to
     * @return void
     * @phpstan-impure
     */
    public function renameFile(string $from, string $to): void;

    /**
     * @return bool True if media was deleted. Shared media cannot be deleted and will return false.
     */
    public function deleteAll(): bool;

    /**
     * @param string|null $to
     * @return bool True if media was moved. Shared media cannot be deleted and will return false.
     */
    public function moveAll(string $to = null): bool;

    /**
     * @param string|null $to
     * @return bool True if media was copied. Shared media cannot be deleted and will return false.
     */
    public function copyAll(string $to = null): bool;
}
