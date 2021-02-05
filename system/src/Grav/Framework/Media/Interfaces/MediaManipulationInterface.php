<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Media
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Media\Interfaces;

use Grav\Common\Media\Interfaces\MediaInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Interface MediaManipulationInterface
 * @package Grav\Framework\Media\Interfaces
 * @deprecated 1.7 Not used currently
 */
interface MediaManipulationInterface extends MediaInterface
{
    /**
     * @param UploadedFileInterface $uploadedFile
     */
    public function uploadMediaFile(UploadedFileInterface $uploadedFile): void;

    /**
     * @param string $filename
     */
    public function deleteMediaFile(string $filename): void;
}
