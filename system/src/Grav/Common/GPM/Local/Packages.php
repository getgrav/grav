<?php
namespace Grav\Common\GPM\Local;

use Grav\Common\GPM\Common\CachedCollection;

class Packages extends CachedCollection
{
    public function __construct()
    {
        $items = [
            'plugins' => new Plugins(),
            'themes' => new Themes()
        ];

        parent::__construct($items);
    }
}
