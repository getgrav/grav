<?php
namespace Grav\Component\ArrayTraits;

use Symfony\Component\Yaml\Yaml;

/**
 * Implements data export to array, YAML and JSON
 * @package Grav\Component\ArrayTraits
 *
 * @property array $items
 */
trait Export
{
    /**
     * Convert blueprints into an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->items;
    }

    /**
     * Convert blueprints into YAML string.
     *
     * @return string
     */
    public function toYaml()
    {
        return Yaml::dump($this->items);
    }

    /**
     * Convert blueprints into JSON string.
     *
     * @return string
     */
    public function toJson()
    {
        return json_encode($this->items);
    }
}
