<?php
namespace Grav\Plugin;

use Grav\Common\GPM\GPM;
use Grav\Common\Grav;
use Grav\Common\Page\Page;
use Grav\Common\Page\Pages;
use Grav\Common\Plugin;
use Grav\Common\Uri;
use RocketTheme\Toolbox\File\File;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\Session\Session;

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
     * @var  string
     */
    protected $theme;

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
     * @var Session
     */
    protected $session;

    /**
     * @var Popularity
     */
    protected $popularity;

    /**
     * @var string
     */
    protected $base;

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => [['login', 100000], ['onPluginsInitialized', 1000]],
            'onShutdown'           => ['onShutdown', 1000]
        ];
    }

    /**
     * If the admin path matches, initialize the Login plugin configuration and set the admin
     * as active.
     */
    public function login()
    {
        // Check for Pro version is enabled
        if ($this->config->get('plugins.admin-pro.enabled')) {
            $this->active = false;
            return;
        }

        $route = $this->config->get('plugins.admin.route');
        if (!$route) {
            return;
        }

        $this->grav['debugger']->addMessage("Admin Basic");

        $this->base = '/' . trim($route, '/');
        $this->uri = $this->grav['uri'];

        // Only activate admin if we're inside the admin path.
        if ($this->uri->route() == $this->base ||
            substr($this->uri->route(), 0, strlen($this->base) + 1) == $this->base . '/') {
            $this->active = true;
        }
    }

    /**
     * If the admin plugin is set as active, initialize the admin
     */
    public function onPluginsInitialized()
    {
        // Only activate admin if we're inside the admin path.
        if ($this->active) {
            $this->initializeAdmin();
        }

        // We need popularity no matter what
        require_once __DIR__ . '/classes/popularity.php';
        $this->popularity = new Popularity();
    }

    /**
     * Sets longer path to the home page allowing us to have list of pages when we enter to pages section.
     */
    public function onPagesInitialized()
    {
        $this->session = $this->grav['session'];

        // Set original route for the home page.
        $home = '/' . trim($this->config->get('system.home.alias'), '/');

        // Disable Asset pipelining
        $this->config->set('system.assets.css_pipeline', false);
        $this->config->set('system.assets.js_pipeline', false);

        // set the default if not set before
        $this->session->expert = $this->session->expert ?: false;

        // set session variable if it's passed via the url
        if ($this->uri->param('mode') == 'expert') {
            $this->session->expert = true;
        } elseif ($this->uri->param('mode') == 'normal') {
            $this->session->expert = false;
        }

        /** @var Pages $pages */
        $pages = $this->grav['pages'];

        $this->grav['admin']->routes = $pages->routes();

        // Remove default route from routes.
        if (isset($this->grav['admin']->routes['/'])) {
            unset($this->grav['admin']->routes['/']);
        }

        $page = $pages->dispatch('/', true);

        // If page is null, the default page does not exist, and we cannot route to it
        if ($page) {
            $page->route($home);
        }

        // Make local copy of POST.
        $post = !empty($_POST) ? $_POST : array();

        // Handle tasks.
        $this->admin->task = $task = !empty($post['task']) ? $post['task'] : $this->uri->param('task');
        if ($task) {
            require_once __DIR__ . '/classes/controller.php';
            $controller = new AdminController($this->grav, $this->template, $task, $this->route, $post);
            $controller->execute();
            $controller->redirect();
        } elseif ($this->template == 'logs' && $this->route) {
            // Display RAW error message.
            echo $this->admin->logEntry();
            exit();
        }

        $self = $this;

        // Replace page service with admin.
        $this->grav['page'] = function () use ($self) {
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
        $twig_paths = [];
        $this->grav->fireEvent('onAdminTwigTemplatePaths', new Event(['paths' => &$twig_paths]));

        $twig_paths[] = __DIR__ . '/themes/' . $this->theme . '/templates';

        $this->grav['twig']->twig_paths = $twig_paths;

    }

    /**
     * Set all twig variables for generating output.
     */
    public function onTwigSiteVariables()
    {
        $twig = $this->grav['twig'];

        // Dynamic type support
        $format = $this->uri->extension();
        $ext = '.' . ($format ? $format : 'html') . TWIG_EXT;

        $twig->twig_vars['location'] = $this->template;
        $twig->twig_vars['base_url_relative_frontend'] = $twig->twig_vars['base_url_relative'];
        $twig->twig_vars['base_url_relative'] .=
            ($twig->twig_vars['base_url_relative'] != '/' ? '/' : '') . trim($this->config->get('plugins.admin.route'),
                '/');
        $twig->twig_vars['theme_url'] = '/user/plugins/admin/themes/' . $this->theme;
        $twig->twig_vars['base_url'] = $twig->twig_vars['base_url_relative'];
        $twig->twig_vars['admin'] = $this->admin;

        switch ($this->template) {
            case 'dashboard':
                $twig->twig_vars['popularity'] = $this->popularity;
                break;
            case 'pages':
                $page = $this->admin->page(true);
                if ($page != null) {
                    $twig->twig_vars['file'] = File::instance($page->filePath());
                    $twig->twig_vars['media_types'] = str_replace('defaults,', '',
                        implode(',.', array_keys($this->config->get('media'))));

                }
                break;
        }
    }

    public function onShutdown()
    {
        // Just so we know that we're in this debug mode
        if ($this->config->get('plugins.admin.popularity.enabled')) {

            // Only track non-admin
            if (!$this->active) {
                $this->popularity->trackHit();
            }
        }
    }

    /**
     * Handles getting GPM updates
     */
    public function onTaskGPM()
    {
        $action = $_POST['action']; // getUpdatable | getUpdatablePlugins | getUpdatableThemes | gravUpdates
        $flush  = isset($_POST['flush']) && $_POST['flush'] == true ? true : false;

        if (isset($this->grav['session'])) {
            $this->grav['session']->close();
        }

        try {
            $gpm = new GPM($flush);

            switch ($action) {
                case 'getUpdates':
                    $resources_updates = $gpm->getUpdatable();
                    $grav_updates = [
                        "isUpdatable" => $gpm->grav->isUpdatable(),
                        "assets"      => $gpm->grav->getAssets(),
                        "version"     => GRAV_VERSION,
                        "available"   => $gpm->grav->getVersion(),
                        "date"        => $gpm->grav->getDate(),
                        "isSymlink"   => $gpm->grav->isSymlink()
                    ];

                    echo json_encode([
                        "status" => "success",
                        "payload" => ["resources" => $resources_updates, "grav" => $grav_updates, "installed" => $gpm->countInstalled(), 'flushed' => $flush]
                    ]);
                    break;
            }
        } catch (\Exception $e) {
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }

        exit;
    }

    /**
     * Initialize the admin.
     *
     * @throws \RuntimeException
     */
    protected function initializeAdmin()
    {
        $this->enable([
            'onPagesInitialized'  => ['onPagesInitialized', 1000],
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 1000],
            'onTwigSiteVariables' => ['onTwigSiteVariables', 1000],
            'onTask.GPM'          => ['onTaskGPM', 0]
        ]);

        // Check for required plugins
        if (!$this->grav['config']->get('plugins.login.enabled') ||
            !$this->grav['config']->get('plugins.form.enabled') ||
            !$this->grav['config']->get('plugins.email.enabled')) {
            throw new \RuntimeException('One of the required plugins is missing or not enabled');
        }

        // Double check we have system.yaml and site.yaml
        $config_files[] = $this->grav['locator']->findResource('user://config') . '/system.yaml';
        $config_files[] = $this->grav['locator']->findResource('user://config') . '/site.yaml';
        foreach ($config_files as $config_file) {
            if (!file_exists($config_file)) {
                touch($config_file);
            }
        }



        // Decide admin template and route.
        $path = trim(substr($this->uri->route(), strlen($this->base)), '/');
        $this->template = 'dashboard';

        if ($path) {
            $array = explode('/', $path, 2);
            $this->template = array_shift($array);
            $this->route = array_shift($array);
        }

        // Initialize admin class.
        require_once __DIR__ . '/classes/admin.php';
        $this->admin = new Admin($this->grav, $this->base, $this->template, $this->route);

        // And store the class into DI container.
        $this->grav['admin'] = $this->admin;

        // Get theme for admin
        $this->theme = $this->config->get('plugins.admin.theme', 'grav');
    }
}
