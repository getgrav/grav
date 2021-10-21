<?php

/**
 * @package    Grav\Common
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

use Clockwork\Clockwork;
use Clockwork\DataSource\MonologDataSource;
use Clockwork\DataSource\PsrMessageDataSource;
use Clockwork\DataSource\XdebugDataSource;
use Clockwork\Helpers\ServerTiming;
use Clockwork\Request\UserData;
use Clockwork\Storage\FileStorage;
use DebugBar\DataCollector\ConfigCollector;
use DebugBar\DataCollector\DataCollectorInterface;
use DebugBar\DataCollector\ExceptionsCollector;
use DebugBar\DataCollector\MemoryCollector;
use DebugBar\DataCollector\MessagesCollector;
use DebugBar\DataCollector\PhpInfoCollector;
use DebugBar\DataCollector\RequestDataCollector;
use DebugBar\DataCollector\TimeDataCollector;
use DebugBar\DebugBar;
use DebugBar\DebugBarException;
use DebugBar\JavascriptRenderer;
use Grav\Common\Config\Config;
use Grav\Common\Processors\ProcessorInterface;
use Grav\Common\Twig\TwigClockworkDataSource;
use Grav\Framework\Psr7\Response;
use Monolog\Logger;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionObject;
use SplFileInfo;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Throwable;
use Twig\Environment;
use Twig\Template;
use Twig\TemplateWrapper;
use function array_slice;
use function call_user_func;
use function count;
use function define;
use function defined;
use function extension_loaded;
use function get_class;
use function gettype;
use function is_array;
use function is_bool;
use function is_object;
use function is_scalar;
use function is_string;

/**
 * Class Debugger
 * @package Grav\Common
 */
class Debugger
{
    /** @var static */
    protected static $instance;
    /** @var Grav|null */
    protected $grav;
    /** @var Config|null */
    protected $config;
    /** @var JavascriptRenderer|null */
    protected $renderer;
    /** @var DebugBar|null */
    protected $debugbar;
    /** @var Clockwork|null */
    protected $clockwork;
    /** @var bool */
    protected $enabled = false;
    /** @var bool */
    protected $initialized = false;
    /** @var array */
    protected $timers = [];
    /** @var array */
    protected $deprecations = [];
    /** @var callable|null */
    protected $errorHandler;
    /** @var float */
    protected $requestTime;
    /** @var float */
    protected $currentTime;
    /** @var int */
    protected $profiling = 0;
    /** @var bool */
    protected $censored = false;

    /**
     * Debugger constructor.
     */
    public function __construct()
    {
        static::$instance = $this;

        $this->currentTime = microtime(true);

        if (!defined('GRAV_REQUEST_TIME')) {
            define('GRAV_REQUEST_TIME', $this->currentTime);
        }

        $this->requestTime = $_SERVER['REQUEST_TIME_FLOAT'] ?? GRAV_REQUEST_TIME;

        // Set deprecation collector.
        $this->setErrorHandler();
    }

    /**
     * @return Clockwork|null
     */
    public function getClockwork(): ?Clockwork
    {
        return $this->enabled ? $this->clockwork : null;
    }

    /**
     * Initialize the debugger
     *
     * @return $this
     * @throws DebugBarException
     */
    public function init()
    {
        if ($this->initialized) {
            return $this;
        }

        $this->grav = Grav::instance();
        $this->config = $this->grav['config'];

        // Enable/disable debugger based on configuration.
        $this->enabled = (bool)$this->config->get('system.debugger.enabled');
        $this->censored = (bool)$this->config->get('system.debugger.censored', false);

        if ($this->enabled) {
            $this->initialized = true;

            $clockwork = $debugbar = null;

            switch ($this->config->get('system.debugger.provider', 'debugbar')) {
                case 'clockwork':
                    $this->clockwork = $clockwork = new Clockwork();
                    break;
                default:
                    $this->debugbar = $debugbar = new DebugBar();
            }

            $plugins_config = (array)$this->config->get('plugins');
            ksort($plugins_config);

            if ($clockwork) {
                $log = $this->grav['log'];
                $clockwork->setStorage(new FileStorage('cache://clockwork'));
                if (extension_loaded('xdebug')) {
                    $clockwork->addDataSource(new XdebugDataSource());
                }
                if ($log instanceof Logger) {
                    $clockwork->addDataSource(new MonologDataSource($log));
                }

                $timeline = $clockwork->timeline();
                if ($this->requestTime !== GRAV_REQUEST_TIME) {
                    $event = $timeline->event('Server');
                    $event->finalize($this->requestTime, GRAV_REQUEST_TIME);
                }
                if ($this->currentTime !== GRAV_REQUEST_TIME) {
                    $event = $timeline->event('Loading');
                    $event->finalize(GRAV_REQUEST_TIME, $this->currentTime);
                }
                $event = $timeline->event('Site Setup');
                $event->finalize($this->currentTime, microtime(true));
            }

            if ($this->censored) {
                $censored = ['CENSORED' => true];
            }

            if ($debugbar) {
                $debugbar->addCollector(new PhpInfoCollector());
                $debugbar->addCollector(new MessagesCollector());
                if (!$this->censored) {
                    $debugbar->addCollector(new RequestDataCollector());
                }
                $debugbar->addCollector(new TimeDataCollector($this->requestTime));
                $debugbar->addCollector(new MemoryCollector());
                $debugbar->addCollector(new ExceptionsCollector());
                $debugbar->addCollector(new ConfigCollector($censored ?? (array)$this->config->get('system'), 'Config'));
                $debugbar->addCollector(new ConfigCollector($censored ?? $plugins_config, 'Plugins'));
                $debugbar->addCollector(new ConfigCollector($this->config->get('streams.schemes'), 'Streams'));

                if ($this->requestTime !== GRAV_REQUEST_TIME) {
                    $debugbar['time']->addMeasure('Server', $debugbar['time']->getRequestStartTime(), GRAV_REQUEST_TIME);
                }
                if ($this->currentTime !== GRAV_REQUEST_TIME) {
                    $debugbar['time']->addMeasure('Loading', GRAV_REQUEST_TIME, $this->currentTime);
                }
                $debugbar['time']->addMeasure('Site Setup', $this->currentTime, microtime(true));
            }

            $this->addMessage('Grav v' . GRAV_VERSION . ' - PHP ' . PHP_VERSION);
            $this->config->debug();

            if ($clockwork) {
                $clockwork->info('System Configuration', $censored ?? $this->config->get('system'));
                $clockwork->info('Plugins Configuration', $censored ?? $plugins_config);
                $clockwork->info('Streams', $this->config->get('streams.schemes'));
            }
        }

        return $this;
    }

    public function finalize(): void
    {
        if ($this->clockwork && $this->enabled) {
            $this->stopProfiling('Profiler Analysis');
            $this->addMeasures();

            $deprecations = $this->getDeprecations();
            $count = count($deprecations);
            if (!$count) {
                return;
            }

            /** @var UserData $userData */
            $userData = $this->clockwork->userData('Deprecated');
            $userData->counters([
                'Deprecated' => count($deprecations)
            ]);
            /*
            foreach ($deprecations as &$deprecation) {
                $d = $deprecation;
                unset($d['message']);
                $this->clockwork->log('deprecated', $deprecation['message'], $d);
            }
            unset($deprecation);
             */

            $userData->table('Your site is using following deprecated features', $deprecations);
        }
    }

    public function logRequest(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->enabled || !$this->clockwork) {
            return $response;
        }

        $clockwork = $this->clockwork;

        $this->finalize();

        $clockwork->timeline()->finalize($request->getAttribute('request_time'));

        if ($this->censored) {
            $censored = 'CENSORED';
            $request = $request
                ->withCookieParams([$censored => ''])
                ->withUploadedFiles([])
                ->withHeader('cookie', $censored);
            $request = $request->withParsedBody([$censored => '']);
        }

        $clockwork->addDataSource(new PsrMessageDataSource($request, $response));

        $clockwork->resolveRequest();
        $clockwork->storeRequest();

        $clockworkRequest = $clockwork->getRequest();

        $response = $response
            ->withHeader('X-Clockwork-Id', $clockworkRequest->id)
            ->withHeader('X-Clockwork-Version', $clockwork::VERSION);

        $grav = Grav::instance();
        $basePath = $this->grav['base_url_relative'] . $grav['pages']->base();
        if ($basePath) {
            $response = $response->withHeader('X-Clockwork-Path', $basePath . '/__clockwork/');
        }

        return $response->withHeader('Server-Timing', ServerTiming::fromRequest($clockworkRequest)->value());
    }


    public function debuggerRequest(RequestInterface $request): Response
    {
        $clockwork = $this->clockwork;

        $headers = [
            'Content-Type' => 'application/json',
            'Grav-Internal-SkipShutdown' => 1
        ];

        $path = $request->getUri()->getPath();
        $clockworkDataUri = '#/__clockwork(?:/(?<id>[0-9-]+))?(?:/(?<direction>(?:previous|next)))?(?:/(?<count>\d+))?#';
        if (preg_match($clockworkDataUri, $path, $matches) === false) {
            $response = ['message' => 'Bad Input'];

            return new Response(400, $headers, json_encode($response));
        }

        $id = $matches['id'] ?? null;
        $direction = $matches['direction'] ?? null;
        $count = $matches['count'] ?? null;

        $storage = $clockwork->getStorage();

        if ($direction === 'previous') {
            $data = $storage->previous($id, $count);
        } elseif ($direction === 'next') {
            $data = $storage->next($id, $count);
        } elseif ($id === 'latest') {
            $data = $storage->latest();
        } else {
            $data = $storage->find($id);
        }

        if (preg_match('#(?<id>[0-9-]+|latest)/extended#', $path)) {
            $clockwork->extendRequest($data);
        }

        if (!$data) {
            $response = ['message' => 'Not Found'];

            return new Response(404, $headers, json_encode($response));
        }

        $data = is_array($data) ? array_map(static function ($item) {
            return $item->toArray();
        }, $data) : $data->toArray();

        return new Response(200, $headers, json_encode($data));
    }

    /**
     * @return void
     */
    protected function addMeasures(): void
    {
        if (!$this->enabled) {
            return;
        }

        $nowTime = microtime(true);
        $clkTimeLine = $this->clockwork ? $this->clockwork->timeline() : null;
        $debTimeLine = $this->debugbar ? $this->debugbar['time'] : null;
        foreach ($this->timers as $name => $data) {
            $description = $data[0];
            $startTime = $data[1] ?? null;
            $endTime = $data[2] ?? $nowTime;
            if ($clkTimeLine) {
                $event = $clkTimeLine->event($description);
                $event->finalize($startTime, $endTime);
            } elseif ($debTimeLine) {
                if ($endTime - $startTime < 0.001) {
                    continue;
                }

                $debTimeLine->addMeasure($description ?? $name, $startTime, $endTime);
            }
        }
        $this->timers = [];
    }

    /**
     * Set/get the enabled state of the debugger
     *
     * @param bool|null $state If null, the method returns the enabled value. If set, the method sets the enabled state
     * @return bool
     */
    public function enabled($state = null)
    {
        if ($state !== null) {
            $this->enabled = (bool)$state;
        }

        return $this->enabled;
    }

    /**
     * Add the debugger assets to the Grav Assets
     *
     * @return $this
     */
    public function addAssets()
    {
        if ($this->enabled) {
            // Only add assets if Page is HTML
            $page = $this->grav['page'];
            if ($page->templateFormat() !== 'html') {
                return $this;
            }

            /** @var Assets $assets */
            $assets = $this->grav['assets'];

            // Clockwork specific assets
            if ($this->clockwork) {
                $assets->addCss('/system/assets/debugger/clockwork.css', ['loading' => 'inline']);
                $assets->addJs('/system/assets/debugger/clockwork.js', ['loading' => 'inline']);
            }


            // Debugbar specific assets
            if ($this->debugbar) {
                // Add jquery library
                $assets->add('jquery', 101);

                $this->renderer = $this->debugbar->getJavascriptRenderer();
                $this->renderer->setIncludeVendors(false);

                [$css_files, $js_files] = $this->renderer->getAssets(null, JavascriptRenderer::RELATIVE_URL);

                foreach ((array)$css_files as $css) {
                    $assets->addCss($css);
                }

                $assets->addCss('/system/assets/debugger/phpdebugbar.css', ['loading' => 'inline']);

                foreach ((array)$js_files as $js) {
                    $assets->addJs($js);
                }
            }
        }

        return $this;
    }

    /**
     * @param int $limit
     * @return array
     */
    public function getCaller($limit = 2)
    {
        $trace = debug_backtrace(false, $limit);

        return array_pop($trace);
    }

    /**
     * Adds a data collector
     *
     * @param DataCollectorInterface $collector
     * @return $this
     * @throws DebugBarException
     */
    public function addCollector($collector)
    {
        if ($this->debugbar && !$this->debugbar->hasCollector($collector->getName())) {
            $this->debugbar->addCollector($collector);
        }

        return $this;
    }

    /**
     * Returns a data collector
     *
     * @param string $name
     * @return DataCollectorInterface|null
     * @throws DebugBarException
     */
    public function getCollector($name)
    {
        if ($this->debugbar && $this->debugbar->hasCollector($name)) {
            return $this->debugbar->getCollector($name);
        }

        return null;
    }

    /**
     * Displays the debug bar
     *
     * @return $this
     */
    public function render()
    {
        if ($this->enabled && $this->debugbar) {
            // Only add assets if Page is HTML
            $page = $this->grav['page'];
            if (!$this->renderer || $page->templateFormat() !== 'html') {
                return $this;
            }

            $this->addMeasures();
            $this->addDeprecations();

            echo $this->renderer->render();
        }

        return $this;
    }

    /**
     * Sends the data through the HTTP headers
     *
     * @return $this
     */
    public function sendDataInHeaders()
    {
        if ($this->enabled && $this->debugbar) {
            $this->addMeasures();
            $this->addDeprecations();
            $this->debugbar->sendDataInHeaders();
        }

        return $this;
    }

    /**
     * Returns collected debugger data.
     *
     * @return array|null
     */
    public function getData()
    {
        if (!$this->enabled || !$this->debugbar) {
            return null;
        }

        $this->addMeasures();
        $this->addDeprecations();
        $this->timers = [];

        return $this->debugbar->getData();
    }

    /**
     * Hierarchical Profiler support.
     *
     * @param callable $callable
     * @param string|null $message
     * @return mixed
     */
    public function profile(callable $callable, string $message = null)
    {
        $this->startProfiling();
        $response = $callable();
        $this->stopProfiling($message);

        return $response;
    }

    public function addTwigProfiler(Environment $twig): void
    {
        $clockwork = $this->getClockwork();
        if ($clockwork) {
            $source = new TwigClockworkDataSource($twig);
            $source->listenToEvents();
            $clockwork->addDataSource($source);
        }
    }

    /**
     * Start profiling code.
     *
     * @return void
     */
    public function startProfiling(): void
    {
        if ($this->enabled && extension_loaded('tideways_xhprof')) {
            $this->profiling++;
            if ($this->profiling === 1) {
                // @phpstan-ignore-next-line
                \tideways_xhprof_enable(TIDEWAYS_XHPROF_FLAGS_NO_BUILTINS);
            }
        }
    }

    /**
     * Stop profiling code. Returns profiling array or null if profiling couldn't be done.
     *
     * @param string|null $message
     * @return array|null
     */
    public function stopProfiling(string $message = null): ?array
    {
        $timings = null;
        if ($this->enabled && extension_loaded('tideways_xhprof')) {
            $profiling = $this->profiling - 1;
            if ($profiling === 0) {
                // @phpstan-ignore-next-line
                $timings = \tideways_xhprof_disable();
                $timings = $this->buildProfilerTimings($timings);

                if ($this->clockwork) {
                    /** @var UserData $userData */
                    $userData = $this->clockwork->userData('Profiler');
                    $userData->counters([
                        'Calls' => count($timings)
                    ]);
                    $userData->table('Profiler', $timings);
                } else {
                    $this->addMessage($message ?? 'Profiler Analysis', 'debug', $timings);
                }
            }
            $this->profiling = max(0, $profiling);
        }

        return $timings;
    }

    /**
     * @param array $timings
     * @return array
     */
    protected function buildProfilerTimings(array $timings): array
    {
        // Filter method calls which take almost no time.
        $timings = array_filter($timings, function ($value) {
            return $value['wt'] > 50;
        });

        uasort($timings, function (array $a, array $b) {
            return $b['wt'] <=> $a['wt'];
        });

        $table = [];
        foreach ($timings as $key => $timing) {
            $parts = explode('==>', $key);
            $method = $this->parseProfilerCall(array_pop($parts));
            $context = $this->parseProfilerCall(array_pop($parts));

            // Skip redundant method calls.
            if ($context === 'Grav\Framework\RequestHandler\RequestHandler::handle()') {
                continue;
            }

            // Do not profile library calls.
            if (strpos($context, 'Grav\\') !== 0) {
                continue;
            }

            $table[] = [
                'Context' => $context,
                'Method' => $method,
                'Calls' => $timing['ct'],
                'Time (ms)' => $timing['wt'] / 1000,
            ];
        }

        return $table;
    }

    /**
     * @param string|null $call
     * @return mixed|string|null
     */
    protected function parseProfilerCall(?string $call)
    {
        if (null === $call) {
            return '';
        }
        if (strpos($call, '@')) {
            [$call,] = explode('@', $call);
        }
        if (strpos($call, '::')) {
            [$class, $call] = explode('::', $call);
        }

        if (!isset($class)) {
            return $call;
        }

        // It is also possible to display twig files, but they are being logged in views.
        /*
        if (strpos($class, '__TwigTemplate_') === 0 && class_exists($class)) {
            $env = new Environment();
            / ** @var Template $template * /
            $template = new $class($env);

            return $template->getTemplateName();
        }
        */

        return "{$class}::{$call}()";
    }

    /**
     * Start a timer with an associated name and description
     *
     * @param string      $name
     * @param string|null $description
     * @return $this
     */
    public function startTimer($name, $description = null)
    {
        $this->timers[$name] = [$description, microtime(true)];

        return $this;
    }

    /**
     * Stop the named timer
     *
     * @param string $name
     * @return $this
     */
    public function stopTimer($name)
    {
        if (isset($this->timers[$name])) {
            $endTime = microtime(true);
            $this->timers[$name][] = $endTime;
        }

        return $this;
    }

    /**
     * Dump variables into the Messages tab of the Debug Bar
     *
     * @param mixed  $message
     * @param string $label
     * @param mixed|bool $isString
     * @return $this
     */
    public function addMessage($message, $label = 'info', $isString = true)
    {
        if ($this->enabled) {
            if ($this->censored) {
                if (!is_scalar($message)) {
                    $message = 'CENSORED';
                }
                if (!is_scalar($isString)) {
                    $isString = ['CENSORED'];
                }
            }

            if ($this->debugbar) {
                if (is_array($isString)) {
                    $message = $isString;
                    $isString = false;
                } elseif (is_string($isString)) {
                    $message = $isString;
                    $isString = true;
                }
                $this->debugbar['messages']->addMessage($message, $label, $isString);
            }

            if ($this->clockwork) {
                $context = $isString;
                if (!is_scalar($message)) {
                    $context = $message;
                    $message = gettype($context);
                }
                if (is_bool($context)) {
                    $context = [];
                } elseif (!is_array($context)) {
                    $type = gettype($context);
                    $context = [$type => $context];
                }

                $this->clockwork->log($label, $message, $context);
            }
        }

        return $this;
    }

    /**
     * @param string $name
     * @param object $event
     * @param EventDispatcherInterface $dispatcher
     * @param float|null $time
     * @return $this
     */
    public function addEvent(string $name, $event, EventDispatcherInterface $dispatcher, float $time = null)
    {
        if ($this->enabled && $this->clockwork) {
            $time = $time ?? microtime(true);
            $duration = (microtime(true) - $time) * 1000;

            $data = null;
            if ($event && method_exists($event, '__debugInfo')) {
                $data = $event;
            }

            $listeners = [];
            foreach ($dispatcher->getListeners($name) as $listener) {
                $listeners[] = $this->resolveCallable($listener);
            }

            $this->clockwork->addEvent($name, $data, $time, ['listeners' => $listeners, 'duration' => $duration]);
        }

        return $this;
    }

    /**
     * Dump exception into the Messages tab of the Debug Bar
     *
     * @param Throwable $e
     * @return Debugger
     */
    public function addException(Throwable $e)
    {
        if ($this->initialized && $this->enabled) {
            if ($this->debugbar) {
                $this->debugbar['exceptions']->addThrowable($e);
            }

            if ($this->clockwork) {
                /** @var UserData $exceptions */
                $exceptions = $this->clockwork->userData('Exceptions');
                $exceptions->data(['message' => $e->getMessage()]);

                $this->clockwork->alert($e->getMessage(), ['exception' => $e]);
            }
        }

        return $this;
    }

    /**
     * @return void
     */
    public function setErrorHandler()
    {
        $this->errorHandler = set_error_handler(
            [$this, 'deprecatedErrorHandler']
        );
    }

    /**
     * @param int $errno
     * @param string $errstr
     * @param string $errfile
     * @param int $errline
     * @return bool
     */
    public function deprecatedErrorHandler($errno, $errstr, $errfile, $errline)
    {
        if ($errno !== E_USER_DEPRECATED && $errno !== E_DEPRECATED) {
            if ($this->errorHandler) {
                return call_user_func($this->errorHandler, $errno, $errstr, $errfile, $errline);
            }

            return true;
        }

        if (!$this->enabled) {
            return true;
        }

        // Figure out error scope from the error.
        $scope = 'unknown';
        if (stripos($errstr, 'grav') !== false) {
            $scope = 'grav';
        } elseif (strpos($errfile, '/twig/') !== false) {
            $scope = 'twig';
        } elseif (stripos($errfile, '/yaml/') !== false) {
            $scope = 'yaml';
        } elseif (strpos($errfile, '/vendor/') !== false) {
            $scope = 'vendor';
        }

        // Clean up backtrace to make it more useful.
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);

        // Skip current call.
        array_shift($backtrace);

        // Find yaml file where the error happened.
        if ($scope === 'yaml') {
            foreach ($backtrace as $current) {
                if (isset($current['args'])) {
                    foreach ($current['args'] as $arg) {
                        if ($arg instanceof SplFileInfo) {
                            $arg = $arg->getPathname();
                        }
                        if (is_string($arg) && preg_match('/.+\.(yaml|md)$/i', $arg)) {
                            $errfile = $arg;
                            $errline = 0;

                            break 2;
                        }
                    }
                }
            }
        }

        // Filter arguments.
        $cut = 0;
        $previous = null;
        foreach ($backtrace as $i => &$current) {
            if (isset($current['args'])) {
                $args = [];
                foreach ($current['args'] as $arg) {
                    if (is_string($arg)) {
                        $arg = "'" . $arg . "'";
                        if (mb_strlen($arg) > 100) {
                            $arg = 'string';
                        }
                    } elseif (is_bool($arg)) {
                        $arg = $arg ? 'true' : 'false';
                    } elseif (is_scalar($arg)) {
                        $arg = $arg;
                    } elseif (is_object($arg)) {
                        $arg = get_class($arg) . ' $object';
                    } elseif (is_array($arg)) {
                        $arg = '$array';
                    } else {
                        $arg = '$object';
                    }

                    $args[] = $arg;
                }
                $current['args'] = $args;
            }

            $object = $current['object'] ?? null;
            unset($current['object']);

            $reflection = null;
            if ($object instanceof TemplateWrapper) {
                $reflection = new ReflectionObject($object);
                $property = $reflection->getProperty('template');
                $property->setAccessible(true);
                $object = $property->getValue($object);
            }

            if ($object instanceof Template) {
                $file = $current['file'] ?? null;

                if (preg_match('`(Template.php|TemplateWrapper.php)$`', $file)) {
                    $current = null;
                    continue;
                }

                $debugInfo = $object->getDebugInfo();

                $line = 1;
                if (!$reflection) {
                    foreach ($debugInfo as $codeLine => $templateLine) {
                        if ($codeLine <= $current['line']) {
                            $line = $templateLine;
                            break;
                        }
                    }
                }

                $src = $object->getSourceContext();
                //$code = preg_split('/\r\n|\r|\n/', $src->getCode());
                //$current['twig']['twig'] = trim($code[$line - 1]);
                $current['twig']['file'] = $src->getPath();
                $current['twig']['line'] = $line;

                $prevFile = $previous['file'] ?? null;
                if ($prevFile && $file === $prevFile) {
                    $prevLine = $previous['line'];

                    $line = 1;
                    foreach ($debugInfo as $codeLine => $templateLine) {
                        if ($codeLine <= $prevLine) {
                            $line = $templateLine;
                            break;
                        }
                    }

                    //$previous['twig']['twig'] = trim($code[$line - 1]);
                    $previous['twig']['file'] = $src->getPath();
                    $previous['twig']['line'] = $line;
                }

                $cut = $i;
            } elseif ($object instanceof ProcessorInterface) {
                $cut = $cut ?: $i;
                break;
            }

            $previous = &$backtrace[$i];
        }
        unset($current);

        if ($cut) {
            $backtrace = array_slice($backtrace, 0, $cut + 1);
        }
        $backtrace = array_values(array_filter($backtrace));

        // Skip vendor libraries and the method where error was triggered.
        foreach ($backtrace as $i => $current) {
            if (!isset($current['file'])) {
                continue;
            }
            if (strpos($current['file'], '/vendor/') !== false) {
                $cut = $i + 1;
                continue;
            }
            if (isset($current['function']) && ($current['function'] === 'user_error' || $current['function'] === 'trigger_error')) {
                $cut = $i + 1;
                continue;
            }

            break;
        }

        if ($cut) {
            $backtrace = array_slice($backtrace, $cut);
        }
        $backtrace = array_values(array_filter($backtrace));

        $current = reset($backtrace);

        // If the issue happened inside twig file, change the file and line to match that file.
        $file = $current['twig']['file'] ?? '';
        if ($file) {
            $errfile = $file;
            $errline = $current['twig']['line'] ?? 0;
        }

        $deprecation = [
            'scope' => $scope,
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline,
            'trace' => $backtrace,
            'count' => 1
        ];

        $this->deprecations[] = $deprecation;

        // Do not pass forward.
        return true;
    }

    /**
     * @return array
     */
    protected function getDeprecations(): array
    {
        if (!$this->deprecations) {
            return [];
        }

        $list = [];
        /** @var array $deprecated */
        foreach ($this->deprecations as $deprecated) {
            $list[] = $this->getDepracatedMessage($deprecated)[0];
        }

        return $list;
    }

    /**
     * @return void
     * @throws DebugBarException
     */
    protected function addDeprecations()
    {
        if (!$this->deprecations) {
            return;
        }

        $collector = new MessagesCollector('deprecated');
        $this->addCollector($collector);
        $collector->addMessage('Your site is using following deprecated features:');

        /** @var array $deprecated */
        foreach ($this->deprecations as $deprecated) {
            list($message, $scope) = $this->getDepracatedMessage($deprecated);

            $collector->addMessage($message, $scope);
        }
    }

    /**
     * @param array $deprecated
     * @return array
     */
    protected function getDepracatedMessage($deprecated)
    {
        $scope = $deprecated['scope'];

        $trace = [];
        if (isset($deprecated['trace'])) {
            foreach ($deprecated['trace'] as $current) {
                $class = $current['class'] ?? '';
                $type = $current['type'] ?? '';
                $function = $this->getFunction($current);
                if (isset($current['file'])) {
                    $current['file'] = str_replace(GRAV_ROOT . '/', '', $current['file']);
                }

                unset($current['class'], $current['type'], $current['function'], $current['args']);

                if (isset($current['twig'])) {
                    $trace[] = $current['twig'];
                } else {
                    $trace[] = ['call' => $class . $type . $function] + $current;
                }
            }
        }

        $array = [
            'message' => $deprecated['message'],
            'file' => $deprecated['file'],
            'line' => $deprecated['line'],
            'trace' => $trace
        ];

        return [
            array_filter($array),
            $scope
        ];
    }

    /**
     * @param array $trace
     * @return string
     */
    protected function getFunction($trace)
    {
        if (!isset($trace['function'])) {
            return '';
        }

        return $trace['function'] . '(' . implode(', ', $trace['args'] ?? []) . ')';
    }

    /**
     * @param callable $callable
     * @return string
     */
    protected function resolveCallable(callable $callable)
    {
        if (is_array($callable)) {
            return get_class($callable[0]) . '->' . $callable[1] . '()';
        }

        return 'unknown';
    }
}
