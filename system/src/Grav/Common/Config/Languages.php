<?php
namespace Grav\Common\Config;

use Grav\Common\Data\Data;

/**
 * The Languages class contains configuration rules.
 *
 * @author RocketTheme
 * @license MIT
 */
class Languages extends Data
{
    public function checksum($checksum = null)
    {
        if ($checksum !== null) {
            $this->checksum = $checksum;
        }

        return $this->checksum;
    }

    public function modified($modified = null)
    {
        if ($modified !== null) {
            $this->modified = $modified;
        }

        return $this->modified;
    }

    public function reformat()
    {
        if (isset($this->items['plugins'])) {
            $this->items = array_merge_recursive($this->items, $this->items['plugins']);
            unset($this->items['plugins']);
        }
    }

    public function mergeRecursive(array $data)
    {
        $this->items = array_merge_recursive($this->items, $data);
    }
}
