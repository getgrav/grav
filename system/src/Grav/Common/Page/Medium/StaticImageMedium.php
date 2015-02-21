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
class StaticImageMedium extends Medium
{
    /**
     * @var int
     */
    protected $width = null;

    /**
     * @var int
     */
    protected $height = null;

    public function sourceParsedownElement($attributes, $reset)
    {
        empty($attributes['src']) && $attributes['src'] = $this->url($reset);

        !empty($this->width) && $attributes['width'] = $this->width;
        !empty($this->height) && $attributes['height'] = $this->height;

        return [ 'name' => 'image', 'attributes' => $attributes ];
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

        !empty($this->width) && $this->linkAttributes['data-width'] = $this->width;
        !empty($this->height) && $this->linkAttributes['data-height'] = $this->height;

        return parent::link($reset);
    }

    protected function resize($width = null, $height = null)
    {
        $this->attributes['width'] = $width;
        $this->attributes['height'] = $height;

        return $this;
    }
}
