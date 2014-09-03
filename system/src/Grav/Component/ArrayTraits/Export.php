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
     * Convert object into an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->items;
    }

    /**
     * Convert object into YAML string.
     *
     * @return string
     */
    public function toYaml()
    {
        return Yaml::dump($this->toArray());
    }

    /**
     * Convert object into JSON string.
     *
     * @return string
     */
    public function toJson()
    {
        return json_encode($this->toArray());
    }
}
