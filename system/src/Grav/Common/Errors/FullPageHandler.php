<?php

/**
 * @package    Grav\Common\Errors
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Errors;

use Whoops\Exception\Frame;
use Whoops\Exception\FrameCollection;
use Whoops\Handler\PrettyPageHandler;

/**
 * Class FullPageHandler
 * @package Grav\Common\Errors
 *
 * The full backtrace page of `errors.display: 1`. It is Whoops' PrettyPageHandler
 * without the two things on it that hold a site's secrets, because the page goes
 * to whoever caused the error: the values of environment variables, and the
 * arguments of the functions in the stack trace.
 */
class FullPageHandler extends PrettyPageHandler
{
    /**
     * Entries of $_SERVER that describe the request and the web server (the CGI variables
     * and what PHP, Apache and nginx add to them), next to the `HTTP_*` request headers.
     * Anything else in $_SERVER is an environment variable.
     */
    protected const SERVER_REQUEST_KEYS = [
        'AUTH_TYPE', 'CONTENT_LENGTH', 'CONTENT_TYPE', 'CONTEXT_DOCUMENT_ROOT', 'CONTEXT_PREFIX',
        'DOCUMENT_ROOT', 'DOCUMENT_URI', 'GATEWAY_INTERFACE', 'HTTPS', 'ORIG_PATH_INFO', 'PATH_INFO',
        'PATH_TRANSLATED', 'PHP_AUTH_USER', 'PHP_SELF', 'QUERY_STRING', 'REDIRECT_STATUS',
        'REDIRECT_URL', 'REMOTE_ADDR', 'REMOTE_HOST', 'REMOTE_PORT', 'REMOTE_USER', 'REQUEST_METHOD',
        'REQUEST_SCHEME', 'REQUEST_TIME', 'REQUEST_TIME_FLOAT', 'REQUEST_URI', 'SCRIPT_FILENAME',
        'SCRIPT_NAME', 'SERVER_ADDR', 'SERVER_ADMIN', 'SERVER_NAME', 'SERVER_PORT', 'SERVER_PROTOCOL',
        'SERVER_SIGNATURE', 'SERVER_SOFTWARE', 'argc', 'argv',
    ];

    /**
     * @return int|null
     */
    public function handle()
    {
        $this->hideEnvironment();

        return parent::handle();
    }

    /**
     * Mask the environment in the $_SERVER and $_ENV tables.
     *
     * Both hold the variables of the server environment (`SetEnv`, `fastcgi_param`, the
     * process environment) and of Grav's `.env` files, which is where a site keeps the
     * secrets it wants out of its YAML (`GRAV_CONFIG__*` overrides, API keys). Only the
     * entries of $_SERVER that describe the request keep their value; the rest are listed
     * by name with the value masked.
     *
     * @return void
     */
    protected function hideEnvironment(): void
    {
        foreach (array_keys($_ENV) as $key) {
            $this->hideSuperglobalKey('_ENV', $key);
        }

        foreach (array_keys($_SERVER) as $key) {
            $key = (string) $key;
            if (!in_array($key, static::SERVER_REQUEST_KEYS, true) && !str_starts_with($key, 'HTTP_')) {
                $this->hideSuperglobalKey('_SERVER', $key);
            }
        }
    }

    /**
     * The stack frames without their arguments.
     *
     * Whoops dumps the arguments of every frame, and those hold whatever the site was
     * working on: all of $_SERVER (Uri::createFromEnvironment()), passwords, config values.
     * PHP leaves them out itself only where `zend.exception_ignore_args` is on.
     *
     * @return FrameCollection
     */
    protected function getExceptionFrames()
    {
        $frames = parent::getExceptionFrames();
        $frames->map(static function (Frame $frame) {
            $bare = new Frame(array_diff_key($frame->getRawFrame(), ['args' => true]));
            $bare->setApplication($frame->isApplication());
            foreach ($frame->getComments() as $comment) {
                $bare->addComment($comment['comment'], $comment['context']);
            }

            return $bare;
        });

        return $frames;
    }
}
