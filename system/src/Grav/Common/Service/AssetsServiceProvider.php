<?php
namespace Grav\Common\Service;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Grav\Common\Assets;

class AssetsServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container) {
        $container['assets'] = new Assets();
    }
}
