<?php

/**
 * @package    Grav\Common\Errors
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Errors;

use Whoops\Handler\Handler;

/**
 * Class SimpleJsonHandler
 * @package Grav\Common\Errors
 *
 * Minimal JSON error response for JSON/AJAX requests when error display is
 * suppressed (`errors.display: 0` or `-1`). Unlike Whoops' JsonResponseHandler
 * it never emits the exception type, message, file, line, or trace, so an
 * unauthenticated client cannot read internal paths or details in production.
 * The JSON content type is preserved so API clients still receive JSON.
 */
class SimpleJsonHandler extends Handler
{
    /**
     * @return int
     */
    public function handle()
    {
        $code = $this->getInspector()->getException()->getCode();
        if (($code >= 400) && ($code < 600)) {
            $this->getRun()->sendHttpCode($code);
        } else {
            $code = 500;
        }

        echo json_encode([
            'error' => [
                'code' => $code,
                'message' => 'An error occurred while processing your request.',
            ],
        ], JSON_UNESCAPED_SLASHES);

        return Handler::QUIT;
    }

    /**
     * @return string
     */
    public function contentType()
    {
        return 'application/json';
    }
}
