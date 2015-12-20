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
        $errors = new Errors;
        $container['errors'] = $errors;
    }
}
