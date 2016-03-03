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
        $log = new Logger('grav');
        // TODO: Use streams.
        $log_file = LOG_DIR.'grav.log';

        $log->pushHandler(new StreamHandler($log_file, Logger::DEBUG));

        $container['log'] = $log;
    }
}
