<?php
namespace Grav\Common;

/**
 * The Plugin object just holds the id and path to a plugin.
 *
 * @author RocketTheme
 * @license MIT
 */
class Plugin
{
    /**
     * @var Config
     */
    public $config;

    /**
     * Constructor.
     *
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }
}
