<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Page\Page;
use Grav\Common\Page\Pages;
use Grav\Common\Filesystem\File;
use Grav\Common\Grav;
use Grav\Common\Uri;

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
     * @return array
     */
    public static function getSubscribedEvents() {
        return [
            'onPluginsInitialized' => [['login', 100000], ['onPluginsInitialized', 1000]]
        ];
    }

    /**
     * Initialize administration plugin if admin path matches.
     *
     * Disables system cache.
     */
    public function login()
    {
        $route = $this->config->get('plugins.admin.route');
        if (!$route) {
            return;
        }

        $this->base = '/' . trim($route, '/');
        $this->uri = $this->grav['uri'];

        // Only activate admin if we're inside the admin path.
        if (substr($this->uri->route(), 0, strlen($this->base)) == $this->base) {
            // Disable system caching.
            $this->config->set('system.cache.enabled', false);

            // Change login behavior.
            $this->config->set('plugins.login', $this->config->get('plugins.admin.login'));

            $this->active = true;
        }
    }

        /**
     * Initialize administration plugin if admin path matches.
     *
     * Disables system cache.
     */
    public function onPluginsInitialized()
    {
        // Only activate admin if we're inside the admin path.
        if ($this->active) {
            $this->enable([
                'onPagesInitialized' => ['onPagesInitialized', 1000],
                'onTwigTemplatePaths' => ['onTwigTemplatePaths', 1000],
                'onTwigSiteVariables' => ['onTwigSiteVariables', 1000]
            ]);

            // Disable system caching.
            $this->config->set('system.cache.enabled', false);

            // Change login behavior.
            $this->config->set('plugins.login', $this->config->get('plugins.admin.login'));

            // Decide admin template and route.
            $path = trim(substr($this->uri->route(), strlen($this->base)), '/');
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
            $this->admin = new Admin($this->grav, $this->base, $this->template, $this->route);

            // And store the class into DI container.
            $this->grav['admin'] = $this->admin;
        }
    }

    /**
     * Sets longer path to the home page allowing us to have list of pages when we enter to pages section.
     */
    public function onPagesInitialized()
    {
        // Set original route for the home page.
        $home = '/' . trim($this->config->get('system.home.alias'), '/');

        /** @var Pages $pages */
        $pages = $this->grav['pages'];
        $pages->dispatch('/', true)->route($home);

        // Set page if user hasn't been authorised.
/*        if (!$this->admin->authorise()) {
            $this->template = $this->admin->user ? 'denied' : 'login';
        }
*/
        // Make local copy of POST.
        $post = !empty($_POST) ? $_POST : array();

        // Handle tasks.
        $task = !empty($post['task']) ? $post['task'] : $this->uri->param('task');
        if ($task) {
            require_once __DIR__ . '/classes/controller.php';
            $controller = new AdminController($this->grav, $this->template, $task, $this->route, $post);
            $success = $controller->execute();
            $controller->redirect();
        } elseif ($this->template == 'logs' && $this->route) {
            // Display RAW error message.
            echo $this->admin->logEntry();
            exit();
        }

        $self = $this;

        // Replace page service with admin.
        $this->grav['page'] = function ($c) use ($self) {
            $page = new Page;
            $page->init(new \SplFileInfo(__DIR__ . "/pages/admin/{$self->template}.md"));
            $page->slug(basename($self->template));

            return $page;
        };
    }

    /**
     * Add twig paths to plugin templates.
     */
    public function onTwigTemplatePaths()
    {
        $twig = $this->grav['twig'];
        $twig->twig_paths = array(__DIR__ . '/theme/templates');
    }

    /**
     * Set all twig variables for generating output.
     */
    public function onTwigSiteVariables()
    {
        // TODO: use real plugin name instead
        $theme_url = $this->config->get('system.base_url_relative') . '/user/plugins/admin/theme';
        $twig = $this->grav['twig'];

        $twig->template = $this->template . '.html.twig';
        $twig->twig_vars['location'] = $this->template;
        $twig->twig_vars['base_url_relative'] .=
            ($twig->twig_vars['base_url_relative'] != '/' ? '/' : '') . trim($this->config->get('plugins.admin.route'), '/');
        $twig->twig_vars['theme_url'] = $theme_url;
        $twig->twig_vars['base_url'] = $twig->twig_vars['base_url_relative'];
        $twig->twig_vars['admin'] = $this->admin;

        switch ($this->template) {
            case 'plugins':
                $twig->twig_vars['plugins'] = $this->grav['plugins']->all();
                break;
            case 'pages':
                $twig->twig_vars['file'] = File\General::instance($this->admin->page(true)->filePath());
                break;
        }
    }
}
