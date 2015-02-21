<?php
namespace Grav\Common\Page\Medium;

use Grav\Common\Config\Config;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use Grav\Common\GravTrait;
use Grav\Common\Data\Blueprint;
use Grav\Common\Data\Data;
use Gregwar\Image\Image as ImageFile;

class ThumbnailImageMedium extends ImageMedium
{
    public $parent = null;

    public $linked = false;

    /**
     * Return srcset string for this Medium and its alternatives.
     *
     * @param bool $reset
     * @return string
     */
    public function srcset($reset = true)
    {
        return '';
    }

    public function parsedownElement($title = null, $alt = null, $class = null, $reset = true)
    {
        return $this->bubble('parsedownElement', [$title, $alt, $class, $reset]);
    }

    public function html($title = null, $alt = null, $class = null, $reset = true)
    {
        return $this->bubble('html', [$title, $alt, $class, $reset]);
    }

    public function display($mode)
    {
        return $this->bubble('display', [$mode], false);
    }

    public function link($reset = true)
    {
        return $this->bubble('link', [$reset], false);
    }

    public function lightbox($width = null, $height = null, $reset = true)
    {
        return $this->bubble('lightbox', [$width, $height, $reset], false);
    }

    public function bubble($method, $arguments = [], $testLinked = true)
    {
        if (!$testLinked || $this->linked) {
            return call_user_func_array(array($this->parent, $method), $arguments);
        } else {
            return call_user_func_array(array($this, 'parent::' . $method), $arguments);
        }
    }
}
