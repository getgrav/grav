<?php
namespace Grav\Common;

use Grav\Common\Page\Medium\ImageMedium;
use Grav\Common\Page\Pages;
use Grav\Common\Service\ConfigServiceProvider;
use Grav\Common\Service\ErrorServiceProvider;
use Grav\Common\Service\LoggerServiceProvider;
use Grav\Common\Service\StreamsServiceProvider;
use RocketTheme\Toolbox\DI\Container;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\Event\EventDispatcher;

/**
 * Grav
 *
 * @author Andy Miller
 * @link http://www.rockettheme.com
 * @license http://opensource.org/licenses/MIT
 *
 * Influenced by Pico, Stacey, Kirby, PieCrust and other great platforms...
 */
class Grav extends Container
{
    /**
     * @var string
     */
    public $output;

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

        $container['grav'] = $container;

        $container['debugger'] = new Debugger();
        $container['debugger']->startTimer('_init', 'Initialize');

        $container->register(new LoggerServiceProvider);

        $container->register(new ErrorServiceProvider);

        $container['uri'] = function ($c) {
            return new Uri($c);
        };

        $container['task'] = function ($c) {
            return !empty($_POST['task']) ? $_POST['task'] : $c['uri']->param('task');
        };

        $container['events'] = function ($c) {
            return new EventDispatcher;
        };
        $container['cache'] = function ($c) {
            return new Cache($c);
        };
        $container['plugins'] = function ($c) {
            return new Plugins();
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
            /** @var Pages $pages */
            $pages = $c['pages'];

            /** @var Uri $uri */
            $uri = $c['uri'];

            $path = rtrim($uri->path(), '/');
            $path = $path ?: '/';

            $page = $pages->dispatch($path);

            if (!$page || !$page->routable()) {
                $path_parts = pathinfo($path);
                $page = $c['pages']->dispatch($path_parts['dirname'], true);
                if ($page) {
                    $media = $page->media()->all();

                    $parsed_url = parse_url(urldecode($uri->basename()));

                    $media_file = $parsed_url['path'];

                    // if this is a media object, try actions first
                    if (isset($media[$media_file])) {
                        $medium = $media[$media_file];
                        foreach ($uri->query(null, true) as $action => $params) {
                            if (in_array($action, ImageMedium::$magic_actions)) {
                                call_user_func_array(array(&$medium, $action), explode(',', $params));
                            }
                        }
                        Utils::download($medium->path(), false);
                    } else {
                        Utils::download($page->path() . DIRECTORY_SEPARATOR . $uri->basename(), true);
                    }
                }

                // If no page found, fire event
                $event = $c->fireEvent('onPageNotFound');

                if (isset($event->page)) {
                    $page = $event->page;
                } else {
                    throw new \RuntimeException('Page Not Found', 404);
                }
            }
            return $page;
        };
        $container['output'] = function ($c) {
            return $c['twig']->processSite($c['uri']->extension());
        };
        $container['browser'] = function ($c) {
            return new Browser();
        };

        $container['base_url_absolute'] = function ($c) {
            return $c['config']->get('system.base_url_absolute') ?: $c['uri']->rootUrl(true);
        };
        $container['base_url_relative'] = function ($c) {
            return $c['config']->get('system.base_url_relative') ?: $c['uri']->rootUrl(false);
        };
        $container['base_url'] = function ($c) {
            return $c['config']->get('system.absolute_urls') ? $c['base_url_absolute'] : $c['base_url_relative'];
        };

        $container->register(new StreamsServiceProvider);
        $container->register(new ConfigServiceProvider);

        $container['debugger']->stopTimer('_init');

        return $container;
    }

    public function process()
    {
        /** @var Debugger $debugger */
        $debugger = $this['debugger'];

        // Initialize configuration.
        $debugger->startTimer('_config', 'Configuration');
        $this['config']->init();
        $this['uri']->init();
        $this['errors']->resetHandlers();
        $debugger->init();
        $this['config']->debug();
        $debugger->stopTimer('_config');

        // Use output buffering to prevent headers from being sent too early.
        ob_start();
        if ($this['config']->get('system.cache.gzip')) {
            ob_start('ob_gzhandler');
        }

        // Initialize the timezone
        if ($this['config']->get('system.timezone')) {
            date_default_timezone_set($this['config']->get('system.timezone'));
        }

        $debugger->startTimer('streams', 'Streams');
        $this['streams'];
        $debugger->stopTimer('streams');

        $debugger->startTimer('plugins', 'Plugins');
        $this['plugins']->init();
        $this->fireEvent('onPluginsInitialized');
        $debugger->stopTimer('plugins');

        $debugger->startTimer('themes', 'Themes');
        $this['themes']->init();
        $this->fireEvent('onThemeInitialized');
        $debugger->stopTimer('themes');

        $task = $this['task'];
        if ($task) {
            $this->fireEvent('onTask.' . $task);
        }

        $this['assets']->init();
        $this->fireEvent('onAssetsInitialized');

        $debugger->startTimer('twig', 'Twig');
        $this['twig']->init();
        $debugger->stopTimer('twig');

        $debugger->startTimer('pages', 'Pages');
        $this['pages']->init();
        $this->fireEvent('onPagesInitialized');
        $debugger->stopTimer('pages');

        $this->fireEvent('onPageInitialized');

        $debugger->addAssets();

        // Process whole page as required
        $debugger->startTimer('render', 'Render');
        $this->output = $this['output'];
        $this->fireEvent('onOutputGenerated');
        $debugger->stopTimer('render');

        // Set the header type
        $this->header();
        echo $this->output;
        $debugger->render();

        $this->fireEvent('onOutputRendered');

        register_shutdown_function([$this, 'shutdown']);
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

        if (isset($this['session'])) {
            $this['session']->close();
        }

        if ($this['uri']->isExternal($route)) {
            $url = $route;
        } else {
            $url = rtrim($uri->rootUrl(), '/') .'/'. trim($route, '/');
        }

        header("Location: {$url}", true, $code);
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
        $extension = $this['uri']->extension();

        /** @var Page $page */
        $page = $this['page'];

        header('Content-type: ' . $this->mime($extension));

        // Calculate Expires Headers if set to > 0
        $expires = $page->expires();

        if ($expires > 0) {
            $expires_date = gmdate('D, d M Y H:i:s', time() + $expires) . ' GMT';
            header('Cache-Control: max-age=' . $expires_date);
            header('Expires: '. $expires_date);
        }

        // Set the last modified time
        if ($page->lastModified()) {
            $last_modified_date = gmdate('D, d M Y H:i:s', $page->modified()) . ' GMT';
            header('Last-Modified: ' . $last_modified_date);
        }

        // Calculate a Hash based on the raw file
        if ($page->eTag()) {
            header('ETag: ' . md5($page->raw() . $page->modified()));
        }

        // Set debugger data in headers
        if (!($extension === null || $extension == 'html')) {
            $this['debugger']->enabled(false);
        }

        // Set HTTP response code
        if (isset($this['page']->header()->http_response_code)) {
            http_response_code($this['page']->header()->http_response_code);
        }
    }

    /**
     * Fires an event with optional parameters.
     *
     * @param  string $eventName
     * @param  Event  $event
     * @return Event
     */
    public function fireEvent($eventName, Event $event = null)
    {
        /** @var EventDispatcher $events */
        $events = $this['events'];
        return $events->dispatch($eventName, $event);
    }

    /**
     * Set the final content length for the page and flush the buffer
     *
     */
    public function shutdown()
    {
        if ($this['config']->get('system.debugger.shutdown.close_connection')) {
            //stop user abort
            if (function_exists('ignore_user_abort')) {
                @ignore_user_abort(true);
            }

            // close the session
            if (isset($this['session'])) {
                $this['session']->close();
            }

            // flush buffer if gzip buffer was started
            if ($this['config']->get('system.cache.gzip')) {
                ob_end_flush(); // gzhandler buffer
            }

            // get lengh and close the connection
            header('Content-Length: ' . ob_get_length());
            header("Connection: close");

            // flush the regular buffer
            ob_end_flush();
            @ob_flush();
            flush();

            // fix for fastcgi close connection issue
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }

        }

        $this->fireEvent('onShutdown');
    }
}
