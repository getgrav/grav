<?php
namespace Grav\Common;

use Grav\Common\Config\Blueprints;
use Grav\Common\Config\Config;
use Grav\Common\Config\ConfigServiceProvider;
use Grav\Common\Service\StreamsServiceProvider;
use Grav\Component\DI\Container;
use Grav\Component\EventDispatcher\Event;
use Grav\Component\EventDispatcher\EventDispatcher;

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

        $container['grav'] = $container;

        $container['events'] = function ($c) {
            return new EventDispatcher;
        };
        $container['uri'] = function ($c) {
            return new Uri($c);
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
            $page = $c['pages']->dispatch($c['uri']->route());

            if (!$page || !$page->routable()) {
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
        $container['user_agent'] = function ($c) {
            return new \phpUserAgent();
        };

        $container->register(new StreamsServiceProvider);
        $container->register(new ConfigServiceProvider);

        return $container;
    }

    public function process()
    {
        // Use output buffering to prevent headers from being sent too early.
        ob_start();

        // Initialize configuration.
        $this['config']->init();

        $this['plugins']->init();

        $this->fireEvent('onPluginsInitialized');

        $this['assets']->init();

        $this->fireEvent('onAssetsInitialized');

        $this['twig']->init();
        $this['pages']->init();

        $this->fireEvent('onPagesInitialized');

        $this->fireEvent('onPageInitialized');

        // Process whole page as required
        $this->output = $this['output'];

        $this->fireEvent('onOutputGenerated');

        // Set the header type
        $this->header();

        echo $this->output;

        ob_end_flush();
        flush();

        $this->fireEvent('onOutputRendered');
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
}
