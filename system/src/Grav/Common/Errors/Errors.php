<?php
namespace Grav\Common\Errors;

use Grav\Common\Grav;
use Whoops\Handler\CallbackHandler;
use Whoops\Handler\HandlerInterface;
use Whoops\Run;

/**
 * Class Debugger
 * @package Grav\Common
 */
class Errors extends \Whoops\Run
{

    public function pushHandler($handler, $key = null)
    {
        if (is_callable($handler)) {
            $handler = new CallbackHandler($handler);
        }

        if (!$handler instanceof HandlerInterface) {
            throw new \InvalidArgumentException(
                "Argument to " . __METHOD__ . " must be a callable, or instance of"
                . "Whoops\\Handler\\HandlerInterface"
            );
        }

        // Store with key if provided
        if ($key) {
            $this->handlerStack[$key] = $handler;
        } else {
            $this->handlerStack[] = $handler;
        }

        return $this;
    }

    public function resetHandlers()
    {
        $grav = Grav::instance();
        $config = $grav['config']->get('system.errors');
        if (isset($config['display']) && !$config['display']) {
            unset($this->handlerStack['pretty']);
            $this->handlerStack = array('simple' => new SimplePageHandler()) + $this->handlerStack;
        }
        if (isset($config['log']) && !$config['log']) {
            unset($this->handlerStack['log']);
        }
    }

}
