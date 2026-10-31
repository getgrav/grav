<?php

/**
 * @package    Grav\Common\Page
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Medium;

use Error;
use Exception;
use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Gregwar\Image\Exceptions\GenerationError;
use Gregwar\Image\Image;
use Gregwar\Image\Source;
use InvalidArgumentException;
use RangeException;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RuntimeException;
use function array_key_exists;
use function call_user_func_array;
use function count;
use function extension_loaded;
use function in_array;
use function ord;
use function strlen;

/**
 * Class ImageFile
 * @package Grav\Common\Page\Medium
 *
 * @method Image applyExifOrientation($exif_orienation)
 */
class ImageFile extends Image
{
    /** @var int Most pixels one operation may allocate, 0 for no limit */
    protected $maxPixels = 0;

    /**
     * Image constructor with adapter configuration from Grav.
     *
     * @param string|null $originalFile
     * @param int|null $width
     * @param int|null $height
     */
    public function __construct($originalFile = null, $width = null, $height = null)
    {
        parent::__construct($originalFile, $width, $height);
        
        // Set the adapter based on Grav configuration
        $grav = Grav::instance();
        $adapter = $grav['config']->get('system.images.adapter', 'gd');
        try {
            $this->setAdapter($adapter);
        } catch (Exception) {
            $grav['log']->error(
                'Image adapter "' . $adapter . '" is not available. Falling back to GD adapter.'
            );
        }
    }

    /**
     * Destruct also image object.
     */
    #[\ReturnTypeWillChange]
    public function __destruct()
    {
        $adapter = $this->adapter;
        if ($adapter) {
            $adapter->deinit();
        }
    }

    /**
     * Clear previously applied operations
     *
     * @return void
     */
    public function clearOperations()
    {
        $this->operations = [];
    }

    /**
     * Holds the canvas of every queued operation to a total-pixel ceiling.
     *
     * @param int $maxPixels 0 for no limit
     * @return $this
     */
    public function setMaxPixels($maxPixels)
    {
        $this->maxPixels = (int) $maxPixels;

        return $this;
    }

    /**
     * Applies the operations, each measured against the ceiling just before it
     * runs. Only here is the size it starts from known rather than predicted:
     * whatever the operations before it, the Exif orientation fix and a resize
     * that declined to enlarge among them, really left.
     *
     * @return void
     * @throws RangeException
     */
    public function applyOperations()
    {
        foreach ($this->operations as [$method, $args]) {
            if ($this->maxPixels > 0) {
                $canvas = ImageMedium::urlActionCanvas($method, $args, $this->adapter->width(), $this->adapter->height());
                if ($canvas === null || $canvas[0] > $this->maxPixels) {
                    // A refusal, not a broken image: save() would otherwise
                    // swallow the exception and serve the fallback image.
                    $this->useFallback(false);

                    throw new RangeException("Image operation {$method} is above the pixel limit");
                }
            }

            call_user_func_array([$this->adapter, $method], $args);
        }
    }

    /**
     * This is the same as the Gregwar Image class except this one fires a Grav Event on creation of new cached file
     *
     * @param string $type the image type
     * @param int $quality the quality (for JPEG)
     * @param bool $actual
     * @param array $extras
     * @return string
     */
    public function cacheFile($type = 'jpg', $quality = 80, $actual = false, $extras = [])
    {
        if ($type === 'guess') {
            $type = $this->guessType();
        }

        // The type names the cached file. save() refuses an unknown one as well,
        // but only after the cache has looked that file up, removed it if it is
        // older than the image and created the folders leading to it.
        if (!isset(self::$types[$type])) {
            throw new InvalidArgumentException('Given type (' . $type . ') is not valid');
        }

        if (!$this->forceCache && !count($this->operations) && $type === $this->guessType()) {
            return $this->getFilename($this->getFilePath());
        }

        // Computes the hash
        $this->hash = $this->getHash($type, $quality, $extras);

        /** @var Config $config */
        $config = Grav::instance()['config'];

        // Seo friendly image names
        $seofriendly = $config->get('system.images.seofriendly', false);

        if ($seofriendly) {
            $mini_hash = substr($this->hash, 0, 4) . substr($this->hash, -4);
            $cacheFile = "{$this->prettyName}-{$mini_hash}";
        } else {
            $cacheFile = "{$this->hash}-{$this->prettyName}";
        }

        $cacheFile .= '.' . $type;

        // If the files does not exists, save it
        $image = $this;

        // Target file should be younger than all the current image
        // dependencies
        $conditions = ['younger-than' => $this->getDependencies()];

        // The generating function
        $generate = function ($target) use ($image, $type, $quality) {
            try {
                $result = $image->save($target, $type, $quality);
            } catch (Error $e) {
                // save() turns exceptions into the fallback image, but a GD built
                // without AVIF or WebP fails on an undefined function instead.
                if (!$image->useFallbackImage) {
                    throw $e;
                }

                $result = $image->getCacheFallback();
            }

            if ($result !== $target) {
                throw new GenerationError($result);
            }

            Grav::instance()->fireEvent('onImageMediumSaved', new Event(['image' => $target]));
        };

        // Asking the cache for the cacheFile
        try {
            $perms = $config->get('system.images.cache_perms', '0775');
            $perms = octdec((string) $perms);
            $file = $this->getCacheSystem()->setDirectoryMode($perms)->getOrCreateFile($cacheFile, $conditions, $generate, $actual);
        } catch (GenerationError $e) {
            $file = $e->getNewFile();
        }

        // Nulling the resource
        $adapter = $this->getAdapter();
        $adapter->setSource(new Source\File($file));
        $adapter->deinit();

        if ($actual) {
            return $file;
        }

        return $this->getFilename($file);
    }

    /**
     * Gets the hash.
     *
     * @param string $type
     * @param int $quality
     * @param array $extras
     * @return string
     */
    public function getHash($type = 'guess', $quality = 80, $extras = [])
    {
        if (null === $this->hash) {
            $this->generateHash($type, $quality, $extras);
        }

        return $this->hash;
    }

    /**
     * Generates the hash.
     *
     * @param string $type
     * @param int $quality
     * @param array $extras
     */
    public function generateHash($type = 'guess', $quality = 80, $extras = [])
    {
        $inputInfos = $this->source->getInfos();

        $data = [
            $inputInfos,
            $this->serializeOperations(),
            $type,
            $quality,
            $extras
        ];

        $this->hash = sha1(serialize($data));
    }

    /**
     * Read exif rotation from file and apply it.
     */
    public function fixOrientation()
    {
        if (!extension_loaded('exif')) {
            throw new RuntimeException('You need to EXIF PHP Extension to use this function');
        }

        if (!file_exists($this->source->getInfos()) || !in_array(@exif_imagetype($this->source->getInfos()), [IMAGETYPE_JPEG, IMAGETYPE_TIFF_II, IMAGETYPE_TIFF_MM], true)) {
            return $this;
        }

        // resolve any streams
        /** @var UniformResourceLocator $locator */
        $locator = Grav::instance()['locator'];
        $filepath = $this->source->getInfos();
        if ($locator->isStream($filepath)) {
            $filepath = $locator->findResource($this->source->getInfos(), true, true);
        }

        // Make sure file exists
        if (!file_exists($filepath)) {
            return $this;
        }

        try {
            $exif = @exif_read_data($filepath);
        } catch (Exception $e) {
            Grav::instance()['log']->error($filepath . ' - ' . $e->getMessage());
            return $this;
        }

        if ($exif === false || !array_key_exists('Orientation', $exif)) {
            return $this;
        }

        return $this->applyExifOrientation($exif['Orientation']);
    }

    /**
     * Read source dimensions using the image decoder's header rules.
     *
     * @param string $file
     * @return array{0:int,1:int}|null Null for a file that cannot be measured
     */
    public static function decodedSize(string $file): ?array
    {
        $type = (new Source\File($file))->guessType();
        if ($type === 'jpeg') {
            return static::jpegFrameSize($file);
        }

        $size = @getimagesize($file);
        if (!$size || $size['mime'] !== 'image/' . $type) {
            return null;
        }

        $header = (string) @file_get_contents($file, false, null, 0, 16);
        if ($type === 'webp' && !str_starts_with($header, 'RIFF')) {
            return null;
        }
        if ($type === 'png' && substr($header, 8) !== "\0\0\0\rIHDR") {
            return null;
        }

        return [(int) $size[0], (int) $size[1]];
    }

    /**
     * Walks the markers of a JPEG up to the frame header the way libjpeg's read_markers() does.
     *
     * PHP's header reader takes every marker it does not know for a segment with a
     * length. libjpeg knows that RST0-7 and TEM have none, so behind one of them
     * the two read different bytes and need not find the same frame header.
     *
     * @param string $file
     * @return array{0:int,1:int}|null Null when there is no frame header to be found that way
     */
    protected static function jpegFrameSize(string $file): ?array
    {
        $handle = @fopen($file, 'rb');
        if (!$handle) {
            return null;
        }

        try {
            if (fread($handle, 2) !== "\xFF\xD8") {
                return null;
            }

            while (true) {
                // Bytes that are no marker are passed over, and so are fill bytes in front of the marker code.
                do {
                    $byte = fgetc($handle);
                } while ($byte !== false && $byte !== "\xFF");
                do {
                    $byte = fgetc($handle);
                } while ($byte === "\xFF");
                if ($byte === false) {
                    return null;
                }

                $marker = ord($byte);
                if ($marker <= 0x01 || ($marker >= 0xD0 && $marker <= 0xD7)) {
                    // A stuffed zero is no marker, TEM and RST0-7 have no length: the next marker follows.
                    continue;
                }
                if ($marker >= 0xD8 && $marker <= 0xDA) {
                    // A second SOI, EOI or SOS: an error without a frame header before it.
                    return null;
                }

                if ($marker >= 0xC0 && $marker <= 0xCF && !in_array($marker, [0xC4, 0xC8, 0xCC], true)) {
                    $frame = (string) fread($handle, 7);
                    if (strlen($frame) < 7) {
                        return null;
                    }
                    $frame = unpack('nlength/Cprecision/nheight/nwidth', $frame);

                    return [$frame['width'], $frame['height']];
                }

                $length = (string) fread($handle, 2);
                $length = strlen($length) === 2 ? unpack('n', $length)[1] : 0;
                // The length counts its own two bytes.
                if ($length < 2 || ($length > 2 && fseek($handle, $length - 2, SEEK_CUR) !== 0)) {
                    return null;
                }
            }
        } finally {
            fclose($handle);
        }
    }
}
