<?php
namespace Grav\Plugin;

use \Grav\Common\Plugin;
use \Grav\Common\Registry;
use \Grav\Common\Page\Page;
use \Grav\Common\Page\Pages;
use \Grav\Common\Filesystem\File;
use \Grav\Common\Grav;
use \Grav\Common\Uri;

class AdminPlugin extends Plugin
{
    /**
     * @var bool
     */
    protected $active = false;

    /**
     * @var string
     */
    protected $template;

    /**
     * @var string
     */
    protected $route;

    /**
     * @var Uri
     */
    protected $uri;

    /**
     * @var Admin
     */
    protected $admin;

    /**
     * Initialize administration plugin if admin path matches.
     *
     * Disables system cache.
     */
    public function onAfterInitPlugins()
    {
        $route = $this->config->get('plugins.admin.route');

        if (!$route) {
            return;
        }

        $this->uri = Registry::get('Uri');
        $base = '/' . trim($route, '/');

        // Only activate admin if we're inside the admin path.
        if (substr($this->uri->route(), 0, strlen($base)) == $base) {
            $this->active = true;

            // Disable system caching.
            $this->config->set('system.cache.enabled', false);

            // Decide admin template and route.
            $path = trim(substr($this->uri->route(), strlen($base)), '/');
            $this->template = 'dashboard';

            if ($path) {
                $array = explode('/', $path, 2);
                $this->template = array_shift($array);
                $this->route = array_shift($array);

                // Set path for new page.
                if ($this->uri->param('new')) {
                    $this->route .= '/new';
                }
            }

            // Initialize admin class.
            require_once __DIR__ . '/classes/admin.php';
            $this->admin = new Admin($base, $this->template, $this->route);

            // And store the class into registry.
            $registry = Registry::instance();
            $registry->store('Admin', $this->admin);
        }
    }

    /**
     * Sets longer path to the home page allowing us to have list of pages when we enter to pages section.
     */
    public function onAfterGetPages()
    {
        if (!$this->active) {
            return;
        }

        // Set original route for the home page.
        $home = '/' . trim($this->config->get('system.home.alias'), '/');

        /** @var Pages $pages */
        $pages = Registry::get('Pages');
        $pages->dispatch('/', true)->route($home);
    }

    /**
     * Main administration controller.
     */
    public function onAfterGetPage()
    {
        if (!$this->active) {
            return;
        }

        // Set page if user hasn't been authorised.
        if (!$this->admin->authorise()) {
            $this->template = $this->admin->user ? 'denied' : 'login';
        }

        // Make local copy of POST.
        $post = !empty($_POST) ? $_POST : array();

        // Handle tasks.
        $task = !empty($post['task']) ? $post['task'] : $this->uri->param('task');
        if ($task) {
            require_once __DIR__ . '/classes/controller.php';
            $controller = new AdminController($this->template, $task, $this->route, $post);
            $success = $controller->execute();
            $controller->redirect();
        } elseif ($this->template == 'logs' && $this->route) {
            // Display RAW error message.
            echo $this->admin->logEntry();
            exit();
        }

        /** @var Grav $grav */
        $grav = Registry::get('Grav');

        // Finally create admin page.
        $page = new Page;
        $page->init(new \SplFileInfo(__DIR__ . "/pages/admin/{$this->template}.md"));
        $page->slug(basename($this->template));
        $grav->page = $page;
    }

    /**
     * Add twig paths to plugin templates.
     */
    public function onAfterTwigTemplatesPaths()
    {
        if (!$this->active) {
            return;
        }

        $twig = Registry::get('Twig');
        $twig->twig_paths = array(__DIR__ . '/theme/templates');
    }

    /**
     * Set all twig variables for generating output.
     */
    public function onAfterTwigSiteVars()
    {
        if (!$this->active) {
            return;
        }

        $theme_url = $this->config->get('system.base_url_relative') . '/user/plugins/' . basename(__DIR__) . '/theme';
        $twig = Registry::get('Twig');

        $twig->template = $this->template . '.html.twig';
        $twig->twig_vars['location'] = $this->template;
        $twig->twig_vars['base_url_relative'] .=
            ($twig->twig_vars['base_url_relative'] != '/' ? '/' : '') . trim($this->config->get('plugins.admin.route'), '/');
        $twig->twig_vars['theme_url'] = $theme_url;
        $twig->twig_vars['admin'] = $this->admin;

        switch ($this->template) {
            case 'plugins':
                $twig->twig_vars['plugins'] = \Grav\Common\Plugins::all();
                break;
            case 'pages':
                $twig->twig_vars['file'] = File\General::instance($this->admin->page(true)->filePath());
                break;
        }
    }
}
