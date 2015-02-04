<?php
namespace Grav\Common\Page\Medium;

use Grav\Common\Config\Config;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use Grav\Common\GravTrait;
use Grav\Common\Data\Blueprint;
use Grav\Common\Data\Data;
use Gregwar\Image\Image as ImageFile;

class VideoMedium extends Medium
{
    protected $mode = 'video';

    protected $videoAttributes = [];

	public static $magic_actions = [
        // Gregwar OR internal depending on context
        'resize',

        // Gregwar Image functions
        'forceResize', 'cropResize', 'crop', 'cropZoom',
        'negate', 'brightness', 'contrast', 'grayscale',
        'emboss', 'smooth', 'sharp', 'edge', 'colorize', 'sepia'
    ];

    public function parsedownElement($title = null, $alt = null, $class = null, $reset = true)
    {
        $element;

        if (!$this->linkAttributes) {
            $video_location = $this->url(false);
            $video_attributes = $this->video_attributes;
            $video_attributes['controls'] = true;

            if ($reset) {
                $this->reset();
            }

            $element = [
                'name' => 'video',
                'text' => '<source src="' . $video_location . '">Your browser does not support the video tag.',
                'attributes' => $video_attributes
            ];
        } else {

            $thumbnail = $this->get('thumb');
            if ($thumbnail) {
                $innerElement = $thumbnail->parsedownElement($title, $alt, $class, $reset);
            } else {
                $innerElement = $title ? $title : $this->path(false);
            }

            $link_attributes = $this->linkAttributes;

            if ($class) {
                $link_attributes['class'] = $class;
            }

            $element = [
                'name' => 'a',
                'attributes' => $this->linkAttributes,
                'handler' => is_string($innerElement) ? 'line' : 'element',
                'text' => $innerElement
            ];

            $this->linkAttributes = [];
            if ($reset) {
                $this->reset();
            }
        }

        return $element;
    }

    /**
     * Enable link for the medium object.
     *
     * @param null $width
     * @param null $height
     * @return $this
     */
    public function link($width = null, $height = null, $reset = true)
    {
        $this->linkAttributes['href'] = $this->url();
                
        $this->mode = 'thumb';

        return $this;
    }

    public function reset()
    {
        $this->videoAttributes = [];
    }

    protected function _resize($width = null, $height = null)
    {
        $this->videoAttributes['width'] = $width;
        $this->videoAttributes['height'] = $height;

        return $this;
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
        if ($method == 'cropZoom') {
            $method = 'zoomCrop';
        }

        $mode = $this->mode;
        $target = null;

        if ($mode == 'video') {

            $target = $this;
            $valid = in_array($method, self::$magic_actions);

            $method = '_' . $method;

        } else if ($mode == 'thumb') {

            $target = $this->get('thumb');
            $target_class = get_class($target);
            $valid = $target && in_array($method, $target_class::$magic_actions);

        }

        if ($valid) {
            try {
                $result = call_user_func_array(array($target, $method), $args);
            } catch (\BadFunctionCallException $e) {
                $result = null;
            }
        }

        if ($mode == 'thumb' && $result) {
            $this->set('thumb', $result);
        }

        return $this;
    }
}