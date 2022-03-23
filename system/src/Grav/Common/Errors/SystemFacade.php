<?php

/**
 * @package    Grav\Common\Errors
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Errors;

/**
 * Class SystemFacade
 * @package Grav\Common\Errors
 */
class SystemFacade extends \Whoops\Util\SystemFacade
{
    /** @var callable */
    protected $whoopsShutdownHandler;

    /**
     * @param callable $function
     * @return void
     */
    public function registerShutdownFunction(callable $function)
    {
        $this->whoopsShutdownHandler = $function;
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /**
     * Special case to deal with Fatal errors and the like.
     *
     * @return void
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


    /**
     * @param int $httpCode
     *
     * @return int
     */
    public function setHttpResponseCode($httpCode)
    {
        if (!headers_sent()) {
            // Ensure that no 'location' header is present as otherwise this
            // will override the HTTP code being set here, and mask the
            // expected error page.
            header_remove('location');

            // Work around PHP bug #8218 (8.0.17 & 8.1.4).
            header_remove('Content-Encoding');
        }

        return http_response_code($httpCode);
    }
}
