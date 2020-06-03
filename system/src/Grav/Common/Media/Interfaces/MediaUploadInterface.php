<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
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
     * @param UploadedFileInterface $uploadedFile
     * @param string|null $filename
     * @throws RuntimeException
     */
    public function checkUploadedFile(UploadedFileInterface $uploadedFile, string $filename = null): string;

    /**
     * Upload file to the media collection.
     *
     * @param UploadedFileInterface $uploadedFile
     * @param string|null $filename
     * @return void
     * @throws RuntimeException
     */
    public function uploadFile(UploadedFileInterface $uploadedFile, string $filename = null): void;

    /**
     * @param string $filename
     * @return void
     */
    public function deleteFile(string $filename): void;
}
