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
 * @method $this watermark(Image $other, string $position = 'center-center', float $scale = 1.0) Queued on the image adapter
 */
class ImageFile extends Image
{
    /**
     * Most scans a JPEG may have to be decoded. Encoders write about ten for a
     * progressive image and one to four for a baseline one.
     */
    public const MAX_JPEG_SCANS = 100;

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
            // max_pixels bounds the size of one pass over the image, and a JPEG
            // decoder makes a pass for every scan in the file, however many
            // there are. Counted here, where the file is about to be decoded, so
            // that an image with a cached copy is not read again. As for a source
            // above max_pixels, the original is served.
            $source = (string) $image->getFilePath();
            if ($source !== '' && static::jpegScanCount($source, static::MAX_JPEG_SCANS + 1) > static::MAX_JPEG_SCANS) {
                Grav::instance()['log']->warning(sprintf(
                    'Refusing to process image source with more than %d JPEG scans: %s',
                    static::MAX_JPEG_SCANS, $source
                ));

                throw new GenerationError($source);
            }

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

        $orientation = static::exifOrientation($filepath);
        if ($orientation === null) {
            return $this;
        }

        return $this->applyExifOrientation($orientation);
    }

    /**
     * Read the Exif orientation of a JPEG or TIFF file.
     *
     * Nothing but the Orientation entry of the first image directory is looked at.
     * exif_read_data() keeps every segment of the file and a copy of every value a
     * directory entry points to, so a small crafted file costs it hundreds of megabytes.
     *
     * @param string $file
     * @return int|null Null for a file that has none
     */
    public static function exifOrientation(string $file): ?int
    {
        $handle = @fopen($file, 'rb');
        if (!$handle) {
            return null;
        }

        try {
            // The Exif segment of a JPEG holds what a TIFF file starts with.
            $start = fread($handle, 2) === "\xFF\xD8" ? static::jpegExifOffset($handle) : 0;
            if ($start === null || fseek($handle, $start) !== 0) {
                return null;
            }

            $header = (string) fread($handle, 8);
            $order = substr($header, 0, 2);
            if (strlen($header) < 8 || ($order !== 'II' && $order !== 'MM')) {
                return null;
            }
            [$short, $long] = $order === 'II' ? ['v', 'V'] : ['n', 'N'];
            $header = unpack("{$short}magic/{$long}offset", $header, 2);
            if ($header['magic'] !== 42 || fseek($handle, $start + $header['offset']) !== 0) {
                return null;
            }

            $count = (string) fread($handle, 2);
            $count = strlen($count) === 2 ? unpack($short, $count)[1] : 0;
            // An entry has twelve bytes: tag, type, number of values, and the value when four bytes hold it.
            $entries = $count > 0 ? (string) stream_get_contents($handle, $count * 12) : '';
            for ($pos = 0, $end = strlen($entries) - 12; $pos <= $end; $pos += 12) {
                $entry = unpack("{$short}tag/{$short}type/{$long}count", $entries, $pos);
                if ($entry['tag'] !== 0x0112) {
                    continue;
                }
                if ($entry['count'] !== 1 || ($entry['type'] !== 3 && $entry['type'] !== 4)) {
                    return null;
                }

                return unpack($entry['type'] === 3 ? $short : $long, $entries, $pos + 8)[1];
            }

            return null;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Finds the Exif data of a JPEG, walking the markers the way jpegFrameSize() does.
     *
     * @param resource $handle Positioned behind the start of image marker
     * @return int|null Where the TIFF header of the first Exif segment starts, null without such a segment
     */
    protected static function jpegExifOffset($handle): ?int
    {
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
                // A second SOI, EOI or SOS: the segments in front of the image data have an end here.
                return null;
            }

            $length = (string) fread($handle, 2);
            $length = strlen($length) === 2 ? unpack('n', $length)[1] : 0;
            // The length counts its own two bytes.
            $length -= 2;
            if ($length < 0) {
                return null;
            }
            if ($marker === 0xE1 && $length >= 6) {
                if (fread($handle, 6) === "Exif\0\0") {
                    return ftell($handle);
                }
                $length -= 6;
            }
            if ($length > 0 && fseek($handle, $length, SEEK_CUR) !== 0) {
                return null;
            }
        }
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
     * Counts the scans of a JPEG, which is the number of passes the decoder makes over the image.
     *
     * A scan starts with the SOS marker and the length of its header, 6 bytes and 2 for
     * each of its components, and libjpeg reads none with more than 4 of them. Every
     * place in the file where that stands is counted. The decoder cannot start a scan
     * anywhere else, but not each of them is one: a thumbnail in the metadata has scans
     * of its own. So the number is the most the decoder can meet, not less.
     *
     * @param string $file
     * @param int $enough Stop reading once this many have been counted
     * @return int 0 for a file that is no JPEG
     */
    public static function jpegScanCount(string $file, int $enough = PHP_INT_MAX): int
    {
        $handle = @fopen($file, 'rb');
        if (!$handle) {
            return 0;
        }

        $count = 0;
        $tail = (string) fread($handle, 2);
        if ($tail === "\xFF\xD8") {
            while ($count < $enough && ($chunk = (string) fread($handle, 1048576)) !== '') {
                // The end of the last read comes along: a scan header may lie across two of them.
                $chunk = $tail . $chunk;
                $count += (int) preg_match_all('/\xFF\xDA\x00[\x06\x08\x0A\x0C\x0E]/', $chunk);
                $tail = substr($chunk, -3);
            }
        }
        fclose($handle);

        return $count;
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
