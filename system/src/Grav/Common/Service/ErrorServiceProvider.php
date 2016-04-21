<?php
namespace Grav\Common\Service;

use Grav\Common\Errors\Errors;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class ErrorServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $errors = new Errors;
        $container['errors'] = $errors;
    }
}
