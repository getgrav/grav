<?php
namespace Grav\Common\GPM\Local;

use Grav\Common\Iterator;

class Packages extends Iterator {
    private $plugins, $themes;

    public function __construct() {
        $plugins = new Plugins();
        $themes  = new Themes();

        $this->plugins = $plugins;
        $this->themes  = $themes;

        $this->append(['plugins' => $this->plugins]);
        $this->append(['themes' => $this->themes]);
    }
}
