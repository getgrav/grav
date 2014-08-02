<?php

namespace Gregwar\Image\Adapter;

use Gregwar\Image\Image;
use Gregwar\Image\Source\Source;

/**
 * all the functions / methods to work on images
 *
 * if changing anything please also add it to \Gregwar\Image\Image
 *
 * @author wodka <michael.schramm@gmail.com>
 */
interface AdapterInterface{
	/**
	 * set the image source for the adapter
	 *
	 * @param Source $source
	 * @return $this
	 */
	public function setSource(Source $source);

	/**
	 * get the raw resource
	 *
	 * @return resource
	 */
	public function getResource();

	/**
	 * Gets the name of the adapter
	 *
	 * @return string
	 */
	public function getName();

	/**
	 * Image width
	 *
	 * @return int
	 */
	public function width();

	/**
	 * Image height
	 *
	 * @return int
	 */
	public function height();

	/**
	 * Init the resource
	 *
	 * @return $this
	 */
	public function init();

	/**
	 * Save the image as a gif
	 *
	 * @return $this
	 */
	public function saveGif($file);

	/**
	 * Save the image as a png
	 *
	 * @return $this
	 */
	public function savePng($file);

	/**
	 * Save the image as a jpeg
	 *
	 * @return $this
	 */
	public function saveJpeg($file, $quality);

	/**
	 * Works as resize() excepts that the layout will be cropped
	 *
	 * @param int $width the width
	 * @param int $height the height
	 * @param int $background the background
	 *
	 * @return $this
	 */
	public function cropResize($width = null, $height = null, $background=0xffffff);


	/**
	 * Resize the image preserving scale. Can enlarge it.
	 *
	 * @param int $width the width
	 * @param int $height the height
	 * @param int $background the background
	 * @param boolean $crop
	 *
	 * @return $this
	 */
	public function scaleResize($width = null, $height = null, $background=0xffffff, $crop = false);

	/**
	 * Resizes the image. It will never be enlarged.
	 *
	 * @param int $width the width
	 * @param int $height the height
	 * @param int $background the background
	 * @param boolean $force
	 * @param boolean $rescale
	 * @param boolean $crop
	 *
	 * @return $this
	 */
	public function resize($width = null, $height = null, $background = 0xffffff, $force = false, $rescale = false, $crop = false);

	/**
	 * Crops the image
	 *
	 * @param int $x the top-left x position of the crop box
	 * @param int $y the top-left y position of the crop box
	 * @param int $width the width of the crop box
	 * @param int $height the height of the crop box
	 *
	 * @return $this
	 */
	public function crop($x, $y, $width, $height);

	/**
	 * enable progressive image loading
	 *
	 * @return $this
	 */
	public function enableProgressive();

	/**
	 * Resizes the image forcing the destination to have exactly the
	 * given width and the height
	 *
	 * @param int $width the width
	 * @param int $height the height
	 * @param int $background the background
	 *
	 * @return $this
	 */
	public function forceResize($width = null, $height = null, $background = 0xffffff);

	/**
	 * Perform a zoom crop of the image to desired width and height
	 *
	 * @param integer $width  Desired width
	 * @param integer $height Desired height
	 * @param int $background
	 *
	 * @return $this
	 */
	public function zoomCrop($width, $height, $background = 0xffffff);


	/**
	 * Fills the image background to $bg if the image is transparent
	 *
	 * @param int $background background color
	 *
	 * @return $this
	 */
	public function fillBackground($background = 0xffffff);


	/**
	 * Negates the image
	 *
	 * @return $this
	 */
	public function negate();

	/**
	 * Changes the brightness of the image
	 *
	 * @param int $brightness the brightness
	 *
	 * @return $this
	 */
	public function brightness($brightness);

	/**
	 * Contrasts the image
	 *
	 * @param int $contrast the contrast [-100, 100]
	 *
	 * @return $this
	 */
	public function contrast($contrast);

	/**
	 * Apply a grayscale level effect on the image
	 *
	 * @return $this
	 */
	public function grayscale();

	/**
	 * Emboss the image
	 *
	 * @return $this
	 */
	public function emboss();

	/**
	 * Smooth the image
	 *
	 * @param int $p value between [-10,10]
	 *
	 * @return $this
	 */
	public function smooth($p);

	/**
	 * Sharps the image
	 *
	 * @return $this
	 */
	public function sharp();

	/**
	 * Edges the image
	 *
	 * @return $this
	 */
	public function edge();

	/**
	 * Colorize the image
	 *
	 * @param int $red value in range [-255, 255]
	 * @param int $green value in range [-255, 255]
	 * @param int $blue value in range [-255, 255]
	 *
	 * @return $this
	 */
	public function colorize($red, $green, $blue);

	/**
	 * apply sepia to the image
	 *
	 * @return $this
	 */
	public function sepia();

	/**
	 * Merge with another image
	 *
	 * @param Image $other
	 * @param int $x
	 * @param int $y
	 * @param int $width
	 * @param int $height
	 *
	 * @return $this
	 */
	public function merge(Image $other, $x = 0, $y = 0, $width = null, $height = null);

	/**
	 * Rotate the image
	 *
	 * @param float $angle
	 * @param int $background
	 *
	 * @return $this
	 */
	public function rotate($angle, $background = 0xffffff);

	/**
	 * Fills the image
	 *
	 * @param int $color
	 * @param int $x
	 * @param int $y
	 *
	 * @return $this
	 */
	public function fill($color = 0xffffff, $x = 0, $y = 0);

	/**
	 * write text to the image
	 *
	 * @param string $font
	 * @param string $text
	 * @param int $x
	 * @param int $y
	 * @param int $size
	 * @param int $angle
	 * @param int $color
	 * @param string $align
	 */
	public function write($font, $text, $x = 0, $y = 0, $size = 12, $angle = 0, $color = 0x000000, $align = 'left');

	/**
	 * Draws a rectangle
	 *
	 * @param int $x1
	 * @param int $y1
	 * @param int $x2
	 * @param int $y2
	 * @param int $color
	 * @param bool $filled
	 *
	 * @return $this
	 */
	public function rectangle($x1, $y1, $x2, $y2, $color, $filled = false);

	/**
	 * Draws a rounded rectangle
	 *
	 * @param int $x1
	 * @param int $y1
	 * @param int $x2
	 * @param int $y2
	 * @param int $radius
	 * @param int $color
	 * @param bool $filled
	 *
	 * @return $this
	 */
	public function roundedRectangle($x1, $y1, $x2, $y2, $radius, $color, $filled = false);

	/**
	 * Draws a line
	 *
	 * @param int $x1
	 * @param int $y1
	 * @param int $x2
	 * @param int $y2
	 * @param int $color
	 *
	 * @return $this
	 */
	public function line($x1, $y1, $x2, $y2, $color = 0x000000);

	/**
	 * Draws an ellipse
	 *
	 * @param int $cx
	 * @param int $cy
	 * @param int $width
	 * @param int $height
	 * @param int $color
	 * @param bool $filled
	 *
	 * @return $this
	 */
	public function ellipse($cx, $cy, $width, $height, $color = 0x000000, $filled = false);

	/**
	 * Draws a circle
	 *
	 * @param int $cx
	 * @param int $cy
	 * @param int $r
	 * @param int $color
	 * @param bool $filled
	 *
	 * @return $this
	 */
	public function circle($cx, $cy, $r, $color = 0x000000, $filled = false);

	/**
	 * Draws a polygon
	 *
	 * @param array $points
	 * @param int $color
	 * @param bool $filled
	 *
	 * @return $this
	 */
	public function polygon(array $points, $color, $filled = false);
}
