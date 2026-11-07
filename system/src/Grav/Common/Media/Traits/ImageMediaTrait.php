<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media\Traits;

use Grav\Common\Grav;
use Grav\Common\Media\Interfaces\ImageManipulateInterface;
use Grav\Common\Media\Interfaces\ImageMediaInterface;
use Grav\Common\Media\Interfaces\MediaCollectionInterface;
use Grav\Common\Page\Medium\ImageFile;
use Grav\Common\Page\Medium\ImageMedium;
use Grav\Common\Page\Medium\MediumFactory;
use function array_key_exists;
use function extension_loaded;
use function func_num_args;
use function function_exists;
use function in_array;
use function is_string;

/**
 * Trait ImageMediaTrait
 * @package Grav\Common\Media\Traits
 */
trait ImageMediaTrait
{
    /** @var ImageFile|null */
    protected $image;

    /** @var string */
    protected $format = 'guess';

    /** @var int */
    protected $quality;

    /** @var int */
    protected $default_quality;

    /** @var bool */
    protected $debug_watermarked = false;

    /** @var bool  */
    protected $auto_sizes;

    /** @var bool */
    protected $aspect_ratio;

    /** @var integer */
    protected $retina_scale;

    /** @var bool */
    protected $watermark;

    /** @var array|null The watermark() arguments queued on this image, if any */
    protected $watermarked;

    /** @var bool */
    protected $progressive;

    /** @var bool Whether anything is queued that changes the image's pixels, format or quality */
    protected $transformed = false;

    /** @var array{0:int,1:int}|null As an alternative, the size the resize actions queued so far leave this image at */
    protected $queued_size;

    /** @var array<string, array{0:int,1:int}|null> Source dimensions by path, cached for the request */
    protected static $sourceSizeCache = [];

    /** @var array */
    public static $magic_actions = [
        'resize', 'forceResize', 'cropResize', 'crop', 'zoomCrop',
        'negate', 'brightness', 'contrast', 'grayscale', 'emboss',
        'smooth', 'sharp', 'edge', 'colorize', 'sepia', 'enableProgressive',
        'rotate', 'flip', 'fixOrientation', 'gaussianBlur', 'format', 'create', 'fill', 'merge'
    ];

    /** @var array */
    public static $magic_resize_actions = [
        'resize' => [0, 1],
        'forceResize' => [0, 1],
        'cropResize' => [0, 1],
        'crop' => [0, 1, 2, 3],
        'zoomCrop' => [0, 1]
    ];

    /** @var string */
    protected $sizes = '100vw';

    /**
     * The canvas a URL resize action allocates, and the size it leaves the image at.
     *
     * The query-string numbers are not the canvas. A single dimension or a
     * percentage is derived from the current aspect ratio (`forceResize=46000`
     * on a square source is 46000x46000), and zoomCrop() enlarges to cover the
     * box before it crops (`zoomCrop=46000,1` also builds 46000x46000), so a
     * ceiling on width*height of the request lets both through.
     *
     * Floats, so an absurd request cannot overflow an int. Returns null when a
     * dimension is not a whole number or a percentage.
     *
     * @param string $action One of $magic_resize_actions
     * @param array $args The comma-separated query arguments
     * @param int $width Current image width
     * @param int $height Current image height
     * @return array{0:float,1:float,2:float}|null [canvas pixels, resulting width, resulting height]
     */
    public static function urlResizeCanvas(string $action, array $args, int $width, int $height): ?array
    {
        $positions = static::$magic_resize_actions[$action] ?? null;
        if ($positions === null || $width < 1 || $height < 1) {
            return null;
        }

        $count = count($positions);
        $w = $args[$positions[$count - 2]] ?? null;
        $h = $args[$positions[$count - 1]] ?? null;

        if ($h === null && preg_match('/^(\d+(?:\.\d+)?)%$/', (string) $w, $matches)) {
            // Whole pixels, rounded up: the canvas is never smaller than the fraction.
            $w = ceil($width * (float) $matches[1] / 100);
            $h = ceil($height * (float) $matches[1] / 100);
        } else {
            foreach ([$w, $h] as $value) {
                if ($value !== null && !ctype_digit((string) $value)) {
                    return null;
                }
            }
            $w = (float) $w;
            $h = (float) $h;
        }

        if ($w <= 0 && $h <= 0) {
            [$w, $h] = [(float) $width, (float) $height];
        } elseif ($h <= 0) {
            $h = ceil($w * $height / $width);
        } elseif ($w <= 0) {
            $w = ceil($h * $width / $height);
        }

        $pixels = $w * $h;
        if ($action === 'zoomCrop') {
            $scale = max($w / $width, $h / $height);
            $pixels = max($pixels, ceil($width * $scale) * ceil($height * $scale));
        }

        return [$pixels, $w, $h];
    }

    /**
     * The largest canvas any image action allocates, and the size it leaves the image at.
     *
     * Resizes are measured by urlResizeCanvas(). rotate() builds the box around
     * the turned image, so a quarter turn swaps the sides that a later
     * one-dimension resize derives the other from. GD's gaussianBlur() halves
     * the image once per step, rounding up, then doubles it back: once a side
     * is down to one pixel each further step only doubles the canvas, so
     * `gaussianBlur=14` ends at 8192x8192 whatever the source. Everything else
     * works on the image as it is.
     *
     * @param string $action One of $magic_actions
     * @param array $args The comma-separated query arguments
     * @param int $width Current image width
     * @param int $height Current image height
     * @return array{0:float,1:float,2:float}|null [canvas pixels, resulting width, resulting height], null when an argument that sizes the canvas is not a usable number
     */
    public static function urlActionCanvas(string $action, array $args, int $width, int $height): ?array
    {
        if (isset(static::$magic_resize_actions[$action])) {
            return static::urlResizeCanvas($action, $args, $width, $height);
        }

        if ($action === 'rotate') {
            $angle = $args[0] ?? null;
            if (!is_numeric($angle) || !is_finite((float) $angle)) {
                return null;
            }

            // Rounded, so that the cosine of a right angle is zero.
            $cos = abs(round(cos(deg2rad((float) $angle)), 9));
            $sin = abs(round(sin(deg2rad((float) $angle)), 9));
            $w = ceil($width * $cos + $height * $sin);
            $h = ceil($width * $sin + $height * $cos);

            return [$w * $h, $w, $h];
        }

        if ($action === 'gaussianBlur') {
            $steps = $args[0] ?? 1;
            if (!ctype_digit((string) $steps) || (int) $steps < 1) {
                return null;
            }

            // No image outlasts 64 halvings, and the powers stay finite.
            $steps = min((int) $steps, 64);
            $grown = 2.0 ** ($steps - 1);
            $pixels = ceil($width * 0.5 ** $steps) * $grown * ceil($height * 0.5 ** $steps) * $grown;

            return [$pixels, (float) $width, (float) $height];
        }

        return [(float) $width * $height, (float) $width, (float) $height];
    }

    /**
     * Allows the ability to override the image's pretty name stored in cache
     *
     * @param string $name
     */
    public function setImagePrettyName($name)
    {
        $this->set('prettyname', $name);
        if ($this->image) {
            $this->image->setPrettyName($name);
        }
    }

    /**
     * @return string
     */
    public function getImagePrettyName()
    {
        if ($this->get('prettyname')) {
            return $this->get('prettyname');
        }

        $basename = $this->get('basename');
        if (preg_match('/[a-z0-9]{40}-(.*)/', (string) $basename, $matches)) {
            $basename = $matches[1];
        }
        return $basename;
    }

    /**
     * Simply processes with no extra methods.  Useful for triggering events.
     *
     * @return $this
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
     * widths. Existing image alternatives won't be overwritten. Widths and steps
     * below one are ignored.
     *
     * @param  int|int[] $min_width
     * @param  int       $max_width
     * @param  int       $step
     * @return $this
     */
    public function derivatives($min_width, $max_width = 2500, $step = 200)
    {
        // Resize from the largest real image: this one or a retina file. The
        // derivatives from an earlier call are narrower than the image they
        // were made from, and their width is the one they will be resized to,
        // not the width of their file, which is the full-size source.
        // getgrav/grav#4389.
        $base = $this;
        foreach ($this->alternatives as $width => $alternative) {
            if ($width > $base->get('width')) {
                $base = $alternative;
            }
        }

        $widths = [];

        if (func_num_args() === 1) {
            foreach ((array) func_get_arg(0) as $width) {
                $width = (int) $width;
                if ($width > 0 && $width < $base->get('width')) {
                    $widths[] = $width;
                }
            }
        } else {
            // A Markdown image URL passes whatever stands between the commas as
            // strings. The count has to start at a width that exists and go up,
            // or the loop never ends (`?derivatives=400,400,0`).
            $min_width = (int) $min_width;
            $max_width = min((int) $max_width, $base->get('width'));
            $step = (int) $step;
            if ($min_width < 1 || $step < 1) {
                return $this;
            }

            for ($width = $min_width; $width <= $max_width; $width += $step) {
                if ($width >= $base->get('width')) {
                    continue;
                }
                $widths[] = $width;
            }
        }

        foreach ($widths as $width) {
            // Only generate image alternatives that don't already exist
            if (array_key_exists((int) $width, $this->alternatives)) {
                continue;
            }

            $derivative = MediumFactory::fromFile($base->get('filepath'));

            // It's possible that MediumFactory::fromFile returns null if the
            // original image file no longer exists and this class instance was
            // retrieved from the page cache
            if (null !== $derivative) {
                $index = 2;
                $alt_widths = array_keys($this->alternatives);
                sort($alt_widths);

                foreach ($alt_widths as $i => $key) {
                    if ($width > $key) {
                        $index += max($i, 1);
                    }
                }

                $basename = preg_replace('/(@\d+x)?$/', "@{$width}w", $base->get('basename'), 1);
                $derivative->setImagePrettyName($basename);

                // Width and height both from the file being resized.
                $ratio = $derivative->get('width') / $width;
                $height = $derivative->get('height') / $ratio;

                $derivative->resize($width, $height);
                $derivative->set('width', $width);
                $derivative->set('height', $height);

                // A derivative is this image at another width, so it takes the
                // format and quality already set here. Without this the result
                // depended on the order the actions arrived in: format() only
                // reaches the alternatives that exist when it is called.
                // getgrav/grav#4317.
                if ($derivative instanceof ImageManipulateInterface) {
                    if ($this->format !== 'guess') {
                        $derivative->format($this->format);
                    }
                    $derivative->quality($this->quality);
                }

                // Same for a watermark asked for before the derivatives were.
                if ($this->watermarked && method_exists($derivative, 'queueWatermark')) {
                    $derivative->queueWatermark($this->watermarked);
                }

                // And for the URL params that came in before them: querystring()
                // fans each new param out to the alternatives that exist when it
                // is called, nothing else.
                if ($this->medium_querystring) {
                    $derivative->querystring($this->querystring(null, false));
                }

                // An alternative's ratio is its width over this image's, as for
                // a retina file (`@2x` is 2): a resize on this image is scaled by
                // it for each alternative. The ratio above is the inverse, the
                // source over the derivative, and made a derivative grow when this
                // image shrank (cropZoom(300, 300) turned the 400w one 891 wide).
                $this->addAlternative($width / ($this->get('width') ?: $derivative->get('width')), $derivative);
            }
        }

        return $this;
    }

    /**
     * Queues a watermark on this image and on each alternative. The stamp is
     * sized and placed when the image is processed, so every image is stamped
     * at its own final size.
     *
     * @param array $args [stamp image, position, scale from 0 to 1]
     * @return void
     */
    protected function queueWatermark(array $args)
    {
        if (!$this->image) {
            $this->image();
        }

        $this->transformed = true;
        $this->watermarked = $args;
        $this->image->watermark(...$args);

        foreach ($this->alternatives as $medium) {
            if (method_exists($medium, 'queueWatermark')) {
                $medium->queueWatermark($args);
            }
        }
    }

    /**
     * Clear out the alternatives.
     */
    public function clearAlternatives()
    {
        $this->alternatives = [];
    }

    /**
     * Sets or gets the quality of the image. Setting it reaches every
     * alternative too, so a srcset is encoded at one quality throughout.
     *
     * @param  int|null $quality 0-100 quality
     * @return int|$this
     */
    public function quality($quality = null)
    {
        if ($quality) {
            if (!$this->image) {
                $this->image();
            }

            $this->transformed = true;
            $this->quality = $quality;

            // The magic actions fan out to the alternatives from __call(), which
            // a declared method never passes through, so it is done here.
            // getgrav/grav#4317.
            foreach ($this->alternatives as $medium) {
                if ($medium instanceof ImageManipulateInterface) {
                    $medium->quality($quality);
                }
            }

            return $this;
        }

        return $this->quality;
    }

    /**
     * Sets image output format, for the image and every alternative, so a
     * srcset is served in one format throughout.
     *
     * @param string $format
     * @return $this
     */
    public function format($format)
    {
        // The format ends up as the extension of the cached file, and it comes
        // from markdown, page Twig and, with `images.url_actions`, the request
        // URL. Anything that is not an output type is ignored.
        if (!is_string($format)) {
            return $this;
        }
        $format = strtolower($format);
        if ($format !== 'guess' && !isset(ImageFile::$types[$format])) {
            return $this;
        }

        if (!$this->image) {
            $this->image();
        }

        $this->transformed = true;
        $this->format = $format;

        // As in quality(): __call() does the fan-out for the magic actions and
        // never sees a declared method. getgrav/grav#4317.
        foreach ($this->alternatives as $medium) {
            if ($medium instanceof ImageManipulateInterface) {
                $medium->format($format);
            }
        }

        return $this;
    }

    /**
     * Holds the actions queued on the open image to a total-pixel ceiling, each
     * measured as it runs against the size the image really has by then.
     * Processing the image throws a RangeException when one is above it.
     *
     * @param int $pixels 0 for no limit
     * @return $this
     */
    public function maxPixels(int $pixels)
    {
        if ($this->image) {
            $this->image->setMaxPixels($pixels);
        }

        return $this;
    }

    /**
     * Set or get sizes parameter for srcset media action
     *
     * @param  string|null $sizes
     * @return string
     */
    public function sizes($sizes = null)
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
     */
    public function filter($filter = 'image.filters.default')
    {
        $filters = (array) $this->get($filter, []);
        foreach ($filters as $params) {
            $params = (array) $params;
            $method = array_shift($params);
            $this->__call($method, $params);
        }

        return $this;
    }

    /**
     * Return the image higher quality version
     *
     * @return ImageMediaInterface|$this the alternative version with higher quality
     */
    public function higherQualityAlternative()
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
     * Gets medium image, resets image manipulation operations.
     *
     * @return $this
     */
    protected function image()
    {
        $locator = Grav::instance()['locator'];

        // Use existing cache folder or if it doesn't exist, create it.
        $cacheDir = $locator->findResource('cache://images', true) ?: $locator->findResource('cache://images', true, true);

        // Make sure we free previous image. Assign null rather than unset(): unset()
        // removes the declared property, and every later write then falls through to
        // Data's __set() and lands in $items['image'], overwriting the media type's
        // own `image` settings with the ImageFile object. That is what silently
        // killed the default filters in 1.4.6. getgrav/grav#4284.
        $this->image = null;

        /** @var MediaCollectionInterface $media */
        $media = $this->get('media');
        if ($media && method_exists($media, 'getImageFileObject')) {
            $this->image = $media->getImageFileObject($this);
        } else {
            $this->image = ImageFile::open($this->get('filepath'));
        }

        $this->image
            ->setCacheDir($cacheDir)
            ->setActualCacheDir($cacheDir)
            ->setPrettyName($this->getImagePrettyName());

        // Fix orientation if enabled
        $config = Grav::instance()['config'];
        if ($config->get('system.images.auto_fix_orientation', false) &&
            extension_loaded('exif') && function_exists('exif_read_data')) {
            $this->image->fixOrientation();
        }

        // Set CLS configuration
        $this->auto_sizes = $config->get('system.images.cls.auto_sizes', false);
        $this->aspect_ratio = $config->get('system.images.cls.aspect_ratio', false);
        $this->retina_scale = $config->get('system.images.cls.retina_scale', 1);

        $this->watermark = $config->get('system.images.watermark.watermark_all', false);
        $this->progressive = $config->get('system.images.progressive_jpeg', true);

        return $this;
    }

    /**
     * Whether the source file is itself an output of saveImage() in the image cache.
     *
     * @param string $file
     * @return bool
     */
    protected function isProcessedCopy(string $file): bool
    {
        $cacheDir = Grav::instance()['locator']->findResource('cache://images', true);

        return $cacheDir && str_starts_with($file, rtrim((string) $cacheDir, '/') . '/');
    }

    /**
     * Save the image with cache.
     *
     * @return string
     */
    protected function saveImage()
    {
        if (!$this->image) {
            if (!Grav::instance()['config']->get('system.images.watermark.watermark_all', false)) {
                return parent::path(false);
            }

            // `watermark_all` is applied below, so an image with nothing queued
            // on it still has to be opened and saved. It is closed again after,
            // leaving the medium the untouched original it was: reset() would
            // otherwise treat it as processed and drop its retina alternatives.
            // getgrav/grav#4359.
            $this->image();
            try {
                return $this->saveImage();
            } finally {
                $this->image = null;
                $this->watermarked = null;
                $this->transformed = false;
            }
        }

        // Refuse oversized source rasters before getgrav/image initializes its
        // adapter. GD and Imagick decode the full source before applying a resize,
        // so limiting only the requested output dimensions does not bound memory.
        //
        // This has to read the file rather than trust the `width`/`height` meta:
        // derivatives() overwrites those with the requested output size while
        // `filepath` still points at the full-size source. One srcset can put many
        // derivatives of the same source through here, so the reads are cached for
        // the request.
        $maxPixels = (int) Grav::instance()['config']->get('system.images.max_pixels', 25000000);
        $sourcePath = (string) $this->get('filepath');
        if (!array_key_exists($sourcePath, static::$sourceSizeCache)) {
            static::$sourceSizeCache[$sourcePath] = ImageFile::decodedSize($sourcePath);
        }
        [$width, $height] = static::$sourceSizeCache[$sourcePath] ?? [0, 0];
        // A source whose decoded size cannot be measured is refused too. Imagick decodes
        // formats PHP cannot read the size of (JPEG XL, HEIC), so an unknown size
        // is not a small one.
        if ($maxPixels > 0 && ($width < 1 || $height < 1)) {
            Grav::instance()['log']->warning(sprintf(
                'Refusing to process image source whose size cannot be read (system.images.max_pixels): %s',
                $sourcePath
            ));
            return parent::path(false);
        }
        if ($maxPixels > 0 && $height > intdiv($maxPixels, $width)) {
            Grav::instance()['log']->warning(sprintf(
                'Refusing to process image source above system.images.max_pixels: %s (%dx%d)',
                $sourcePath, $width, $height
            ));
            return parent::path(false);
        }

        $this->filter();

        if (isset($this->result)) {
            return $this->result;
        }

        if ($this->format === 'guess') {
            $extension = strtolower($this->get('extension'));
            // Assigned directly: format() records a transformation, and keeping
            // the original's own format is not one. Not every extension media.yaml
            // calls an image is a type ImageFile can write (`jpe` is not), and
            // save() throws on those, so such a file is saved as what it contains.
            $this->format = isset(ImageFile::$types[$extension]) ? $extension : $this->image->guessType();
        }

        if (!$this->debug_watermarked && $this->get('debug')) {
            $ratio = $this->get('ratio');
            if (!$ratio) {
                $ratio = 1;
            }

            $locator = Grav::instance()['locator'];
            $overlay = $locator->findResource("system://assets/responsive-overlays/{$ratio}x.png") ?: $locator->findResource('system://assets/responsive-overlays/unknown.png');
            $this->image->merge(ImageFile::open($overlay));
        }

        // `watermark_all`, unless this image already has a watermark of its own,
        // or is a processed copy from the image cache, such as the 1x image made
        // from an @2x file, which was stamped when it was saved.
        if ($this->watermark && !$this->watermarked && !$this->isProcessedCopy($sourcePath)) {
            $this->watermark();
        }

        // Queued last on purpose: GD keeps the interlace flag on the image resource,
        // and any operation that builds a new resource (a resize) drops it. Checked
        // against the resolved output format so a JPEG converted to PNG or WebP is
        // not interlaced along with it. getgrav/grav#4284.
        if ($this->progressive && in_array($this->format, ['jpg', 'jpeg'], true)) {
            $this->image->enableProgressive();
        }

        return $this->image->cacheFile($this->format, $this->quality, false, [$this->get('width'), $this->get('height'), $this->get('modified')]);
    }
}
