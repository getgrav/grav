<?php

/**
 * @package    Grav\Common\Errors
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Errors;

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
     * @return void
     */
    public function resetHandlers()
    {
        $grav = Grav::instance();
        $config = $grav['config']->get('system.errors');
        $jsonRequest = $_SERVER && isset($_SERVER['HTTP_ACCEPT']) && $_SERVER['HTTP_ACCEPT'] === 'application/json';

        // Setup Whoops-based error handler
        $system = new SystemFacade;
        $whoops = new Run($system);

        $verbosity = 1;

        if (isset($config['display'])) {
            if (is_int($config['display'])) {
                $verbosity = $config['display'];
            } else {
                $verbosity = $config['display'] ? 1 : 0;
            }
        }

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
            $whoops->prependHandler(new JsonResponseHandler());
        }

        if (isset($config['log']) && $config['log']) {
            $logger = $grav['log'];
            $whoops->pushHandler(function ($exception, $inspector, $run) use ($logger) {
                try {
                    $logger->addCritical($exception->getMessage() . ' - Trace: ' . $exception->getTraceAsString());
                } catch (Exception $e) {
                    echo $e;
                }
            });
        }

        $whoops->register();

        // Re-register deprecation handler.
        $grav['debugger']->setErrorHandler();
    }
}
