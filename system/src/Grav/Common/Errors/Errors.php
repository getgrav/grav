<?php
/**
 * @package    Grav.Common.Errors
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Errors;

use Grav\Common\Grav;
use Whoops;

class Errors
{
    public function resetHandlers()
    {
        $grav = Grav::instance();
        $config = $grav['config']->get('system.errors');
        $jsonRequest = $_SERVER && isset($_SERVER['HTTP_ACCEPT']) && $_SERVER['HTTP_ACCEPT'] == 'application/json';

        // Setup Whoops-based error handler
        $system = new SystemFacade;
        $whoops = new \Whoops\Run($system);

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
                $error_page = new Whoops\Handler\PrettyPageHandler;
                $error_page->setPageTitle('Crikey! There was an error...');
                $error_page->addResourcePath(GRAV_ROOT . '/system/assets');
                $error_page->addCustomCss('whoops.css');
                $whoops->pushHandler($error_page);
                break;
            case -1:
                $whoops->pushHandler(new BareHandler);
                break;
            default:
                $whoops->pushHandler(new SimplePageHandler);
                break;
        }

        if (method_exists('Whoops\Util\Misc', 'isAjaxRequest')) { //Whoops 2.0
            if (Whoops\Util\Misc::isAjaxRequest() || $jsonRequest) {
                $whoops->pushHandler(new Whoops\Handler\JsonResponseHandler);
            }
        } elseif (function_exists('Whoops\isAjaxRequest')) { //Whoops 2.0.0-alpha
            if (Whoops\isAjaxRequest() || $jsonRequest) {
                $whoops->pushHandler(new Whoops\Handler\JsonResponseHandler);
            }
        } else { //Whoops 1.x
            $json_page = new Whoops\Handler\JsonResponseHandler;
            $json_page->onlyForAjaxRequests(true);
        }

        if (isset($config['log']) && $config['log']) {
            $logger = $grav['log'];
            $whoops->pushHandler(function($exception, $inspector, $run) use ($logger) {
                try {
                    $logger->addCritical($exception->getMessage() . ' - Trace: ' . $exception->getTraceAsString());
                } catch (\Exception $e) {
                    echo $e;
                }
            }, 'log');
        }

        $whoops->register();

        // Re-register deprecation handler.
        $grav['debugger']->setErrorHandler();
    }
}
