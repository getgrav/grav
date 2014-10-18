<?php
namespace Grav\Common\Service;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use \Monolog\Logger;
use \Monolog\Handler\StreamHandler;

class LoggerServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        // create a log channel
        $log = new Logger('grav');
        $log->pushHandler(new StreamHandler(LOG_DIR.'info.log', Logger::WARNING));

        $container['log'] = $log;
    }
}
