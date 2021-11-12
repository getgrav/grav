<?php

/**
 * @package    Grav\Common
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

use Composer\Autoload\ClassLoader;
use Grav\Common\Config\Config;
use Grav\Common\Config\Setup;
use Grav\Common\Helpers\Exif;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Medium\ImageMedium;
use Grav\Common\Page\Medium\Medium;
use Grav\Common\Page\Pages;
use Grav\Common\Processors\AssetsProcessor;
use Grav\Common\Processors\BackupsProcessor;
use Grav\Common\Processors\DebuggerAssetsProcessor;
use Grav\Common\Processors\InitializeProcessor;
use Grav\Common\Processors\PagesProcessor;
use Grav\Common\Processors\PluginsProcessor;
use Grav\Common\Processors\RenderProcessor;
use Grav\Common\Processors\RequestProcessor;
use Grav\Common\Processors\SchedulerProcessor;
use Grav\Common\Processors\TasksProcessor;
use Grav\Common\Processors\ThemesProcessor;
use Grav\Common\Processors\TwigProcessor;
use Grav\Common\Scheduler\Scheduler;
use Grav\Common\Service\AccountsServiceProvider;
use Grav\Common\Service\AssetsServiceProvider;
use Grav\Common\Service\BackupsServiceProvider;
use Grav\Common\Service\ConfigServiceProvider;
use Grav\Common\Service\ErrorServiceProvider;
use Grav\Common\Service\FilesystemServiceProvider;
use Grav\Common\Service\FlexServiceProvider;
use Grav\Common\Service\InflectorServiceProvider;
use Grav\Common\Service\LoggerServiceProvider;
use Grav\Common\Service\OutputServiceProvider;
use Grav\Common\Service\PagesServiceProvider;
use Grav\Common\Service\RequestServiceProvider;
use Grav\Common\Service\SessionServiceProvider;
use Grav\Common\Service\StreamsServiceProvider;
use Grav\Common\Service\TaskServiceProvider;
use Grav\Common\Twig\Twig;
use Grav\Framework\DI\Container;
use Grav\Framework\Psr7\Response;
use Grav\Framework\RequestHandler\RequestHandler;
use Grav\Framework\Session\Messages;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RocketTheme\Toolbox\Event\Event;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use function array_key_exists;
use function call_user_func_array;
use function function_exists;
use function get_class;
use function in_array;
use function is_callable;
use function is_int;
use function strlen;

/**
 * Grav container is the heart of Grav.
 *
 * @package Grav\Common
 */
class Grav extends Container
{
    /** @var string Processed output for the page. */
    public $output;

    /** @var static The singleton instance */
    protected static $instance;

    /**
     * @var array Contains all Services and ServicesProviders that are mapped
     *            to the dependency injection container.
     */
    protected static $diMap = [
        AccountsServiceProvider::class,
        AssetsServiceProvider::class,
        BackupsServiceProvider::class,
        ConfigServiceProvider::class,
        ErrorServiceProvider::class,
        FilesystemServiceProvider::class,
        FlexServiceProvider::class,
        InflectorServiceProvider::class,
        LoggerServiceProvider::class,
        OutputServiceProvider::class,
        PagesServiceProvider::class,
        RequestServiceProvider::class,
        SessionServiceProvider::class,
        StreamsServiceProvider::class,
        TaskServiceProvider::class,
        'browser'    => Browser::class,
        'cache'      => Cache::class,
        'events'     => EventDispatcher::class,
        'exif'       => Exif::class,
        'plugins'    => Plugins::class,
        'scheduler'  => Scheduler::class,
        'taxonomy'   => Taxonomy::class,
        'themes'     => Themes::class,
        'twig'       => Twig::class,
        'uri'        => Uri::class,
    ];

    /**
     * @var array All middleware processors that are processed in $this->process()
     */
    protected $middleware = [
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

    /** @var array */
    protected $initialized = [];

    /**
     * Reset the Grav instance.
     *
     * @return void
     */
    public static function resetInstance(): void
    {
        if (self::$instance) {
            // @phpstan-ignore-next-line
            self::$instance = null;
        }
    }

    /**
     * Return the Grav instance. Create it if it's not already instanced
     *
     * @param array $values
     * @return Grav
     */
    public static function instance(array $values = [])
    {
        if (null === self::$instance) {
            self::$instance = static::load($values);

            /** @var ClassLoader|null $loader */
            $loader = self::$instance['loader'] ?? null;
            if ($loader) {
                // Load fix for Deferred Twig Extension
                $loader->addPsr4('Phive\\Twig\\Extensions\\Deferred\\', LIB_DIR . 'Phive/Twig/Extensions/Deferred/', true);
            }
        } elseif ($values) {
            $instance = self::$instance;
            foreach ($values as $key => $value) {
                $instance->offsetSet($key, $value);
            }
        }

        return self::$instance;
    }

    /**
     * Get Grav version.
     *
     * @return string
     */
    public function getVersion(): string
    {
        return GRAV_VERSION;
    }

    /**
     * @return bool
     */
    public function isSetup(): bool
    {
        return isset($this->initialized['setup']);
    }

    /**
     * Setup Grav instance using specific environment.
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

        // Force environment if passed to the method.
        if ($environment) {
            Setup::$environment = $environment;
        }

        // Initialize setup and streams.
        $this['setup'];
        $this['streams'];

        return $this;
    }

    /**
     * Initialize CLI environment.
     *
     * Call after `$grav->setup($environment)`
     *
     * - Load configuration
     * - Initialize logger
     * - Disable debugger
     * - Set timezone, locale
     * - Load plugins (call PluginsLoadedEvent)
     * - Set Pages and Users type to be used in the site
     *
     * This method WILL NOT initialize assets, twig or pages.
     *
     * @return $this
     */
    public function initializeCli()
    {
        InitializeProcessor::initializeCli($this);

        return $this;
    }

    /**
     * Process a request
     *
     * @return void
     */
    public function process(): void
    {
        if (isset($this->initialized['process'])) {
            return;
        }

        // Initialize Grav if needed.
        $this->setup();

        $this->initialized['process'] = true;

        $container = new Container(
            [
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

        $default = static function () {
            return new Response(404, ['Expires' => 0, 'Cache-Control' => 'no-store, max-age=0'], 'Not Found');
        };

        $collection = new RequestHandler($this->middleware, $default, $container);

        $response = $collection->handle($this['request']);
        $body = $response->getBody();

        /** @var Messages $messages */
        $messages = $this['messages'];

        // Prevent caching if session messages were displayed in the page.
        $noCache = $messages->isCleared();
        if ($noCache) {
            $response = $response->withHeader('Cache-Control', 'no-store, max-age=0');
        }

        // Handle ETag and If-None-Match headers.
        if ($response->getHeaderLine('ETag') === '1') {
            $etag = md5($body);
            $response = $response->withHeader('ETag', '"' . $etag . '"');

            $search = trim($this['request']->getHeaderLine('If-None-Match'), '"');
            if ($noCache === false && $search === $etag) {
                $response = $response->withStatus(304);
                $body = '';
            }
        }

        // Echo page content.
        $this->header($response);
        echo $body;

        $this['debugger']->render();

        // Response object can turn off all shutdown processing. This can be used for example to speed up AJAX responses.
        // Note that using this feature will also turn off response compression.
        if ($response->getHeaderLine('Grav-Internal-SkipShutdown') !== '1') {
            register_shutdown_function([$this, 'shutdown']);
        }
    }

    /**
     * Terminates Grav request with a response.
     *
     * Please use this method instead of calling `die();` or `exit();`. Note that you need to create a response object.
     *
     * @param ResponseInterface $response
     * @return void
     */
    public function close(ResponseInterface $response): void
    {
        // Make sure nothing extra gets written to the response.
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Close the session.
        if (isset($this['session'])) {
            $this['session']->close();
        }

        /** @var ServerRequestInterface $request */
        $request = $this['request'];

        /** @var Debugger $debugger */
        $debugger = $this['debugger'];
        $response = $debugger->logRequest($request, $response);

        $body = $response->getBody();

        /** @var Messages $messages */
        $messages = $this['messages'];

        // Prevent caching if session messages were displayed in the page.
        $noCache = $messages->isCleared();
        if ($noCache) {
            $response = $response->withHeader('Cache-Control', 'no-store, max-age=0');
        }

        // Handle ETag and If-None-Match headers.
        if ($response->getHeaderLine('ETag') === '1') {
            $etag = md5($body);
            $response = $response->withHeader('ETag', '"' . $etag . '"');

            $search = trim($this['request']->getHeaderLine('If-None-Match'), '"');
            if ($noCache === false && $search === $etag) {
                $response = $response->withStatus(304);
                $body = '';
            }
        }

        // Echo page content.
        $this->header($response);
        echo $body;
        exit();
    }

    /**
     * @param ResponseInterface $response
     * @return void
     * @deprecated 1.7 Do not use
     */
    public function exit(ResponseInterface $response): void
    {
        $this->close($response);
    }

    /**
     * Terminates Grav request and redirects browser to another location.
     *
     * Please use this method instead of calling `header("Location: {$url}", true, 302); exit();`.
     *
     * @param string $route Internal route.
     * @param int|null $code  Redirection code (30x)
     * @return void
     */
    public function redirect($route, $code = null): void
    {
        $response = $this->getRedirectResponse($route, $code);

        $this->close($response);
    }

    /**
     * Returns redirect response object from Grav.
     *
     * @param string $route Internal route.
     * @param int|null $code  Redirection code (30x)
     * @return ResponseInterface
     */
    public function getRedirectResponse($route, $code = null): ResponseInterface
    {
        /** @var Uri $uri */
        $uri = $this['uri'];

        // Clean route for redirect
        $route = preg_replace("#^\/[\\\/]+\/#", '/', $route);

        if ($code < 300 || $code > 399) {
            $code = null;
        }

        if (null === $code) {
            // Check for redirect code in the route: e.g. /new/[301], /new[301]/route or /new[301].html
            $regex = '/.*(\[(30[1-7])\])(.\w+|\/.*?)?$/';
            preg_match($regex, $route, $matches);
            if ($matches) {
                $route = str_replace($matches[1], '', $matches[0]);
                $code = $matches[2];
            }
        }

        if ($code === null) {
            $code = $this['config']->get('system.pages.redirect_default_code', 302);
        }

        if ($uri::isExternal($route)) {
            $url = $route;
        } else {
            $url = rtrim($uri->rootUrl(), '/') . '/';

            if ($this['config']->get('system.pages.redirect_trailing_slash', true)) {
                $url .= trim($route, '/'); // Remove trailing slash
            } else {
                $url .= ltrim($route, '/'); // Support trailing slash default routes
            }
        }

        if ($uri->extension() === 'json') {
            return new Response(200, ['Content-Type' => 'application/json'], json_encode(['code' => $code, 'redirect' => $url], JSON_THROW_ON_ERROR));
        }

        return new Response($code, ['Location' => $url]);
    }

    /**
     * Redirect browser to another location taking language into account (preferred)
     *
     * @param string $route Internal route.
     * @param int    $code  Redirection code (30x)
     * @return void
     */
    public function redirectLangSafe($route, $code = null): void
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
     * @return void
     */
    public function header(ResponseInterface $response = null): void
    {
        if (null === $response) {
            /** @var PageInterface $page */
            $page = $this['page'];
            $response = new Response($page->httpResponseCode(), $page->httpHeaders(), '');
        }

        header("HTTP/{$response->getProtocolVersion()} {$response->getStatusCode()} {$response->getReasonPhrase()}");
        foreach ($response->getHeaders() as $key => $values) {
            // Skip internal Grav headers.
            if (strpos($key, 'Grav-Internal-') === 0) {
                continue;
            }
            foreach ($values as $i => $value) {
                header($key . ': ' . $value, $i === 0);
            }
        }
    }

    /**
     * Set the system locale based on the language and configuration
     *
     * @return void
     */
    public function setLocale(): void
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
     * @param object $event
     * @return object
     */
    public function dispatchEvent($event)
    {
        /** @var EventDispatcherInterface $events */
        $events = $this['events'];
        $eventName = get_class($event);

        $timestamp = microtime(true);
        $event = $events->dispatch($event);

        /** @var Debugger $debugger */
        $debugger = $this['debugger'];
        $debugger->addEvent($eventName, $event, $events, $timestamp);

        return $event;
    }

    /**
     * Fires an event with optional parameters.
     *
     * @param  string $eventName
     * @param  Event|null $event
     * @return Event
     */
    public function fireEvent($eventName, Event $event = null)
    {
        /** @var EventDispatcherInterface $events */
        $events = $this['events'];
        if (null === $event) {
            $event = new Event();
        }

        $timestamp = microtime(true);
        $events->dispatch($event, $eventName);

        /** @var Debugger $debugger */
        $debugger = $this['debugger'];
        $debugger->addEvent($eventName, $event, $events, $timestamp);

        return $event;
    }

    /**
     * Set the final content length for the page and flush the buffer
     *
     * @return void
     */
    public function shutdown(): void
    {
        // Prevent user abort allowing onShutdown event to run without interruptions.
        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(true);
        }

        // Close the session allowing new requests to be handled.
        if (isset($this['session'])) {
            $this['session']->close();
        }

        /** @var Config $config */
        $config = $this['config'];
        if ($config->get('system.debugger.shutdown.close_connection', true)) {
            // Flush the response and close the connection to allow time consuming tasks to be performed without leaving
            // the connection to the client open. This will make page loads to feel much faster.

            // FastCGI allows us to flush all response data to the client and finish the request.
            $success = function_exists('fastcgi_finish_request') ? @fastcgi_finish_request() : false;
            if (!$success) {
                // Unfortunately without FastCGI there is no way to force close the connection.
                // We need to ask browser to close the connection for us.

                if ($config->get('system.cache.gzip')) {
                    // Flush gzhandler buffer if gzip setting was enabled to get the size of the compressed output.
                    ob_end_flush();
                } elseif ($config->get('system.cache.allow_webserver_gzip')) {
                    // Let web server to do the hard work.
                    header('Content-Encoding: identity');
                } elseif (function_exists('apache_setenv')) {
                    // Without gzip we have no other choice than to prevent server from compressing the output.
                    // This action turns off mod_deflate which would prevent us from closing the connection.
                    @apache_setenv('no-gzip', '1');
                } else {
                    // Fall back to unknown content encoding, it prevents most servers from deflating the content.
                    header('Content-Encoding: none');
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
     * @return mixed|null
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

        $container->registerServices();

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
    protected function registerServices(): void
    {
        foreach (self::$diMap as $serviceKey => $serviceClass) {
            if (is_int($serviceKey)) {
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
     * @return PageInterface|false
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
        if (!empty($fallback_types) && !in_array($uri_extension, $fallback_types, true)) {
            return false;
        }
        if (!array_key_exists($uri_extension, $supported_types)) {
            return false;
        }

        $path_parts = pathinfo($path);

        /** @var Pages $pages */
        $pages = $this['pages'];
        $page = $pages->find($path_parts['dirname'], true);

        if ($page) {
            $media = $page->media()->all();
            $parsed_url = parse_url(rawurldecode($uri->basename()));
            $media_file = $parsed_url['path'];

            // if this is a media object, try actions first
            if (isset($media[$media_file])) {
                /** @var Medium $medium */
                $medium = $media[$media_file];
                foreach ($uri->query(null, true) as $action => $params) {
                    if (in_array($action, ImageMedium::$magic_actions, true)) {
                        call_user_func_array([&$medium, $action], explode(',', $params));
                    }
                }
                Utils::download($medium->path(), false);
            }

            // unsupported media type, try to download it...
            if ($uri_extension) {
                $extension = $uri_extension;
            } elseif (isset($path_parts['extension'])) {
                $extension = $path_parts['extension'];
            } else {
                $extension = null;
            }

            if ($extension) {
                $download = true;
                if (in_array(ltrim($extension, '.'), $config->get('system.media.unsupported_inline_types', []), true)) {
                    $download = false;
                }
                Utils::download($page->path() . DIRECTORY_SEPARATOR . $uri->basename(), $download);
            }
        }

        // Nothing found
        return false;
    }
}
