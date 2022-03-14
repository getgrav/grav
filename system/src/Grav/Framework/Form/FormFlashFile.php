<?php

/**
 * @package    Grav\Framework\Form
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
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
use function is_string;
use function sprintf;

/**
 * Class FormFlashFile
 * @package Grav\Framework\Form
 */
class FormFlashFile implements UploadedFileInterface, JsonSerializable
{
    /** @var string */
    private $field;
    /** @var bool */
    private $moved = false;
    /** @var array */
    private $upload;
    /** @var FormFlash */
    private $flash;

    /**
     * FormFlashFile constructor.
     * @param string $field
     * @param array $upload
     * @param FormFlash $flash
     */
    public function __construct(string $field, array $upload, FormFlash $flash)
    {
        $this->field = $field;
        $this->upload = $upload;
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
    public function getStream()
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
    public function moveTo($targetPath)
    {
        $this->validateActive();

        if (!is_string($targetPath) || empty($targetPath)) {
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

    /**
     * @return string
     */
    public function getField(): string
    {
        return $this->field;
    }

    /**
     * @return int
     */
    public function getSize()
    {
        return $this->upload['size'];
    }

    /**
     * @return int
     */
    public function getError()
    {
        return $this->upload['error'] ?? \UPLOAD_ERR_OK;
    }

    /**
     * @return string
     */
    public function getClientFilename()
    {
        return $this->upload['name'] ?? 'unknown';
    }

    /**
     * @return string
     */
    public function getClientMediaType()
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
