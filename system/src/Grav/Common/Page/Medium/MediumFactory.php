<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Medium;

use Grav\Common\Grav;
use Grav\Common\Data\Blueprint;
use Grav\Common\Media\Interfaces\ImageMediaInterface;
use Grav\Common\Media\Interfaces\MediaObjectInterface;
use Grav\Common\Utils;
use Grav\Framework\Form\FormFlashFile;
use Psr\Http\Message\UploadedFileInterface;
use function dirname;
use function is_array;

/**
 * Class MediumFactory
 * @package Grav\Common\Page\Medium
 */
class MediumFactory
{
    /**
     * Create Medium from a file
     *
     * @param  string $file
     * @param  array  $params
     * @return Medium|null
     */
    public static function fromFile($file, array $params = [])
    {
        if (!file_exists($file)) {
            return null;
        }

        $parts = Utils::pathinfo($file);
        $path = $parts['dirname'];
        $filename = $parts['basename'];
        $ext = $parts['extension'] ?? '';
        $basename = $parts['filename'];

        $config = Grav::instance()['config'];

        $media_params = $ext ? $config->get('media.types.' . strtolower($ext)) : null;
        if (!is_array($media_params)) {
            return null;
        }

        // Remove empty 'image' attribute
        if (isset($media_params['image']) && empty($media_params['image'])) {
            unset($media_params['image']);
        }

        $params += $media_params;

        // Add default settings for undefined variables.
        $params += (array)$config->get('media.types.defaults');
        $params += [
            'type' => 'file',
            'thumb' => 'media/thumb.png',
            'mime' => 'application/octet-stream',
            'filepath' => $file,
            'filename' => $filename,
            'basename' => $basename,
            'extension' => $ext,
            'path' => $path,
            'modified' => filemtime($file),
            'thumbnails' => []
        ];

        $locator = Grav::instance()['locator'];

        $file = $locator->findResource("image://{$params['thumb']}");
        if ($file) {
            $params['thumbnails']['default'] = $file;
        }

        return static::fromArray($params);
    }

    /**
     * Create Medium from an uploaded file
     *
     * @param  UploadedFileInterface $uploadedFile
     * @param  array  $params
     * @return Medium|null
     */
    public static function fromUploadedFile(UploadedFileInterface $uploadedFile, array $params = [])
    {
        // For now support only FormFlashFiles, which exist over multiple requests. Also ignore errored and moved media.
        if (!$uploadedFile instanceof FormFlashFile || $uploadedFile->getError() !== \UPLOAD_ERR_OK || $uploadedFile->isMoved()) {
            return null;
        }

        $clientName = $uploadedFile->getClientFilename();
        if (!$clientName) {
            return null;
        }

        $parts = Utils::pathinfo($clientName);
        $filename = $parts['basename'];
        $ext = $parts['extension'] ?? '';
        $basename = $parts['filename'];
        $file = $uploadedFile->getTmpFile();
        $path = $file ? dirname($file) : '';

        $config = Grav::instance()['config'];

        $media_params = $ext ? $config->get('media.types.' . strtolower($ext)) : null;
        if (!is_array($media_params)) {
            return null;
        }

        $params += $media_params;

        // Add default settings for undefined variables.
        $params += (array)$config->get('media.types.defaults');
        $params += [
            'type' => 'file',
            'thumb' => 'media/thumb.png',
            'mime' => 'application/octet-stream',
            'filepath' => $file,
            'filename' => $filename,
            'basename' => $basename,
            'extension' => $ext,
            'path' => $path,
            'modified' => $file ? filemtime($file) : 0,
            'thumbnails' => []
        ];

        $locator = Grav::instance()['locator'];

        $file = $locator->findResource("image://{$params['thumb']}");
        if ($file) {
            $params['thumbnails']['default'] = $file;
        }

        return static::fromArray($params);
    }

    /**
     * Create Medium from array of parameters
     *
     * @param  array          $items
     * @return Medium
     */
    public static function fromArray(array $items = [])
    {
        $type = $items['type'] ?? null;

        switch ($type) {
            case 'image':
                return new ImageMedium($items);
            case 'thumbnail':
                return new ThumbnailImageMedium($items);
            case 'vector':
                return new VectorImageMedium($items);
            case 'animated':
                return new StaticImageMedium($items);
            case 'video':
                return new VideoMedium($items);
            case 'audio':
                return new AudioMedium($items);
            default:
                return new Medium($items);
        }
    }

    /**
     * Create a new ImageMedium by scaling another ImageMedium object.
     *
     * @param  ImageMediaInterface|MediaObjectInterface $medium
     * @param  int         $from
     * @param  int         $to
     * @return ImageMediaInterface|MediaObjectInterface|array
     */
    public static function scaledFromMedium($medium, int $from, int $to)
    {
        if (!$medium instanceof ImageMedium || $to > $from) {
            return $medium;
        }

        $basename = str_replace('@' . $from . 'x', $to !== 1 ? '@' . $to . 'x' : '', $medium->get('basename'));

        $medium = clone $medium;
        $medium->setImagePrettyName($basename);
        $medium->retinaScale($to);

        return ['file' => $medium, 'size' => 0];
    }
}
