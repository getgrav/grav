<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Media
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Media\Interfaces;

use Grav\Common\Media\Interfaces\MediaInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Interface MediaManipulationInterface
 * @package Grav\Framework\Media\Interfaces
 */
interface MediaManipulationInterface extends MediaInterface
{
    /**
     * @param UploadedFileInterface $uploadedFile
     */
    public function uploadMediaFile(UploadedFileInterface $uploadedFile) : void;

    /**
     * @param string $filename
     */
    public function deleteMediaFile(string $filename) : void;
}
