<?php
/**
 * @package    Grav.Common
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

use Grav\Common\Config\Config;
use Grav\Common\Page\Medium\ImageMedium;
use Grav\Common\Page\Medium\Medium;
use Grav\Common\Page\Page;
use RocketTheme\Toolbox\DI\Container;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\Event\EventDispatcher;

class Grav extends Container
{
    /**
     * @var string Processed output for the page.
     */
    public $output;

    /**
     * @var static The singleton instance
     */
    protected static $instance;

    /**
     * @var array Contains all Services and ServicesProviders that are mapped
     *            to the dependency injection container.
     */
    protected static $diMap = [
        'Grav\Common\Service\LoggerServiceProvider',
        'Grav\Common\Service\ErrorServiceProvider',
        'uri'                     => 'Grav\Common\Uri',
        'events'                  => 'RocketTheme\Toolbox\Event\EventDispatcher',
        'cache'                   => 'Grav\Common\Cache',
        'Grav\Common\Service\SessionServiceProvider',
        'plugins'                 => 'Grav\Common\Plugins',
        'themes'                  => 'Grav\Common\Themes',
        'twig'                    => 'Grav\Common\Twig\Twig',
        'taxonomy'                => 'Grav\Common\Taxonomy',
        'language'                => 'Grav\Common\Language\Language',
        'pages'                   => 'Grav\Common\Page\Pages',
        'Grav\Common\Service\TaskServiceProvider',
        'Grav\Common\Service\AssetsServiceProvider',
        'Grav\Common\Service\PageServiceProvider',
        'Grav\Common\Service\OutputServiceProvider',
        'browser'                 => 'Grav\Common\Browser',
        'exif'                    => 'Grav\Common\Helpers\Exif',
        'Grav\Common\Service\StreamsServiceProvider',
        'Grav\Common\Service\ConfigServiceProvider',
        'inflector'               => 'Grav\Common\Inflector',
        'siteSetupProcessor'      => 'Grav\Common\Processors\SiteSetupProcessor',
        'configurationProcessor'  => 'Grav\Common\Processors\ConfigurationProcessor',
        'errorsProcessor'         => 'Grav\Common\Processors\ErrorsProcessor',
        'debuggerInitProcessor'   => 'Grav\Common\Processors\DebuggerInitProcessor',
        'initializeProcessor'     => 'Grav\Common\Processors\InitializeProcessor',
        'pluginsProcessor'        => 'Grav\Common\Processors\PluginsProcessor',
        'themesProcessor'         => 'Grav\Common\Processors\ThemesProcessor',
        'tasksProcessor'          => 'Grav\Common\Processors\TasksProcessor',
        'assetsProcessor'         => 'Grav\Common\Processors\AssetsProcessor',
        'twigProcessor'           => 'Grav\Common\Processors\TwigProcessor',
        'pagesProcessor'          => 'Grav\Common\Processors\PagesProcessor',
        'debuggerAssetsProcessor' => 'Grav\Common\Processors\DebuggerAssetsProcessor',
        'renderProcessor'         => 'Grav\Common\Processors\RenderProcessor',
    ];

    /**
     * @var array All processors that are processed in $this->process()
     */
    protected $processors = [
        'siteSetupProcessor',
        'configurationProcessor',
        'errorsProcessor',
        'debuggerInitProcessor',
        'initializeProcessor',
        'pluginsProcessor',
        'themesProcessor',
        'tasksProcessor',
        'assetsProcessor',
        'twigProcessor',
        'pagesProcessor',
        'debuggerAssetsProcessor',
        'renderProcessor',
    ];

    /**
     * Reset the Grav instance.
     */
    public static function resetInstance()
    {
        if (self::$instance) {
            self::$instance = null;
        }
    }

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
        } elseif ($values) {
            $instance = self::$instance;
            foreach ($values as $key => $value) {
                $instance->offsetSet($key, $value);
            }
        }

        return self::$instance;
    }

    /**
     * Process a request
     */
    public function process()
    {
        // process all processors (e.g. config, initialize, assets, ..., render)
        foreach ($this->processors as $processor) {
            $processor = $this[$processor];
            $this->measureTime($processor->id, $processor->title, function () use ($processor) {
                $processor->process();
            });
        }

        /** @var Debugger $debugger */
        $debugger = $this['debugger'];
        $debugger->render();

        register_shutdown_function([$this, 'shutdown']);
    }

    /**
     * Set the system locale based on the language and configuration
     */
    public function setLocale()
    {
        // Initialize Locale if set and configured.
        if ($this['language']->enabled() && $this['config']->get('system.languages.override_locale')) {
            $language = $this['language']->getLanguage();
            setlocale(LC_ALL, strlen($language) < 3 ? ($language . '_' . strtoupper($language)) : $language);
        } elseif ($this['config']->get('system.default_locale')) {
            setlocale(LC_ALL, $this['config']->get('system.default_locale'));
        }
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
            $code = $this['config']->get('system.pages.redirect_default_code', 302);
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
        if (!$this['uri']->isExternal($route)) {
            $this->redirect($this['pages']->route($route), $code);
        } else {
            $this->redirect($route, $code);
        }
    }

    /**
     * Set response header.
     */
    public function header()
    {
        /** @var Page $page */
        $page = $this['page'];

        $format = $page->templateFormat();

        header('Content-type: ' . Utils::getMimeByExtension($format, 'text/html'));

        $cache_control = $page->cacheControl();

        // Calculate Expires Headers if set to > 0
        $expires = $page->expires();

        if ($expires > 0) {
            $expires_date = gmdate('D, d M Y H:i:s', time() + $expires) . ' GMT';
            if (!$cache_control) {
                header('Cache-Control: max-age=' . $expires);
            }
            header('Expires: ' . $expires_date);
        }

        // Set cache-control header
        if ($cache_control) {
            header('Cache-Control: ' . strtolower($cache_control));
        }

        // Set the last modified time
        if ($page->lastModified()) {
            $last_modified_date = gmdate('D, d M Y H:i:s', $page->modified()) . ' GMT';
            header('Last-Modified: ' . $last_modified_date);
        }

        // Calculate a Hash based on the raw file
        if ($page->eTag()) {
            header('ETag: "' . md5($page->raw() . $page->modified()).'"');
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
                // Unfortunately without FastCGI there is no way to force close the connection.
                // We need to ask browser to close the connection for us.
                if ($this['config']->get('system.cache.gzip')) {
                    // Flush gzhandler buffer if gzip setting was enabled.
                    ob_end_flush();

                } else {
                    // Without gzip we have no other choice than to prevent server from compressing the output.
                    // This action turns off mod_deflate which would prevent us from closing the connection.
                    if ($this['config']->get('system.cache.allow_webserver_gzip')) {
                        header('Content-Encoding: identity');
                    } else {
                        header('Content-Encoding: none');
                    }

                }


                // Get length and close the connection.
                header('Content-Length: ' . ob_get_length());
                header("Connection: close");

                ob_end_flush();
                @ob_flush();
                flush();
            }
        }

        // Run any time consuming tasks.
        $this->fireEvent('onShutdown');
    }

    /**
     * Magic Catch All Function
     * Used to call closures like measureTime on the instance.
     * Source: http://stackoverflow.com/questions/419804/closures-as-class-members
     */
    public function __call($method, $args)
    {
        $closure = $this->$method;
        call_user_func_array($closure, $args);
    }

    /**
     * Initialize and return a Grav instance
     *
     * @param  array $values
     *
     * @return static
     */
    protected static function load(array $values)
    {
        $container = new static($values);

        $container['grav'] = $container;

        $container['debugger'] = new Debugger();
        $debugger = $container['debugger'];

        // closure that measures time by wrapping a function into startTimer and stopTimer
        // The debugger can be passed to the closure. Should be more performant
        // then to get it from the container all time.
        $container->measureTime = function ($timerId, $timerTitle, $callback) use ($debugger) {
            $debugger->startTimer($timerId, $timerTitle);
            $callback();
            $debugger->stopTimer($timerId);
        };

        $container->measureTime('_services', 'Services', function () use ($container) {
            $container->registerServices($container);
        });

        return $container;
    }

    /**
     * Register all services
     * Services are defined in the diMap. They can either only the class
     * of a Service Provider or a pair of serviceKey => serviceClass that
     * gets directly mapped into the container.
     *
     * @return void
     */
    protected function registerServices()
    {
        foreach (self::$diMap as $serviceKey => $serviceClass) {
            if (is_int($serviceKey)) {
                $this->registerServiceProvider($serviceClass);
            } else {
                $this->registerService($serviceKey, $serviceClass);
            }
        }
    }

    /**
     * Register a service provider with the container.
     *
     * @param  string $serviceClass
     *
     * @return void
     */
    protected function registerServiceProvider($serviceClass)
    {
        $this->register(new $serviceClass);
    }

    /**
     * Register a service with the container.
     *
     * @param  string $serviceKey
     * @param  string $serviceClass
     *
     * @return void
     */
    protected function registerService($serviceKey, $serviceClass)
    {
        $this[$serviceKey] = function ($c) use ($serviceClass) {
            return new $serviceClass($c);
        };
    }

    /**
     * This attempts to find media, other files, and download them
     *
     * @param $path
     */
    public function fallbackUrl($path)
    {
        $this->fireEvent('onPageFallBackUrl');

        /** @var Uri $uri */
        $uri = $this['uri'];

        /** @var Config $config */
        $config = $this['config'];

        $uri_extension = strtolower($uri->extension());
        $fallback_types = $config->get('system.media.allowed_fallback_types', null);
        $supported_types = $config->get('media.types');

        // Check whitelist first, then ensure extension is a valid media type
        if (!empty($fallback_types) && !\in_array($uri_extension, $fallback_types, true)) {
            return false;
        }
        if (!array_key_exists($uri_extension, $supported_types)) {
            return false;
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

            // Nothing found
            return false;
        }

        return $page;
    }
}
