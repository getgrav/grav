<?php declare(strict_types=1);

/**
 * @package    Grav\Framework\RequestHandler
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\RequestHandler\Middlewares;

use Grav\Framework\Psr7\UploadedFile;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use function array_slice;
use function count;
use function in_array;
use function is_array;
use function strlen;

/**
 * Multipart request support for PUT and PATCH.
 */
class MultipartRequestSupport implements MiddlewareInterface
{
    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $contentType = $request->getHeaderLine('content-type');
        $method = $request->getMethod();
        if (!str_starts_with($contentType, 'multipart/form-data') || !in_array($method, ['PUT', 'PATH'], true)) {
            return $handler->handle($request);
        }

        $boundary = explode('; boundary=', $contentType, 2)[1] ?? '';
        $parts = explode("--{$boundary}", $request->getBody()->getContents());
        $parts = array_slice($parts, 1, count($parts) - 2);

        $params = [];
        $files = [];
        foreach ($parts as $part) {
            $this->processPart($params, $files, $part);
        }

        return $handler->handle($request->withParsedBody($params)->withUploadedFiles($files));
    }

    /**
     * @param array $params
     * @param array $files
     * @param string $part
     * @return void
     */
    protected function processPart(array &$params, array &$files, string $part): void
    {
        $part = ltrim($part, "\r\n");
        [$rawHeaders, $body] = explode("\r\n\r\n", $part, 2);

        // Parse headers.
        $rawHeaders = explode("\r\n", $rawHeaders);
        $headers = array_reduce(
            $rawHeaders,
            static function (array $headers, $header) {
                [$name, $value] = explode(':', $header);
                $headers[strtolower($name)] = ltrim($value, ' ');

                return $headers;
            },
            []
        );

        if (!isset($headers['content-disposition'])) {
            return;
        }

        // Parse content disposition header.
        $contentDisposition = $headers['content-disposition'];
        preg_match('/^(.+); *name="([^"]+)"(; *filename="([^"]+)")?/', $contentDisposition, $matches);
        $name = $matches[2];
        $filename = $matches[4] ?? null;

        if ($filename !== null) {
            $stream = Stream::create($body);
            $this->addFile($files, $name, new UploadedFile($stream, strlen($body), UPLOAD_ERR_OK, $filename, $headers['content-type'] ?? null));
        } elseif (strpos($contentDisposition, 'filename') !== false) {
            // Not uploaded file.
             $stream = Stream::create('');
            $this->addFile($files, $name, new UploadedFile($stream, 0, UPLOAD_ERR_NO_FILE));
        } else {
            // Regular field.
            $params[$name] = substr($body, 0, -2);
        }
    }

    /**
     * @param array $files
     * @param string $name
     * @param UploadedFileInterface $file
     * @return void
     */
    protected function addFile(array &$files, string $name, UploadedFileInterface $file): void
    {
        if (strpos($name, '[]') === strlen($name) - 2) {
            $name = substr($name, 0, -2);

            if (isset($files[$name]) && is_array($files[$name])) {
                $files[$name][] = $file;
            } else {
                $files[$name] = [$file];
            }
        } else {
            $files[$name] = $file;
        }
    }
}
