<?php

/**
 * @package    Grav\Framework\RequestHandler
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

declare(strict_types=1);

namespace Grav\Framework\RequestHandler\Middlewares;

use Grav\Common\Data\ValidationException;
use Grav\Common\Debugger;
use Grav\Common\Grav;
use Grav\Framework\Psr7\Response;
use JsonException;
use JsonSerializable;
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
    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * @throws JsonException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $exception) {
            $code = $exception->getCode();
            if ($exception instanceof ValidationException) {
                $message = $exception->getMessage();
            } else {
                $message = htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            $extra = $exception instanceof JsonSerializable ? $exception->jsonSerialize() : [];

            $response = [
                'code' => $code,
                'status' => 'error',
                'message' => $message,
                'error' => [
                    'code' => $code,
                    'message' => $message,
                ] + $extra
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
            $json = json_encode($response, JSON_THROW_ON_ERROR);

            return new Response($code ?: 500, ['Content-Type' => 'application/json'], $json);
        }
    }
}
