<?php declare(strict_types=1);

namespace Grav\Framework\Contracts\Image;

use Grav\Framework\Image\Image;

/**
 * Image operations interface.
 */
interface ImageResizeInterface
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
}
