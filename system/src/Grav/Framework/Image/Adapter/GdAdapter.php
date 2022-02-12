<?php declare(strict_types=1);

namespace Grav\Framework\Image\Adapter;

use Grav\Framework\Contracts\Image\ImageAdapterInterface;
use InvalidArgumentException;
use RuntimeException;
use UnexpectedValueException;

/**
 * GD Image adapter.
 */
class GdAdapter extends Adapter
{
    /** @var array<string,int> */
    public static $types = [
        'jpeg'  => \IMG_JPG,
        'jpg'  => \IMG_JPG,
        'gif'   => \IMG_GIF,
        'png'   => \IMG_PNG,
        'webp'  => \IMG_WEBP
    ];

    /** @var \GdImage|resource */
    protected $resource;

    /**
     * {@inheritdoc}
     */
    public static function isEnabled(): bool
    {
        return extension_loaded('gd') && function_exists('gd_info');
    }

    /**
     * {@inheritdoc}
     */
    public static function isSupported(string $type): bool
    {
        $test = self::$types[$type] ?? 0;

        return (bool)(imagetypes() & $test);
    }

    /**
     * @param \GdImage|resource $resource
     */
    public function __construct($resource)
    {
        if (PHP_VERSION_ID > 80000) {
            if (!$resource instanceof \GdImage) {
                throw new InvalidArgumentException('Resource has to be GD Image');
            }
        } elseif (!is_resource($resource)) {
            throw new InvalidArgumentException('Resource has to be GD Image');
        }

        $this->resource = $resource;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'GD';
    }

    /**
     * @return \GdImage|resource
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * {@inheritdoc}
     */
    public function fillBackground(?int $background = 0xffffff)
    {
        $w = $this->width();
        $h = $this->height();
        $n = imagecreatetruecolor($w, $h);
        if (!$n) {
            throw new RuntimeException('Image background color fill failed');
        }
        imagefill($n, 0, 0, $this->allocateColor($background));
        imagecopyresampled($n, $this->resource, 0, 0, 0, 0, $w, $h, $w, $h);
        imagedestroy($this->resource);
        $this->resource = $n;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function resize(?int $background, int $target_width, int $target_height, int $new_width, int $new_height)
    {
        $width = $this->width();
        $height = $this->height();
        $n = imagecreatetruecolor($target_width, $target_height);
        if (!$n) {
            throw new RuntimeException('Failed to resize image: image creation failed');
        }

        if ($background !== null) {
            imagefill($n, 0, 0, $this->allocateColor($background));
        } else {
            imagealphablending($n, false);
            $color = $this->allocateColor(null);

            imagefill($n, 0, 0, $color);
            imagesavealpha($n, true);
        }

        imagecopyresampled($n, $this->resource, ($target_width - $new_width) / 2, ($target_height - $new_height) / 2, 0, 0, $new_width, $new_height, $width, $height);
        imagedestroy($this->resource);

        $this->resource = $n;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function crop(int $x, int $y, int $width, int $height)
    {
        $destination = imagecreatetruecolor($width, $height);
        if (!$destination) {
            throw new RuntimeException('Image crop failed');
        }

        imagealphablending($destination, false);
        imagesavealpha($destination, true);
        imagecopy($destination, $this->resource, 0, 0, $x, $y, $this->width(), $this->height());
        imagedestroy($this->resource);

        $this->resource = $destination;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function negate()
    {
        imagefilter($this->resource, IMG_FILTER_NEGATE);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function brightness($brightness)
    {
        imagefilter($this->resource, IMG_FILTER_BRIGHTNESS, $brightness);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function contrast($contrast)
    {
        imagefilter($this->resource, IMG_FILTER_CONTRAST, $contrast);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function grayscale()
    {
        imagefilter($this->resource, IMG_FILTER_GRAYSCALE);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function emboss()
    {
        imagefilter($this->resource, IMG_FILTER_EMBOSS);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function smooth(int $p)
    {
        imagefilter($this->resource, IMG_FILTER_SMOOTH, $p);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function sharp()
    {
        imagefilter($this->resource, IMG_FILTER_MEAN_REMOVAL);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function edge()
    {
        imagefilter($this->resource, IMG_FILTER_EDGEDETECT);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function colorize(int $red, int $green, int $blue)
    {
        imagefilter($this->resource, IMG_FILTER_COLORIZE, $red, $green, $blue);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function sepia()
    {
        imagefilter($this->resource, IMG_FILTER_GRAYSCALE);
        imagefilter($this->resource, IMG_FILTER_COLORIZE, 100, 50, 0);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function gaussianBlur(int $blurFactor = 1)
    {
        if ($blurFactor < 1) {
            return $this;
        }

        $originalWidth = $this->width();
        $originalHeight = $this->height();

        $smallestWidth = (int)ceil($originalWidth * (0.5 ** $blurFactor));
        $smallestHeight = (int)ceil($originalHeight * (0.5 ** $blurFactor));

        // for the first run, the previous image is the original input
        $prevImage = $this->resource;
        $prevWidth = $originalWidth;
        $prevHeight = $originalHeight;

        // scale way down and gradually scale back up, blurring all the way
        for ($i = 0; $i < $blurFactor; ++$i) {
            // determine dimensions of next image
            $nextWidth = (int)($smallestWidth * (2 ** $i));
            $nextHeight = (int)($smallestHeight * (2 ** $i));

            // resize previous image to next size
            $nextImage = imagecreatetruecolor($nextWidth, $nextHeight);
            if (!$nextImage) {
                throw new RuntimeException('Image gaussian blur failed');
            }
            imagecopyresized($nextImage, $prevImage, 0, 0, 0, 0,
                $nextWidth, $nextHeight, $prevWidth, $prevHeight);

            // apply blur filter
            imagefilter($nextImage, IMG_FILTER_GAUSSIAN_BLUR);

            // now the new image becomes the previous image for the next step
            $prevImage = $nextImage;
            $prevWidth = $nextWidth;
            $prevHeight = $nextHeight;
        }

        // scale back to original size and blur one more time
        imagecopyresized($this->resource, $nextImage,
            0, 0, 0, 0, $originalWidth, $originalHeight, $nextWidth, $nextHeight);
        imagefilter($this->resource, IMG_FILTER_GAUSSIAN_BLUR);

        // clean up
        imagedestroy($prevImage);

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @param GdAdapter $other
     */
    public function merge(ImageAdapterInterface $other, int $x = 0, int $y = 0, int $width = null, int $height = null)
    {
        if (!$other instanceof self) {
            throw new InvalidArgumentException('Image to be merged needs to be instance of GdAdapter');
        }

        imagealphablending($this->resource, true);

        if (null === $width) {
            $width = $other->width();
        }

        if (null === $height) {
            $height = $other->height();
        }

        imagecopyresampled($this->resource, $other->getResource(), $x, $y, 0, 0, $width, $height, $width, $height);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function rotate(float $angle, ?int $background = 0xffffff)
    {
        $resource = imagerotate($this->resource, $angle, $this->allocateColor($background));
        if (!$resource) {
            throw new RuntimeException('Image rotate failed');
        }

        $this->resource = $resource;
        imagealphablending($this->resource, true);
        imagesavealpha($this->resource, true);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function fill(int $color = 0xffffff, int $x = 0, int $y = 0)
    {
        imagealphablending($this->resource, false);
        imagefill($this->resource, $x, $y, $this->allocateColor($color));

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $font, string $text, int $x = 0, int $y = 0, float $size = 12.0, float $angle = 0.0, int $color = 0x000000, string $align = 'left')
    {
        imagealphablending($this->resource, true);

        if ($align !== 'left') {
            $sim_size = $this->getTTFBox($font, $text, $size, $angle);

            if ($align === 'center') {
                $x -= $sim_size['width'] / 2;
            }

            if ($align === 'right') {
                $x -= $sim_size['width'];
            }
        }

        imagettftext($this->resource, $size, $angle, $x, $y, $this->allocateColor($color), $font, $text);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function rectangle(int $x1, int $y1, int $x2, int $y2, int $color, bool $filled = false)
    {
        $c = $this->allocateColor($color);
        if ($filled) {
            imagefilledrectangle($this->resource, $x1, $y1, $x2, $y2, $c);
        } else {
            imagerectangle($this->resource, $x1, $y1, $x2, $y2, $c);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function roundedRectangle(int $x1, int $y1, int $x2, int $y2, int $radius, int $color, bool $filled = false)
    {
        $c = $this->allocateColor($color);

        if ($filled) {
            imagefilledrectangle($this->resource, $x1 + $radius, $y1, $x2 - $radius, $y2, $c);
            imagefilledrectangle($this->resource, $x1, $y1 + $radius, $x1 + $radius - 1, $y2 - $radius, $c);
            imagefilledrectangle($this->resource, $x2 - $radius + 1, $y1 + $radius, $x2, $y2 - $radius, $c);

            imagefilledarc($this->resource, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, 180, 270, $c, IMG_ARC_PIE);
            imagefilledarc($this->resource, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, 270, 360, $c, IMG_ARC_PIE);
            imagefilledarc($this->resource, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, 90, 180, $c, IMG_ARC_PIE);
            imagefilledarc($this->resource, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, 360, 90, $c, IMG_ARC_PIE);
        } else {
            imageline($this->resource, $x1 + $radius, $y1, $x2 - $radius, $y1, $c);
            imageline($this->resource, $x1 + $radius, $y2, $x2 - $radius, $y2, $c);
            imageline($this->resource, $x1, $y1 + $radius, $x1, $y2 - $radius, $c);
            imageline($this->resource, $x2, $y1 + $radius, $x2, $y2 - $radius, $c);

            imagearc($this->resource, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, 180, 270, $c);
            imagearc($this->resource, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, 270, 360, $c);
            imagearc($this->resource, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, 90, 180, $c);
            imagearc($this->resource, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, 360, 90, $c);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function line(int $x1, int $y1, int $x2, int $y2, $color = 0x000000)
    {
        imageline($this->resource, $x1, $y1, $x2, $y2, $this->allocateColor($color));

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function ellipse(int $cx, int $cy, int $width, int $height, $color = 0x000000, bool $filled = false)
    {
        $c = $this->allocateColor($color);
        if ($filled) {
            imagefilledellipse($this->resource, $cx, $cy, $width, $height, $c);
        } else {
            imageellipse($this->resource, $cx, $cy, $width, $height, $c);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function circle(int $cx, int $cy, int $r, $color = 0x000000, bool $filled = false)
    {
        return $this->ellipse($cx, $cy, $r, $r, $this->allocateColor($color), $filled);
    }

    /**
     * {@inheritdoc}
     */
    public function polygon(array $points, $color, bool $filled = false)
    {
        $num = (int)(count($points) / 2);
        $c = $this->allocateColor($color);

        if ($filled) {
            imagefilledpolygon($this->resource, $points, $num, $c);
        } else {
            imagepolygon($this->resource, $points, $num, $c);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function flip(bool $flipVertical, bool $flipHorizontal)
    {
        if (!$flipVertical && !$flipHorizontal) {
            return $this;
        }

        if (function_exists('imageflip')) {
            if ($flipVertical && $flipHorizontal) {
                $flipMode = \IMG_FLIP_BOTH;
            } elseif ($flipVertical && !$flipHorizontal) {
                $flipMode = \IMG_FLIP_VERTICAL;
            } elseif (!$flipVertical && $flipHorizontal) {
                $flipMode = \IMG_FLIP_HORIZONTAL;
            }

            if (isset($flipMode)) {
                imageflip($this->resource, $flipMode);
            }
        } else {
            $width = $this->width();
            $height = $this->height();

            $src_x      = 0;
            $src_y      = 0;
            $src_width  = $width;
            $src_height = $height;

            if ($flipVertical) {
                $src_y      = $height - 1;
                $src_height = -$height;
            }

            if ($flipHorizontal) {
                $src_x      = $width - 1;
                $src_width  = -$width;
            }

            $imgdest = imagecreatetruecolor($width, $height);
            if (!$imgdest) {
                throw new RuntimeException('Image flip failed');
            }

            imagealphablending($imgdest, false);
            imagesavealpha($imgdest, true);

            if (imagecopyresampled($imgdest, $this->resource, 0, 0, $src_x, $src_y, $width, $height, $src_width, $src_height)) {
                imagedestroy($this->resource);
                $this->resource = $imgdest;
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function width(): int
    {
        return imagesx($this->resource);
    }

    /**
     * {@inheritdoc}
     */
    public function height(): int
    {
        return imagesy($this->resource);
    }

    /**
     * {@inheritdoc}
     */
    public function saveGif(string $filepath)
    {
        $transColor = imagecolorallocatealpha($this->resource, 255, 255, 255, 127);
        if (!$transColor) {
            throw new RuntimeException('Image save failed');
        }

        imagecolortransparent($this->resource, $transColor);
        imagegif($this->resource, $filepath);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function savePng(string $filepath)
    {
        imagepng($this->resource, $filepath);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function saveWebp(string $filepath, int $quality)
    {
        imagewebp($this->resource, $filepath, $quality);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function saveJpeg(string $filepath, int $quality)
    {
        imagejpeg($this->resource, $filepath, $quality);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function enableProgressive()
    {
        imageinterlace($this->resource, true);

        return $this;
    }

    /**
     * Create empty image.
     *
     * @param int $width
     * @param int $height
     * @return void
     */
    protected function createImage(int $width, int $height): void
    {
        $this->resource = imagecreatetruecolor($width, $height) ?: null;
    }

    /**
     * Create image from a string.
     *
     * @param string $data
     * @return void
     */
    protected function createImageFromString(string $data): void
    {
        $this->resource = @imagecreatefromstring($data) ?: null;
    }

    /**
     * Converts the image to true color.
     *
     * @return void
     */
    protected function convertToTrueColor(): void
    {
        if (!imageistruecolor($this->resource)) {
            imagepalettetotruecolor($this->resource);
        }

        imagesavealpha($this->resource, true);
    }

    /**
     * Try to open the file using jpeg.
     *
     * @param string $filepath
     * @return void
     */
    protected function openJpeg(string $filepath): void
    {
        if (file_exists($filepath) && filesize($filepath)) {
            $this->resource = @imagecreatefromjpeg($filepath) ?: null;
        } else {
            $this->resource = null;
        }
    }

    /**
     * Try to open the file using gif.
     *
     * @param string $filepath
     * @return void
     */
    protected function openGif(string $filepath): void
    {
        if (file_exists($filepath) && filesize($filepath)) {
            $this->resource = @imagecreatefromgif($filepath) ?: null;
        } else {
            $this->resource = null;
        }
    }

    /**
     * Try to open the file using PNG.
     *
     * @param string $filepath
     * @return void
     */
    protected function openPng(string $filepath): void
    {
        if (file_exists($filepath) && filesize($filepath)) {
            $this->resource = @imagecreatefrompng($filepath) ?: null;
        } else {
            $this->resource = null;
        }
    }

    /**
     * Try to open the file using WEBP.
     *
     * @param string $filepath
     * @return void
     */
    protected function openWebp(string $filepath): void
    {
        if (file_exists($filepath) && filesize($filepath)) {
            $this->resource = @imagecreatefromwebp($filepath) ?: null;
        } else {
            $this->resource = null;
        }
    }

    /**
     * Get color in x, y.
     *
     * @param int $x
     * @param int $y
     * @return int|false
     */
    protected function getColor(int $x, int $y)
    {
        return imagecolorat($this->resource, $x, $y);
    }

    /**
     * Load image resource.
     *
     * @param \GdImage|resource $resource
     */
    protected function loadResource($resource): void
    {
        $this->resource = $resource;

        imagesavealpha($this->resource, true);
    }

    /**
     * Load file.
     *
     * @param string $filepath
     * @param string $type
     * @return void
     * @throws UnexpectedValueException
     */
    protected function loadFile(string $filepath, string $type): void
    {
        if (!static::isSupported($type)) {
            throw new UnexpectedValueException('Type ' . $type . ' is not supported by GD');
        }

        switch ($type) {
            case 'jpeg':
                $this->openJpeg($filepath);
                break;
            case 'gif':
                $this->openGif($filepath);
                break;
            case 'png':
                $this->openPng($filepath);
                break;
            case 'webp':
                $this->openWebp($filepath);
                break;
            default:
                throw new UnexpectedValueException('Unable to open file (' . $filepath . ')');
        }

        if (null === $this->getResource()) {
            throw new UnexpectedValueException('Unable to open file (' . $filepath . ')');
        }

        $this->convertToTrueColor();
    }

    /**
     * Give the bounding box of a text using TrueType fonts.
     *
     * @param string $font
     * @param string $text
     * @param float $size
     * @param float $angle
     * @return array
     */
    protected function getTTFBox(string $font, string $text, float $size, float $angle = 0): array
    {
        $box = imagettfbbox($size, $angle, $font, $text);
        if (false === $box) {
            throw new RuntimeException('Failed to allocate room for text');
        }

        return [
            'width'  => abs($box[2] - $box[0]),
            'height' => abs($box[3] - $box[5]),
        ];
    }


    /**
     * Allocate color for the image.
     *
     * @param int|null $color
     * @return int
     */
    protected function allocateColor(?int $color): int
    {
        $colorRGBA = $color ?? 0x7fffffff;

        $b = ($colorRGBA) & 0xff;
        $colorRGBA >>= 8;
        $g = ($colorRGBA) & 0xff;
        $colorRGBA >>= 8;
        $r = ($colorRGBA) & 0xff;
        $colorRGBA >>= 8;
        $a = ($colorRGBA) & 0xff;

        $c = imagecolorallocatealpha($this->resource, $r, $g, $b, $a);

        if (false !== $c && $color === null) {
            imagecolortransparent($this->resource, $c);
        }

        if (false === $c) {
            throw new RuntimeException('Failed to allocate color');
        }

        return $c;
    }
}
