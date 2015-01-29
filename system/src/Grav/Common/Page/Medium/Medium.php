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
 * Medium can have a few files:
 * - video.mov              Medium file itself.
 * - video.mov.meta.yaml    Metadata for the medium.
 * - video.mov.thumb.jpg    Thumbnail image for the medium.
 * - video@2x.mov           Alternate sizes of medium
 *
 */
class Medium extends Data
{
    use GravTrait;

    public static $valid_actions = [
        'format', 'lightbox', 'link', 'reset',
    ];

    /**
     * @var Medium[]
     */
    protected $alternatives = array();

    /**
     * @var string
     */
    protected $linkTarget;

    /**
     * @var string
     */
    protected $linkAttributes = [];

    public static function factory($items = array(), Blueprint $blueprint = null)
    {
        $type = isset($items['type']) ? $items['type'] : null;

        switch ($type) {
            case 'image':
                return new ImageMedium($items, $blueprint);
                break;
            case 'video':
                return new VideoMedium($items, $blueprint);
                break;
            default:
                return new self($items, $blueprint);
                break;
        }
    }

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
     * Return string representation of the object (html or url).
     *
     * @return string
     */
    public function __toString()
    {
        return $this->linkImage ? $this->html() : $this->url();
    }

    /**
     * Return PATH to file.
     *
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
    public function html($title = null, $class = null, $reset = true)
    {
        $data = $this->htmlRaw($reset);

        $output = $data['text'];

        if (isset($data['a_href'])) {
            
            $attributes = '';
            foreach ($data['a_attributes'] as $prop => $value) {
                $attributes .= " {$prop}=\"{$value}\"";
            }

            $output = '<a href="' . $data['a_href'] . '"' . $attributes . ' class="'. $class . '">' . $output . '</a>';
        }

        return $output;
    }

    /**
     * Return HTML array from medium.
     *
     * @param bool   $reset
     * @param string $title
     *
     * @return array
     */
    public function htmlRaw($reset = true, $title = '')
    {
        $data = [
            'text' => $title ? $title : $this->path($reset)
        ];

        if ($this->linkTarget) {
            $output['a_href'] = $this->linkTarget;
            $output['a_attributes'] = $this->linkAttributes;

            $this->linkTarget = null;
            $this->linkAttributes = [];
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
        $this->linkTarget = self::$grav['base_url'] . preg_replace('|^' . GRAV_ROOT . '|', '', $this->get('filepath'));

        return $this;
    }

    /**
     * Enable lightbox for the medium.
     *
     * @param null $width
     * @param null $height
     * @return Medium
     */
    public function lightbox($width = null, $height = null)
    {
        $this->linkAttributes['rel'] = 'lightbox';

        return $this->link($width, $height);
    }

    /**
     * Reset image.
     *
     * @return $this
     */
    public function reset()
    {
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
        return $this;
    }

    /**
     * Add meta file for the medium.
     *
     * @param $type
     * @return $this
     */
    public function addMetaFile($filepath)
    {
        self::$grav['debugger']->addMessage($filepath);
        $this->merge(CompiledYamlFile::instance($filepath)->content());

        return $this;
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

    public function getAlternatives()
    {
        return $this->alternatives;
    }
}
