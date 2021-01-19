<?php

/**
 * @package    Grav\Framework\RequestHandler
 *
 * @copyright  Copyright (C) 2015 - 2020 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

declare(strict_types=1);

namespace Grav\Framework\RequestHandler\Middlewares;

use Grav\Common\Debugger;
use Grav\Common\Grav;
use Grav\Framework\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use function get_class;

/**
 * Class Exceptions
 * @package Grav\Framework\RequestHandler\Middlewares
 */
class Exceptions implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $exception) {
            $response = [
                'error' => [
                    'code' => $exception->getCode(),
                    'message' => $exception->getMessage(),
                ]
            ];

            /** @var Debugger $debugger */
            $debugger = Grav::instance()['debugger'];
            if ($debugger->enabled()) {
                $response['error'] += [
                    'type' => get_class($exception),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => explode("\n", $exception->getTraceAsString()),
                ];
            }

            /** @var string $json */
            $json = json_encode($response);

            return new Response($exception->getCode() ?: 500, ['Content-Type' => 'application/json'], $json);
        }
    }
}
