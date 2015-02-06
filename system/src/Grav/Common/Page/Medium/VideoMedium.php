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
    protected $videoAttributes = [];

	public static $magic_actions = [
        'resize'
    ];

    public function parsedownElement($title = null, $alt = null, $class = null, $reset = true)
    {
        $element;

        if (!$this->linkAttributes) {
            $video_location = $this->url(false);
            $video_attributes = $this->videoAttributes;
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

            if (!empty($this->videoAttributes['width']) && empty($link_attributes['data-width'])) {
                $link_attributes['data-width'] = $this->videoAttributes['width'];
            }

            if (!empty($this->videoAttributes['height']) && empty($link_attributes['data-height'])) {
                $link_attributes['data-height'] = $this->videoAttributes['height'];
            }

            if ($class) {
                $link_attributes['class'] = $class;
            }

            $element = [
                'name' => 'a',
                'attributes' => $link_attributes,
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
}