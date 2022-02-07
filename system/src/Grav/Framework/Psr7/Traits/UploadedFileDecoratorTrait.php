<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Psr7
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Psr7\Traits;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Trait UploadedFileDecoratorTrait
 * @package Grav\Framework\Psr7\Traits
 */
trait UploadedFileDecoratorTrait
{
    /** @var UploadedFileInterface */
    protected $uploadedFile;

    /**
     * @return StreamInterface
     */
    public function getStream(): StreamInterface
    {
        return $this->uploadedFile->getStream();
    }

    /**
     * @param string $targetPath
     */
    public function moveTo($targetPath): void
    {
        $this->uploadedFile->moveTo($targetPath);
    }

    /**
     * @return int|null
     */
    public function getSize(): ?int
    {
        return $this->uploadedFile->getSize();
    }

    /**
     * @return int
     */
    public function getError(): int
    {
        return $this->uploadedFile->getError();
    }

    /**
     * @return string|null
     */
    public function getClientFilename(): ?string
    {
        return $this->uploadedFile->getClientFilename();
    }

    /**
     * @return string|null
     */
    public function getClientMediaType(): ?string
    {
        return $this->uploadedFile->getClientMediaType();
    }
}
