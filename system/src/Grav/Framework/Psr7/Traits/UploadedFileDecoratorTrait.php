<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Psr7
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Psr7\Traits;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;

trait UploadedFileDecoratorTrait
{
    /** @var UploadedFileInterface */
    protected $uploadedFile;

    public function getStream(): StreamInterface
    {
        return $this->uploadedFile->getStream();
    }

    public function moveTo($targetPath): void
    {
        $this->uploadedFile->moveTo($targetPath);
    }

    public function getSize(): ?int
    {
        return $this->uploadedFile->getSize();
    }

    public function getError(): int
    {
        return $this->uploadedFile->getError();
    }

    public function getClientFilename(): ?string
    {
        return $this->uploadedFile->getClientFilename();
    }

    public function getClientMediaType(): ?string
    {
        return $this->uploadedFile->getClientMediaType();
    }
}
