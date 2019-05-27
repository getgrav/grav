<?php

/**
 * @package    Grav\Common
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common;

use Clockwork\Clockwork;
use Clockwork\DataSource\MonologDataSource;
use Clockwork\DataSource\PhpDataSource;
use Clockwork\DataSource\XdebugDataSource;
use Clockwork\Request\Timeline;
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
use DebugBar\JavascriptRenderer;
use DebugBar\StandardDebugBar;
use Grav\Common\Config\Config;
use Grav\Common\Processors\ProcessorInterface;
use Grav\Common\Twig\TwigClockworkDataSource;
use Monolog\Logger;
use RocketTheme\Toolbox\Event\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Twig\Template;
use Twig\TemplateWrapper;

class Debugger
{
    /** @var static */
    protected static $instance;

    /** @var Grav $grav */
    protected $grav;

    /** @var Config $config */
    protected $config;

    /** @var JavascriptRenderer $renderer */
    protected $renderer;

    /** @var StandardDebugBar $debugbar */
    protected $debugbar;

    /** @var Clockwork */
    protected $clockwork;

    /** @var bool */
    protected $enabled = false;

    protected $initialized = false;

    /** @var array */
    protected $timers = [];

    /** @var array $deprecations */
    protected $deprecations = [];

    /** @var callable */
    protected $errorHandler;

    protected $requestTime;
    protected $currentTime;

    /**
     * Debugger constructor.
     */
    public function __construct()
    {
        static::$instance = $this;

        $this->currentTime = microtime(true);

        if (!\defined('GRAV_REQUEST_TIME')) {
            \define('GRAV_REQUEST_TIME', $this->currentTime);
        }

        $this->requestTime = $_SERVER['REQUEST_TIME_FLOAT'] ?? GRAV_REQUEST_TIME;

        // Set deprecation collector.
        $this->setErrorHandler();
    }

    public function getClockwork(): ?Clockwork
    {
        return $this->enabled ? $this->clockwork : null;
    }

    /**
     * Initialize the debugger
     *
     * @return $this
     * @throws \DebugBar\DebugBarException
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

        if ($this->enabled) {
            $this->initialized = true;

            // Clockwork initialization.
            $this->clockwork = $clockwork = new Clockwork();

            // Debugbar initialization.
            $this->debugbar = $debugbar = new DebugBar();

            $plugins_config = (array)$this->config->get('plugins');
            ksort($plugins_config);

            if ($clockwork) {
                $log = $this->grav['log'];
                $clockwork->setStorage(new FileStorage(GRAV_ROOT . '/cache/clockwork'));
                $clockwork->addDataSource(new PhpDataSource());
                if (extension_loaded('xdebug')) {
                    $clockwork->addDataSource(new XdebugDataSource());
                }
                if ($log instanceof Logger) {
                    $clockwork->addDataSource(new MonologDataSource($log));
                }

                $clockwork->addDataSource(new TwigClockworkDataSource($this->grav['twig']));

                $timeLine = $clockwork->getTimeline();
                if ($this->requestTime !== GRAV_REQUEST_TIME) {
                    $timeLine->addEvent('server', 'Server', $this->requestTime, GRAV_REQUEST_TIME);
                }
                if ($this->currentTime !== GRAV_REQUEST_TIME) {
                    $timeLine->addEvent('loading', 'Loading', GRAV_REQUEST_TIME, $this->currentTime);
                }
                $timeLine->addEvent('setup', 'Site Setup', $this->currentTime, microtime(true));
            }

            if ($debugbar) {
                $debugbar->addCollector(new PhpInfoCollector());
                $debugbar->addCollector(new MessagesCollector());
                $debugbar->addCollector(new RequestDataCollector());
                $debugbar->addCollector(new TimeDataCollector($this->requestTime));
                $debugbar->addCollector(new MemoryCollector());
                $debugbar->addCollector(new ExceptionsCollector());
                $debugbar->addCollector(new ConfigCollector((array)$this->config->get('system'), 'Config'));
                $debugbar->addCollector(new ConfigCollector($plugins_config, 'Plugins'));

                if ($this->requestTime !== GRAV_REQUEST_TIME) {
                    $debugbar['time']->addMeasure('Server', $debugbar['time']->getRequestStartTime(), GRAV_REQUEST_TIME);
                }
                if ($this->currentTime !== GRAV_REQUEST_TIME) {
                    $debugbar['time']->addMeasure('Loading', GRAV_REQUEST_TIME, $this->currentTime);
                }
                $debugbar['time']->addMeasure('Site Setup', $this->currentTime,  microtime(true));
            }

            $this->addMessage('Grav v' . GRAV_VERSION);
            $this->config->debug();

            if ($clockwork) {
                $clockwork->info('System Configuration', $this->config->get('system'));
                $clockwork->info('Plugins Configuration', $plugins_config);
                $clockwork->info('Streams', $this->config->get('streams.schemes'));

            }
        }

        return $this;
    }

    public function finalize(): void
    {
        if ($this->clockwork && $this->enabled) {
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
            foreach ($deprecations as &$deprecation) {
                if (0) {
                    $d = $deprecation;
                    unset($d['message']);
                    $this->clockwork->log('deprecated', $deprecation['message'], $d);
                }
                unset($deprecation['trace']);
            }
            unset($deprecation);

            $userData->table('Your site is using following deprecated features', $deprecations);
        }
    }

    protected function addMeasures()
    {
        if (!$this->enabled) {
            return;
        }

        $nowTime = microtime(true);
        $clkTimeLine = $this->clockwork ? $this->clockwork->getTimeline() : null;
        $debTimeLine = $this->debugbar ? $this->debugbar['time'] : null;
        foreach ($this->timers as $name => $data) {
            $description = $data[0];
            $startTime = $data[1];
            $endTime = $data[2] ?? $nowTime;
            if ($endTime - $startTime < 0.001) {
                continue;
            }

            if ($clkTimeLine) {
                $clkTimeLine->addEvent($name, $description, $startTime, $endTime);
            }

            if ($debTimeLine) {
                $debTimeLine->addMeasure($description, $startTime,  $endTime);
            }
        }
        $this->timers = [];
    }

    /**
     * Set/get the enabled state of the debugger
     *
     * @param bool $state If null, the method returns the enabled value. If set, the method sets the enabled state
     *
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
        if ($this->enabled && $this->debugbar) {

            // Only add assets if Page is HTML
            $page = $this->grav['page'];
            if ($page->templateFormat() !== 'html') {
                return $this;
            }

            /** @var Assets $assets */
            $assets = $this->grav['assets'];

            // Add jquery library
            $assets->add('jquery', 101);

            $this->renderer = $this->debugbar->getJavascriptRenderer();
            $this->renderer->setIncludeVendors(false);

            // Get the required CSS files
            list($css_files, $js_files) = $this->renderer->getAssets(null, JavascriptRenderer::RELATIVE_URL);
            foreach ((array)$css_files as $css) {
                $assets->addCss($css);
            }

            $assets->addCss('/system/assets/debugger.css');

            foreach ((array)$js_files as $js) {
                $assets->addJs($js);
            }
        }

        return $this;
    }

    public function getCaller($limit = 2)
    {
        $trace = debug_backtrace(false, $limit);

        return array_pop($trace);
    }

    /**
     * Adds a data collector
     *
     * @param DataCollectorInterface $collector
     *
     * @return $this
     * @throws \DebugBar\DebugBarException
     */
    public function addCollector($collector)
    {
        if ($this->debugbar) {
            $this->debugbar->addCollector($collector);
        }

        return $this;
    }

    /**
     * Returns a data collector
     *
     * @param DataCollectorInterface $collector
     *
     * @return DataCollectorInterface
     * @throws \DebugBar\DebugBarException
     */
    public function getCollector($collector)
    {
        if ($this->debugbar) {
            return $this->debugbar->getCollector($collector);
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
     * @return array
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
     * Start a timer with an associated name and description
     *
     * @param string      $name
     * @param string|null $description
     *
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
     *
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
     * @param bool   $isString
     *
     * @return $this
     */
    public function addMessage($message, $label = 'info', $isString = true)
    {
        if ($this->enabled) {
            if ($this->debugbar) {
                $this->debugbar['messages']->addMessage($message, $label, $isString);
            }

            if ($this->clockwork) {
                $this->clockwork->log($label, $message);
            }
        }

        return $this;
    }

    public function addEvent(string $name, ?Event $event, EventDispatcherInterface $dispatcher)
    {
        if ($this->enabled) {
            if ($this->clockwork) {
                $listeners = [];
                foreach ($dispatcher->getListeners($name) as $listener) {
                    $listeners[] = $this->resolveCallable($listener);
                }
                $this->clockwork->addEvent($name, null, microtime(true), ['listeners' => $listeners]);
            }
        }

        return $this;
    }

    /**
     * Dump exception into the Messages tab of the Debug Bar
     *
     * @param \Exception $e
     * @return Debugger
     */
    public function addException(\Exception $e)
    {
        if ($this->initialized && $this->enabled) {
            if ($this->debugbar) {
                $this->debugbar['exceptions']->addException($e);
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
        if ($errno !== E_USER_DEPRECATED) {
            if ($this->errorHandler) {
                return \call_user_func($this->errorHandler, $errno, $errstr, $errfile, $errline);
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
                        if ($arg instanceof \SplFileInfo) {
                            $arg = $arg->getPathname();
                        }
                        if (\is_string($arg) && preg_match('/.+\.(yaml|md)$/i', $arg)) {
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
                    if (\is_string($arg)) {
                        $arg = "'" . $arg . "'";
                        if (mb_strlen($arg) > 100) {
                            $arg = 'string';
                        }
                    } elseif (\is_bool($arg)) {
                        $arg = $arg ? 'true' : 'false';
                    } elseif (\is_scalar($arg)) {
                        $arg = $arg;
                    } elseif (\is_object($arg)) {
                        $arg = get_class($arg) . ' $object';
                    } elseif (\is_array($arg)) {
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
                $reflection = new \ReflectionObject($object);
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

    protected function getFunction($trace)
    {
        if (!isset($trace['function'])) {
            return '';
        }

        return $trace['function'] . '(' . implode(', ', $trace['args'] ?? []) . ')';
    }

    protected function resolveCallable(callable $callable)
    {
        if (is_array($callable)) {
            return get_class($callable[0]) . '->' . $callable[1] . '()';
        }

        return 'unknown';
    }
}
