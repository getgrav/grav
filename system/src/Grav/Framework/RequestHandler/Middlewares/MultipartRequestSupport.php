<?php declare(strict_types=1);

/**
 * @package    Grav\Framework\RequestHandler
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\RequestHandler\Middlewares;

use Grav\Framework\Psr7\Response;
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
        if (!str_starts_with($contentType, 'multipart/form-data') || !in_array($method, ['PUT', 'PATCH'], true)) {
            return $handler->handle($request);
        }

        $boundary = explode('; boundary=', $contentType, 2)[1] ?? '';
        // RFC 2046 allows 1 to 70 characters; searching the body for a longer one can cost its length per body byte.
        if ($boundary === '' || strlen($boundary) > 70) {
            return $handler->handle($request->withParsedBody([])->withUploadedFiles([]));
        }

        $parts = explode("--{$boundary}", $request->getBody()->getContents());
        $parts = array_slice($parts, 1, count($parts) - 2);

        // Field and file names become array keys, so refuse more parts than PHP accepts in a POST.
        $max = $this->getMaxParts();
        if (count($parts) > $max) {
            $body = json_encode([
                'status' => 413,
                'title' => 'Payload Too Large',
                'detail' => sprintf('The multipart body has more than the %d parts allowed.', $max),
            ]);

            return new Response(413, [
                'Content-Type' => 'application/problem+json',
                'Cache-Control' => 'no-store, max-age=0',
            ], (string) $body);
        }

        $params = [];
        $files = [];
        foreach ($parts as $part) {
            $this->processPart($params, $files, $part);
        }

        return $handler->handle($request->withParsedBody($params)->withUploadedFiles($files));
    }

    /**
     * Most parts one body may have: the limit PHP has for multipart POST requests.
     *
     * @return int
     */
    protected function getMaxParts(): int
    {
        $max = ini_get('max_multipart_body_parts');
        if ($max === false || (int) $max < 0) {
            // Not set: PHP allows as many parts as it allows fields and files.
            $max = (int) ini_get('max_input_vars') + (int) ini_get('max_file_uploads');
        }

        return max(0, (int) $max);
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
        $sections = explode("\r\n\r\n", $part, 2);
        if (count($sections) !== 2) {
            return;
        }
        [$rawHeaders, $body] = $sections;

        // Parse headers in place and retain colons in their values.
        $headers = [];
        foreach (explode("\r\n", $rawHeaders) as $header) {
            $fields = explode(':', $header, 2);
            if (count($fields) !== 2 || $fields[0] === '') {
                continue;
            }
            [$name, $value] = $fields;
            $name = strtolower($name);
            // Keep the headers read below only: a name chosen by the client must not become an array key.
            if ($name === 'content-disposition' || $name === 'content-type') {
                $headers[$name] = ltrim($value, ' ');
            }
        }

        if (!isset($headers['content-disposition'])) {
            return;
        }

        // Parse content disposition header.
        $contentDisposition = $headers['content-disposition'];
        preg_match('/^(.+); *name="([^"]+)"(; *filename="([^"]+)")?/', (string) $contentDisposition, $matches);
        if (!isset($matches[2])) {
            return;
        }
        $name = $matches[2];
        $filename = $matches[4] ?? null;

        if ($filename !== null) {
            // Drop any client-supplied path, as PHP does for POST uploads in $_FILES.
            $filename = basename(str_replace('\\', '/', $filename));
            $stream = Stream::create($body);
            $this->addFile($files, $name, new UploadedFile($stream, strlen($body), UPLOAD_ERR_OK, $filename, $headers['content-type'] ?? null));
        } elseif (str_contains((string) $contentDisposition, 'filename')) {
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
