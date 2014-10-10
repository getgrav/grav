<?php
namespace Grav\Common\GPM\Local;

use Grav\Common\Iterator;

class Packages extends Iterator
{
    private $plugins;
    private $themes;
    protected static $cache;

    public function __construct()
    {
        // local cache to speed things up
        if (!isset(self::$cache[__METHOD__])) {
            self::$cache[__METHOD__] = [
                'plugins' => new Plugins(),
                'themes'  => new Themes()
            ];
        }

        $this->plugins = self::$cache[__METHOD__]['plugins'];
        $this->themes  = self::$cache[__METHOD__]['themes'];

        $this->append(['plugins' => $this->plugins]);
        $this->append(['themes' => $this->themes]);
    }
}
