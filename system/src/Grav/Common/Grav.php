<?php

/**
 * @package    Grav\Common
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

use Grav\Common\Config\Config;
use Grav\Common\Config\Setup;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Medium\ImageMedium;
use Grav\Common\Page\Medium\Medium;
use Grav\Common\Processors\AssetsProcessor;
use Grav\Common\Processors\BackupsProcessor;
use Grav\Common\Processors\ConfigurationProcessor;
use Grav\Common\Processors\DebuggerAssetsProcessor;
use Grav\Common\Processors\DebuggerProcessor;
use Grav\Common\Processors\ErrorsProcessor;
use Grav\Common\Processors\InitializeProcessor;
use Grav\Common\Processors\LoggerProcessor;
use Grav\Common\Processors\PagesProcessor;
use Grav\Common\Processors\PluginsProcessor;
use Grav\Common\Processors\RenderProcessor;
use Grav\Common\Processors\RequestProcessor;
use Grav\Common\Processors\SchedulerProcessor;
use Grav\Common\Processors\TasksProcessor;
use Grav\Common\Processors\ThemesProcessor;
use Grav\Common\Processors\TwigProcessor;
use Grav\Framework\DI\Container;
use Grav\Framework\Psr7\Response;
use Grav\Framework\RequestHandler\RequestHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\Event\EventDispatcher;

/**
 * Grav container is the heart of Grav.
 *
 * @package Grav\Common
 */
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
        'Grav\Common\Service\AccountsServiceProvider',
        'Grav\Common\Service\AssetsServiceProvider',
        'Grav\Common\Service\BackupsServiceProvider',
        'Grav\Common\Service\ConfigServiceProvider',
        'Grav\Common\Service\ErrorServiceProvider',
        'Grav\Common\Service\FilesystemServiceProvider',
        'Grav\Common\Service\InflectorServiceProvider',
        'Grav\Common\Service\LoggerServiceProvider',
        'Grav\Common\Service\OutputServiceProvider',
        'Grav\Common\Service\PagesServiceProvider',
        'Grav\Common\Service\RequestServiceProvider',
        'Grav\Common\Service\SessionServiceProvider',
        'Grav\Common\Service\StreamsServiceProvider',
        'Grav\Common\Service\TaskServiceProvider',
        'browser'    => 'Grav\Common\Browser',
        'cache'      => 'Grav\Common\Cache',
        'events'     => 'RocketTheme\Toolbox\Event\EventDispatcher',
        'exif'       => 'Grav\Common\Helpers\Exif',
        'plugins'    => 'Grav\Common\Plugins',
        'scheduler'  => 'Grav\Common\Scheduler\Scheduler',
        'taxonomy'   => 'Grav\Common\Taxonomy',
        'themes'     => 'Grav\Common\Themes',
        'twig'       => 'Grav\Common\Twig\Twig',
        'uri'        => 'Grav\Common\Uri',
    ];

    /**
     * @var array All middleware processors that are processed in $this->process()
     */
    protected $middleware = [
        'configurationProcessor',
        'loggerProcessor',
        'errorsProcessor',
        'debuggerProcessor',
        'initializeProcessor',
        'pluginsProcessor',
        'themesProcessor',
        'requestProcessor',
        'tasksProcessor',
        'backupsProcessor',
        'schedulerProcessor',
        'assetsProcessor',
        'twigProcessor',
        'pagesProcessor',
        'debuggerAssetsProcessor',
        'renderProcessor',
    ];

    protected $initialized = [];

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
     * Setup Grav instance using specific environment.
     *
     * Initializes Grav streams by
     *
     * @param string|null $environment
     * @return $this
     */
    public function setup(string $environment = null)
    {
        if (isset($this->initialized['setup'])) {
            return $this;
        }

        $this->initialized['setup'] = true;

        $this->measureTime('_setup', 'Site Setup', function () use ($environment) {
            // Force environment if passed to the method.
            if ($environment) {
                Setup::$environment = $environment;
            }

            $this['setup'];
            $this['streams'];
        });

        return $this;
    }

    /**
     * Initialize CLI environment.
     *
     * Call after `$grav->setup($environment)`
     *
     * - Load configuration
     * - Disable debugger
     * - Set timezone, locale
     * - Load plugins
     * - Set Users type to be used in the site
     *
     * This method WILL NOT initialize assets, twig or pages.
     *
     * @param string|null $environment
     * @return $this
     */
    public function initializeCli()
    {
        InitializeProcessor::initializeCli($this);

        return $this;
    }

    /**
     * Process a request
     */
    public function process()
    {
        if (isset($this->initialized['process'])) {
            return;
        }

        // Initialize Grav if needed.
        $this->setup();

        $this->initialized['process'] = true;

        $container = new Container(
            [
                'configurationProcessor' => function () {
                    return new ConfigurationProcessor($this);
                },
                'loggerProcessor' => function () {
                    return new LoggerProcessor($this);
                },
                'errorsProcessor' => function () {
                    return new ErrorsProcessor($this);
                },
                'debuggerProcessor' => function () {
                    return new DebuggerProcessor($this);
                },
                'initializeProcessor' => function () {
                    return new InitializeProcessor($this);
                },
                'backupsProcessor' => function () {
                    return new BackupsProcessor($this);
                },
                'pluginsProcessor' => function () {
                    return new PluginsProcessor($this);
                },
                'themesProcessor' => function () {
                    return new ThemesProcessor($this);
                },
                'schedulerProcessor' => function () {
                    return new SchedulerProcessor($this);
                },
                'requestProcessor' => function () {
                    return new RequestProcessor($this);
                },
                'tasksProcessor' => function () {
                    return new TasksProcessor($this);
                },
                'assetsProcessor' => function () {
                    return new AssetsProcessor($this);
                },
                'twigProcessor' => function () {
                    return new TwigProcessor($this);
                },
                'pagesProcessor' => function () {
                    return new PagesProcessor($this);
                },
                'debuggerAssetsProcessor' => function () {
                    return new DebuggerAssetsProcessor($this);
                },
                'renderProcessor' => function () {
                    return new RenderProcessor($this);
                },
            ]
        );

        $default = function (ServerRequestInterface $request) {
            return new Response(404, ['Expires' => 0, 'Cache-Control' => 'no-cache, no-store, must-revalidate'], 'Not Found');
        };

        /** @var Debugger $debugger */
        $debugger = $this['debugger'];

        $collection = new RequestHandler($this->middleware, $default, $container);

        $response = $collection->handle($this['request']);
        $body = $response->getBody();

        // Handle ETag and If-None-Match headers.
        if ($response->getHeaderLine('ETag') === '1') {
            $etag = md5($body);
            $response = $response->withHeader('ETag', $etag);

            if ($this['request']->getHeaderLine('If-None-Match') === $etag) {
                $response = $response->withStatus(304);
                $body = '';
            }
        }

        $this->header($response);
        echo $body;

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
            setlocale(LC_ALL, \strlen($language) < 3 ? ($language . '_' . strtoupper($language)) : $language);
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

        // Clean route for redirect
        $route = preg_replace("#^\/[\\\/]+\/#", '/', $route);

         // Check for code in route
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
     *
     * @param ResponseInterface|null $response
     */
    public function header(ResponseInterface $response = null)
    {
        if (null === $response) {
            /** @var PageInterface $page */
            $page = $this['page'];
            $response = new Response($page->httpResponseCode(), $page->httpHeaders(), '');
        }

        header("HTTP/{$response->getProtocolVersion()} {$response->getStatusCode()} {$response->getReasonPhrase()}");
        foreach ($response->getHeaders() as $key => $values) {
            foreach ($values as $i => $value) {
                header($key . ': ' . $value, $i === 0);
            }
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
        if (\function_exists('ignore_user_abort')) {
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
            $success = \function_exists('fastcgi_finish_request') ? @fastcgi_finish_request() : false;

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
                header('Connection: close');

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
     *
     * Used to call closures.
     *
     * Source: http://stackoverflow.com/questions/419804/closures-as-class-members
     *
     * @param string $method
     * @param array $args
     * @return
     */
    public function __call($method, $args)
    {
        $closure = $this->{$method} ?? null;

        return is_callable($closure) ? $closure(...$args) : null;
    }

    /**
     * Measure how long it takes to do an action.
     *
     * @param string $timerId
     * @param string $timerTitle
     * @param callable $callback
     * @return mixed   Returns value returned by the callable.
     */
    public function measureTime(string $timerId, string $timerTitle, callable $callback)
    {
        $debugger = $this['debugger'];
        $debugger->startTimer($timerId, $timerTitle);
        $result = $callback();
        $debugger->stopTimer($timerId);

        return $result;
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

        $container['debugger'] = new Debugger();
        $container['grav'] = function (Container $container) {
            user_error('Calling $grav[\'grav\'] or {{ grav.grav }} is deprecated since Grav 1.6, just use $grav or {{ grav }}', E_USER_DEPRECATED);

            return $container;
        };

        $container->measureTime('_services', 'Services', function () use ($container) {
            $container->registerServices();
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
            if (\is_int($serviceKey)) {
                $this->register(new $serviceClass);
            } else {
                $this[$serviceKey] = function ($c) use ($serviceClass) {
                    return new $serviceClass($c);
                };
            }
        }
    }

    /**
     * This attempts to find media, other files, and download them
     *
     * @param string $path
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

        /** @var PageInterface $page */
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
                    if (\in_array($action, ImageMedium::$magic_actions, true)) {
                        \call_user_func_array([&$medium, $action], explode(',', $params));
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
                if (\in_array(ltrim($extension, '.'), $config->get('system.media.unsupported_inline_types', []), true)) {
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
