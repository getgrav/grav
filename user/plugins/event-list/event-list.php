<?php

namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Plugin;

/**
 * Class EventListPlugin
 * @package Grav\Plugin
 */
class EventListPlugin extends Plugin
{
    /**
     * @return array
     *
     * The getSubscribedEvents() gives the core a list of events
     *     that the plugin wants to listen to. The key of each
     *     array section is the event that the plugin listens to
     *     and the value (in the form of an array) contains the
     *     callable (or function) as well as the priority. The
     *     higher the number the higher the priority.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => [
                ['autoload', 100000],
                ['onPluginsInitialized', 0]
            ],
        ];
    }

    /**
     * Composer autoload
     *
     * @return ClassLoader
     */
    public function autoload(): ClassLoader
    {
        return require __DIR__ . '/vendor/autoload.php';
    }

    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized(): void
    {
        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            return;
        }

        // Add CSS to the page

        // Enable the main events we are interested in
        $this->enable([
            'onTwigExtensions' => [
                ['onTwigExtensions', 0]
            ],
            'onTwigTemplatePaths' => [
                ['onTwigTemplatePaths', 0]
            ]
        ]);
    }

    /**
     * Add template directory to twig lookup path.
     */
    public function onTwigTemplatePaths()
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }

    /**
     * Register the Twig extension (using a global object)
     */
    public function onTwigExtensions(): void
    {
        $this->grav['twig']->twig->addGlobal(
            'events',
            new EventList\Events($this->grav)
        );
    }
}
