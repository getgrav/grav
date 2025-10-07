<?php

/**
 * @package    Grav\Framework\Form
 *
 * @copyright  Copyright (c) 2015 - 2025 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Form;

use Grav\Common\Security;
use Grav\Common\Utils;
use Grav\Framework\Psr7\Stream;
use InvalidArgumentException;
use JsonSerializable;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use function copy;
use function fopen;
use function sprintf;

/**
 * Class FormFlashFile
 * @package Grav\Framework\Form
 */
class FormFlashFile implements UploadedFileInterface, JsonSerializable
{
    /** @var string */
    private $id;
    /** @var bool */
    private $moved = false;
    /** @var FormFlash */
    private $flash;

    /**
     * FormFlashFile constructor.
     * @param string $field
     * @param array $upload
     * @param FormFlash $flash
     */
    public function __construct(private readonly string $field, private array $upload, FormFlash $flash)
    {
        $this->id = $flash->getId() ?: $flash->getUniqueId();
        $this->flash = $flash;

        $tmpFile = $this->getTmpFile();
        if (!$tmpFile && $this->isOk()) {
            $this->upload['error'] = \UPLOAD_ERR_NO_FILE;
        }

        if (!isset($this->upload['size'])) {
            $this->upload['size'] = $tmpFile && $this->isOk() ? filesize($tmpFile) : 0;
        }
    }

    /**
     * @return StreamInterface
     */
    public function getStream(): StreamInterface
    {
        $this->validateActive();

        $tmpFile = $this->getTmpFile();
        if (null === $tmpFile) {
            throw new RuntimeException('No temporary file');
        }

        $resource = fopen($tmpFile, 'rb');
        if (false === $resource) {
            throw new RuntimeException('No temporary file');
        }

        return Stream::create($resource);
    }

    /**
     * @param string $targetPath
     * @return void
     */
    public function moveTo(string $targetPath): void
    {
        $this->validateActive();

        if ($targetPath === '') {
            throw new InvalidArgumentException('Invalid path provided for move operation; must be a non-empty string');
        }
        $tmpFile = $this->getTmpFile();
        if (null === $tmpFile) {
            throw new RuntimeException('No temporary file');
        }

        $this->moved = copy($tmpFile, $targetPath);

        if (false === $this->moved) {
            throw new RuntimeException(sprintf('Uploaded file could not be moved to %s', $targetPath));
        }

        $filename = $this->getClientFilename();
        if ($filename) {
            $this->flash->removeFile($filename, $this->field);
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getField(): string
    {
        return $this->field;
    }

    /**
     * @return int|null
     */
    public function getSize(): ?int
    {
        return $this->upload['size'] ?? null;
    }

    /**
     * @return int
     */
    public function getError(): int
    {
        return $this->upload['error'] ?? \UPLOAD_ERR_OK;
    }

    /**
     * @return string|null
     */
    public function getClientFilename(): ?string
    {
        return $this->upload['name'] ?? 'unknown';
    }

    /**
     * @return string|null
     */
    public function getClientMediaType(): ?string
    {
        return $this->upload['type'] ?? 'application/octet-stream';
    }

    /**
     * @return bool
     */
    public function isMoved(): bool
    {
        return $this->moved;
    }

    /**
     * @return array
     */
    public function getMetaData(): array
    {
        if (isset($this->upload['crop'])) {
            return ['crop' => $this->upload['crop']];
        }

        return [];
    }

    /**
     * @return string
     */
    public function getDestination()
    {
        return $this->upload['path'] ?? '';
    }

    /**
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->upload;
    }

    /**
     * @return void
     */
    public function checkXss(): void
    {
        $tmpFile = $this->getTmpFile();
        $mime = $this->getClientMediaType();
        if (Utils::contains($mime, 'svg', false)) {
            $response = Security::detectXssFromSvgFile($tmpFile);
            if ($response) {
                throw new RuntimeException(sprintf('SVG file XSS check failed on %s', $response));
            }
        }
    }

    /**
     * @return string|null
     */
    public function getTmpFile(): ?string
    {
        $tmpName = $this->upload['tmp_name'] ?? null;

        if (!$tmpName) {
            return null;
        }

        $tmpFile = $this->flash->getTmpDir() . '/' . $tmpName;

        return file_exists($tmpFile) ? $tmpFile : null;
    }

    /**
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function __debugInfo()
    {
        return [
            'id:private' => $this->id,
            'field:private' => $this->field,
            'moved:private' => $this->moved,
            'upload:private' => $this->upload,
        ];
    }

    /**
     * @return void
     * @throws RuntimeException if is moved or not ok
     */
    private function validateActive(): void
    {
        if (!$this->isOk()) {
            throw new RuntimeException('Cannot retrieve stream due to upload error');
        }

        if ($this->moved) {
            throw new RuntimeException('Cannot retrieve stream after it has already been moved');
        }

        if (!$this->getTmpFile()) {
            throw new RuntimeException('Cannot retrieve stream as the file is missing');
        }
    }

    /**
     * @return bool return true if there is no upload error
     */
    private function isOk(): bool
    {
        return \UPLOAD_ERR_OK === $this->getError();
    }
}
