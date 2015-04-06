<?php
namespace Grav\Common\GPM\Remote;

use Grav\Common\GPM\Common\CachedCollection;

class Packages extends CachedCollection
{
    public function __construct($refresh = false, $callback = null)
    {
        $items = [
            'plugins' => new Plugins($refresh, $callback),
            'themes' => new Themes($refresh, $callback)
        ];

        parent::__construct($items);
    }
}
