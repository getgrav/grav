<?php
namespace Grav\Common;

use \Tracy\Debugger;
use \Grav\Common\Page\Page;
use \Grav\Common\Page\Pages;


/**
 * Grav
 *
 * @author Andy Miller
 * @link http://www.rockettheme.com
 * @license http://opensource.org/licenses/MIT
 * @version 0.1
 *
 * Originally based on Pico by Gilbert Pellegrom - http://pico.dev7studios.com
 * Influeced by Pico, Stacey, Kirby, PieCrust and other great platforms...
 *
 * @property  Plugins  $plugins
 * @property  Config   $config
 * @property  Cache    $cache
 * @property  Uri      $uri
 * @property  Pages    $pages
 * @property  Page     $page
 */
class Grav extends Getters
{
    /**
     * @var string  Grav output.
     */
    protected $output;

    /**
     * @var array
     */
    protected $plugins;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Cache
     */
    protected $cache;

    /**
     * @var Uri
     */
    protected $uri;

    /**
     * @var Pages
     */
    protected $pages;

    /**
     * @var Page
     */
    protected $page;

    /**
     * @var Twig
     */
    protected $twig;

    /**
     * @var Taxonomy
     */
    protected $taxonomy;

    public function process()
    {
        // Get the URI and URL (needed for configuration)
        $this->uri = Registry::get('Uri');

        // Get the Configuration settings and caching
        $this->config = Registry::get('Config');

        Debugger::$logDirectory = $this->config->get('system.debugger.log.enabled') ? LOG_DIR : null;
        Debugger::$maxDepth = $this->config->get('system.debugger.max_depth');

        // Switch debugger into development mode if configured
        if ($this->config->get('system.debugger.enabled')) {
            if (function_exists('ini_set')) {
                ini_set('display_errors', true);
            }
            Debugger::$productionMode = Debugger::DEVELOPMENT;
            $this->fireEvent('onAfterInitDebug');
        }

        // Get the Caching setup
        $this->cache = Registry::get('Cache');
        $this->cache->init();

        // Get Plugins
        $plugins = new Plugins();
        $this->plugins = $plugins->load();
        $this->fireEvent('onAfterInitPlugins');

        // Get current theme and hook it into plugins.
        $themes = new Themes();
        $this->plugins['Theme'] = $themes->load();

        // Get twig object
        $this->twig = Registry::get('Twig');
        $this->twig->init();

        // Get all the Pages that Grav knows about
        $this->pages = Registry::get('Pages');
        $this->pages->init();
        $this->fireEvent('onAfterGetPages');

        // Get the taxonomy and set it on the grav object
        $this->taxonomy = Registry::get('Taxonomy');

        // Get current page
        $this->page = $this->pages->dispatch($this->uri->route());
        $this->fireEvent('onAfterGetPage');

        // If there's no page, throw exception
        if (!$this->page) {
            throw new \RuntimeException('Page Not Found', 404);
        }

        // Process whole page as required
        $this->output = $this->twig->processSite($this->uri->extension());
        $this->fireEvent('onAfterGetOutput');

        // Set the header type
        $this->header();

        echo $this->output;
    }

    /**
     * Redirect browser to another location.
     *
     * @param string $route Internal route.
     * @param int $code Redirection code (30x)
     */
    public function redirect($route, $code = 303)
    {
        header("Location: " . rtrim($this->uri->rootUrl(), '/') .'/'. trim($route, '/'), true, $code);
        exit();
    }

    /**
     * Returns mime type for the file format.
     *
     * @param string $format
     * @return string
     */
    public function mime($format)
    {
        switch ($format) {
            case 'json':
                return 'application/json';
            case 'html':
                return 'text/html';
            case 'atom':
                return 'application/atom+xml';
            case 'rss':
                return 'application/rss+xml';
            case 'xml':
                return 'application/xml';
        }
        return 'text/html';
    }

    /**
     * Set response header.
     */
    public function header()
    {
        header('Content-type: ' . $this->mime($this->uri->extension()));
    }

    /**
     * Log a message.
     *
     * @param string $message
     */
    protected static function log($message)
    {
        if (Debugger::$logDirectory) {
            Debugger::log(sprintf($message, Debugger::timer() * 1000));
        }
    }

    /**
     * Processes any hooks and runs them.
     */
    public function fireEvent()
    {
        $args = func_get_args();
        $hook_id = array_shift($args);
        $no_timing_hooks = array('onAfterPageProcessed','onAfterFolderProcessed', 'onAfterCollectionProcessed');

        if (!empty($this->plugins)) {
            foreach ($this->plugins as $plugin) {
                if (is_callable(array($plugin, $hook_id))) {
                    call_user_func_array(array($plugin, $hook_id), $args);
                }
            }
        }

        if ($this->config && $this->config->get('system.debugger.log.timing') && !in_array($hook_id, $no_timing_hooks)) {
            static::log($hook_id.': %f ms');
        }
    }
}
