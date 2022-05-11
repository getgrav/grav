<?php declare(strict_types=1);

namespace Grav\Framework\Contracts\Image;

/**
 * Image adapter interface.
 */
interface ImageAdapterInterface extends ImageInfoInterface, ImageSaveInterface
{
    /**
     * Returns true if the adapter is enabled.
     *
     * @return bool
     */
    public static function isEnabled(): bool;

    /**
     * Returns true if the image type is supported by the adapter.
     *
     * @param string $type
     * @return bool
     */
    public static function isSupported(string $type): bool;

    /**
     * Get the raw resource.
     *
     * @return object|resource
     */
    public function getResource();

    /**
     * Gets the name of the adapter.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Gets the retina scaling for the image.
     *
     * Image size for resize operations and image sizes or coordinates will be multiplied by this factor.
     *
     * @return int
     */
    public function getRetinaScale(): int;

    /**
     * Sets the retina scaling for the image.
     *
     * NOTE: Set this before any image operations.
     *
     * @return $this
     */
    public function setRetinaScale(int $scale);

    /**
     * Resizes the image.
     *
     * @param int|null $background
     * @param int $target_width
     * @param int $target_height
     * @param int $new_width
     * @param int $new_height
     * @return $this
     */
    public function resize(?int $background, int $target_width, int $target_height, int $new_width, int $new_height);

    /**
     * Crops the image.
     *
     * @param int $x      The top-left x position of the crop box
     * @param int $y      The top-left y position of the crop box
     * @param int $width  The width of the crop box
     * @param int $height The height of the crop box
     * @return $this
     */
    public function crop(int $x, int $y, int $width, int $height);

    /**
     * Fixes image orientation based on exif data.
     *
     * @return $this
     */
    public function fixOrientation();

    /**
     * Applies orientation using exif orientation value.
     *
     * @param int $exif_orientation
     * @return $this
     */
    public function applyExifOrientation(int $exif_orientation);

    /**
     * Enables progressive image loading.
     *
     * @return $this
     */
    public function enableProgressive();

    /**
     * Fills the image background if the image is transparent.
     *
     * @param int|null $background Image background, null if transparent
     * @return $this
     */
    public function fillBackground(?int $background = 0xffffff);

    /**
     * Negates the image.
     *
     * @return $this
     */
    public function negate();

    /**
     * Changes the brightness of the image.
     *
     * @param int $brightness Image brightness
     * @return $this
     */
    public function brightness(int $brightness);

    /**
     * Changes the contrast of the image.
     *
     * @param int $contrast Image contrast [-100, 100]
     * @return $this
     */
    public function contrast(int $contrast);

    /**
     * Applies a grayscale level effect on the image.
     *
     * @return $this
     */
    public function grayscale();

    /**
     * Embosses the image.
     *
     * @return $this
     */
    public function emboss();

    /**
     * Smooths the image.
     *
     * @param int $p value between [-10, 10]
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
     * Applies edge filtering to the image.
     *
     * @return $this
     */
    public function edge();

    /**
     * Colorizes the image.
     *
     * @param int $red   Value in range [-255, 255]
     * @param int $green Value in range [-255, 255]
     * @param int $blue  Value in range [-255, 255]
     * @return $this
     */
    public function colorize(int $red, int $green, int $blue);

    /**
     * Applies sepia toning to the image.
     *
     * @return $this
     */
    public function sepia();

    /**
     * Merges the image with another image.
     *
     * @param ImageAdapterInterface $other
     * @param int $x
     * @param int $y
     * @param int $width
     * @param int $height
     * @return $this
     */
    public function merge(ImageAdapterInterface $other, int $x = 0, int $y = 0, int $width = 0, int $height = 0);

    /**
     * Rotates the image.
     *
     * @param float $angle
     * @param int|null $background Image background, null if transparent
     * @return $this
     */
    public function rotate(float $angle, ?int $background = 0xffffff);

    /**
     * Fills the image with color.
     *
     * @param int $color
     * @param int $x
     * @param int $y
     * @return $this
     */
    public function fill(int $color = 0xffffff, int $x = 0, int $y = 0);

    /**
     * Writes text to the image.
     *
     * @param string $font
     * @param string $text
     * @param int $x
     * @param int $y
     * @param float $size
     * @param float $angle
     * @param int $color
     * @param string $align
     * @return $this
     */
    public function write(string $font, string $text, int $x = 0, int $y = 0, float $size = 12.0, float $angle = 0.0, int $color = 0x000000, string $align = 'left');

    /**
     * Draws a rectangle.
     *
     * @param int $x1
     * @param int $y1
     * @param int $x2
     * @param int $y2
     * @param int $color
     * @param bool $filled
     * @return $this
     */
    public function rectangle(int $x1, int $y1, int $x2, int $y2, int $color, bool $filled = false);

    /**
     * Draws a rounded rectangle.
     *
     * @param int $x1
     * @param int $y1
     * @param int $x2
     * @param int $y2
     * @param int $radius
     * @param int $color
     * @param bool $filled
     * @return $this
     */
    public function roundedRectangle(int $x1, int $y1, int $x2, int $y2, int $radius, int $color, bool $filled = false);

    /**
     * Draws a line.
     *
     * @param int $x1
     * @param int $y1
     * @param int $x2
     * @param int $y2
     * @param int $color
     * @return $this
     */
    public function line(int $x1, int $y1, int $x2, int $y2, int $color = 0x000000);

    /**
     * Draws an ellipse.
     *
     * @param int $cx
     * @param int $cy
     * @param int $width
     * @param int $height
     * @param int $color
     * @param bool $filled
     * @return $this
     */
    public function ellipse(int $cx, int $cy, int $width, int $height, int $color = 0x000000, bool $filled = false);

    /**
     * Draws a circle.
     *
     * @param int $cx
     * @param int $cy
     * @param int $r
     * @param int $color
     * @param bool $filled
     * @return $this
     */
    public function circle(int $cx, int $cy, int $r, int $color = 0x000000, bool $filled = false);

    /**
     * Draws a polygon.
     *
     * @param array $points
     * @param int $color
     * @param bool $filled
     * @return $this
     */
    public function polygon(array $points, int $color, bool $filled = false);

    /**
     * Flips the image.
     *
     * @param bool $flipVertical
     * @param bool $flipHorizontal
     * @return $this
     */
    public function flip(bool $flipVertical, bool $flipHorizontal);
}
