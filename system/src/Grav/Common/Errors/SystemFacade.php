<?php

/**
 * @package    Grav\Common\Errors
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Errors;

class SystemFacade extends \Whoops\Util\SystemFacade
{
    protected $whoopsShutdownHandler;

    /**
     * @param callable $function
     *
     * @return void
     */
    public function registerShutdownFunction(callable $function)
    {
        $this->whoopsShutdownHandler = $function;
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /**
     * Special case to deal with Fatal errors and the like.
     */
    public function handleShutdown()
    {
        $error = $this->getLastError();

        // Ignore core warnings and errors.
        if ($error && !($error['type'] & (E_CORE_WARNING | E_CORE_ERROR))) {
            $handler = $this->whoopsShutdownHandler;
            $handler();
        }
    }
}
