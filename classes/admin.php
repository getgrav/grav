<?php
namespace Grav\Plugin;

use Grav\Common\File\CompiledYamlFile;
use Grav\Common\User\User;
use Grav\Common\Grav;
use Grav\Common\Plugins;
use Grav\Common\Themes;
use Grav\Common\Uri;
use Grav\Common\Page\Pages;
use Grav\Common\Page\Page;
use Grav\Common\Data;
use Grav\Common\GPM\Local\Packages as LocalPackages;
use RocketTheme\Toolbox\File\File;
use RocketTheme\Toolbox\File\LogFile;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use RocketTheme\Toolbox\Session\Message;
use RocketTheme\Toolbox\Session\Session;
use Symfony\Component\Yaml\Yaml;

class Admin
{
    /**
     * @var Grav
     */
    public $grav;

    /**
     * @var Uri $uri
     */
    protected $uri;

    /**
     * @var array
     */
    protected $pages = array();

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var Data\Blueprints
     */
    protected $blueprints;

    /**
     * @var string
     */
    public $base;

    /**
     * @var string
     */
    public $location;

    /**
     * @var string
     */
    public $route;

    /**
     * @var User
     */
    public $user;

    /**
     * @var Packages
     */
    public $localPackages;


    /**
     * Constructor.
     *
     * @param Grav   $grav
     * @param string $base
     * @param string $location
     * @param string $route
     */
    public function __construct(Grav $grav, $base, $location, $route)
    {
        $this->grav = $grav;
        $this->base = $base;
        $this->location = $location;
        $this->route = $route;

        $this->uri = $this->grav['uri'];
        $this->session = $this->grav['session'];
        $this->user = $this->grav['user'];
    }

    /**
     * Get current session.
     *
     * @return Session
     */
    public function session()
    {
        return $this->session;
    }

    /**
     * Add message into the session queue.
     *
     * @param string $msg
     * @param string $type
     */
    public function setMessage($msg, $type = 'info')
    {
        /** @var Message $messages */
        $messages = $this->grav['messages'];
        $messages->add($msg, $type);
    }

    /**
     * Fetch and delete messages from the session queue.
     *
     * @param string $type
     * @return array
     */
    public function messages($type = null)
    {
        /** @var Message $messages */
        $messages = $this->grav['messages'];
        return $messages->fetch($type);
    }

    /**
     * Authenticate user.
     *
     * @param  array $form Form fields.
     * @return bool
     */
    public function authenticate($form)
    {
        if (!$this->user->authenticated && isset($form['username']) && isset($form['password'])) {
            $file = CompiledYamlFile::instance(ACCOUNTS_DIR . $form['username'] . YAML_EXT);
            if ($file->exists()) {
                $user = new User($file->content());
                $user->authenticated = true;

                // Authenticate user.
                $result = $user->authenticate($form['password']);

                if ($result) {
                    $this->user = $this->session->user = $user;

                    /** @var Grav $grav */
                    $grav = $this->grav;
                    $grav->redirect($this->uri->route());
                }
            }
        }

        return $this->authorise();
    }

    /**
     * Checks user authorisation to the action.
     *
     * @param  string  $action
     * @return bool
     */
    public function authorise($action = 'admin.login')
    {
        return $this->user->authorise($action);
    }

    /**
     * Returns edited page.
     *
     * @param bool $route
     * @return Page
     */
    public function page($route = false)
    {
        $path = $this->route;

        if ($route && !$path) {
            $path = '/';
        }

        if (!isset($this->pages[$path])) {
            $this->pages[$path] = $this->getPage($path);
        }

        return $this->pages[$path];
    }

    /**
     * Returns blueprints for the given type.
     *
     * @param string $type
     * @return Data\Blueprint
     */
    public function blueprints($type)
    {
        if ($this->blueprints === null) {
            $this->blueprints = new Data\Blueprints(SYSTEM_DIR . '/blueprints/');
        }
        return $this->blueprints->get($type);
    }

    /**
     * Gets configuration data.
     *
     * @param string $type
     * @param array $post
     * @return Data\Data|null
     * @throws \RuntimeException
     */
    public function data($type, $post = array())
    {
        static $data = [];

        if (isset($data[$type])) {
            return $data[$type];
        }

        if (!$post) {
            $post = isset($_POST) ? $_POST : [];
        }

        switch ($type) {
            case 'configuration':
            case 'system':
                $type = 'system';
                $blueprints = $this->blueprints("config/{$type}");
                $config = $this->grav['config'];
                $obj = new Data\Data($config->get('system'), $blueprints);
                $obj->merge($post);
                $file = CompiledYamlFile::instance(USER_DIR . "config/{$type}.yaml");
                $obj->file($file);
                $data[$type] = $obj;
                break;

            case 'settings':
            case 'site':
                $type = 'site';
                $blueprints = $this->blueprints("config/{$type}");
                $config = $this->grav['config'];
                $obj = new Data\Data($config->get('site'), $blueprints);
                $obj->merge($post);
                $file = CompiledYamlFile::instance(USER_DIR . "config/{$type}.yaml");
                $obj->file($file);
                $data[$type] = $obj;
                break;

            case 'login':
                $data[$type] = null;
                break;

            default:
                /** @var UniformResourceLocator $locator */
                $locator = $this->grav['locator'];
                $filename = $locator->findResource("config://{$type}.yaml", true, true);
                $file = CompiledYamlFile::instance($filename);

                if (preg_match('|plugins/|', $type)) {
                    /** @var Plugins $plugins */
                    $plugins = $this->grav['plugins'];
                    $obj = $plugins->get(preg_replace('|plugins/|', '', $type));
                    $obj->merge($post);
                    $obj->file($file);

                    $data[$type] = $obj;
                } elseif (preg_match('|themes/|', $type)) {
                    /** @var Themes $themes */
                    $themes = $this->grav['themes'];
                    $obj = $themes->get(preg_replace('|themes/|', '', $type));
                    $obj->merge($post);
                    $obj->file($file);

                    $data[$type] = $obj;
                } elseif (preg_match('|users/|', $type)) {
                    $obj = User::load(preg_replace('|users/|', '', $type));
                    $obj->merge($post);

                    $data[$type] = $obj;
                } else {
                    throw new \RuntimeException("Data type '{$type}' doesn't exist!");
                }
        }

        return $data[$type];
    }

    /**
     * Converts dot notation to array notation.
     *
     * @param  string $name
     * @return string
     */
    public function field($name)
    {
        $path = explode('.', $name);

        return array_shift($path) . ($path ? '[' . implode('][', $path) . ']' : '');
    }

    /**
     * Get all routes.
     *
     * @return array
     */
    public function routes()
    {
        /** @var Pages $pages */
        $pages = $this->grav['pages'];
        return $pages->routes();
    }

    /**
     * Get all plugins.
     *
     * @return array
     */
    public function plugins()
    {
        if (!$this->localPackages) {
            $this->localPackages = new LocalPackages();
        }

        return $this->localPackages['plugins'];
    }

    /**
     * Get all themes.
     *
     * @return array
     */
    public function themes()
    {


        if (!$this->localPackages) {
            $this->localPackages = new LocalPackages();
        }

        return $this->localPackages['themes'];
    }

    /**
     * Get log file for fatal errors.
     *
     * @return string
     */
    public function logs()
    {
        if (!isset($this->logs)) {
            $file = LogFile::instance(LOG_DIR . 'exception.log');

            $content = $file->content();

            $this->logs = array_reverse($content);
        }
        return $this->logs;
    }

    /**
     * Used by the Dashboard in the admin to display the X latest pages
     * that have been modified
     *
     * @param  integer $count number of pages to pull back
     * @return array
     */
    public function latestPages($count = 10)
    {
        /** @var Pages $pages */
        $pages = $this->grav['pages'];

        $latest = array();

        foreach ($pages->routes() as $url => $path) {
            $page = $pages->dispatch($url);
            $latest[$page->route()] = ['modified'=>$page->modified(),'page'=>$page];
        }

        // sort based on modified
        uasort($latest, function ($a, $b) {
            if ($a['modified'] == $b['modified']) {
                return 0;
            }
            return ($a['modified'] > $b['modified']) ? -1 : 1;
        });

        // build new array with just pages in it
        // TODO: Optimized this
        $list = array();
        foreach ($latest as $item) {
            $list[] = $item['page'];
        }

        return array_slice($list, 0, $count);
    }

    /**
     * Get log file for fatal errors.
     *
     * @return string
     */
    public function logEntry()
    {
        $file = File::instance(LOG_DIR . $this->route . '.html');
        $content = $file->content();

        return $content;
    }

    /**
     * Returns the page creating it if it does not exist.
     *
     * @param $path
     * @return Page
     */
    protected function getPage($path)
    {
        /** @var Pages $pages */
        $pages = $this->grav['pages'];

        if ($path && $path[0] != '/') {
            $path = "/{$path}";
        }

        $page = $path ? $pages->dispatch($path, true) : $pages->root();

        if (!$page) {
            $slug = basename($path);
            $ppath = dirname($path);

            // Find or create parent(s).
            $parent = $this->getPage($ppath != '/' ? $ppath : '');

            // Create page.
            $page = new Page;
            $page->parent($parent);
            $page->filePath($parent->path().'/'.$slug.'/'.$page->name());

            // Add routing information.
            $pages->addPage($page, $path);

            // Determine page type.
            if (isset($this->session->{$page->route()})) {
                // Found the type and header from the session.
                $data = $this->session->{$page->route()};
                $page->name($data['type'] . '.md');
                $page->header(['title' => $data['title']]);
                $page->frontmatter(Yaml::dump((array) $page->header()));
            } else {
                // Find out the type by looking at the parent.
                $type = $parent->childType() ? $parent->childType() : $parent->blueprints()->get('child_type', 'default');
                $page->name($type.CONTENT_EXT);
                $page->header();
            }
            $page->modularTwig($slug[0] == '_');
        }

        return $page;
    }

    /**
     * Static helper method to return current route.
     *
     * @return string
     */
    public static function route()
    {
        return dirname('/' . Grav::instance()['admin']->route);
    }
}
