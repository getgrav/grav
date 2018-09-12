<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\File
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\File\Interfaces;

interface FileInterface
{
    /**
     * Get full path to the file.
     *
     * @return string
     */
    public function getFilePath() : string;

    /**
     * Get path to the file.
     *
     * @return string
     */
    public function getPath() : string;

    /**
     * Get filename.
     *
     * @return string
     */
    public function getFilename() : string;

    /**
     * Return name of the file without extension.
     *
     * @return string
     */
    public function getBasename() : string;

    /**
     * Return file extension.
     *
     * @param $withDot
     * @return string
     */
    public function getExtension($withDot = false) : string;

    /**
     * Check if file exits.
     *
     * @return bool
     */
    public function exists() : bool;

    /**
     * Return file modification time.
     *
     * @return int|bool Timestamp or false if file doesn't exist.
     */
    public function getCreationTime();

    /**
     * Return file modification time.
     *
     * @return int|bool Timestamp or false if file doesn't exist.
     */
    public function getModificationTime();

    /**
     * Lock file for writing. You need to manually unlock().
     *
     * @param bool $block  For non-blocking lock, set the parameter to false.
     * @return bool
     * @throws \RuntimeException
     */
    public function lock($block = true) : bool;

    /**
     * Unlock file.
     *
     * @return bool
     */
    public function unlock() : bool;

    /**
     * Returns true if file has been locked for writing.
     *
     * @return bool True = locked, false = not locked.
     */
    public function isLocked() : bool;

    /**
     * Check if file can be written.
     *
     * @return bool
     */
    public function isWritable() : bool;

    /**
     * (Re)Load a file and return RAW file contents.
     *
     * @return string
     */
    public function load();

    /**
     * Save file.
     *
     * @param  mixed $data
     * @throws \RuntimeException
     */
    public function save($data);

    /**
     * Rename file in the filesystem if it exists.
     *
     * @param string $path
     * @return bool
     */
    public function rename($path) : bool;

    /**
     * Delete file from filesystem.
     *
     * @return bool
     */
    public function delete() : bool;

    /**
     * Multi-byte-safe pathinfo replacement.
     * Replacement for pathinfo(), but stream, multibyte and cross-platform safe.
     *
     * @see    http://www.php.net/manual/en/function.pathinfo.php
     *
     * @param string     $path    A filename or path, does not need to exist as a file
     * @param int|string $options Either a PATHINFO_* constant,
     *                            or a string name to return only the specified piece
     *
     * @return string|array
     */
    public static function pathinfo($path, $options = null);
}
