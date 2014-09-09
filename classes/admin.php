<?php
namespace Grav\Plugin;

use Grav\Common\User\User;
use Grav\Common\Filesystem\File;
use Grav\Common\Grav;
use Grav\Common\Plugins;
use Grav\Common\Session;
use Grav\Common\Themes;
use Grav\Common\Uri;
use Grav\Common\Page\Pages;
use Grav\Common\Page\Page;
use Grav\Common\Data;

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
     * @var Session\Session
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
     * @return Session\Session
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
        /** @var Session\Message $messages */
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
        /** @var Session\Message $messages */
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
            $file = File\Yaml::instance(ACCOUNTS_DIR . $form['username'] . YAML_EXT);
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
        static $data = array();

        if (isset($data[$type])) {
            return $data[$type];
        }

        switch ($type) {
            case 'configuration':
            case 'system':
                $type = 'system';
                $blueprints = $this->blueprints($type);
                $file = File\Yaml::instance(USER_DIR . "config/{$type}.yaml");
                $obj = new Data\Data($file->content(), $blueprints);
                $obj->merge($post);
                $obj->file($file);
                $data[$type] = $obj;
                break;

            case 'settings':
            case 'site':
                $type = 'site';
                $blueprints = $this->blueprints($type);
                $file = File\Yaml::instance(USER_DIR . "config/{$type}.yaml");
                $obj = new Data\Data($file->content(), $blueprints);
                $obj->merge($post);
                $obj->file($file);
                $data[$type] = $obj;
                break;

            case 'login':
                $data[$type] = null;
                break;

            default:
                if (preg_match('|plugins/|', $type)) {
                    $obj = $this->grav['plugins']->get(preg_replace('|plugins/|', '', $type));
                    $obj->merge($post);

                    $data[$type] = $obj;
                } elseif (preg_match('|themes/|', $type)) {
                    $obj = $this->grav['themes']->get(preg_replace('|themes/|', '', $type));
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
     * Get all themes.
     *
     * @return array
     */
    public function themes()
    {
        return $this->grav['themes']->all();
    }

    /**
     * Get all routes.
     *
     * @return array
     */
    public function routes()
    {
        return $this->grav['pages']->routes()->all();
    }

    /**
     * Get all plugins.
     *
     * @return array
     */
    public function plugins()
    {
        return $this->grav['plugins']->all();
    }

    /**
     * Get log file for fatal errors.
     *
     * @return string
     */
    public function logs()
    {
        if (!isset($this->logs)) {
            $file = File\Log::instance(LOG_DIR . 'exception.log');

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
     * @return [type]         [description]
     */
    public function latestPages($count=10)
    {
        $latest = array();

        foreach ($this->grav['pages']->routes() as $url => $path) {
            $page = $this->grav['pages']->dispatch($url);
            $latest[$page->route()] = ['modified'=>$page->modified(),'page'=>$page];
        }

        // sort based on modified
        uasort($latest, function($a, $b) {
            if ($a['modified'] == $b['modified']) {
                return 0;
            }
            return ($a['modified'] > $b['modified']) ? -1 : 1;
        });

        // build new array with just pages in it
        // TODO: Optimized this
        $pages = array();
        foreach ($latest as $item) {
            $pages[] = $item['page'];
        }

        return array_slice($pages, 0, $count);
    }

    /**
     * Get log file for fatal errors.
     *
     * @return string
     */
    public function logEntry()
    {
        $file = File\General::instance(LOG_DIR . $this->route . '.html');
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
            $page->header();

            // Add routing information.
            $pages->addPage($page, $path);

            // Determine page type.
            if (isset($this->session->{$page->route()})) {
                // Found the type from the session.
                $page->name($this->session->{$page->route()} . '.md');
            } else {
                // Find out the type by looking at the parent.
                $type = $parent->child_type() ? $parent->child_type() : $parent->blueprints()->get('child_type', 'default');
                $page->name($type.CONTENT_EXT);
            }
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
