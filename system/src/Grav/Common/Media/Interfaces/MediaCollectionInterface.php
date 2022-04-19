<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media\Interfaces;

use Grav\Common\Data\Blueprint;
use Grav\Common\Page\Medium\Medium;
use RuntimeException;

/**
 * Class implements media collection interface.
 */
interface MediaCollectionInterface extends \Grav\Framework\Media\Interfaces\MediaCollectionInterface
{
    /**
     * Get media id.
     *
     * @return string
     */
    public function getId(): string;

    /**
     * Get media type used in MediaFactory.
     *
     * @return string
     */
    public function getType(): string;

    /**
     * Get media name used in MediaFactory.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Return media path.
     *
     * @param string|null $filename
     * @return string|null
     */
    public function getPath(string $filename = null): ?string;

    /**
     * Return media file url.
     *
     * @param string $filename
     * @return string
     */
    public function getUrl(string $filename): string;

    /**
     * Get medium by filename.
     *
     * @param string $filename
     * @return MediaObjectInterface|null
     */
    public function get(string $filename): ?MediaObjectInterface;

    /**
     * Get a list of all media.
     *
     * @return array<string,MediaObjectInterface>
     */
    public function all(): array;

    /**
     * Get a list of all image media.
     *
     * @return array<string,MediaObjectInterface>
     */
    public function images(): array;

    /**
     * Get a list of all video media.
     *
     * @return array<string,MediaObjectInterface>
     */
    public function videos(): array;

    /**
     * Get a list of all audio media.
     *
     * @return array<string,MediaObjectInterface>
     */
    public function audios(): array;

    /**
     * Get a list of all file media.
     *
     * @return array<string,MediaObjectInterface>
     */
    public function files(): array;

    /**
     * Set file modification timestamps (query params) for all the media files.
     *
     * @param string|int|null $timestamp
     * @return $this
     */
    public function setTimestamps($timestamp = null);

    /**
     * @param string $name
     * @param MediaObjectInterface $file
     * @return void
     */
    public function add(string $name, MediaObjectInterface $file): void;

    /**
     * Create Medium from a file.
     *
     * @param  string $file
     * @param  array  $params
     * @return Medium|null
     */
    public function createFromFile(string $file, array $params = []): ?MediaObjectInterface;

    /**
     * Create Medium from array of parameters
     *
     * @param  array          $items
     * @param  Blueprint|null $blueprint
     * @return Medium|null
     */
    public function createFromArray(array $items = [], Blueprint $blueprint = null): ?MediaObjectInterface;

    /**
     * @param string $filename
     * @param array|null $info
     * @return string
     * @throws RuntimeException
     * @internal Use $medium->readFile() instead!
     */
    public function readFile(string $filename, array $info = null): string;

    /**
     * @param string $filename
     * @param array|null $info
     * @return resource
     * @throws RuntimeException
     * @internal Use $medium->readFile() instead!
     */
    public function readStream(string $filename, array $info = null);
}
