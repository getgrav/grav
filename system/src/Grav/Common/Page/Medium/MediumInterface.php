<?php
namespace Grav\Common\Page\Medium;

use Grav\Common\Data\Blueprint;
use Grav\Common\Data\DataInterface;

/**
 * The Image medium holds information related to an individual image. These are then stored in the Media object.
 *
 * @author Grav
 * @license MIT
 *
 */
interface MediumInterface extends DataInterface
{
    /**
     * @var string
     */
    protected $mode;

    /**
     * @var array
     */
    public static $magic_actions;

    /**
     * @var \Grav\Common\Markdown\Parsedown
     */
    protected $parsedown;

    /**
     * @var Medium[]
     */
    protected $alternatives;

    /**
     * @var array
     */
    protected $attributes;

    /**
     * @var array
     */
    protected $linkAttributes;

    /**
     * Construct.
     *
     * @param array $items
     * @param Blueprint $blueprint
     */
    public function __construct($items = array(), Blueprint $blueprint = null);

    /**
     * Add meta file for the medium.
     *
     * @param $type
     * @return $this
     */
    public function addMetaFile($filepath);

    /**
     * Add alternative Medium to this Medium.
     *
     * @param $ratio
     * @param Medium $alternative
     */
    public function addAlternative($ratio, Medium $alternative);

    /**
     * Return string representation of the object (html).
     *
     * @return string
     */
    public function __toString();

    /**
     * Return PATH to file.
     *
     * @param bool $reset
     * @return  string path to file
     */
    public function path($reset = true);

    /**
     * Return URL to file.
     *
     * @param bool $reset
     * @return string
     */
    public function url($reset = true);

    /**
     * Return HTML markup from the medium.
     *
     * @param string $title
     * @param string $alt
     * @param string $class
     * @param bool $reset
     * @return string
     */
    public function html($title = null, $alt = null, $class = null, $reset = true);

    /**
     * Return Parsedown Element from the medium.
     *
     * @param string $title
     * @param string $alt
     * @param string $class
     * @param bool $reset
     * @return string
     */
    public function parsedownElement($title = null, $alt = null, $class = null, $reset = true);

    /**
     * Reset medium.
     *
     * @return $this
     */
    public function reset();

    /**
     * Switch display mode.
     *
     * @param string $mode
     *
     * @return $this
     */
    public function display($mode);

    /**
     * Switch thumbnail.
     *
     * @param string $type
     *
     * @return $this
     */
    public function thumbnail($type);

    /**
     * Enable link for the medium object.
     *
     * @param bool $reset
     * @return $this
     */
    public function link($reset = true);

    /**
     * Enable lightbox for the medium.
     *
     * @param null $width
     * @param null $height
     * @param bool $reset
     * @return Medium
     */
    public function lightbox($width = null, $height = null, $reset = true);
}
