<?php
namespace Grav\Common\Data;

/**
 * Metadata storage object
 *
 * @author RocketTheme
 * @license MIT
 */
class MetaData
{
    public $charset;
    public $name;
    public $property;
    public $content;
    public $http_equiv;

    public function __construct($name = null, $content = null)
    {
        if ($name) {
            $this->name = $name;
        }
        if ($content) {
            $this->content = $content;
        }

    }
}
