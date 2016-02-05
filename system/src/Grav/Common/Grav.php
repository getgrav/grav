<?php
namespace Grav\Common;

use Grav\Common\Config\Config;
use Grav\Common\Language\Language;
use Grav\Common\Page\Medium\ImageMedium;
use Grav\Common\Page\Medium\Medium;
use Grav\Common\Page\Page;
use Grav\Common\Page\Pages;
use Grav\Common\Service\ConfigServiceProvider;
use Grav\Common\Service\ErrorServiceProvider;
use Grav\Common\Service\LoggerServiceProvider;
use Grav\Common\Service\StreamsServiceProvider;
use Grav\Common\Twig\Twig;
use RocketTheme\Toolbox\DI\Container;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\Event\EventDispatcher;

/**
 * Grav
 *
 * @author  Andy Miller
 * @link    http://www.rockettheme.com
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

    /**
     * Return the Grav instance. Create it if it's not already instanced
     *
     * @param array $values
     *
     * @return Grav
     */
    public static function instance(array $values = [])
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

    /**
     * Initialize and return a Grav instance
     *
     * @param array $values
     *
     * @return static
     */
    protected static function load(array $values)
    {
        $container = new static($values);

        $container['grav'] = $container;

        $container['debugger'] = new Debugger();
        $container['debugger']->startTimer('_services', 'Services');

        $container->register(new LoggerServiceProvider);

        $container->register(new ErrorServiceProvider);

        $container['uri'] = function ($c) {
            /** @var Grav $c */
            return new Uri($c);
        };

        $container['task'] = function ($c) {
            /** @var Grav $c */
            return !empty($_POST['task']) ? $_POST['task'] : $c['uri']->param('task');
        };

        $container['events'] = function () {
            return new EventDispatcher;
        };
        $container['cache'] = function ($c) {
            /** @var Grav $c */
            return new Cache($c);
        };
        $container['session'] = function ($c) {
            /** @var Grav $c */
            return new Session($c);
        };
        $container['plugins'] = function () {
            return new Plugins();
        };
        $container['themes'] = function ($c) {
            /** @var Grav $c */
            return new Themes($c);
        };
        $container['twig'] = function ($c) {
            /** @var Grav $c */
            return new Twig($c);
        };
        $container['taxonomy'] = function ($c) {
            /** @var Grav $c */
            return new Taxonomy($c);
        };
        $container['language'] = function ($c) {
            /** @var Grav $c */
            return new Language($c);
        };

        $container['pages'] = function ($c) {
            /** @var Grav $c */
            return new Pages($c);
        };

        $container['assets'] = new Assets();

        $container['page'] = function ($c) {
            /** @var Grav $c */

            /** @var Pages $pages */
            $pages = $c['pages'];
            /** @var Language $language */
            $language = $c['language'];

            /** @var Uri $uri */
            $uri = $c['uri'];

            $path = $uri->path(); // Don't trim to support trailing slash default routes
            $path = $path ?: '/';

            $page = $pages->dispatch($path);

            // Redirection tests
            if ($page) {
                // Language-specific redirection scenarios
                if ($language->enabled()) {
                    if ($language->isLanguageInUrl() && !$language->isIncludeDefaultLanguage()) {
                        $c->redirect($page->route());
                    }
                    if (!$language->isLanguageInUrl() && $language->isIncludeDefaultLanguage()) {
                        $c->redirectLangSafe($page->route());
                    }
                }
                // Default route test and redirect
                if ($c['config']->get('system.pages.redirect_default_route') && $page->route() != $path) {
                    $c->redirectLangSafe($page->route());
                }
            }

            // if page is not found, try some fallback stuff
            if (!$page || !$page->routable()) {

                // Try fallback URL stuff...
                $c->fallbackUrl($path);

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
            /** @var Grav $c */
            return $c['twig']->processSite($c['uri']->extension());
        };

        $container['browser'] = function () {
            return new Browser();
        };

        $container->register(new StreamsServiceProvider);
        $container->register(new ConfigServiceProvider);

        $container['inflector'] = new Inflector();

        $container['debugger']->stopTimer('_services');

        return $container;
    }

    /**
     * Process a request
     */
    public function process()
    {
        /** @var Debugger $debugger */
        $debugger = $this['debugger'];

        // Load site setup and initializing streams.
        $debugger->startTimer('_setup', 'Site Setup');
        $this['setup']->init();
        $this['streams'];
        $debugger->stopTimer('_setup');

        // Initialize configuration.
        $debugger->startTimer('_config', 'Configuration');
        $this['config']->init();
        $debugger->stopTimer('_config');

        // Initialize error handlers.
        $this['errors']->resetHandlers();

        // Initialize debugger.
        $debugger->init();
        $debugger->startTimer('init', 'Initialize');
        $this['config']->debug();

        // Use output buffering to prevent headers from being sent too early.
        ob_start();
        if ($this['config']->get('system.cache.gzip')) {
            // Enable zip/deflate with a fallback in case of if browser does not support compressing.
            if (!ob_start("ob_gzhandler")) {
                ob_start();
            }
        }

        // Initialize the timezone.
        if ($this['config']->get('system.timezone')) {
            date_default_timezone_set($this['config']->get('system.timezone'));
        }

        // Initialize uri, session.
        $this['uri']->init();
        $this['session']->init();

        // Initialize Locale if set and configured.
        if ($this['language']->enabled() && $this['config']->get('system.languages.override_locale')) {
            setlocale(LC_ALL, $this['language']->getLanguage());
        } elseif ($this['config']->get('system.default_locale')) {
            setlocale(LC_ALL, $this['config']->get('system.default_locale'));
        }

        $debugger->stopTimer('init');

        $debugger->startTimer('plugins', 'Plugins');
        $this['plugins']->init();
        $this->fireEvent('onPluginsInitialized');
        $debugger->stopTimer('plugins');

        $debugger->startTimer('themes', 'Themes');
        $this['themes']->init();
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
     * @param int    $code  Redirection code (30x)
     */
    public function redirect($route, $code = null)
    {
        /** @var Uri $uri */
        $uri = $this['uri'];

        //Check for code in route
        $regex = '/.*(\[(30[1-7])\])$/';
        preg_match($regex, $route, $matches);
        if ($matches) {
            $route = str_replace($matches[1], '', $matches[0]);
            $code = $matches[2];
        }

        if ($code === null) {
            $code = $this['config']->get('system.pages.redirect_default_code', 301);
        }

        if (isset($this['session'])) {
            $this['session']->close();
        }

        if ($uri->isExternal($route)) {
            $url = $route;
        } else {
            $url = rtrim($uri->rootUrl(), '/') . '/';

            if ($this['config']->get('system.pages.redirect_trailing_slash', true)) {
                $url .= trim($route, '/'); // Remove trailing slash
            } else {
                $url .= ltrim($route, '/'); // Support trailing slash default routes
            }
        }

        header("Location: {$url}", true, $code);
        exit();
    }

    /**
     * Redirect browser to another location taking language into account (preferred)
     *
     * @param string $route Internal route.
     * @param int    $code  Redirection code (30x)
     */
    public function redirectLangSafe($route, $code = null)
    {
        /** @var Language $language */
        $language = $this['language'];

        if (!$this['uri']->isExternal($route) && $language->enabled() && $language->isIncludeDefaultLanguage()) {
            $this->redirect($language->getLanguage() . $route, $code);
        } else {
            $this->redirect($route, $code);
        }
    }

    /**
     * Returns mime type for the file format.
     *
     * @param string $format
     *
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
            header('Cache-Control: max-age=' . $expires);
            header('Expires: ' . $expires_date);
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

        // Vary: Accept-Encoding
        if ($this['config']->get('system.pages.vary_accept_encoding', false)) {
            header('Vary: Accept-Encoding');
        }
    }

    /**
     * Fires an event with optional parameters.
     *
     * @param  string $eventName
     * @param  Event  $event
     *
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
        // Prevent user abort allowing onShutdown event to run without interruptions.
        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(true);
        }

        // Close the session allowing new requests to be handled.
        if (isset($this['session'])) {
            $this['session']->close();
        }

        if ($this['config']->get('system.debugger.shutdown.close_connection', true)) {
            // Flush the response and close the connection to allow time consuming tasks to be performed without leaving
            // the connection to the client open. This will make page loads to feel much faster.

            // FastCGI allows us to flush all response data to the client and finish the request.
            $success = function_exists('fastcgi_finish_request') ? @fastcgi_finish_request() : false;

            if (!$success) {
                // Unfortunately without FastCGI there is no way to close the connection. We need to ask browser to
                // close the connection for us.

                if ($this['config']->get('system.cache.gzip')) {
                    // Flush gzhandler buffer if gzip setting was enabled.
                    ob_end_flush();

                } else {
                    // Without gzip we have no other choice than to prevent server from compressing the output.
                    // This action turns off mod_deflate which would prevent us from closing the connection.
                    header('Content-Encoding: none');
                }

                // Get length and close the connection.
                header('Content-Length: ' . ob_get_length());
                header("Connection: close");

                // Finally flush the regular buffer.
                ob_end_flush();
                @ob_flush();
                flush();
            }
        }

        // Run any time consuming tasks.
        $this->fireEvent('onShutdown');
    }

    /**
     * This attempts to find media, other files, and download them
     *
     * @param $path
     */
    protected function fallbackUrl($path)
    {
        /** @var Uri $uri */
        $uri = $this['uri'];

        /** @var Config $config */
        $config = $this['config'];

        $uri_extension = $uri->extension();
        $fallback_types = $config->get('system.media.allowed_fallback_types', null);
        $supported_types = $config->get('media');

        // Check whitelist first, then ensure extension is a valid media type
        if (!empty($fallback_types) && !in_array($uri_extension, $fallback_types)) {
            return;
        } elseif (!array_key_exists($uri_extension, $supported_types)) {
            return;
        }

        $path_parts = pathinfo($path);

        /** @var Page $page */
        $page = $this['pages']->dispatch($path_parts['dirname'], true);

        if ($page) {
            $media = $page->media()->all();
            $parsed_url = parse_url(rawurldecode($uri->basename()));
            $media_file = $parsed_url['path'];

            // if this is a media object, try actions first
            if (isset($media[$media_file])) {
                /** @var Medium $medium */
                $medium = $media[$media_file];
                foreach ($uri->query(null, true) as $action => $params) {
                    if (in_array($action, ImageMedium::$magic_actions)) {
                        call_user_func_array([&$medium, $action], explode(',', $params));
                    }
                }
                Utils::download($medium->path(), false);
            }

            // unsupported media type, try to download it...
            if ($uri_extension) {
                $extension = $uri_extension;
            } else {
                if (isset($path_parts['extension'])) {
                    $extension = $path_parts['extension'];
                } else {
                    $extension = null;
                }
            }

            if ($extension) {
                $download = true;
                if (in_array(ltrim($extension, '.'), $config->get('system.media.unsupported_inline_types', []))) {
                    $download = false;
                }
                Utils::download($page->path() . DIRECTORY_SEPARATOR . $uri->basename(), $download);
            }
        }
    }
}
