<?php
namespace Grav\Common\GPM\Remote;

use Grav\Common\Iterator;

class Packages extends Iterator
{
    private $plugins;
    private $themes;
    protected static $cache;

    public function __construct($refresh = false, $callback = null)
    {
        // local cache to speed things up
        if (!isset(self::$cache[__METHOD__])) {
            self::$cache[__METHOD__] = [
                'plugins' => new Plugins($refresh, $callback),
                'themes'  => new Themes($refresh, $callback)
            ];
        }

        $this->plugins = self::$cache[__METHOD__]['plugins']->toArray();
        $this->themes  = self::$cache[__METHOD__]['themes']->toArray();

        $this->append(['plugins' => $this->plugins]);
        $this->append(['themes' => $this->themes]);
    }
}
