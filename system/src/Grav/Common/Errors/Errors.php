<?php

/**
 * @package    Grav\Common\Errors
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Errors;

use ErrorException;
use Exception;
use Grav\Common\Grav;
use Whoops\Handler\JsonResponseHandler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;
use Whoops\Util\Misc;
use function is_int;

/**
 * Class Errors
 * @package Grav\Common\Errors
 */
class Errors
{
    /**
     * Register lightweight error/exception/shutdown handlers that construct the
     * full Whoops stack only when the first error actually arrives. On the vast
     * majority of requests none of that machinery is ever needed.
     *
     * @return void
     */
    public function resetHandlers()
    {
        $grav = Grav::instance();
        $config = $grav['config']->get('system.errors');
        $jsonRequest = $_SERVER && isset($_SERVER['HTTP_ACCEPT']) && $_SERVER['HTTP_ACCEPT'] === 'application/json';

        $verbosity = 1;

        if (isset($config['display'])) {
            if (is_int($config['display'])) {
                $verbosity = $config['display'];
            } else {
                $verbosity = $config['display'] ? 1 : 0;
            }
        }

        $logging = !empty($config['log']);

        $whoops = null;
        $factory = function () use (&$whoops, $verbosity, $logging, $jsonRequest) {
            return $whoops ??= $this->createWhoops($verbosity, $logging, $jsonRequest);
        };

        set_exception_handler(static function ($exception) use ($factory) {
            $factory()->handleException($exception);
        });

        set_error_handler(static function ($level, $message, $file = '', $line = 0) use ($factory) {
            if (!($level & error_reporting())) {
                // Mirrors Whoops\Run::handleError(): leave silenced errors to PHP.
                return false;
            }

            return $factory()->handleError($level, $message, $file, $line);
        });

        register_shutdown_function(static function () use ($factory) {
            $error = error_get_last();
            if ($error === null) {
                return;
            }

            // Same fatal set Whoops handles at shutdown (Misc::isLevelFatal), minus the
            // core warnings/errors Grav's SystemFacade has always ignored there.
            if ($error['type'] & (E_ERROR | E_PARSE | E_COMPILE_ERROR | E_COMPILE_WARNING)) {
                $whoops = $factory();
                // An exception thrown in a shutdown handler will not propagate to the
                // exception handler, so render it directly like Whoops::handleShutdown does.
                $whoops->allowQuit(false);
                $whoops->handleException(new ErrorException($error['message'], $error['type'], $error['type'], $error['file'], $error['line']));
            }
        });

        // Re-register deprecation handler.
        $grav['debugger']->setErrorHandler();
    }

    /**
     * Build the Whoops error handler stack. Called on first error, not at bootstrap.
     *
     * @param int $verbosity
     * @param bool $logging
     * @param bool $jsonRequest
     * @return Run
     */
    protected function createWhoops(int $verbosity, bool $logging, bool $jsonRequest): Run
    {
        // Setup Whoops-based error handler
        $system = new SystemFacade;
        $whoops = new Run($system);

        switch ($verbosity) {
            case 1:
                $error_page = new PrettyPageHandler();
                $error_page->setPageTitle('Crikey! There was an error...');
                $error_page->addResourcePath(GRAV_ROOT . '/system/assets');
                $error_page->addCustomCss('whoops.css');
                $whoops->prependHandler($error_page);
                break;
            case -1:
                $whoops->prependHandler(new BareHandler);
                break;
            default:
                $whoops->prependHandler(new SimplePageHandler);
                break;
        }

        if ($jsonRequest || Misc::isAjaxRequest()) {
            // Only expose full exception detail (type, message, file, line) to a
            // JSON/AJAX client when error display is on. With display suppressed
            // (`errors.display: 0`/`-1`) fall back to a sanitized JSON body that
            // leaks nothing, mirroring the generic page the HTML path returns.
            if ($verbosity > 0) {
                $whoops->prependHandler(new JsonResponseHandler());
            } else {
                $whoops->prependHandler(new SimpleJsonHandler());
            }
        }

        if ($logging) {
            $whoops->pushHandler(function ($exception, $inspector, $run) {
                try {
                    $logger = Grav::instance()['log'];
                    $logger->critical($exception->getMessage() . ' - Trace: ' . $exception->getTraceAsString());
                } catch (Exception $e) {
                    echo $e;
                }
            });
        }

        return $whoops;
    }
}
