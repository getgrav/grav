<?php
/**
 * @package    Grav.Common.Errors
 *
 * @copyright  Copyright (C) 2014 - 2016 RocketTheme, LLC. All rights reserved.
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
        $whoops = new \Whoops\Run;

        if (isset($config['display'])) {
            if ($config['display']) {
                $error_page = new Whoops\Handler\PrettyPageHandler;
                $error_page->setPageTitle('Crikey! There was an error...');
                $error_page->addResourcePath(GRAV_ROOT . '/system/assets');
                $error_page->addCustomCss('whoops.css');
                $whoops->pushHandler($error_page);
            } else {
                $whoops->pushHandler(new SimplePageHandler);
            }
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
    }
}
