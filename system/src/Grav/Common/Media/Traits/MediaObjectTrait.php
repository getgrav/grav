<?php

/**
 * @package    Grav\Common\Media
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Media\Traits;

use Grav\Common\Data\Data;
use Grav\Common\Media\Interfaces\ImageMediaInterface;
use Grav\Common\Media\Interfaces\MediaFileInterface;
use Grav\Common\Media\Interfaces\MediaLinkInterface;
use Grav\Common\Media\Interfaces\MediaObjectInterface;
use Grav\Common\Page\Medium\Medium;
use Grav\Common\Page\Medium\ThumbnailImageMedium;
use Grav\Common\Utils;
use RuntimeException;
use function count;
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
    public ?string $timestamp = null;
    /** @var array */
    public array $metadata = [];

    protected string $mode = 'source';
    protected ?MediaObjectInterface $_thumbnail = null;
    /** @var string[] */
    protected array $thumbnailTypes = ['page', 'default'];
    protected ?string $thumbnailType = null;
    /** @var MediaObjectInterface[] */
    protected array $alternatives = [];
    /** @var array */
    protected array $attributes = [];
    /** @var array */
    protected array $styleAttributes = [];
    /** @var array */
    protected array $medium_querystring = [];

    /**
     * @return array
     * @phpstan-pure
     */
    private function serializeMediaObjectTrait(): array
    {
        return [
            'mode' => $this->mode,
            'thumbnailTypes' => $this->thumbnailTypes,
            'thumbnailType' => $this->thumbnailType,
            'alternatives' => $this->alternatives,
            'attributes' => $this->attributes,
            'styleAttributes' => $this->styleAttributes,
            'metadata' => $this->metadata,
            'medium_querystring' => $this->medium_querystring,
            'timestamp' => $this->timestamp,
        ];
    }

    /**
     * @param array $data
     * @return void
     * @phpstan-impure
     */
    private function unserializeMediaObjectTrait(array $data): void
    {
        $this->mode = $data['mode'];
        $this->thumbnailTypes = $data['thumbnailTypes'];
        $this->thumbnailType = $data['thumbnailType'];
        $this->alternatives = $data['alternatives'];
        $this->attributes = $data['attributes'];
        $this->styleAttributes = $data['styleAttributes'];
        $this->metadata = $data['metadata'];
        $this->medium_querystring = $data['medium_querystring'];
        $this->timestamp = $data['timestamp'];
    }

    /**
     * Create a copy of this media object
     *
     * @return static
     * @phpstan-pure
     */
    public function copy()
    {
        return clone $this;
    }

    /**
     * Return just metadata from the Medium object
     *
     * @return Data
     * @phpstan-pure
     */
    public function meta(): Data
    {
        return new Data($this->getItems());
    }

    /**
     * Set querystring to file modification timestamp (or value provided as a parameter).
     *
     * @param string|int|null $timestamp
     * @return $this
     * @phpstan-impure
     */
    public function setTimestamp($timestamp = null)
    {
        if (null !== $timestamp) {
            $timestamp = (string)$timestamp;
        } else {
            $timestamp = null;
        }

        if ($timestamp === $this->timestamp) {
            return $this;
        }

        $this->timestamp = $timestamp;

        return $this;
    }

    /**
     * Returns an array containing just the metadata
     *
     * @return array
     * @phpstan-pure
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * Add meta file for the medium.
     *
     * @param string $filepath
     * @return void
     * @phpstan-impure
     */
    abstract public function addMetaFile(string $filepath): void;

    /**
     * Add alternative Medium to this Medium.
     *
     * @param int|float $ratio
     * @param MediaObjectInterface $alternative
     * @return void
     * @phpstan-impure
     */
    public function addAlternative($ratio, MediaObjectInterface $alternative): void
    {
        if (!is_numeric($ratio) || $ratio === 0) {
            return;
        }

        $alternative->set('ratio', $ratio);
        $width = $alternative->get('width', 0);

        $this->alternatives[$width] = $alternative;
    }

    /**
     * @param bool $withDerived
     * @return array
     * @phpstan-pure
     */
    public function getAlternatives(bool $withDerived = true): array
    {
        $alternatives = [];
        foreach ($this->alternatives + [$this->get('width', 0) => $this] as $size => $alternative) {
            if ($withDerived || $alternative->filename === Utils::basename($alternative->filepath)) {
                $alternatives[$size] = $alternative;
            }
        }

        ksort($alternatives, SORT_NUMERIC);

        return $alternatives;
    }

    /**
     * Return string representation of the object (html).
     *
     * @return string
     * @phpstan-pure
     */
    abstract public function __toString(): string;

    /**
     * Get/set query string for the file's url
     *
     * @param  string|null  $querystring
     * @param  bool $withQuestionmark
     * @return string
     * @phpstan-impure
     */
    public function querystring(string $querystring = null, bool $withQuestionmark = true): string
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
     * Get the URL with full query string
     *
     * @param string $url
     * @return string
     * @phpstan-pure
     */
    public function urlQuerystring(string $url): string
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
     * @phpstan-impure
     */
    public function urlHash(string $hash = null, bool $withHash = true): string
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
     * @phpstan-impure
     */
    public function parsedownElement(string $title = null, string $alt = null, string $class = null, string $id = null, bool $reset = true): array
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
                $element = $this->textParsedownElement($attributes);
                break;
            case 'thumbnail':
                $thumbnail = $this->getThumbnail();
                $element = $thumbnail->sourceParsedownElement($attributes);
                break;
            case 'source':
                $element = $this->sourceParsedownElement($attributes);
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
     * @phpstan-impure
     */
    public function reset()
    {
        $this->attributes = [];
        $this->medium_querystring = [];

        return $this;
    }

    /**
     * Add custom attribute to medium.
     *
     * @param string $attribute
     * @param string|null $value
     * @return $this
     * @phpstan-impure
     */
    public function attribute(string $attribute = '', ?string $value = '')
    {
        if ('' === $attribute) {
            return $this;
        }

        $currentValue = $this->attributes[$attribute] ?? null;
        if ($currentValue === $value) {
            return $this;
        }

        if (null !== $value) {
            $this->attributes[$attribute] = $value;
        } else {
            unset($this->attributes[$attribute]);
        }

        return $this;
    }

    /**
     * Switch display mode.
     *
     * @param string $mode
     * @return MediaLinkInterface|MediaObjectInterface|null
     * @phpstan-impure
     */
    public function display(string $mode = 'source')
    {
        if ($this->mode === $mode) {
            return $this;
        }

        $this->mode = $mode;
        if ($mode === 'thumbnail') {
            return $this->getThumbnail()->reset();
        }

        return $this->reset();
    }

    /**
     * Helper method to determine if this media item has a thumbnail or not
     *
     * @param string $type;
     * @return bool
     * @phpstan-pure
     */
    public function thumbnailExists(string $type = 'page'): bool
    {
        $thumbs = $this->get('thumbnails');

        return isset($thumbs[$type]);
    }

    /**
     * Switch thumbnail.
     *
     * @param string $type
     * @return $this
     * @phpstan-impure
     */
    public function thumbnail(string $type = 'auto')
    {
        if ($type !== 'auto' && !in_array($type, $this->thumbnailTypes, true)) {
            return $this;
        }
        if ($this->thumbnailType === $type) {
            return $this;
        }

        $this->thumbnailType = $type;
        $this->_thumbnail = null;

        return $this;
    }

    /**
     * Return URL to file.
     *
     * @param bool $reset
     * @return string
     * @phpstan-impure
     */
    abstract public function url(bool $reset = true): string;

    /**
     * Turn the current Medium into a Link
     *
     * @param  bool $reset
     * @param  array  $attributes
     * @return MediaLinkInterface
     * @phpstan-impure
     */
    public function link(bool $reset = true, array $attributes = []): MediaLinkInterface
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
     * @phpstan-impure
     */
    public function lightbox(int $width = null, int $height = null, bool $reset = true): MediaLinkInterface
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
     * @param string ...$args
     * @return $this
     * @phpstan-impure
     */
    public function classes(string ...$args)
    {
        if (!empty($args)) {
            $classes = implode(',', $args);
        } else {
            $classes = null;
        }

        $currentClasses = $this->attributes['class'] ?? null;
        if ($currentClasses === $classes) {
            return $this;
        }

        if ($classes) {
            $this->attributes['class'] = $classes;
        } else {
            unset($this->attributes['class']);
        }

        return $this;
    }

    /**
     * Add an id to the element from Markdown or Twig
     * Example: ![Example](myimg.png?id=primary-img)
     *
     * @param string|null $id
     * @return $this
     */
    public function id(string $id = null)
    {
        $currentId = $this->attributes['id'] ?? null;
        if ($currentId === $id) {
            return $this;
        }

        if ($id) {
            $this->attributes['id'] = trim($id);
        } else {
            unset($this->attributes['id']);
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
    public function style(string $style)
    {
        $this->styleAttributes[] = rtrim($style, ';') . ';';

        return $this;
    }

    /**
     * Allow any action to be called on this medium from twig or markdown
     *
     * @param string $method
     * @param array $args
     * @return MediaObjectInterface|MediaLinkInterface
     */
    public function __call(string $method, array $args)
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
     * @return array
     * @phpstan-pure
     */
    protected function sourceParsedownElement(array $attributes): array
    {
        return $this->textParsedownElement($attributes);
    }

    /**
     * Parsedown element for text display mode
     *
     * @param  array $attributes
     * @return array
     * @phpstan-pure
     */
    protected function textParsedownElement(array $attributes): array
    {
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
     * @return Medium&ImageMediaInterface
     * @phpstan-pure Changes internal state (cached image), but does not affect behavior or change object state.
     */
    protected function getThumbnail(): ImageMediaInterface
    {
        if (null === $this->_thumbnail) {
            $thumbnails = (array)$this->get('thumbnails') + ['system' => 'system://images/media/thumb.png'];
            $types = $this->thumbnailTypes;
            $types[] = 'system';

            if ($this->thumbnailType && $this->thumbnailType !== 'auto') {
                array_unshift($types, $this->thumbnailType);
            }

            $image = null;
            foreach ($types as $type) {
                $thumb = $thumbnails[$type] ?? null;
                if ($thumb) {
                    if (is_string($thumb)) {
                        $image = $this->createThumbnail($thumb);
                    } elseif ($thumb instanceof ThumbnailImageMedium) {
                        $image = $thumb;
                    } else {
                        throw new RuntimeException('Unsupported thumbnail type', 500);
                    }

                    if ($image) {
                        break;
                    }
                }
            }

            if (!$image) {
                throw new RuntimeException(sprintf("Default thumbnail image '%s' does not exist!", $thumbnails['system']), 500);
            }

            if (!$image instanceof ImageMediaInterface) {
                throw new RuntimeException(sprintf("Thumbnail '%s' is not an image", $image->filepath), 500);
            }

            $image->set('parent', $this);
            $this->_thumbnail = $image;
        }

        return $this->_thumbnail;
    }
}
