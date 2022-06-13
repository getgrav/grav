<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media\Interfaces;

use ArrayAccess;
use Grav\Common\Data\Data;

/**
 * Class implements media object interface.
 *
 * @property string $type
 * @property string $filename
 * @property string $filepath
 */
interface MediaObjectInterface extends \Grav\Framework\Media\Interfaces\MediaObjectInterface, ArrayAccess
{
    /**
     * Create a copy of this media object
     *
     * @return static
     */
    public function copy();

    /**
     * Return just metadata from the Medium object
     *
     * @return Data
     */
    public function meta();

    /**
     * Set querystring to file modification timestamp (or value provided as a parameter).
     *
     * @param string|int|null $timestamp
     * @return $this
     */
    public function setTimestamp($timestamp = null);

    /**
     * Returns an array containing just the metadata
     *
     * @return array
     */
    public function metadata();

    /**
     * Add meta file for the medium.
     *
     * @param string $filepath
     */
    public function addMetaFile($filepath);

    /**
     * Add alternative Medium to this Medium.
     *
     * @param int|float $ratio
     * @param MediaObjectInterface $alternative
     */
    public function addAlternative($ratio, MediaObjectInterface $alternative);

    /**
     * Get list of image alternatives. Includes the current media image as well.
     *
     * @param bool $withDerived If true, include generated images as well. If false, only return existing files.
     * @return array
     */
    public function getAlternatives(bool $withDerived = true): array;

    /**
     * Return string representation of the object (html).
     *
     * @return string
     */
    public function __toString();

    /**
     * Get/set querystring for the file's url
     *
     * @param  string|null  $querystring
     * @param  bool $withQuestionmark
     * @return string
     */
    public function querystring($querystring = null, $withQuestionmark = true);

    /**
     * Get the URL with full querystring
     *
     * @param string $url
     * @return string
     */
    public function urlQuerystring($url);

    /**
     * Get/set hash for the file's url
     *
     * @param  string|null $hash
     * @param  bool $withHash
     * @return string
     */
    public function urlHash($hash = null, $withHash = true);

    /**
     * Get an element (is array) that can be rendered by the Parsedown engine
     *
     * @param  string|null  $title
     * @param  string|null  $alt
     * @param  string|null  $class
     * @param  string|null  $id
     * @param  bool $reset
     * @return array
     */
    public function parsedownElement($title = null, $alt = null, $class = null, $id = null, $reset = true);

    /**
     * Reset medium.
     *
     * @return $this
     */
    public function reset();

    /**
     * Add custom attribute to medium.
     *
     * @param string $attribute
     * @param string $value
     * @return $this
     */
    public function attribute($attribute = null, $value = '');

    /**
     * Switch display mode.
     *
     * @param string $mode
     * @return MediaObjectInterface|null
     */
    public function display($mode = 'source');

    /**
     * Helper method to determine if this media item has a thumbnail or not
     *
     * @param string $type;
     * @return bool
     */
    public function thumbnailExists($type = 'page');

    /**
     * Switch thumbnail.
     *
     * @param string $type
     * @return $this
     */
    public function thumbnail($type = 'auto');

    /**
     * Turn the current Medium into a Link
     *
     * @param  bool $reset
     * @param  array  $attributes
     * @return MediaLinkInterface
     */
    public function link($reset = true, array $attributes = []);

    /**
     * Turn the current Medium into a Link with lightbox enabled
     *
     * @param  int  $width
     * @param  int  $height
     * @param  bool $reset
     * @return MediaLinkInterface
     */
    public function lightbox($width = null, $height = null, $reset = true);

    /**
     * Add a class to the element from Markdown or Twig
     * Example: ![Example](myimg.png?classes=float-left) or ![Example](myimg.png?classes=myclass1,myclass2)
     *
     * @return $this
     */
    public function classes();

    /**
     * Add an id to the element from Markdown or Twig
     * Example: ![Example](myimg.png?id=primary-img)
     *
     * @param string $id
     * @return $this
     */
    public function id($id);

    /**
     * Allows to add an inline style attribute from Markdown or Twig
     * Example: ![Example](myimg.png?style=float:left)
     *
     * @param string $style
     * @return $this
     */
    public function style($style);

    /**
     * Allow any action to be called on this medium from twig or markdown
     *
     * @param string $method
     * @param mixed $args
     * @return $this
     */
    #[\ReturnTypeWillChange]
    public function __call($method, $args);

    /**
     * Set value by using dot notation for nested arrays/objects.
     *
     * @example $data->set('this.is.my.nested.variable', $value);
     *
     * @param string $name Dot separated path to the requested value.
     * @param mixed $value New value.
     * @param string|null $separator Separator, defaults to '.'
     * @return $this
     */
    public function set($name, $value, $separator = null);
}
