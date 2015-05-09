<?php
namespace Grav\Common\Service;

use Grav\Common\Errors\Errors;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Whoops\Handler\JsonResponseHandler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Handler\PlainTextHandler;

class ErrorServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        // Setup Whoops-based error handler
        $errors = new Errors;

        $error_page = new PrettyPageHandler;
        $error_page->setPageTitle('Crikey! There was an error...');
        $error_page->setEditor('sublime');
        $error_page->addResourcePath(GRAV_ROOT . '/system/assets');
        $error_page->addCustomCss('whoops.css');

        $json_page = new JsonResponseHandler;
        $json_page->onlyForAjaxRequests(true);

        $errors->pushHandler($error_page, 'pretty');
        $errors->pushHandler(new PlainTextHandler, 'text');
        $errors->pushHandler($json_page, 'json');

        $logger = $container['log'];
        $errors->pushHandler(function (\Exception $exception, $inspector, $run) use ($logger) {
            try {
                $logger->addCritical($exception->getMessage() . ' - Trace: ' . $exception->getTraceAsString());
            } catch (\Exception $e) {
                echo $e;
            }
        }, 'log');

        $errors->register();

        $container['errors'] = $errors;
    }
}
