<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media\Traits;

use BadFunctionCallException;
use Exception;
use Grav\Common\Config\Config;
use Grav\Common\Debugger;
use Grav\Common\Grav;
use Grav\Common\Media\Interfaces\ImageMediaInterface;
use Grav\Common\Page\Medium\ImageMedium;
use Grav\Common\Utils;
use Grav\Framework\File\Formatter\JsonFormatter;
use Grav\Framework\File\JsonFile;
use Grav\Framework\Image\Adapter\GdAdapter;
use Grav\Framework\Image\Image;
use Grav\Framework\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use function func_num_args;
use function in_array;

/**
 * Trait ImageMediaTrait
 * @package Grav\Common\Media\Traits
 */
trait ImageMediaTrait
{
    /** @var string[] */
    public static array $magic_actions = [
        'resize', 'forceResize', 'cropResize', 'crop', 'zoomCrop',
        'negate', 'brightness', 'contrast', 'grayscale', 'emboss',
        'smooth', 'sharp', 'edge', 'colorize', 'sepia',
        'enableProgressive', 'rotate', 'flip', 'fixOrientation', 'gaussianBlur',
        'format', 'create', 'fill', 'merge'
    ];

    /** @var array<string,array<int>> */
    public static array $magic_resize_actions = [
        'resize' => [0, 1],
        'forceResize' => [0, 1],
        'cropResize' => [0, 1],
        'crop' => [0, 1, 2, 3],
        'zoomCrop' => [0, 1]
    ];

    protected ?Image $image = null;
    protected string $format = 'guess';
    protected int $quality = 85;
    protected int $scale = 1;
    protected bool $watermark = false;
    protected string $sizes = '100vw';

    /**
     * @return array
     * @phpstan-pure
     */
    private function serializeImageMediaTrait(): array
    {
        return [
            'image' => $this->image,
            'format' => $this->format,
            'quality' => $this->quality,
            'scale' => $this->scale,
            'watermark' => $this->watermark,
            'sizes' => $this->sizes,
        ];
    }

    /**
     * @param array $data
     * @return void
     * @phpstan-impure
     */
    private function unserializeImageMediaTrait(array $data): void
    {
        $this->image = $data['image'];
        $this->format = $data['format'];
        $this->quality = $data['quality'];
        $this->scale = $data['scale'];
        $this->watermark = $data['watermark'];
        $this->sizes = $data['sizes'];
    }

    /**
     * Creates scaled image from cache.
     *
     * NOTE: If timestamp is 0, file wasn't created.
     *
     * @param string $filepath
     * @return array
     * @phpstan-impure
     */
    protected static function createImageFromCache(string $filepath): array
    {
        [$path, $basename, $ext, $scale] = static::parseFilepath($filepath);

        $type = $ext ? Image::getImageType($ext) : null;
        if (!$type) {
            return [$path, '', 0, 0, 1, null];
        }

        // Only allow retina scale between 1 and 3.
        $scale = $scale ?? 1;
        if ($scale < 1 || $scale > 3) {
            return [$path, '', 0, 0, $scale, $ext];
        }

        // Find out if image has been registered.
        $filepath = static::findCacheMetaFile($path, $basename, $ext) ?? '';
        if (!$filepath) {
            return [$path, '', 0, 0, $scale, $ext];
        }

        // Load meta file.
        $cachepath = "{$filepath}.json";
        $file = static::getCacheMetaFile($cachepath);
        $mime = Image::getMimeType($ext);

        $data = $file->load();
        $quality = $data['extra']['quality'] ?? 80;
        $mediaUri = $data['extra']['media-uri'] ?? null;
        $debug = $data['extra']['debug'] ?? false;

        $mediaFactory = Grav::instance()['media_factory'];

        try {
            $fileData = $mediaFactory->readFile($mediaUri);
            $adapter = GdAdapter::createFromString($fileData);
            if ($scale > 1) {
                $filepath = GRAV_WEBROOT . "{$path}{$basename}@{$scale}x.{$ext}";
            }

            $image = Image::createFromArray($data);
            $image->setRetinaScale($scale);

            // If debugging is turned on, show overlay for retina scaling.
            if ($debug) {
                /** @var UniformResourceLocator $locator */
                $locator = Grav::instance()['locator'];
                $overlay = $locator->findResource("system://assets/responsive-overlays/{$scale}x.png") ?: $locator->findResource('system://assets/responsive-overlays/unknown.png');
                if ($overlay) {
                    $image_info = getimagesize($overlay);
                    if ($image_info) {
                        $info = [
                            'modified' => filemtime($overlay),
                            'size' => filesize($overlay),
                            'width' => $image_info[0],
                            'height' => $image_info[0],
                            'mime' => $image_info['mime'],
                            'scale' => $scale
                        ];

                        $overlayImage = new Image($overlay, $info);

                        $image->merge($overlayImage);
                    }
                }
            }

            $image->setAdapter($adapter);
            $filepath = $image->save($filepath, $type, $quality);
            $image->freeAdapter();

            $time = filemtime($filepath);
            $size = filesize($filepath);
        } catch (Exception $e) {
            /** @var Debugger $debugger */
            $debugger = Grav::instance()['debugger'];
            $debugger->addException($e);

            return [$filepath, $mime, 0, 0, $scale, $ext];
        }

        return [$filepath, $mime, $time, $size, $scale, $ext];
    }

    /**
     * @param string $path
     * @param string $basename
     * @param string $extension
     * @return string|null
     */
    protected static function findCacheMetaFile(string $path, string $basename, string $extension): ?string
    {
        static $map = [
            'jpg' => ['jpg', 'webp'],
            'jpe' => ['jpe', 'webp'],
            'jpeg' => ['jpeg', 'webp'],
            'webp' => ['webp', 'jpg', 'jpeg', 'jpe'],
            'png' => ['png'],
            'gif' => ['gif']
        ];

        $basepath = GRAV_WEBROOT . "{$path}{$basename}";

        $search = $map[$extension] ?? [];
        foreach ($search as $ext) {
            $filepath = "{$basepath}.{$ext}";
            $cachepath = "{$filepath}.json";
            if (is_file($cachepath)) {
                return $filepath;
            }
        }

        return null;
    }

    /**
     * @param string $path
     * @return ResponseInterface
     * @phpstan-impure
     */
    public static function createImageResponseFromCache(string $path): ResponseInterface
    {
        [$filepath, $mime, $time, $size,, $ext] = static::createImageFromCache($path);
        if ($time && is_file($filepath)) {
            $code = 200;
        } else {
            $code = 404;

            $grav = Grav::instance();

            /** @var Config $config */
            $config = $grav['config'];

            /** @var UniformResourceLocator $locator */
            $locator = $grav['locator'];

            $media_params = $ext ? $config->get('media.types.' . strtolower($ext)) : null;
            if (!$media_params) {
                $media_params = $config->get('media.types.defaults');
            }
            $thumb = $media_params['thumb'] ?? 'media/thumb.png';

            $filepath = $locator->getResource('system://images/' . $thumb);
            if (!$filepath || !is_file($filepath)) {
                return new Response($code, [], 'Not Found');
            }

            $ext = Utils::pathinfo($filepath, PATHINFO_EXTENSION);
            $mime = Image::getMimeType($ext);
            $time = filemtime($filepath);
            $size = filesize($filepath);
        }

        $body = fopen($filepath, 'rb');
        $headers = [
            'Content-Type' => $mime,
            'Last-Modified' => gmdate('D, d M Y H:i:s', $time) . ' GMT',
            'ETag' => sprintf('%x-%x', $size, $time)
        ];

        return new Response($code, $headers, $body);
    }

    /**
     * Also unset the image on destruct.
     */
    public function __destruct()
    {
        unset($this->image);
    }

    /**
     * Also clone image.
     */
    public function __clone()
    {
        if ($this->image) {
            $this->image = clone $this->image;
        }

        parent::__clone();
    }

    /**
     * @return void
     * @phpstan-impure
     */
    protected function resetImage(): void
    {
        if ($this->image) {
            $this->image();
            $this->filter();
            $this->clearAlternatives();
        }
    }

    /**
     * Allows the ability to override the image's pretty name stored in cache
     *
     * @param string $name
     * @return void
     * @phpstan-impure
     */
    public function setImagePrettyName(string $name): void
    {
        $this->set('prettyname', $name);
    }

    /**
     * @return string
     * @phpstan-pure
     */
    public function getImagePrettyName(): string
    {
        $prettyName = $this->get('prettyname');
        if ($prettyName) {
            return $prettyName;
        }

        return $this->get('basename');
    }

    /**
     * Simply processes with no extra methods.  Useful for triggering events.
     *
     * @return $this
     * @phpstan-impure
     */
    public function cache()
    {
        if (!$this->image) {
            $this->image();
        }

        return $this;
    }

    /**
     * Generate alternative image widths, using either an array of integers, or
     * a min width, a max width, and a step parameter to fill out the necessary
     * widths. Existing image alternatives won't be overwritten.
     *
     * TODO: Is this being used anywhere?
     *
     * @param  int|int[] $min_width
     * @param  int       $max_width
     * @param  int       $step
     * @return $this
     * @phpstan-impure
     */
    public function derivatives($min_width, int $max_width = 2500, int $step = 200)
    {
        // Get the largest image to be the base.
        if (empty($this->alternatives)) {
            $base = $this;
        } else {
            $max = max(array_keys($this->alternatives));
            $base = $this->alternatives[$max];
        }

        $baseWidth = $base->get('width');
        $filepath = $base->get('filepath');

        $widths = [];
        if (func_num_args() === 1) {
            foreach ((array) $min_width as $width) {
                if ($width < $baseWidth) {
                    $widths[] = (int)$width;
                }
            }
        } else {
            $max_width = min($max_width, $baseWidth);
            for ($width = $min_width; $width < $max_width; $width += $step) {
                $widths[] = (int)$width;
            }
        }

        foreach ($widths as $width) {
            // Only generate image alternatives that don't already exist
            if (isset($this->alternatives[$width])) {
                continue;
            }

            // It's possible that MediumFactory::fromFile returns null if the
            // original image file no longer exists and this class instance was
            // retrieved from the page cache
            $derivative = $this->getMedia()->createFromFile($filepath);
            if (null !== $derivative) {
                $basename = preg_replace('/(@\d+x)?$/', "@{$width}w", $base->get('basename'), 1);
                $derivative->setImagePrettyName($basename);

                $ratio = $baseWidth / $width;
                $height = $derivative->get('height') / $ratio;

                $derivative->resize($width, $height);
                $derivative->set('width', $width);
                $derivative->set('height', $height);

                $this->addAlternative($ratio, $derivative);
            }
        }

        return $this;
    }

    /**
     * Clear out the alternatives.
     *
     * TODO: It's better just to get a new clone of the image from the media collection instead.
     *
     * @return void
     * @phpstan-impure
     */
    public function clearAlternatives(): void
    {
        $this->alternatives = [];
    }

    /**
     * Sets or gets the quality of the image
     *
     * @param  int|null $quality 0-100 quality
     * @return int|$this
     * @phpstan-impure
     */
    public function quality(int $quality = null)
    {
        if ($quality) {
            if (!$this->image) {
                $this->image();
            }

            $this->quality = $quality;

            return $this;
        }

        return $this->quality;
    }

    /**
     * Sets image output format.
     *
     * @param string $format
     * @return $this
     * @phpstan-impure
     */
    public function format(string $format)
    {
        if (!$this->image) {
            $this->image();
        }

        $this->format = $format;

        return $this;
    }

    /**
     * Set or get sizes parameter for srcset media action
     *
     * @param  string|null $sizes
     * @return string|$this
     * @phpstan-impure
     */
    public function sizes(string $sizes = null)
    {
        if ($sizes) {
            $this->sizes = $sizes;

            return $this;
        }

        return empty($this->sizes) ? '100vw' : $this->sizes;
    }

    /**
     * Allows to set the width attribute from Markdown or Twig
     * Examples: ![Example](myimg.png?width=200&height=400)
     *           ![Example](myimg.png?resize=100,200&width=100&height=200)
     *           ![Example](myimg.png?width=auto&height=auto)
     *           ![Example](myimg.png?width&height)
     *           {{ page.media['myimg.png'].width().height().html }}
     *           {{ page.media['myimg.png'].resize(100,200).width(100).height(200).html }}
     *
     * @param string|int $value A value or 'auto' or empty to use the width of the image
     * @return $this
     * @phpstan-impure
     */
    public function width($value = 'auto')
    {
        if (!$value || $value === 'auto') {
            $this->attributes['width'] = null;
        } else {
            $this->attributes['width'] = $value;
        }

        return $this;
    }

    /**
     * Allows to set the height attribute from Markdown or Twig
     * Examples: ![Example](myimg.png?width=200&height=400)
     *           ![Example](myimg.png?resize=100,200&width=100&height=200)
     *           ![Example](myimg.png?width=auto&height=auto)
     *           ![Example](myimg.png?width&height)
     *           {{ page.media['myimg.png'].width().height().html }}
     *           {{ page.media['myimg.png'].resize(100,200).width(100).height(200).html }}
     *
     * @param string|int $value A value or 'auto' or empty to use the height of the image
     * @return $this
     * @phpstan-impure
     */
    public function height($value = 'auto')
    {
        if (!$value || $value === 'auto') {
            $this->attributes['height'] = null;
        } else {
            $this->attributes['height'] = $value;
        }

        return $this;
    }

    /**
     * Filter image by using user defined filter parameters.
     *
     * @param string $filter Filter to be used.
     * @return $this
     * @phpstan-impure
     */
    public function filter(string $filter = 'image.filters.default')
    {
        $filters = (array)$this->get($filter, []);
        foreach ($filters as $params) {
            $params = (array)$params;
            $method = array_shift($params);
            $this->__call($method, $params);
        }

        return $this;
    }

    /**
     * Return the image higher quality version
     *
     * @return ImageMediaInterface|$this the alternative version with higher quality
     * @phpstan-pure
     */
    public function higherQualityAlternative(): ImageMediaInterface
    {
        if ($this->alternatives) {
            /** @var ImageMedium $max */
            $max = reset($this->alternatives);
            /** @var ImageMedium $alternative */
            foreach ($this->alternatives as $alternative) {
                // FIXME: this makes no sense. We cannot know the quality from the images unless they were cached.
                if ($alternative->quality() > $max->quality()) {
                    $max = $alternative;
                }
            }

            return $max;
        }

        return $this;
    }

    /**
     * Handle this commonly used variant
     *
     * @param mixed $args
     * @return $this
     * @phpstan-impure
     */
    public function cropZoom(...$args)
    {
        return $this->zoomCrop(...$args);
    }

    /**
     * @param string|null $file
     * @param string|null $position
     * @param float|null $scale
     * @return $this
     * @phpstan-impure
     */
    public function watermark(string $file = null, string $position = null, float $scale = null)
    {
        $grav = $this->getGrav();

        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];
        /** @var Config $config */
        $config = $grav['config'];

        if ($file === '1') { // ![](image.jpg?watermark) returns $image='1';
            $file = null;
        }
        $file = $file ?? $config->get('system.images.watermark.image', 'system://images/watermark.png');

        $watermark = $locator->isStream($file) ? $locator->findResource($file) : GRAV_WEBROOT . '/' . $file;
        $image_info = $watermark ? getimagesize($watermark) : false;
        if (!$image_info) {
            return $this;
        }

        $scale = ($scale ?? (float)$config->get('system.images.watermark.scale', 33)) / 100;

        $info = [
            'modified' => filemtime($watermark),
            'size' => filesize($watermark),
            'width' => $image_info[0],
            'height' => $image_info[0],
            'mime' => $image_info['mime']
        ];

        $watermarkImage = new Image($watermark, $info);

        if (null === $this->image) {
            $this->image();
        }

        $width = $this->image->width();
        $height = $this->image->width();

        // Position operations
        $positionParts = strpos($position, '-') ? explode('-',  $position, 2) : [];
        $positionY = $positionParts[0] ?? $config->get('system.images.watermark.position_y', 'center');
        $positionX = $positionParts[1] ?? $config->get('system.images.watermark.position_x', 'center');

        // Scaling operations
        $scaledWidth    = (int)($width * $scale);
        $scaledHeight   = (int)($height * $scale);

        switch ($positionY) {
            case 'top':
                $positionY = 0;
                break;
            case 'bottom':
                $positionY = $height - $scaledHeight;
                break;
            case 'center':
            default:
                $positionY = (int)(($height - $scaledHeight) / 2);
                break;
        }

        switch ($positionX) {
            case 'left':
                $positionX = 0;
                break;
            case 'right':
                $positionX = $width - $scaledWidth;
                break;
            case 'center':
            default:
                $positionX = (int)(($width - $scaledWidth) / 2);
                break;
        }

        $this->image->merge($watermarkImage, $positionX, $positionY, $scaledWidth, $scaledHeight);

        // Do not apply watermark more than once.
        $this->watermark = false;

        return $this;
    }

    /**
     * Add a frame to image
     *
     * FIXME: not working yet.
     *
     * @return $this
     * @phpstan-impure
     */
    public function addFrame(int $border = 10, string $color = '0x000000')
    {
        if (null === $this->image) {
            $this->image();
        }

        $image = $this->image;

        $dst_width = $image->width()+2*$border;
        $dst_height = $image->height()+2*$border;

        $info = [
            'width' => $dst_width,
            'height' => $dst_height,
        ];

        // Fixme
        $frame = new Image('', $info);
        $frame->fill($color);

        $this->image = $frame;

        $this->merge($image, $border, $border);

        return $this;
    }

    /**
     * @param string $method
     * @return bool
     */
    public function isAction(string $method): bool
    {
        return in_array($method, static::$magic_actions, true) || parent::isAction($method);
    }

    /**
     * Forward the call to the image processing method.
     *
     * @param string $method
     * @param array $args
     * @return mixed
     * @phpstan-impure
     */
    public function __call(string $method, array $args)
    {
        if (!in_array($method, static::$magic_actions, true)) {
            return null;
        }

        // Always initialize image.
        if (!$this->image) {
            $this->image();
        }

        try {
            $this->image->{$method}(...$args);

            /** @var ImageMediaInterface $medium */
            foreach ($this->alternatives as $medium) {
                $args_copy = $args;

                // regular image: resize 400x400 -> 200x200
                // --> @2x: resize 800x800->400x400
                if (isset(static::$magic_resize_actions[$method])) {
                    foreach (static::$magic_resize_actions[$method] as $param) {
                        if (isset($args_copy[$param])) {
                            $args_copy[$param] *= $medium->get('ratio');
                        }
                    }
                }

                // Do the same call for alternative media.
                $medium->{$method}(...$args_copy);
            }
        } catch (BadFunctionCallException $e) {
        }

        return $this;
    }

    /**
     * Gets medium image, resets image manipulation operations.
     *
     * @return $this
     * @phpstan-impure
     */
    protected function image()
    {
        $filepath = $this->filepath;
        $webroot = preg_quote(GRAV_WEBROOT, '`');
        $root = preg_quote(GRAV_ROOT, '`');
        $filepath = preg_replace(['`^' . $webroot . '/`u', '`^' . $root . '/`u'], ['GRAV_WEBROOT/', 'GRAV_ROOT/'], $filepath);

        // Create a new image.
        $this->image = new Image($filepath, $this->getItems());
        $this->image->fixOrientation();
        $this->undef('url');

        // Cached media doesn't need timestamps.
        $this->timestamp = null;

        return $this;
    }

    /**
     * Save the image with cache.
     *
     * @return string
     * @phpstan-impure
     */
    protected function saveImage(): string
    {
        if (!$this->image) {
            return parent::path(false);
        }

        $this->filter();

        if (isset($this->result)) {
            return $this->result;
        }

        if ($this->watermark) {
            $this->watermark();
        }

        return $this->generateCache();
    }

    /**
     * @return string
     * @phpstan-impure
     */
    protected function generateCache(): string
    {
        $quality = $this->quality;
        $format = $this->format;
        if ($format === 'guess') {
            $extension = strtolower($this->get('extension'));
            $format = $extension;
        }

        $image = $this->image;

        $mediaUri = $this->getMedia()->getMediaUri($this->filename);
        if (null === $mediaUri) {
            $mediaUri = 'media-local://' . str_replace('GRAV_WEBROOT', '', $image->getFilepath());
        }

        $image->extra['format'] = $format;
        $image->extra['quality'] = $quality;
        $image->extra['media-uri'] = $mediaUri;
        $image->extra['mime'] = $this->mime;
        $image->extra['debug'] = $this->debug;

        $data = $image->jsonSerialize();
        $hash = $data['hash'];
        $d1 = substr($hash, 0, 2);
        $d2 = substr($hash, 2, 2);
        $d3 = substr($hash, 4);
        $prettyName = $this->getImagePrettyName();
        $basename = preg_replace('/(@\d+x)?$/', '', $prettyName, 1);

        $imageFile = "cache://images/{$d1}/{$d2}/{$d3}/{$prettyName}.{$format}";
        $cacheFile = "cache://images/{$d1}/{$d2}/{$d3}/{$basename}.{$format}.json";

        /** @var UniformResourceLocator $locator */
        $locator = $this->getGrav()['locator'];
        $imageFile = '/' . $locator->getResource($imageFile, false);
        $cacheFile = $locator->getResource($cacheFile);

        $file = static::getCacheMetaFile($cacheFile);
        if (!$file->exists()) {
            $file->save($data);
        } else {
            $file->touch();
        }

        return GRAV_WEBROOT . $imageFile;
    }

    /**
     * @param string $filepath
     * @return JsonFile
     * @phpstan-pure
     */
    protected static function getCacheMetaFile(string $filepath): JsonFile
    {
        $formatter = new JsonFormatter(['encode_options' => JSON_PRETTY_PRINT]);

        return new JsonFile($filepath, $formatter);
    }

    /**
     * @param string $path
     * @return array|null
     * @phpstan-pure
     */
    protected static function parseFilepath(string $path): ?array
    {
        if (!preg_match('{^(.*)?([^/]+)(?:@(\d+)x)?\.([^.]+)$}Uu', $path, $matches)) {
            return null;
        }

        $scale = '' !== $matches[3] ? (int)$matches[3] : null;

        return [$matches[1], $matches[2], $matches[4], $scale];
    }
}
