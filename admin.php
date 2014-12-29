<?php
namespace Grav\Plugin;

use Grav\Common\GPM\GPM;
use Grav\Common\Grav;
use Grav\Common\Page\Page;
use Grav\Common\Page\Pages;
use Grav\Common\Plugin;
use Grav\Common\Uri;
use RocketTheme\Toolbox\File\File;
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
     * Initialize administration plugin if admin path matches.
     *
     * Disables system cache.
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
        if (substr($this->uri->route(), 0, strlen($this->base)) == $this->base) {
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

        // set the default if not set before
        $this->session->expert = $this->session->expert ?: true;

        // set session variable if it's passed via the url
        if ($this->uri->param('mode') == 'expert') {
            $this->session->expert = true;
        } elseif ($this->uri->param('mode') == 'normal') {
            $this->session->expert = false;
        }

        /** @var Pages $pages */
        $pages = $this->grav['pages'];

        $this->grav['admin']->routes = $pages->routes();

        $pages->dispatch('/', true)->route($home);

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
        $this->theme = $this->config->get('plugins.admin.theme', 'grav');
        $this->grav['twig']->twig_paths = array(__DIR__ . '/themes/' . $this->theme . '/templates');
    }

    /**
     * Set all twig variables for generating output.
     */
    public function onTwigSiteVariables()
    {
        // TODO: use real plugin name instead
        $theme_url = $this->grav['base_url'] . '/user/plugins/admin/themes/' . $this->theme;
        $twig = $this->grav['twig'];

        // Dynamic type support
        $format = $this->uri->extension();
        $ext = '.' . ($format ? $format : 'html') . TWIG_EXT;

        $twig->template = $this->template . $ext;
        $twig->twig_vars['location'] = $this->template;
        $twig->twig_vars['base_url_relative_frontend'] = $twig->twig_vars['base_url_relative'];
        $twig->twig_vars['base_url_relative'] .=
            ($twig->twig_vars['base_url_relative'] != '/' ? '/' : '') . trim($this->config->get('plugins.admin.route'),
                '/');
        $twig->twig_vars['theme_url'] = $theme_url;
        $twig->twig_vars['base_url'] = $twig->twig_vars['base_url_relative'];
        $twig->twig_vars['admin'] = $this->admin;

        // fake grav update
        $twig->twig_vars['grav_update'] = array('current' => '0.9.1', 'available' => '0.9.1');

        switch ($this->template) {
            case 'dashboard':
                $twig->twig_vars['popularity'] = $this->popularity;
                break;
            case 'pages':
                $twig->twig_vars['file'] = File::instance($this->admin->page(true)->filePath());
                $twig->twig_vars['media_types'] = str_replace('defaults,', '',
                    implode(',.', array_keys($this->config->get('media'))));
                break;
        }
    }

    public function onShutdown()
    {
        // Just so we know that we're in this debug mode
        echo '<span style="color:red">system.debugger.shutdown.close_connection = false</span>';
        if ($this->config->get('plugins.admin.popularity.enabled')) {

            // Only track non-admin
            if (!$this->active) {
                $this->popularity->trackHit();
            }
        }
    }

    public function onTaskGPM()
    {
        $action = $_POST['action']; // getUpdatable | getUpdatablePlugins | getUpdatableThemes | gravUpdates

        if (isset($this->grav['session'])) {
            $this->grav['session']->close();
        }

        try {
            $gpm = new GPM();

            switch ($action) {
                case 'getUpdates':
                    $resources_updates = $gpm->getUpdatable();
                    $grav_updates = [
                        "isUpdatable" => $gpm->grav->isUpdatable(),
                        "assets"      => $gpm->grav->getAssets(),
                        "version"     => GRAV_VERSION,
                        "available"   => $gpm->grav->getVersion(),
                        "date"        => $gpm->grav->getDate()
                    ];

                    echo json_encode([
                        "success" => true,
                        "payload" => ["resources" => $resources_updates, "grav" => $grav_updates, "installed" => $gpm->countInstalled()]
                    ]);
                    break;
            }
        } catch (\Exception $e) {
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }

        exit;
    }

    protected function initializeAdmin()
    {
        $this->enable([
            'onPagesInitialized'  => ['onPagesInitialized', 1000],
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 1000],
            'onTwigSiteVariables' => ['onTwigSiteVariables', 1000],
            'onTask.GPM'          => ['onTaskGPM', 0]
        ]);

        // Change login behavior.
        $this->config->set('plugins.login', $this->config->get('plugins.admin.login'));

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
    }
}
