<?php
namespace Grav\Common\Service;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Whoops\Handler\JsonResponseHandler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

class ErrorServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        // Setup Whoops error handler
        $whoops = new Run;

        $error_page = new PrettyPageHandler;
        $error_page->setPageTitle('Crikey! There was an error...');
        $error_page->setEditor('sublime');
        $error_page->addResourcePath(GRAV_ROOT . '/system/assets');
        $error_page->addCustomCss('whoops.css');

        $json_page = new JsonResponseHandler;
        $json_page->onlyForAjaxRequests(true);

        $whoops->pushHandler($error_page);
        $whoops->pushHandler($json_page);

        $logger = $container['log'];
        $whoops->pushHandler(function ($exception, $inspector, $run) use($logger) {
            $logger->addCritical($exception->getMessage(). ' - Trace: '. $exception->getTraceAsString());
        });

        $whoops->register();

        $container['whoops'] = $whoops;
    }
}
