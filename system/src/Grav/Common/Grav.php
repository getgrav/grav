<?php
namespace Grav\Common;

use Grav\Component\DI\Container;

/**
 * Grav
 *
 * @author Andy Miller
 * @link http://www.rockettheme.com
 * @license http://opensource.org/licenses/MIT
 * @version 0.8.0
 *
 * Originally based on Pico by Gilbert Pellegrom - http://pico.dev7studios.com
 * Influenced by Pico, Stacey, Kirby, PieCrust and other great platforms...
 */
class Grav extends Container
{
    /**
     * @var string
     */
    protected $output;

    /**
     * @var static
     */
    protected static $instance;

    public static function instance(array $values = array())
    {
        if (!self::$instance) {
            self::$instance = static::load($values);

            GravTrait::setGrav(self::$instance);

        } elseif ($values) {
            $instance = self::$instance;
            foreach ($values as $key => $value) {
                $instance->offsetSet($key, $value);
            }
        }

        return self::$instance;
    }

    protected static function load(array $values)
    {
        $container = new static($values);

        $container['config_path'] = CACHE_DIR . 'config.php';

        $container['grav'] = $container;

        $container['uri'] = function ($c) {
            return new Uri($c);
        };
        $container['config'] = function ($c) {
            return Config::instance($c);
        };
        $container['cache'] = function ($c) {
            return new Cache($c);
        };
        $container['plugins'] = function ($c) {
            return new Plugins($c);
        };
        $container['themes'] = function ($c) {
            return new Themes($c);
        };
        $container['twig'] = function ($c) {
            return new Twig($c);
        };
        $container['taxonomy'] = function ($c) {
            return new Taxonomy($c);
        };
        $container['pages'] = function ($c) {
            return new Page\Pages($c);
        };
        $container['assets'] = function ($c) {
            return new Assets();
        };
        $container['page'] = function ($c) {
            return $c['pages']->dispatch($c['uri']->route());
        };
        $container['output'] = function ($c) {
            return $c['twig']->processSite($c['uri']->extension());
        };
        $container['user_agent'] = function ($c) {
            return new \phpUserAgent();
        };

        return $container;
    }

    public function process()
    {
        $this['plugins']->init();

        $this->fireEvent('onAfterInitPlugins');

        $this['assets']->init();

        $this->fireEvent('onAfterGetAssets');

        $this['twig']->init();
        $this['pages']->init();

        $this->fireEvent('onAfterGetPages');

        $this->fireEvent('onAfterGetPage');

        // If there's no page, throw exception
        if (!$this['page']) {
            throw new \RuntimeException('Page Not Found', 404);
        }

        // Process whole page as required
        $this->output = $this['output'];

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
        /** @var Uri $uri */
        $uri = $this['uri'];
        header("Location: " . rtrim($uri->rootUrl(), '/') .'/'. trim($route, '/'), true, $code);
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
        /** @var Uri $uri */
        $uri = $this['uri'];
        header('Content-type: ' . $this->mime($uri->extension()));
    }

    /**
     * Processes any hooks and runs them.
     */
    public function fireEvent()
    {
        $args = func_get_args();
        $hook_id = array_shift($args);
        $no_timing_hooks = array('onAfterPageProcessed','onAfterFolderProcessed', 'onAfterCollectionProcessed');

        /** @var Plugins $plugins */
        $plugins = $this['plugins'];

        if (!empty($plugins)) {
            foreach ($plugins as $plugin) {
                if (is_callable(array($plugin, $hook_id))) {
                    call_user_func_array(array($plugin, $hook_id), $args);
                }
            }
        }

        /** @var Config $config */
        $config = $this['config'];

        if ($config && $config->get('system.debugger.log.timing') && !in_array($hook_id, $no_timing_hooks)) {
            /** @var Debugger $debugger */
            $debugger = isset($this['debugger']) ? $this['debugger'] : null;
            if ($debugger) {
                $debugger->log($hook_id.': %f ms');
            }
        }
    }
}
