<?php
/**
 * @package    Grav.Common.Page
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Page\Medium;

use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use Grav\Common\Data\Data;
use Grav\Common\Data\Blueprint;

class Medium extends Data implements RenderableInterface
{
    use ParsedownHtmlTrait;

    /**
     * @var string
     */
    protected $mode = 'source';

    /**
     * @var Medium
     */
    protected $_thumbnail = null;

    /**
     * @var array
     */
    protected $thumbnailTypes = [ 'page', 'default' ];

    protected $thumbnailType = null;

    /**
     * @var Medium[]
     */
    protected $alternatives = [];

    /**
     * @var array
     */
    protected $attributes = [];

    /**
     * @var array
     */
    protected $styleAttributes = [];

    /**
     * Construct.
     *
     * @param array $items
     * @param Blueprint $blueprint
     */
    public function __construct($items = [], Blueprint $blueprint = null)
    {
        parent::__construct($items, $blueprint);

        if (Grav::instance()['config']->get('system.media.enable_media_timestamp', true)) {
            $this->querystring('&' . Grav::instance()['cache']->getKey());
        }

        $this->def('mime', 'application/octet-stream');
        $this->reset();
    }

    /**
     * Return just metadata from the Medium object
     *
     * @return $this
     */
    public function meta()
    {
        return new Data($this->items);
    }

    /**
     * Add meta file for the medium.
     *
     * @param $filepath
     */
    public function addMetaFile($filepath)
    {
        $this->merge(CompiledYamlFile::instance($filepath)->content());
    }

    /**
     * Add alternative Medium to this Medium.
     *
     * @param $ratio
     * @param Medium $alternative
     */
    public function addAlternative($ratio, Medium $alternative)
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
    public function __toString()
    {
        return $this->html();
    }

    /**
     * Return PATH to file.
     *
     * @param bool $reset
     * @return string path to file
     */
    public function path($reset = true)
    {
        if ($reset) {
            $this->reset();
        }

        return $this->get('filepath');
    }

    /**
     * Return URL to file.
     *
     * @param bool $reset
     * @return string
     */
    public function url($reset = true)
    {
        $output = preg_replace('|^' . preg_quote(GRAV_ROOT) . '|', '', $this->get('filepath'));

        if ($reset) {
            $this->reset();
        }

        return Grav::instance()['base_url'] . $output . $this->querystring() . $this->urlHash();
    }

    /**
     * Get/set querystring for the file's url
     *
     * @param  string  $hash
     * @param  boolean $withHash
     * @return string
     */
    public function querystring($querystring = null, $withQuestionmark = true)
    {
        if (!is_null($querystring)) {
            $this->set('querystring', ltrim($querystring, '?&'));

            foreach ($this->alternatives as $alt) {
                $alt->querystring($querystring, $withQuestionmark);
            }
        }

        $querystring = $this->get('querystring', '');

        if ($withQuestionmark && !empty($querystring)) {
            return '?' . $querystring;
        } else {
            return $querystring;
        }
    }

    /**
     * Get/set hash for the file's url
     *
     * @param  string  $hash
     * @param  boolean $withHash
     * @return string
     */
    public function urlHash($hash = null, $withHash = true)
    {
        if ($hash) {
            $this->set('urlHash', ltrim($hash, '#'));
        }

        $hash = $this->get('urlHash', '');

        if ($withHash && !empty($hash)) {
            return '#' . $hash;
        } else {
            return $hash;
        }
    }

    /**
     * Get an element (is array) that can be rendered by the Parsedown engine
     *
     * @param  string  $title
     * @param  string  $alt
     * @param  string  $class
     * @param  string  $id
     * @param  boolean $reset
     * @return array
     */
    public function parsedownElement($title = null, $alt = null, $class = null, $id = null, $reset = true)
    {
        $attributes = $this->attributes;

        $style = '';
        foreach ($this->styleAttributes as $key => $value) {
            if (is_numeric($key)) // Special case for inline style attributes, refer to style() method
                $style .= $value;
            else
                $style .= $key . ': ' . $value . ';';
        }
        if ($style) {
            $attributes['style'] = $style;
        }

        if (empty($attributes['title'])) {
            if (!empty($title)) {
                $attributes['title'] = $title;
            } elseif (!empty($this->items['title'])) {
                $attributes['title'] = $this->items['title'];
            }
        }

        if (empty($attributes['alt'])) {
            if (!empty($alt)) {
                $attributes['alt'] = $alt;
            } elseif (!empty($this->items['alt'])) {
                $attributes['alt'] = $this->items['alt'];
            }
        }

        if (empty($attributes['class'])) {
            if (!empty($class)) {
                $attributes['class'] = $class;
            } elseif (!empty($this->items['class'])) {
                $attributes['class'] = $this->items['class'];
            }
        }

        if (empty($attributes['id'])) {
            if (!empty($id)) {
                $attributes['id'] = $id;
            } elseif (!empty($this->items['id'])) {
                $attributes['id'] = $this->items['id'];
            }
        }

        switch ($this->mode) {
            case 'text':
                $element = $this->textParsedownElement($attributes, false);
                break;
            case 'thumbnail':
                $element = $this->getThumbnail()->sourceParsedownElement($attributes, false);
                break;
            case 'source':
                $element = $this->sourceParsedownElement($attributes, false);
                break;
        }

        if ($reset) {
            $this->reset();
        }

        $this->display('source');

        return $element;
    }

    /**
     * Parsedown element for source display mode
     *
     * @param  array $attributes
     * @param  boolean $reset
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
     * @param  boolean $reset
     * @return array
     */
    protected function textParsedownElement(array $attributes, $reset = true)
    {
        $text = empty($attributes['title']) ? empty($attributes['alt']) ? $this->get('filename') : $attributes['alt'] : $attributes['title'];

        $element = [
            'name' => 'p',
            'attributes' => $attributes,
            'text' => $text
        ];

        if ($reset) {
            $this->reset();
        }

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
     * Switch display mode.
     *
     * @param string $mode
     *
     * @return $this
     */
    public function display($mode = 'source')
    {
        if ($this->mode === $mode) {
            return $this;
        }


        $this->mode = $mode;

        return $mode === 'thumbnail' ? $this->getThumbnail()->reset() : $this->reset();
    }

    /**
     * Switch thumbnail.
     *
     * @param string $type
     *
     * @return $this
     */
    public function thumbnail($type = 'auto')
    {
        if ($type !== 'auto' && !in_array($type, $this->thumbnailTypes)) {
            return $this;
        }

        if ($this->thumbnailType !== $type) {
            $this->_thumbnail = null;
        }

        $this->thumbnailType = $type;

        return $this;
    }

    /**
     * Turn the current Medium into a Link
     *
     * @param  boolean $reset
     * @param  array  $attributes
     * @return Link
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

        return new Link($attributes, $this);
    }

    /**
     * Turn the current Medium into a Link with lightbox enabled
     *
     * @param  int  $width
     * @param  int  $height
     * @param  boolean $reset
     * @return Link
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
            $this->attributes['class'] = implode(',', (array)$classes);
        }

        return $this;
    }

    /**
     * Add an id to the element from Markdown or Twig
     * Example: ![Example](myimg.png?id=primary-img)
     *
     * @param $id
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
     * @param mixed $args
     * @return $this
     */
    public function __call($method, $args)
    {
        $qs = $method;
        if (count($args) > 1 || (count($args) == 1 && !empty($args[0]))) {
            $qs .= '=' . implode(',', array_map(function ($a) { return urlencode($a); }, $args));
        }

        if (!empty($qs)) {
            $this->querystring($this->querystring(null, false) . '&' . $qs);
        }

        return $this;
    }

    /**
     * Get the thumbnail Medium object
     *
     * @return ThumbnailImageMedium
     */
    protected function getThumbnail()
    {
        if (!$this->_thumbnail) {
            $types = $this->thumbnailTypes;

            if ($this->thumbnailType !== 'auto') {
                array_unshift($types, $this->thumbnailType);
            }

            foreach ($types as $type) {
                $thumb = $this->get('thumbnails.' . $type, false);

                if ($thumb) {
                    $thumb = $thumb instanceof ThumbnailImageMedium ? $thumb : MediumFactory::fromFile($thumb, ['type' => 'thumbnail']);
                    $thumb->parent = $this;
                }

                if ($thumb) {
                    $this->_thumbnail = $thumb;
                    break;
                }
            }
        }

        return $this->_thumbnail;
    }

}
