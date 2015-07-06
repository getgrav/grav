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

    public function reformat()
    {
        if (isset($this->items['plugins'])) {
            foreach ($this->items['plugins'] as $plugin => $data) {
                $this->items = array_merge_recursive($this->items, $data);
            }
            unset($this->items['plugins']);
        }
    }

}
