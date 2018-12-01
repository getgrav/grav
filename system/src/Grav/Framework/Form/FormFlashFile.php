<?php

/**
 * @package    Grav\Framework\Form
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Form;

use Grav\Framework\Psr7\Stream;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;

class FormFlashFile implements UploadedFileInterface, \JsonSerializable
{
    private $field;
    private $moved = false;
    private $upload;
    private $flash;

    public function __construct(string $field, array $upload, FormFlash $flash)
    {
        $this->field = $field;
        $this->upload = $upload;
        $this->flash = $flash;

        if ($this->isOk() && (empty($this->upload['tmp_name']) || !file_exists($this->getTmpFile()))) {
            $this->upload['error'] = \UPLOAD_ERR_NO_FILE;
        }

        if (!isset($this->upload['size'])) {
            $this->upload['size'] =  $this->isOk() ? filesize($this->getTmpFile()) : 0;
        }
    }

    /**
     * @return StreamInterface
     */
    public function getStream()
    {
        $this->validateActive();

        $resource = \fopen($this->getTmpFile(), 'rb');

        return Stream::create($resource);
    }

    public function moveTo($targetPath)
    {
        $this->validateActive();

        if (!\is_string($targetPath) || empty($targetPath)) {
            throw new \InvalidArgumentException('Invalid path provided for move operation; must be a non-empty string');
        }

        $this->moved = \copy($this->getTmpFile(), $targetPath);

        if (false === $this->moved) {
            throw new \RuntimeException(\sprintf('Uploaded file could not be moved to %s', $targetPath));
        }

        $this->flash->removeFile($this->field, $this->upload['tmp_name']);
    }

    public function getSize()
    {
        return $this->upload['size'];
    }

    public function getError()
    {
        return $this->upload['error'] ?? \UPLOAD_ERR_OK;
    }

    public function getClientFilename()
    {
        return $this->upload['name'] ?? 'unknown';
    }

    public function getClientMediaType()
    {
        return $this->upload['type'] ?? 'application/octet-stream';
    }

    public function isMoved() : bool
    {
        return $this->moved;
    }

    public function getMetaData() : array
    {
        if (isset($this->upload['crop'])) {
            return ['crop' => $this->upload['crop']];
        }

        return [];
    }

    public function getDestination()
    {
        return $this->upload['path'];
    }

    public function jsonSerialize()
    {
        return $this->upload;
    }

    public function __debugInfo()
    {
        return [
            'field:private' => $this->field,
            'moved:private' => $this->moved,
            'upload:private' => $this->upload,
        ];
    }

    /**
     * @throws \RuntimeException if is moved or not ok
     */
    private function validateActive(): void
    {
        if (!$this->isOk()) {
            throw new \RuntimeException('Cannot retrieve stream due to upload error');
        }

        if ($this->moved) {
            throw new \RuntimeException('Cannot retrieve stream after it has already been moved');
        }
    }

    /**
     * @return bool return true if there is no upload error
     */
    private function isOk(): bool
    {
        return \UPLOAD_ERR_OK === $this->getError();
    }

    private function getTmpFile() : string
    {
        return $this->flash->getTmpDir() . '/' . $this->upload['tmp_name'];
    }
}
