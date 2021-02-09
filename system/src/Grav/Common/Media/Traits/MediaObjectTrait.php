<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media\Traits;

use Grav\Common\Data\Data;
use Grav\Common\Media\Interfaces\MediaFileInterface;
use Grav\Common\Media\Interfaces\MediaLinkInterface;
use Grav\Common\Media\Interfaces\MediaObjectInterface;
use Grav\Common\Page\Medium\ThumbnailImageMedium;
use Grav\Common\Utils;
use function count;
use function func_get_args;
use function in_array;
use function is_array;
use function is_string;

/**
 * Class Medium
 * @package Grav\Common\Page\Medium
 *
 * @property string $mime
 */
trait MediaObjectTrait
{
    /** @var string */
    protected $mode = 'source';

    /** @var MediaObjectInterface|null */
    protected $_thumbnail;

    /** @var array */
    protected $thumbnailTypes = ['page', 'default'];

    /** @var string|null */
    protected $thumbnailType;

    /** @var MediaObjectInterface[] */
    protected $alternatives = [];

    /** @var array */
    protected $attributes = [];

    /** @var array */
    protected $styleAttributes = [];

    /** @var array */
    protected $metadata = [];

    /** @var array */
    protected $medium_querystring = [];

    /** @var string */
    protected $timestamp;

    /**
     * Create a copy of this media object
     *
     * @return static
     */
    public function copy()
    {
        return clone $this;
    }

    /**
     * Return just metadata from the Medium object
     *
     * @return Data
     */
    public function meta()
    {
        return new Data($this->getItems());
    }

    /**
     * Set querystring to file modification timestamp (or value provided as a parameter).
     *
     * @param string|int|null $timestamp
     * @return $this
     */
    public function setTimestamp($timestamp = null)
    {
        if (null !== $timestamp) {
            $this->timestamp = (string)($timestamp);
        } elseif ($this instanceof MediaFileInterface) {
            $this->timestamp = (string)$this->modified();
        } else {
            $this->timestamp = '';
        }

        return $this;
    }

    /**
     * Returns an array containing just the metadata
     *
     * @return array
     */
    public function metadata()
    {
        return $this->metadata;
    }

    /**
     * Add meta file for the medium.
     *
     * @param string $filepath
     */
    abstract public function addMetaFile($filepath);

    /**
     * Add alternative Medium to this Medium.
     *
     * @param int|float $ratio
     * @param MediaObjectInterface $alternative
     */
    public function addAlternative($ratio, MediaObjectInterface $alternative)
    {
        if (!is_numeric($ratio) || $ratio === 0) {
            return;
        }

        $alternative->set('ratio', $ratio);
        $width = $alternative->get('width');

        $this->alternatives[$width] = $alternative;
    }

    /**
     * Return string representation of the object (html).
     *
     * @return string
     */
    abstract public function __toString();

    /**
     * Get/set querystring for the file's url
     *
     * @param  string|null  $querystring
     * @param  bool $withQuestionmark
     * @return string
     */
    public function querystring($querystring = null, $withQuestionmark = true)
    {
        if (null !== $querystring) {
            $this->medium_querystring[] = ltrim($querystring, '?&');
            foreach ($this->alternatives as $alt) {
                $alt->querystring($querystring, $withQuestionmark);
            }
        }

        if (empty($this->medium_querystring)) {
            return '';
        }

        // join the strings
        $querystring = implode('&', $this->medium_querystring);
        // explode all strings
        $query_parts = explode('&', $querystring);
        // Join them again now ensure the elements are unique
        $querystring = implode('&', array_unique($query_parts));

        return $withQuestionmark ? ('?' . $querystring) : $querystring;
    }

    /**
     * Get the URL with full querystring
     *
     * @param string $url
     * @return string
     */
    public function urlQuerystring($url)
    {
        $querystring = $this->querystring();
        if (isset($this->timestamp) && !Utils::contains($querystring, $this->timestamp)) {
            $querystring = empty($querystring) ? ('?' . $this->timestamp) : ($querystring . '&' . $this->timestamp);
        }

        return ltrim($url . $querystring . $this->urlHash(), '/');
    }

    /**
     * Get/set hash for the file's url
     *
     * @param  string|null  $hash
     * @param  bool $withHash
     * @return string
     */
    public function urlHash($hash = null, $withHash = true)
    {
        if ($hash) {
            $this->set('urlHash', ltrim($hash, '#'));
        }

        $hash = $this->get('urlHash', '');

        return $withHash && !empty($hash) ? '#' . $hash : $hash;
    }

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
    public function parsedownElement($title = null, $alt = null, $class = null, $id = null, $reset = true)
    {
        $attributes = $this->attributes;
        $items = $this->getItems();

        $style = '';
        foreach ($this->styleAttributes as $key => $value) {
            if (is_numeric($key)) { // Special case for inline style attributes, refer to style() method
                $style .= $value;
            } else {
                $style .= $key . ': ' . $value . ';';
            }
        }
        if ($style) {
            $attributes['style'] = $style;
        }

        if (empty($attributes['title'])) {
            if (!empty($title)) {
                $attributes['title'] = $title;
            } elseif (!empty($items['title'])) {
                $attributes['title'] = $items['title'];
            }
        }

        if (empty($attributes['alt'])) {
            if (!empty($alt)) {
                $attributes['alt'] = $alt;
            } elseif (!empty($items['alt'])) {
                $attributes['alt'] = $items['alt'];
            } elseif (!empty($items['alt_text'])) {
                $attributes['alt'] = $items['alt_text'];
            } else {
                $attributes['alt'] = '';
            }
        }

        if (empty($attributes['class'])) {
            if (!empty($class)) {
                $attributes['class'] = $class;
            } elseif (!empty($items['class'])) {
                $attributes['class'] = $items['class'];
            }
        }

        if (empty($attributes['id'])) {
            if (!empty($id)) {
                $attributes['id'] = $id;
            } elseif (!empty($items['id'])) {
                $attributes['id'] = $items['id'];
            }
        }

        switch ($this->mode) {
            case 'text':
                $element = $this->textParsedownElement($attributes, false);
                break;
            case 'thumbnail':
                $thumbnail = $this->getThumbnail();
                $element = $thumbnail ? $thumbnail->sourceParsedownElement($attributes, false) : [];
                break;
            case 'source':
                $element = $this->sourceParsedownElement($attributes, false);
                break;
            default:
                $element = [];
        }

        if ($reset) {
            $this->reset();
        }

        $this->display('source');

        return $element;
    }

    /**
     * Reset medium.
     *
     * @return $this
     */
    public function reset()
    {
        $this->attributes = [];

        return $this;
    }

    /**
     * Add custom attribute to medium.
     *
     * @param string $attribute
     * @param string $value
     * @return $this
     */
    public function attribute($attribute = null, $value = '')
    {
        if (!empty($attribute)) {
            $this->attributes[$attribute] = $value;
        }
        return $this;
    }

    /**
     * Switch display mode.
     *
     * @param string $mode
     *
     * @return MediaObjectInterface|null
     */
    public function display($mode = 'source')
    {
        if ($this->mode === $mode) {
            return $this;
        }

        $this->mode = $mode;
        if ($mode === 'thumbnail') {
            $thumbnail = $this->getThumbnail();

            return $thumbnail ? $thumbnail->reset() : null;
        }

        return $this->reset();
    }

    /**
     * Helper method to determine if this media item has a thumbnail or not
     *
     * @param string $type;
     * @return bool
     */
    public function thumbnailExists($type = 'page')
    {
        $thumbs = $this->get('thumbnails');

        return isset($thumbs[$type]);
    }

    /**
     * Switch thumbnail.
     *
     * @param string $type
     * @return $this
     */
    public function thumbnail($type = 'auto')
    {
        if ($type !== 'auto' && !in_array($type, $this->thumbnailTypes, true)) {
            return $this;
        }

        if ($this->thumbnailType !== $type) {
            $this->_thumbnail = null;
        }

        $this->thumbnailType = $type;

        return $this;
    }

    /**
     * Return URL to file.
     *
     * @param bool $reset
     * @return string
     */
    abstract public function url($reset = true);

    /**
     * Turn the current Medium into a Link
     *
     * @param  bool $reset
     * @param  array  $attributes
     * @return MediaLinkInterface
     */
    public function link($reset = true, array $attributes = [])
    {
        if ($this->mode !== 'source') {
            $this->display('source');
        }

        foreach ($this->attributes as $key => $value) {
            empty($attributes['data-' . $key]) && $attributes['data-' . $key] = $value;
        }

        empty($attributes['href']) && $attributes['href'] = $this->url();

        return $this->createLink($attributes);
    }

    /**
     * Turn the current Medium into a Link with lightbox enabled
     *
     * @param  int|null  $width
     * @param  int|null  $height
     * @param  bool $reset
     * @return MediaLinkInterface
     */
    public function lightbox($width = null, $height = null, $reset = true)
    {
        $attributes = ['rel' => 'lightbox'];

        if ($width && $height) {
            $attributes['data-width'] = $width;
            $attributes['data-height'] = $height;
        }

        return $this->link($reset, $attributes);
    }

    /**
     * Add a class to the element from Markdown or Twig
     * Example: ![Example](myimg.png?classes=float-left) or ![Example](myimg.png?classes=myclass1,myclass2)
     *
     * @return $this
     */
    public function classes()
    {
        $classes = func_get_args();
        if (!empty($classes)) {
            $this->attributes['class'] = implode(',', $classes);
        }

        return $this;
    }

    /**
     * Add an id to the element from Markdown or Twig
     * Example: ![Example](myimg.png?id=primary-img)
     *
     * @param string $id
     * @return $this
     */
    public function id($id)
    {
        if (is_string($id)) {
            $this->attributes['id'] = trim($id);
        }

        return $this;
    }

    /**
     * Allows to add an inline style attribute from Markdown or Twig
     * Example: ![Example](myimg.png?style=float:left)
     *
     * @param string $style
     * @return $this
     */
    public function style($style)
    {
        $this->styleAttributes[] = rtrim($style, ';') . ';';

        return $this;
    }

    /**
     * Allow any action to be called on this medium from twig or markdown
     *
     * @param string $method
     * @param array $args
     * @return $this
     */
    public function __call($method, $args)
    {
        $count = count($args);
        if ($count > 1 || ($count === 1 && !empty($args[0]))) {
            $method .= '=' . implode(',', array_map(static function ($a) {
                if (is_array($a)) {
                    $a = '[' . implode(',', $a) . ']';
                }

                return rawurlencode($a);
            }, $args));
        }

        if (!empty($method)) {
            $this->querystring($this->querystring(null, false) . '&' . $method);
        }

        return $this;
    }

    /**
     * Parsedown element for source display mode
     *
     * @param  array $attributes
     * @param  bool $reset
     * @return array
     */
    protected function sourceParsedownElement(array $attributes, $reset = true)
    {
        return $this->textParsedownElement($attributes, $reset);
    }

    /**
     * Parsedown element for text display mode
     *
     * @param  array $attributes
     * @param  bool $reset
     * @return array
     */
    protected function textParsedownElement(array $attributes, $reset = true)
    {
        if ($reset) {
            $this->reset();
        }

        $text = $attributes['title'] ?? '';
        if ($text === '') {
            $text = $attributes['alt'] ?? '';
            if ($text === '') {
                $text = $this->get('filename');
            }
        }

        return [
            'name' => 'p',
            'attributes' => $attributes,
            'text' => $text
        ];
    }

    /**
     * Get the thumbnail Medium object
     *
     * @return ThumbnailImageMedium|null
     */
    protected function getThumbnail()
    {
        if (null === $this->_thumbnail) {
            $types = $this->thumbnailTypes;

            if ($this->thumbnailType !== 'auto') {
                array_unshift($types, $this->thumbnailType);
            }

            foreach ($types as $type) {
                $thumb = $this->get("thumbnails.{$type}", false);

                if ($thumb) {
                    $thumb = $thumb instanceof ThumbnailImageMedium ? $thumb : $this->createThumbnail($thumb);
                    $thumb->parent = $this;
                    $this->_thumbnail = $thumb;
                    break;
                }
            }
        }

        return $this->_thumbnail;
    }

    /**
     * Get value by using dot notation for nested arrays/objects.
     *
     * @example $value = $this->get('this.is.my.nested.variable');
     *
     * @param string $name Dot separated path to the requested value.
     * @param mixed $default Default value (or null).
     * @param string|null $separator Separator, defaults to '.'
     * @return mixed Value.
     */
    abstract public function get($name, $default = null, $separator = null);

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
    abstract public function set($name, $value, $separator = null);

    /**
     * @param string $thumb
     */
    abstract protected function createThumbnail($thumb);

    /**
     * @param array $attributes
     * @return MediaLinkInterface
     */
    abstract protected function createLink(array $attributes);

    /**
     * @return array
     */
    abstract protected function getItems(): array;
}
