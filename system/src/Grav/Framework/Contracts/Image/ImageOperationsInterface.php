<?php declare(strict_types=1);

namespace Grav\Framework\Contracts\Image;

use Grav\Framework\Image\Image;

/**
 * Image operations interface.
 */
interface ImageOperationsInterface extends ImageInfoInterface
{
    /**
     * Works as resize() excepts that the layout will be cropped.
     *
     * @param string|int|null $width New width
     * @param string|int|null $height New height
     * @param string|int $background Background color
     * @return $this
     */
    public function cropResize($width = null, $height = null, $background = 0xffffff);

    /**
     * Resizes the image preserving scale. Can enlarge it.
     *
     * @param string|int|null $width New width
     * @param string|int|null $height New height
     * @param string|int $background Background color
     * @param bool $crop
     * @return $this
     */
    public function scaleResize($width = null, $height = null, $background = 0xffffff, bool $crop = false);

    /**
     * Resizes the image forcing the destination to have exactly the given width and the height.
     *
     * @param string|int|null $width New width
     * @param string|int|null $height New height
     * @param string|int $background Background color
     * @return $this
     */
    public function forceResize($width = null, $height = null, $background = 0xffffff);

    /**
     * Resizes the image. It will never be enlarged.
     *
     * @param string|int|null $width New width
     * @param string|int|null $height New height
     * @param string|int $background Background color
     * @param bool $force
     * @param bool $rescale
     * @param bool $crop
     * @return $this
     */
    public function resize($width = null, $height = null, $background = 0xffffff, bool $force = false, bool $rescale = false, bool $crop = false);

    /**
     * Perform a zoom crop of the image to desired width and height.
     *
     * @param string|int|null $width New width
     * @param string|int|null $height New height
     * @param string|int $background Background color
     * @param string|int $xPosLetter
     * @param string|int $yPosLetter
     * @return $this
     */
    public function zoomCrop($width, $height, $background = 0xffffff, $xPosLetter = 'center', $yPosLetter = 'center');

    /**
     * Crops the image.
     *
     * @param int $x      the top-left x position of the crop box
     * @param int $y      the top-left y position of the crop box
     * @param int $width  the width of the crop box
     * @param int $height the height of the crop box
     * @return $this
     */
    public function crop(int $x, int $y, int $width, int $height);

    /**
     * Read exif rotation from file and apply it.
     *
     * @return $this
     */
    public function fixOrientation();

    /**
     * Apply orientation using Exif orientation value.
     *
     * @param int $exif_orientation
     * @return $this
     */
    public function applyExifOrientation(int $exif_orientation);

    /**
     * enable progressive image loading.
     *
     * @return $this
     */
    public function enableProgressive();

    /**
     * Fills the image background to $bg if the image is transparent.
     *
     * @param string|int $background background color
     * @return $this
     */
    public function fillBackground($background = 0xffffff);

    /**
     * Negates the image.
     *
     * @return $this
     */
    public function negate();

    /**
     * Changes the brightness of the image.
     *
     * @param int $brightness the brightness
     * @return $this
     */
    public function brightness(int $brightness);

    /**
     * Contrasts the image.
     *
     * @param int $contrast the contrast [-100, 100]
     * @return $this
     */
    public function contrast(int $contrast);

    /**
     * Apply a grayscale level effect on the image.
     *
     * @return $this
     */
    public function grayscale();

    /**
     * Emboss the image.
     *
     * @return $this
     */
    public function emboss();

    /**
     * Smooth the image.
     *
     * @param int $p value between [-10,10]
     *
     * @return $this
     */
    public function smooth(int $p);

    /**
     * Sharpens the image.
     *
     * @return $this
     */
    public function sharp();

    /**
     * Edges the image.
     *
     * @return $this
     */
    public function edge();

    /**
     * Colorize the image.
     *
     * @param int $red   value in range [-255, 255]
     * @param int $green value in range [-255, 255]
     * @param int $blue  value in range [-255, 255]
     * @return $this
     */
    public function colorize(int $red, int $green, int $blue);

    /**
     * apply sepia to the image.
     *
     * @return $this
     */
    public function sepia();

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
    public function merge(Image $other, int $x = 0, int $y = 0, int $width = 0, int $height = 0);

    /**
     * Rotate the image.
     *
     * @param float $angle
     * @param string|int $background
     * @return $this
     */
    public function rotate(float $angle, $background = 0xffffff);

    /**
     * Fills the image.
     *
     * @param string|int $color
     * @param int $x
     * @param int $y
     * @return $this
     */
    public function fill($color = 0xffffff, int $x = 0, int $y = 0);

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
    public function write(string $font, string $text, int $x = 0, int $y = 0, float $size = 12.0, float $angle = 0.0, $color = 0x000000, string $align = 'left');

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
    public function rectangle(int $x1, int $y1, int $x2, int $y2, $color, bool $filled = false);

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
    public function roundedRectangle(int $x1, int $y1, int $x2, int $y2, int $radius, $color, bool $filled = false);

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
    public function line(int $x1, int $y1, int $x2, int $y2, $color = 0x000000);

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
    public function ellipse(int $cx, int $cy, int $width, int $height, $color = 0x000000, bool $filled = false);

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
    public function circle(int $cx, int $cy, int $r, $color = 0x000000, bool $filled = false);

    /**
     * Draws a polygon.
     *
     * @param array $points
     * @param string|int $color
     * @param bool $filled
     * @return $this
     */
    public function polygon(array $points, $color, bool $filled = false);

    /**
     * Flips the image.
     *
     * @param bool $flipVertical
     * @param bool $flipHorizontal
     * @return $this
     */
    public function flip(bool $flipVertical, bool $flipHorizontal);
}
