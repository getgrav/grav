<?php

/**
 * @package    Grav\Framework\Controller
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

declare(strict_types=1);

namespace Grav\Framework\Controller\Traits;

use Grav\Common\Config\Config;
use Grav\Common\Data\ValidationException;
use Grav\Common\Debugger;
use Grav\Common\Grav;
use Grav\Common\Utils;
use Grav\Framework\Psr7\Response;
use Grav\Framework\RequestHandler\Exception\RequestException;
use Grav\Framework\Route\Route;
use JsonSerializable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Throwable;
use function get_class;
use function in_array;

/**
 * Trait ControllerResponseTrait
 * @package Grav\Framework\Controller\Traits
 */
trait ControllerResponseTrait
{
    /**
     * Display the current page.
     *
     * @return Response
     */
    protected function createDisplayResponse(): ResponseInterface
    {
        return new Response(418);
    }

    /**
     * @param string $content
     * @param int|null $code
     * @param array|null $headers
     * @return Response
     */
    protected function createHtmlResponse(string $content, int $code = null, array $headers = null): ResponseInterface
    {
        $code = $code ?? 200;
        if ($code < 100 || $code > 599) {
            $code = 500;
        }
        $headers = $headers ?? [];

        return new Response($code, $headers, $content);
    }

    /**
     * @param array $content
     * @param int|null $code
     * @param array|null $headers
     * @return Response
     */
    protected function createJsonResponse(array $content, int $code = null, array $headers = null): ResponseInterface
    {
        $code = $code ?? $content['code'] ?? 200;
        if (null === $code || $code < 100 || $code > 599) {
            $code = 200;
        }
        $headers = ($headers ?? []) + [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-store, max-age=0'
        ];

        return new Response($code, $headers, json_encode($content));
    }

    /**
     * @param string $filename
     * @param string|resource|StreamInterface $resource
     * @param array|null $headers
     * @param array|null $options
     * @return ResponseInterface
     */
    protected function createDownloadResponse(string $filename, $resource, array $headers = null, array $options = null): ResponseInterface
    {
        // Required for IE, otherwise Content-Disposition may be ignored
        if (ini_get('zlib.output_compression')) {
            @ini_set('zlib.output_compression', 'Off');
        }

        $headers = $headers ?? [];
        $options = $options ?? ['force_download' => true];

        $file_parts = Utils::pathinfo($filename);

        if (!isset($headers['Content-Type'])) {
            $mimetype = Utils::getMimeByExtension($file_parts['extension']);

            $headers['Content-Type'] = $mimetype;
        }

        // TODO: add multipart download support.
        //$headers['Accept-Ranges'] = 'bytes';

        if (!empty($options['force_download'])) {
            $headers['Content-Disposition'] = 'attachment; filename="' . $file_parts['basename'] . '"';
        }

        if (!isset($headers['Content-Length'])) {
            $realpath = realpath($filename);
            if ($realpath) {
                $headers['Content-Length'] = filesize($realpath);
            }
        }

        $headers += [
            'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
            'Last-Modified' => gmdate('D, d M Y H:i:s') . ' GMT',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache'
        ];

        return new Response(200, $headers, $resource);
    }

    /**
     * @param string $url
     * @param int|null $code
     * @return Response
     */
    protected function createRedirectResponse(string $url, int $code = null): ResponseInterface
    {
        if (null === $code || $code < 301 || $code > 307) {
            $code = (int)$this->getConfig()->get('system.pages.redirect_default_code', 302);
        }

        $ext = Utils::pathinfo($url, PATHINFO_EXTENSION);
        $accept = $this->getAccept(['application/json', 'text/html']);
        if ($ext === 'json' || $accept === 'application/json') {
            return $this->createJsonResponse(['code' => $code, 'status' => 'redirect', 'redirect' => $url]);
        }

        return new Response($code, ['Location' => $url]);
    }

    /**
     * @param Throwable $e
     * @return ResponseInterface
     */
    protected function createErrorResponse(Throwable $e): ResponseInterface
    {
        $response = $this->getErrorJson($e);
        $message = $response['message'];
        $code = $response['code'];
        $reason = $e instanceof RequestException ? $e->getHttpReason() : null;
        $accept = $this->getAccept(['application/json', 'text/html']);

        $request = $this->getRequest();
        $context = $request->getAttributes();

        /** @var Route $route */
        $route = $context['route'] ?? null;

        $ext = $route ? $route->getExtension() : null;
        if ($ext !== 'json' && $accept === 'text/html') {
            $method = $request->getMethod();

            // On POST etc, redirect back to the previous page.
            if ($method !== 'GET' && $method !== 'HEAD') {
                $this->setMessage($message, 'error');
                $referer = $request->getHeaderLine('Referer');

                return $this->createRedirectResponse($referer, 303);
            }

            // TODO: improve error page
            return $this->createHtmlResponse($response['message'], $code);
        }

        return new Response($code, ['Content-Type' => 'application/json'], json_encode($response), '1.1', $reason);
    }

    /**
     * @param Throwable $e
     * @return ResponseInterface
     */
    protected function createJsonErrorResponse(Throwable $e): ResponseInterface
    {
        $response = $this->getErrorJson($e);
        $reason = $e instanceof RequestException ? $e->getHttpReason() : null;

        return new Response($response['code'], ['Content-Type' => 'application/json'], json_encode($response), '1.1', $reason);
    }

    /**
     * @param Throwable $e
     * @return array
     */
    protected function getErrorJson(Throwable $e): array
    {
        $code = $this->getErrorCode($e instanceof RequestException ? $e->getHttpCode() : $e->getCode());
        if ($e instanceof ValidationException) {
            $message = $e->getMessage();
        } else {
            $message = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $extra = $e instanceof JsonSerializable ? $e->jsonSerialize() : [];

        $response = [
            'code' => $code,
            'status' => 'error',
            'message' => $message,
            'redirect' => null,
            'error' => [
                'code' => $code,
                'message' => $message
            ] + $extra
        ];

        /** @var Debugger $debugger */
        $debugger = Grav::instance()['debugger'];
        if ($debugger->enabled()) {
            $response['error'] += [
                'type' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => explode("\n", $e->getTraceAsString())
            ];
        }

        return $response;
    }

    /**
     * @param int $code
     * @return int
     */
    protected function getErrorCode(int $code): int
    {
        static $errorCodes = [
            400, 401, 402, 403, 404, 405, 406, 407, 408, 409, 410, 411, 412, 413, 414, 415, 416, 417, 418,
            422, 423, 424, 425, 426, 428, 429, 431, 451, 500, 501, 502, 503, 504, 505, 506, 507, 508, 511
        ];

        if (!in_array($code, $errorCodes, true)) {
            $code = 500;
        }

        return $code;
    }

    /**
     * @param array $compare
     * @return mixed
     */
    protected function getAccept(array $compare)
    {
        $accepted = [];
        foreach ($this->getRequest()->getHeader('Accept') as $accept) {
            foreach (explode(',', $accept) as $item) {
                if (!$item) {
                    continue;
                }

                $split = explode(';q=', $item);
                $mime = array_shift($split);
                $priority = array_shift($split) ?? 1.0;

                $accepted[$mime] = $priority;
            }
        }

        arsort($accepted);

        // TODO: add support for image/* etc
        $list = array_intersect($compare, array_keys($accepted));
        if (!$list && (isset($accepted['*/*']) || isset($accepted['*']))) {
            return reset($compare);
        }

        return reset($list);
    }

    /**
     * @return ServerRequestInterface
     */
    abstract protected function getRequest(): ServerRequestInterface;

    /**
     * @param string $message
     * @param string $type
     * @return $this
     */
    abstract protected function setMessage(string $message, string $type = 'info');

    /**
     * @return Config
     */
    abstract protected function getConfig(): Config;
}
