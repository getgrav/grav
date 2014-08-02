<?php
namespace Grav\Plugin;

use \Grav\Common\Plugin;
use \Grav\Common\Registry;
use \Grav\Common\Grav;
use \Grav\Common\Page\Page;
use \Grav\Common\Page\Pages;

class ErrorPlugin extends Plugin
{
    /**
     * Display error page if no page was found for the current route.
     */
    public function onAfterGetPage()
    {
        /** @var Grav $grav */
        $grav = Registry::get('Grav');
        /** @var Pages $pages */
        $pages = Registry::get('Pages');

        // Not found: return error page instead.
        if ((!$grav->page || !$grav->page->routable())) {

            // try to load user error page
            $page = $pages->dispatch($this->config->get('error.404', '/error'), true);

            // if none provided use built in
            if (!$page) {
                $page = new Page;
                $page->init(new \SplFileInfo(__DIR__ . '/pages/error.md'));
            }

            // Set the page
            $grav->page = $page;
        }
    }

    /**
     * Add current directory to twig lookup paths.
     */
    public function onAfterTwigTemplatesPaths()
    {
        Registry::get('Twig')->twig_paths[] = __DIR__ . '/templates';
    }
}
