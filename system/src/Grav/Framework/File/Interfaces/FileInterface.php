<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\File
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\File\Interfaces;

interface FileInterface extends \Serializable
{
    /**
     * Get full path to the file.
     *
     * @return string
     */
    public function getFilePath(): string;

    /**
     * Get path to the file.
     *
     * @return string
     */
    public function getPath(): string;

    /**
     * Get filename.
     *
     * @return string
     */
    public function getFilename(): string;

    /**
     * Return name of the file without extension.
     *
     * @return string
     */
    public function getBasename(): string;

    /**
     * Return file extension.
     *
     * @param bool $withDot
     * @return string
     */
    public function getExtension(bool $withDot = false): string;

    /**
     * Check if file exits.
     *
     * @return bool
     */
    public function exists(): bool;

    /**
     * Return file modification time.
     *
     * @return int Unix timestamp. If file does not exist, method returns current time.
     */
    public function getCreationTime(): int;

    /**
     * Return file modification time.
     *
     * @return int Unix timestamp. If file does not exist, method returns current time.
     */
    public function getModificationTime(): int;

    /**
     * Lock file for writing. You need to manually unlock().
     *
     * @param bool $block  For non-blocking lock, set the parameter to false.
     * @return bool
     * @throws \RuntimeException
     */
    public function lock(bool $block = true): bool;

    /**
     * Unlock file.
     *
     * @return bool
     */
    public function unlock(): bool;

    /**
     * Returns true if file has been locked for writing.
     *
     * @return bool True = locked, false = not locked.
     */
    public function isLocked(): bool;

    /**
     * Check if file exists and can be read.
     *
     * @return bool
     */
    public function isReadable(): bool;

    /**
     * Check if file can be written.
     *
     * @return bool
     */
    public function isWritable(): bool;

    /**
     * (Re)Load a file and return RAW file contents.
     *
     * @return string|array|false
     */
    public function load();

    /**
     * Save file.
     *
     * @param  mixed $data
     * @throws \RuntimeException
     */
    public function save($data): void;

    /**
     * Rename file in the filesystem if it exists.
     *
     * @param string $path
     * @return bool
     */
    public function rename(string $path): bool;

    /**
     * Delete file from filesystem.
     *
     * @return bool
     */
    public function delete(): bool;
}
