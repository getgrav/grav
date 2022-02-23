<?php declare(strict_types=1);

namespace Grav\Framework\Image\Traits;

use Grav\Framework\Image\Image;
use Grav\Framework\Image\ImageColor;

/**
 * Trait to record image operations.
 */
trait ImageOperationsTrait
{
    /** @var int */
    protected $width = 0;
    /** @var int */
    protected $height = 0;
    /** @var int|null */
    protected $orientation = null;
    /** @var array */
    protected $dependencies = [];
    /** @var array */
    protected $operations = [];

    /**
     * Image width.
     *
     * @return int
     */
    public function width(): int
    {
        return $this->width;
    }

    /**
     * Image height.
     *
     * @return int
     */
    public function height(): int
    {
        return $this->height;
    }

    /**
     * Works as resize() excepts that the layout will be cropped.
     *
     * @param string|int|null $width  the width
     * @param string|int|null $height he height
     * @param string|int $background the background
     * @return $this
     */
    public function cropResize($width = null, $height = null, $background = 0xffffff)
    {
        return $this->resize($width, $height, $background, false, false, true);
    }

    /**
     * Resize the image preserving scale. Can enlarge it.
     *
     * @param string|int|null $width      the width
     * @param string|int|null $height     the height
     * @param string|int $background the background
     * @param bool $crop
     * @return $this
     */
    public function scaleResize($width = null, $height = null, $background = 0xffffff, bool $crop = false)
    {
        return $this->resize($width, $height, $background, false, true, $crop);
    }

    /**
     * Resizes the image forcing the destination to have exactly the given width and the height.
     *
     * @param string|int|null $width      the width
     * @param string|int|null $height     the height
     * @param string|int $background the background
     * @return $this
     */
    public function forceResize($width = null, $height = null, $background = 0xffffff)
    {
        return $this->resize($width, $height, $background, true);
    }

    /**
     * Resizes the image. It will never be enlarged.
     *
     * @param string|int|null $width      the width
     * @param string|int|null $height     the height
     * @param string|int $background the background
     * @param bool $force
     * @param bool $rescale
     * @param bool $crop
     * @return $this
     */
    public function resize($width = null, $height = null, $background = 0xffffff, bool $force = false, bool $rescale = false, bool $crop = false)
    {
        [$width, $height] = $this->getSize($width, $height);
        if ($width < 0 || $height < 0) {
            return $this;
        }

        $bg = ImageColor::parse($background);

        $current_width = $this->width();
        $current_height = $this->height();
        $new_width = 0;
        $new_height = 0;
        $scale = 1.0;

        if (!$rescale && (!$force || $crop)) {
            if ($width !== 0 && $current_width > $width) {
                $scale = $current_width / $width;
            }

            if ($height !== 0 && $current_height > $height && $current_height / $height > $scale) {
                $scale = $current_height / $height;
            }
        } else {
            if ($width !== 0) {
                $scale = $current_width / $width;
                $new_width = $width;
            }

            if ($height !== 0) {
                if ($width !== 0 && $rescale) {
                    $scale = max($scale, $current_height / $height);
                } else {
                    $scale = $current_height / $height;
                }
                $new_height = $height;
            }
        }

        if (!$force || $width === 0 || $rescale) {
            $new_width = (int)round($current_width / $scale);
        }

        if (!$force || $height === 0 || $rescale) {
            $new_height = (int)round($current_height / $scale);
        }

        if ($width === 0 || $crop) {
            $width = $new_width;
        }

        if ($height === 0 || $crop) {
            $height = $new_height;
        }

        if ($width === $new_width && $height === $new_height && $width === $this->width() && $height === $this->height()) {
            // Nothing to resize.
            return $this;
        }

        $this->operations[] = ['resize', [$bg, $width, $height, $new_width, $new_height]];

        // Update image size.
        $this->width = $width;
        $this->height = $height;

        return $this;
    }

    /**
     * Perform a zoom crop of the image to desired width and height.
     *
     * @param string|int|null $width      Desired width
     * @param string|int|null $height     Desired height
     * @param string|int $background
     * @param string|int $xPosLetter
     * @param string|int $yPosLetter
     * @return $this
     */
    public function zoomCrop($width, $height, $background = 0xffffff, $xPosLetter = 'center', $yPosLetter = 'center')
    {
        [$width, $height] = $this->getSize($width, $height);
        if ($width <= 0 || $height <= 0) {
            return $this;
        }

        $bg = ImageColor::parse($background);

        $originalWidth = $this->width();
        $originalHeight = $this->height();

        // Calculate the different ratios
        $originalRatio = $originalWidth / $originalHeight;
        $newRatio = $width / $height;

        // Compare ratios
        if ($originalRatio > $newRatio) {
            // Original image is wider
            $newHeight = $height;
            $newWidth = (int)($height * $originalRatio);
        } else {
            // Equal width or smaller
            $newHeight = (int)($width / $originalRatio);
            $newWidth = $width;
        }

        // Perform resize
        $this->resize($newWidth, $newHeight, $bg, true);

        // Define x position
        switch ($xPosLetter) {
            case 'L':
            case 'left':
                $xPos = 0;
                break;
            case 'R':
            case 'right':
                $xPos = $newWidth - $width;
                break;
            case 'C':
            case 'center':
                $xPos = (int)(($newWidth - $width) / 2);
                break;
            default:
                $factorW = $newWidth / $originalWidth;
                $xPos = (int)((int)$xPosLetter * $factorW);

                // If the desired cropping position goes beyond the width then
                // set the crop to be within the correct bounds.
                if ($xPos + $width > $newWidth) {
                    $xPos = $newWidth - $width;
                }
        }

        // Define y position
        switch ($yPosLetter) {
            case 'T':
            case 'top':
                $yPos = 0;
                break;
            case 'B':
            case 'bottom':
                $yPos = $newHeight - $height;
                break;
            case 'C':
            case 'center':
                $yPos = (int)(($newHeight - $height) / 2);
                break;
            default:
                $factorH = $newHeight / $originalHeight;
                $yPos = (int)((int)$yPosLetter * $factorH);

                // If the desired cropping position goes beyond the height then
                // set the crop to be within the correct bounds.
                if ($yPos + $height > $newHeight) {
                    $yPos = $newHeight - $height;
                }
        }

        // Crop image to reach desired size
        $this->crop($xPos, $yPos, $width, $height);

        return $this;
    }

    /**
     * Crops the image.
     *
     * @param int $x      the top-left x position of the crop box
     * @param int $y      the top-left y position of the crop box
     * @param int $width  the width of the crop box
     * @param int $height the height of the crop box
     * @return $this
     */
    public function crop(int $x, int $y, int $width, int $height)
    {
        if ($x === 0 && $y === 0 && $width === $this->width && $height === $this->height) {
            // Nothing to crop.
            return $this;
        }

        $this->operations[] = ['crop', [$x, $y, $width, $height]];

        // Update image size.
        $this->width = $width;
        $this->height = $height;

        return $this;
    }

    /**
     * Read exif rotation from file and apply it.
     *
     * @return $this
     */
    public function fixOrientation()
    {
        if (null !== $this->orientation) {
            return $this->applyExifOrientation($this->orientation);
        }

        $this->operations[] = ['fixOrientation', []];

        return $this;
    }

    /**
     * Apply orientation using Exif orientation value.
     *
     * @param int $exif_orientation
     * @return $this
     */
    public function applyExifOrientation(int $exif_orientation)
    {
        $this->operations[] = ['applyExifOrientation', [$exif_orientation]];

        return $this;
    }

    /**
     * enable progressive image loading.
     *
     * @return $this
     */
    public function enableProgressive()
    {
        $this->operations[] = ['enableProgressive', []];

        return $this;
    }

    /**
     * Fills the image background to $bg if the image is transparent.
     *
     * @param string|int $background background color
     * @return $this
     */
    public function fillBackground($background = 0xffffff)
    {
        $bg = ImageColor::parse($background);

        $this->operations[] = ['fillBackground', [$bg]];

        return $this;
    }

    /**
     * Negates the image.
     *
     * @return $this
     */
    public function negate()
    {
        $this->operations[] = ['negate', []];

        return $this;
    }

    /**
     * Changes the brightness of the image.
     *
     * @param int $brightness the brightness
     * @return $this
     */
    public function brightness(int $brightness)
    {
        $this->operations[] = ['brightness', [$brightness]];

        return $this;
    }

    /**
     * Contrasts the image.
     *
     * @param int $contrast the contrast [-100, 100]
     * @return $this
     */
    public function contrast(int $contrast)
    {
        $this->operations[] = ['contrast', [$contrast]];

        return $this;
    }

    /**
     * Apply a grayscale level effect on the image.
     *
     * @return $this
     */
    public function grayscale()
    {
        $this->operations[] = ['grayscale', []];

        return $this;
    }

    /**
     * Emboss the image.
     *
     * @return $this
     */
    public function emboss()
    {
        $this->operations[] = ['emboss', []];

        return $this;
    }

    /**
     * Smooth the image.
     *
     * @param int $p value between [-10,10]
     *
     * @return $this
     */
    public function smooth(int $p)
    {
        $this->operations[] = ['smooth', [$p]];

        return $this;
    }

    /**
     * Sharpens the image.
     *
     * @return $this
     */
    public function sharp()
    {
        $this->operations[] = ['sharp', []];

        return $this;
    }

    /**
     * Edges the image.
     *
     * @return $this
     */
    public function edge()
    {
        $this->operations[] = ['edge', []];

        return $this;
    }

    /**
     * Colorize the image.
     *
     * @param int $red   value in range [-255, 255]
     * @param int $green value in range [-255, 255]
     * @param int $blue  value in range [-255, 255]
     * @return $this
     */
    public function colorize(int $red, int $green, int $blue)
    {
        $this->operations[] = ['colorize', [$red, $green, $blue]];

        return $this;
    }

    /**
     * apply sepia to the image.
     *
     * @return $this
     */
    public function sepia()
    {
        $this->operations[] = ['sepia', []];

        return $this;
    }

    /**
     * @param int $blurFactor
     * @return $this
     */
    public function gaussianBlur(int $blurFactor = 1)
    {
        $this->operations[] = ['gaussianBlur', [$blurFactor]];

        return $this;
    }

    /**
     * Merge with another image.
     *
     * @param Image $other
     * @param int $x
     * @param int $y
     * @param int $width
     * @param int $height
     * @return $this
     */
    public function merge(Image $other, int $x = 0, int $y = 0, int $width = 0, int $height = 0)
    {
        $serialized = $other->jsonSerialize();
        $deps = $other->dependencies;
        $deps[] = $serialized;

        $this->dependencies = array_merge($this->dependencies, $deps);
        $this->operations[] = ['merge', [$serialized, $x, $y, $width, $height]];

        return $this;
    }

    /**
     * Rotate the image.
     *
     * @param float $angle
     * @param string|int $background
     * @return $this
     */
    public function rotate(float $angle, $background = 0xffffff)
    {
        $bg = ImageColor::parse($background);

        $this->operations[] = ['rotate', [$angle, $bg]];

        // FIXME: Image size may change?

        return $this;
    }

    /**
     * Fills the image.
     *
     * @param string|int $color
     * @param int $x
     * @param int $y
     * @return $this
     */
    public function fill($color = 0xffffff, int $x = 0, int $y = 0)
    {
        $c = (int)ImageColor::parse($color);

        $this->operations[] = ['fill', [$c, $x, $y]];

        return $this;
    }

    /**
     * write text to the image.
     *
     * @param string $font
     * @param string $text
     * @param int $x
     * @param int $y
     * @param float $size
     * @param float $angle
     * @param string|int $color
     * @param string $align
     * @return $this
     */
    public function write(string $font, string $text, int $x = 0, int $y = 0, float $size = 12.0, float $angle = 0.0, $color = 0x000000, string $align = 'left')
    {
        $c = (int)ImageColor::parse($color);

        $this->operations[] = ['write', [$font, $text, $x, $y, $size, $angle, $c, $align]];

        return $this;
    }

    /**
     * Draws a rectangle.
     *
     * @param int $x1
     * @param int $y1
     * @param int $x2
     * @param int $y2
     * @param string|int $color
     * @param bool $filled
     * @return $this
     */
    public function rectangle(int $x1, int $y1, int $x2, int $y2, $color, bool $filled = false)
    {
        $c = (int)ImageColor::parse($color);

        $this->operations[] = ['rectangle', [$x1, $y1, $x2, $y2, $c, $filled]];

        return $this;
    }

    /**
     * Draws a rounded rectangle.
     *
     * @param int $x1
     * @param int $y1
     * @param int $x2
     * @param int $y2
     * @param int $radius
     * @param string|int $color
     * @param bool $filled
     * @return $this
     */
    public function roundedRectangle(int $x1, int $y1, int $x2, int $y2, int $radius, $color, bool $filled = false)
    {
        $c = (int)ImageColor::parse($color);

        $this->operations[] = ['roundedRectangle', [$x1, $y1, $x2, $y2, $radius, $c, $filled]];

        return $this;
    }

    /**
     * Draws a line.
     *
     * @param int $x1
     * @param int $y1
     * @param int $x2
     * @param int $y2
     * @param string|int $color
     * @return $this
     */
    public function line(int $x1, int $y1, int $x2, int $y2, $color = 0x000000)
    {
        $c = (int)ImageColor::parse($color);

        $this->operations[] = ['line', [$x1, $y1, $x2, $y2, $c]];

        return $this;
    }

    /**
     * Draws an ellipse.
     *
     * @param int $cx
     * @param int $cy
     * @param int $width
     * @param int $height
     * @param string|int $color
     * @param bool $filled
     * @return $this
     */
    public function ellipse(int $cx, int $cy, int $width, int $height, $color = 0x000000, bool $filled = false)
    {
        $c = (int)ImageColor::parse($color);

        $this->operations[] = ['ellipse', [$cx, $cy, $width, $height, $c, $filled]];

        return $this;
    }

    /**
     * Draws a circle.
     *
     * @param int $cx
     * @param int $cy
     * @param int $r
     * @param string|int $color
     * @param bool $filled
     * @return $this
     */
    public function circle(int $cx, int $cy, int $r, $color = 0x000000, bool $filled = false)
    {
        $c = (int)ImageColor::parse($color);

        $this->operations[] = ['circle', [$cx, $cy, $r, $c, $filled]];

        return $this;
    }

    /**
     * Draws a polygon.
     *
     * @param array $points
     * @param string|int $color
     * @param bool $filled
     * @return $this
     */
    public function polygon(array $points, $color, bool $filled = false)
    {
        $c = (int)ImageColor::parse($color);

        $this->operations[] = ['polygon', [$points, $c, $filled]];

        return $this;
    }

    /**
     * Flips the image.
     *
     * @param bool $flipVertical
     * @param bool $flipHorizontal
     * @return $this
     */
    public function flip(bool $flipVertical, bool $flipHorizontal)
    {
        $this->operations[] = ['flip', [$flipVertical, $flipHorizontal]];

        return $this;
    }

    /**
     * @param string|float|int|null $width
     * @param string|float|int|null $height
     * @return int[]
     */
    protected function getSize($width, $height): array
    {
        if ($height === null && is_string($width) && preg_match('#^(.+)%$#mUs', $width, $matches)) {
            $width = round($this->width() * ((float)$matches[1] / 100.0));
            $height = round($this->height() * ((float)$matches[1] / 100.0));
        }

        return [(int)$width, (int)$height];
    }
}
