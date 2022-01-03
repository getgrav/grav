<?php

/**
 * @package    Grav\Framework\Form
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Form\Interfaces;

use Psr\Http\Message\UploadedFileInterface;

/**
 * Interface FormFlashInterface
 * @package Grav\Framework\Form\Interfaces
 */
interface FormFlashInterface extends \JsonSerializable
{
    /**
     * @param array $config     Available configuration keys: session_id, unique_id, form_name
     */
    public function __construct($config);

    /**
     * Get session Id associated to this form instance.
     *
     * @return string
     */
    public function getSessionId(): string;

    /**
     * Get unique identifier associated to this form instance.
     *
     * @return string
     */
    public function getUniqueId(): string;

    /**
     * Get form name associated to this form instance.
     *
     * @return string
     */
    public function getFormName(): string;

    /**
     * Get URL associated to this form instance.
     *
     * @return string
     */
    public function getUrl(): string;

    /**
     * Get username from the user who was associated to this form instance.
     *
     * @return string
     */
    public function getUsername(): string;

    /**
     * Get email from the user who was associated to this form instance.
     *
     * @return string
     */
    public function getUserEmail(): string;


    /**
     * Get creation timestamp for this form flash.
     *
     * @return int
     */
    public function getCreatedTimestamp(): int;

    /**
     * Get last updated timestamp for this form flash.
     *
     * @return int
     */
    public function getUpdatedTimestamp(): int;

    /**
     * Get raw form data.
     *
     * @return array|null
     */
    public function getData(): ?array;

    /**
     * Set raw form data.
     *
     * @param array|null $data
     * @return void
     */
    public function setData(?array $data): void;

    /**
     * Check if this form flash exists.
     *
     * @return bool
     */
    public function exists(): bool;

    /**
     * Save this form flash.
     *
     * @return $this
     */
    public function save();

    /**
     * Delete this form flash.
     *
     * @return $this
     */
    public function delete();

    /**
     * Get all files associated to a form field.
     *
     * @param string $field
     * @return array
     */
    public function getFilesByField(string $field): array;

    /**
     * Get all files grouped by the associated form fields.
     *
     * @param bool $includeOriginal
     * @return array
     */
    public function getFilesByFields($includeOriginal = false): array;

    /**
     * Add uploaded file to the form flash.
     *
     * @param UploadedFileInterface $upload
     * @param string|null $field
     * @param array|null $crop
     * @return string Return name of the file
     */
    public function addUploadedFile(UploadedFileInterface $upload, string $field = null, array $crop = null): string;

    /**
     * Add existing file to the form flash.
     *
     * @param string $filename
     * @param string $field
     * @param array|null $crop
     * @return bool
     */
    public function addFile(string $filename, string $field, array $crop = null): bool;

    /**
     * Remove any file from form flash.
     *
     * @param string $name
     * @param string|null $field
     * @return bool
     */
    public function removeFile(string $name, string $field = null): bool;

    /**
     * Clear form flash from all uploaded files.
     *
     * @return void
     */
    public function clearFiles();

    /**
     * @return array
     */
    public function jsonSerialize(): array;
}
