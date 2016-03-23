<?php
namespace Grav\Common\Service;

use Pimple\Container;
use Pimple\ServiceProviderInterface;

class OutputServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container) {
        $container['output'] = function ($c) {
            /** @var Grav $c */
            return $c['twig']->processSite($c['uri']->extension());
        };
    }
}
