<?php
namespace Grav\Common\Page\Medium;

use Grav\Common\Config\Config;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use Grav\Common\GravTrait;
use Grav\Common\Data\Blueprint;
use Grav\Common\Data\Data;
use Gregwar\Image\Image as ImageFile;

/**
 * The Image medium holds information related to an individual image. These are then stored in the Media object.
 *
 * @author RocketTheme
 * @license MIT
 *
 * @property string $file_name
 * @property string $type
 * @property string $name       Alias of file_name
 * @property string $description
 * @property string $url
 * @property string $path
 * @property string $thumb
 * @property int    $width
 * @property int    $height
 * @property string $mime
 * @property int    $modified
 *
 * Medium can have up to 3 files:
 * - video.mov              Medium file itself.
 * - video.mov.meta.yaml    Metadata for the medium.
 * - video.mov.thumb.jpg    Thumbnail image for the medium.
 *
 */
class VideoMedium extends Medium
{
    protected $mode = 'video';

    protected $videoAttributes = [];

	public static $valid_actions = [
        // Medium functions
        'format', 'lightbox', 'link', 'reset',

        // Gregwar OR internal depending on context
        'resize',

        // Gregwar Image functions
        'forceResize', 'cropResize', 'crop', 'cropZoom',
        'negate', 'brightness', 'contrast', 'grayscale', 'emboss', 'smooth', 'sharp', 'edge', 'colorize', 'sepia' ];

    public static $valid_video_actions = [
        'lightbox', 'link', 'reset', 'resize'
    ];

	public function html($title = null, $class = null, $reset = true)
	{
		$data = $this->htmlRaw(false);
        if ($reset) {
            $this->reset();
        }

        $title = $title ? $title : $this->get('title');
        $class = $class ? $class : '';

        if (isset($data['a_href'])) {

            $thumb = $this->get('thumb');
            if ($thumb) {
                $output = $thumb->html($reset);
            } else {
                $output = $title ? $title : $this->get('basename');
            }

            $attributes = '';
            foreach ($data['a_attributes'] as $prop => $value) {
                $attributes .= " {$prop}=\"{$value}\"";
            }

            $output = '<a href="' . $data['a_href'] . '"' . $attributes . ' class="'. $class . '">' . $output . '</a>';
        } else {
            $attributes = '';
            foreach ($data['video_attributes'] as $prop => $value) {
                $attributes .= " {$prop}=\"{$value}\"";
            }

            $output = '<video class="'. $class . '" alt="' . $title . '"' . $attributes . ' controls><source src="' . $data['video_src'] . '" type="' . $this->get('mime') . '">Your browser does not support the video tag.</video>';
        }

        return $output;
	}

	public function htmlRaw($reset = true, $title = '')
	{
		$output = [];

        if ($this->linkTarget) {
            $output['a_href'] = $this->linkTarget;
            $output['a_attributes'] = $this->linkAttributes;

            $this->linkTarget = null;
            $this->linkAttributes = [];

            $thumb = $this->get('thumb');
            if ($thumb) {
                $raw_thumb = $thumb->htmlRaw($reset);

                $output['thumb_src'] = $raw_thumb['img_src'];
            }
        } else {
            $output['video_src'] = $this->url($reset);
            $output['video_attributes'] = $this->videoAttributes;
        }

        return $output;
	}

    /**
     * Enable link for the medium object.
     *
     * @param null $width
     * @param null $height
     * @return $this
     */
    public function link($width = null, $height = null)
    {
        // TODO: we need to find out URI in a bit better way.
        $this->linkTarget = $this->url();
                
        $this->mode = 'thumb';

        return $this;
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
            $method = '_' . $method;

        } else if ($mode == 'thumb') {

            $target = $this->get('thumb');

        }

        self::$grav['debugger']->addMessage('Calling ' . $method . ' on ' . $mode);

        if ($target) {
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