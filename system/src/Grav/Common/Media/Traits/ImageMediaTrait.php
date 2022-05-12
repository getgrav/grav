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
use Grav\Framework\File\Formatter\JsonFormatter;
use Grav\Framework\File\JsonFile;
use Grav\Framework\Image\Adapter\GdAdapter;
use Grav\Framework\Image\Image;
use Grav\Framework\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RuntimeException;
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

    /** @var array */
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
        $this->watermark = $data['watermark'];
        $this->sizes = $data['sizes'];
    }

    /**
     * Creates scaled image from cache.
     *
     * NOTE: If timestamp is 0, file wasn't created.
     *
     * @param string $path
     * @return array
     * @phpstan-impure
     */
    protected static function createImageFromCache(string $path): array
    {
        // Find out if browser wants a larger image.
        if (!preg_match('/(.*)(?:@(\d+)x)?\.(.*)$/Uu', $path, $matches)) {
            return [$path, '', 0, 0, 1, null];
        }

        [,$basepath,$scale,$ext] = $matches;

        // Prevent bad retina scales.
        $scale = $scale !== '' ? (int)$scale : 1;
        if ($scale < 1 || $scale > 3) {
            return [$path, '', 0, 0, $scale, $ext];
        }

        $filepath = GRAV_WEBROOT . $path;
        $cachepath = "{$filepath}.json";
        $file = static::getCacheMetaFile($cachepath);
        if (!$file->exists()) {
            $filepath = GRAV_WEBROOT . $basepath . '.' . $ext;
            $cachepath = "{$filepath}.json";
            $file = static::getCacheMetaFile($cachepath);
            if (!$file->exists()) {
                return [$path, '', 0, 0, $scale, $ext];
            }
        }

        $data = $file->load();
        $format = $data['extra']['format'] ?? 'jpg';
        $mime = $data['extra']['mime'] ?? '';
        $quality = $data['extra']['quality'] ?? 80;
        $mediaUri = $data['extra']['media-uri'] ?? null;

        $mediaFactory = Grav::instance()['media_factory'];

        try {
            $fileData = $mediaFactory->readFile($mediaUri);
            $adapter = GdAdapter::createFromString($fileData);
            if ($scale > 1) {
                $filepath = GRAV_WEBROOT . "{$basepath}@{$scale}x.{$ext}";

                $adapter->setRetinaScale($scale);
            }

            $image = Image::createFromArray($data);

            $image->setAdapter($adapter);
            $filepath = $image->save($filepath, $format, $quality);
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

            $mime = 'image/png';
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

        $basename = $this->get('basename');
        if (preg_match('/[a-z0-9]{40}-(.*)/', $basename, $matches)) {
            $basename = $matches[1];
        }

        return $basename;
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
            $this->attributes['width'] = $this->get('width');
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
            $this->attributes['height'] = $this->get('height');
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
     * @param string|null $image
     * @param string|null $position
     * @param int|float|null $scale
     * @return $this
     * @phpstan-impure
     */
    public function watermark(string $image = null, string $position = null, $scale = null)
    {
        $grav = $this->getGrav();

        $locator = $grav['locator'];
        $config = $grav['config'];

        $args = func_get_args();

        $file = $args[0] ?? '1'; // using '1' because of markdown. doing ![](image.jpg?watermark) returns $args[0]='1';
        $file = $file === '1' ? $config->get('system.images.watermark.image') : $args[0];

        $watermark = $locator->findResource($file);
        $image_info = $watermark ? getimagesize($watermark) : false;
        if (!$image_info) {
            return $this;
        }

        $info = [
            'modified' => filemtime($watermark),
            'size' => filesize($watermark),
            'width' => $image_info[0],
            'height' => $image_info[0],
            'mime' => $image_info['mime']
        ];

        $watermark = new Image($watermark, $info);

        $this->image->merge($watermark);

        // Scaling operations
        $scale     = ($scale ?? $config->get('system.images.watermark.scale', 100)) / 100;
        $wwidth    = $this->get('width')  * $scale;
        $wheight   = $this->get('height') * $scale;
        $watermark->resize($wwidth, $wheight);

        // Position operations
        $position = !empty($args[1]) ? explode('-',  $args[1]) : ['center', 'center']; // todo change to config
        $positionY = $position[0] ?? $config->get('system.images.watermark.position_y', 'center');
        $positionX = $position[1] ?? $config->get('system.images.watermark.position_x', 'center');

        switch ($positionY) {
            case 'top':
                $positionY = 0;
                break;

            case 'bottom':
                $positionY = $this->get('height') - $wheight;
                break;

            case 'center':
                $positionY = ($this->get('height')/2) - ($wheight/2);
                break;
        }

        switch ($positionX) {
            case 'left':
                $positionX = 0;
                break;

            case 'right':
                $positionX = $this->get('width') - $wwidth;
                break;

            case 'center':
                $positionX = ($this->get('width')/2) - ($wwidth/2);
                break;
        }

        $this->merge($watermark, $positionX, $positionY);

        return $this;
    }

    /**
     * Add a frame to image
     *
     * @return $this
     * @phpstan-impure
     */
    public function addFrame(int $border = 10, string $color = '0x000000')
    {
        // TODO:
        /*
        if( $border > 0 && preg_match('/^0x[a-f0-9]{6}$/i', $color)) { // $border must be an integer and bigger than 0; $color must be formatted as an HEX value (0x??????).
            $image = ImageFile::fromData($this->readFile());
        }
        else {
            return $this;
        }

        $dst_width = $image->width()+2*$border;
        $dst_height = $image->height()+2*$border;

        $frame = ImageFile::create($dst_width, $dst_height);

        $frame->fill($color);

        $this->image = $frame;

        $this->merge($image, $border, $border);

        $this->saveImage();
        */

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
        $root = preg_quote(GRAV_WEBROOT, '`');
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

        if ($this->get('debug')) {
            $ratio = min(1, (int)$this->get('ratio'));

            /** @var UniformResourceLocator $locator */
            $locator = Grav::instance()['locator'];
            $overlay = $locator->findResource("system://assets/responsive-overlays/{$ratio}x.png") ?: $locator->findResource('system://assets/responsive-overlays/unknown.png');
            if ($overlay) {
                $image_info = getimagesize($overlay);
                if ($image_info) {
                    $info = [
                        'modified' => filemtime($overlay),
                        'size' => filesize($overlay),
                        'width' => $image_info[0],
                        'height' => $image_info[0],
                        'mime' => $image_info['mime']
                    ];

                    $overlayImage = new Image($overlay, $info);

                    $this->image->merge($overlayImage);
                }
            }
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

        $data = $image->jsonSerialize();
        $hash = $data['hash'];
        $d1 = substr($hash, 0, 2);
        $d2 = substr($hash, 2, 2);
        $d3 = substr($hash, 4);
        $prettyName = $this->getImagePrettyName();

        $imageFile = "cache://images/{$d1}/{$d2}/{$d3}/{$prettyName}.{$format}";
        $cacheFile = "{$imageFile}.json";

        /** @var UniformResourceLocator $locator */
        $locator = $this->getGrav()['locator'];
        $imageFile = '/' . $locator->getResource($imageFile, false);
        $cacheFile = $locator->getResource($cacheFile, true);

        $file = static::getCacheMetaFile($cacheFile);
        if (!$file->exists()) {
            $file->save($data);
        } else {
            $file->touch();
        }

        return GRAV_WEBROOT . $imageFile;
        //return $this->generateCacheImage(GRAV_WEBROOT . $imageFile);
    }

    /**
     * @param string $filepath
     * @return string
     * @phpstan-impure
     */
    protected function generateCacheImage(string $filepath): string
    {
        if (file_exists($filepath)) {
            return $filepath;
        }

        try {
            $fileData = $this->readFile();
            $adapter = GdAdapter::createFromString($fileData);

            $image = $this->image;
            $image->setAdapter($adapter);
            $image->save($filepath, 'jpg', $this->quality);
            $image->freeAdapter();

        } catch (RuntimeException $e) {
            /** @var Debugger $debugger */
            $debugger = Grav::instance()['debugger'];
            $debugger->addMessage(sprintf('Could not generate resized image for %s: %s', $this->filename, $e->getMessage()), 'warning');

            // FIXME: Fallback image?
            return '';
        }

        return $filepath;
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
}
