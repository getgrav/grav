<?php
namespace Grav\Common\Page\Medium;

use Grav\Common\Config\Config;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use Grav\Common\GravTrait;
use Grav\Common\Data\Blueprint;
use Grav\Common\Data\Data;
use Grav\Common\Markdown\Parsedown;
use Gregwar\Image\Image as ImageFile;

/**
 * The Image medium holds information related to an individual image. These are then stored in the Media object.
 *
 * @author Grav
 * @license MIT
 *
 */
class Medium extends Data
{
    use GravTrait;

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

    /**
     * @var \Grav\Common\Markdown\Parsedown
     */
    protected $parsedown = null;

    /**
     * @var Medium[]
     */
    protected $alternatives = array();

    /**
     * @var array
     */
    protected $attributes = [];

    /**
     * @var array
     */
    protected $linkAttributes = [];

    /**
     * Construct.
     *
     * @param array $items
     * @param Blueprint $blueprint
     */
    public function __construct($items = array(), Blueprint $blueprint = null)
    {
        parent::__construct($items, $blueprint);

        $this->def('mime', 'application/octet-stream');
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
        $this->alternatives[(float) $ratio] = $alternative;
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
     * @return  string path to file
     */
    public function path($reset = true)
    {
        if ($reset) $this->reset();

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
        $output = preg_replace('|^' . GRAV_ROOT . '|', '', $this->get('filepath'));

        return self::$grav['base_url'] . $output;
    }

    /**
     * Return HTML markup from the medium.
     *
     * @param string $title
     * @param string $class
     * @param bool $reset
     * @return string
     */
    public function html($title = null, $alt = null, $class = null, $reset = true)
    {
        $element = $this->parsedownElement($title, $alt, $class, $reset);

        if (!$this->parsedown) {
            $this->parsedown = new Parsedown(null);
        }

        return $this->parsedown->elementToHtml($element);
    }

    public function parsedownElement($title = null, $alt = null, $class = null, $reset = true)
    {
        $element;

        $attributes = $this->attributes;
        $link_attributes = $this->linkAttributes;

        !empty($title) && empty($attributes['title']) && $attributes['title'] = $title;
        !empty($alt) && empty($attributes['alt']) && $attributes['alt'] = $alt;
        !empty($class) && empty($attributes['class']) && $attributes['class'] = $class;

        switch ($this->mode) {
            case 'text':
                $element = $this->textParsedownElement($attributes, $reset);
                break;
            case 'thumbnail':
                $element = $this->getThumbnail()->sourceParsedownElement($attributes, $reset);
                break;
            case 'source':
                $element = $this->sourceParsedownElement($attributes, $reset);
                break;
        }

        if ($link_attributes) {
            
            $innerElement = $element;
            $element = [
                'name' => 'a',
                'attributes' => $this->linkAttributes,
                'handler' => is_string($innerElement) ? 'line' : 'element',
                'text' => $innerElement
            ];

            if ($reset) {
                $this->linkAttributes = [];
            }
        }

        $this->display('source');

        return $element;
    }

    public function sourceParsedownElement($attributes, $reset)
    {
        return $this->textParsedownElement($attributes, $reset);
    }

    public function textParsedownElement($attributes, $reset)
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
        return $this;
    }

    /**
     * Switch display mode.
     *
     * @param string $mode
     *
     * @return $this
     */
    public function display($mode)
    {
        if ($this->mode === $mode)
            return $this;

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
    public function thumbnail($type)
    {
        if (!in_array($type, $this->thumbnailTypes))
            return;

        if ($this->thumbnailType !== $type) {
            $this->_thumbnail = null;
        }

        $this->thumbnailType = $type;

        return $this;
    }

    /**
     * Enable link for the medium object.
     *
     * @param null $width
     * @param null $height
     * @return $this
     */
    public function link($reset = true)
    {
        if ($this->mode !== 'source') {
            $this->display('source');
        }

        $this->linkAttributes['href'] = $this->url();

        $this->thumbnail('auto');
        $thumb = $this->display('thumbnail');
        $thumb->linked = true;

        return $thumb;
    }

    /**
     * Enable lightbox for the medium.
     *
     * @param null $width
     * @param null $height
     * @return Medium
     */
    public function lightbox($width = null, $height = null, $reset = true)
    {
        $this->linkAttributes['rel'] = 'lightbox';

        if ($width && $height) {
            $this->linkAttributes['data-width'] = $width;
            $this->linkAttributes['data-height'] = $height;
        }

        return $this->link($reset);
    }

    /**
     * Forward the call to the image processing method.
     *
     * @param string $method
     * @param mixed $args
     * @return $this|mixed
     */
    public function __call($method, $args)
    {
        return $this;
    }

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
                    $thumb = $thumb instanceof ThumbnailMedium ? $thumb : Factory::fromFile($thumb, ['type' => 'thumbnail']);
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
