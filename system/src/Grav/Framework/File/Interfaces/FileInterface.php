<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\File
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\File\Interfaces;

use RuntimeException;
use Serializable;

/**
 * Defines common interface for all file readers.
 *
 * File readers allow you to read and optionally write files of various file formats, such as:
 *
 * @used-by \Grav\Framework\File\CsvFile         CVS
 * @used-by \Grav\Framework\File\JsonFile        JSON
 * @used-by \Grav\Framework\File\MarkdownFile    Markdown
 * @used-by \Grav\Framework\File\SerializeFile   Serialized PHP
 * @used-by \Grav\Framework\File\YamlFile        YAML
 *
 * @since 1.6
 */
interface FileInterface extends Serializable
{
    /**
     * Get both path and filename of the file.
     *
     * @return string Returns path and filename in the filesystem. Can also be URI.
     * @api
     */
    public function getFilePath(): string;

    /**
     * Get path of the file.
     *
     * @return string Returns path in the filesystem. Can also be URI.
     * @api
     */
    public function getPath(): string;

    /**
     * Get filename of the file.
     *
     * @return string Returns name of the file.
     * @api
     */
    public function getFilename(): string;

    /**
     * Get basename of the file (filename without the associated file extension).
     *
     * @return string Returns basename of the file.
     * @api
     */
    public function getBasename(): string;

    /**
     * Get file extension of the file.
     *
     * @param bool $withDot If true, return file extension with beginning dot (.json).
     *
     * @return string Returns file extension of the file (can be empty).
     * @api
     */
    public function getExtension(bool $withDot = false): string;

    /**
     * Check if the file exits in the filesystem.
     *
     * @return bool Returns `true` if the filename exists and is a regular file, `false` otherwise.
     * @api
     */
    public function exists(): bool;

    /**
     * Get file creation time.
     *
     * @return int Returns Unix timestamp. If file does not exist, method returns current time.
     * @api
     */
    public function getCreationTime(): int;

    /**
     * Get file modification time.
     *
     * @return int Returns Unix timestamp. If file does not exist, method returns current time.
     * @api
     */
    public function getModificationTime(): int;

    /**
     * Lock file for writing. You need to manually call unlock().
     *
     * @param bool $block For non-blocking lock, set the parameter to `false`.
     *
     * @return bool Returns `true` if the file was successfully locked, `false` otherwise.
     * @throws RuntimeException
     * @api
     */
    public function lock(bool $block = true): bool;

    /**
     * Unlock file after writing.
     *
     * @return bool Returns `true` if the file was successfully unlocked, `false` otherwise.
     * @api
     */
    public function unlock(): bool;

    /**
     * Returns true if file has been locked by you for writing.
     *
     * @return bool Returns `true` if the file is locked, `false` otherwise.
     * @api
     */
    public function isLocked(): bool;

    /**
     * Check if file exists and can be read.
     *
     * @return bool Returns `true` if the file can be read, `false` otherwise.
     * @api
     */
    public function isReadable(): bool;

    /**
     * Check if file can be written.
     *
     * @return bool Returns `true` if the file can be written, `false` otherwise.
     * @api
     */
    public function isWritable(): bool;

    /**
     * (Re)Load a file and return file contents.
     *
     * @return string|array|object|false Returns file content or `false` if file couldn't be read.
     * @api
     */
    public function load();

    /**
     * Save file.
     *
     * See supported data format for each of the file format.
     *
     * @param  mixed $data Data to be saved.
     *
     * @throws RuntimeException
     * @api
     */
    public function save($data): void;

    /**
     * Rename file in the filesystem if it exists.
     *
     * Target folder will be created if if did not exist.
     *
     * @param string $path New path and filename for the file. Can also be URI.
     *
     * @return bool Returns `true` if the file was successfully renamed, `false` otherwise.
     * @api
     */
    public function rename(string $path): bool;

    /**
     * Delete file from filesystem.
     *
     * @return bool Returns `true` if the file was successfully deleted, `false` otherwise.
     * @api
     */
    public function delete(): bool;
}
