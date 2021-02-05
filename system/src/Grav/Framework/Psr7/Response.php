<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Psr7
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Psr7;

use Grav\Framework\Psr7\Traits\ResponseDecoratorTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use function in_array;

/**
 * Class Response
 * @package Slim\Http
 */
class Response implements ResponseInterface
{
    use ResponseDecoratorTrait;

    /** @var string EOL characters used for HTTP response. */
    private const EOL = "\r\n";

    /**
     * @param int                                  $status  Status code
     * @param array                                $headers Response headers
     * @param string|null|resource|StreamInterface $body    Response body
     * @param string                               $version Protocol version
     * @param string|null                          $reason  Reason phrase (optional)
     */
    public function __construct(int $status = 200, array $headers = [], $body = null, string $version = '1.1', string $reason = null)
    {
        $this->message = new \Nyholm\Psr7\Response($status, $headers, $body, $version, $reason);
    }

    /**
     * Json.
     *
     * Note: This method is not part of the PSR-7 standard.
     *
     * This method prepares the response object to return an HTTP Json
     * response to the client.
     *
     * @param  mixed  $data   The data
     * @param  int|null $status The HTTP status code.
     * @param  int    $options Json encoding options
     * @param  int    $depth Json encoding max depth
     * @return static
     */
    public function withJson($data, int $status = null, int $options = 0, int $depth = 512): ResponseInterface
    {
        $json = (string) json_encode($data, $options, $depth);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(json_last_error_msg(), json_last_error());
        }

        $response = $this->getResponse()
            ->withHeader('Content-Type', 'application/json;charset=utf-8')
            ->withBody(new Stream($json));

        if ($status !== null) {
            $response = $response->withStatus($status);
        }

        $new = clone $this;
        $new->message = $response;

        return $new;
    }

    /**
     * Redirect.
     *
     * Note: This method is not part of the PSR-7 standard.
     *
     * This method prepares the response object to return an HTTP Redirect
     * response to the client.
     *
     * @param string $url The redirect destination.
     * @param int|null $status The redirect HTTP status code.
     * @return static
     */
    public function withRedirect(string $url, $status = null): ResponseInterface
    {
        $response = $this->getResponse()->withHeader('Location', $url);

        if ($status === null) {
            $status = 302;
        }

        $new = clone $this;
        $new->message = $response->withStatus($status);

        return $new;
    }

    /**
     * Is this response empty?
     *
     * Note: This method is not part of the PSR-7 standard.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return in_array($this->getResponse()->getStatusCode(), [204, 205, 304], true);
    }


    /**
     * Is this response OK?
     *
     * Note: This method is not part of the PSR-7 standard.
     *
     * @return bool
     */
    public function isOk(): bool
    {
        return $this->getResponse()->getStatusCode() === 200;
    }

    /**
     * Is this response a redirect?
     *
     * Note: This method is not part of the PSR-7 standard.
     *
     * @return bool
     */
    public function isRedirect(): bool
    {
        return in_array($this->getResponse()->getStatusCode(), [301, 302, 303, 307, 308], true);
    }

    /**
     * Is this response forbidden?
     *
     * Note: This method is not part of the PSR-7 standard.
     *
     * @return bool
     * @api
     */
    public function isForbidden(): bool
    {
        return $this->getResponse()->getStatusCode() === 403;
    }

    /**
     * Is this response not Found?
     *
     * Note: This method is not part of the PSR-7 standard.
     *
     * @return bool
     */
    public function isNotFound(): bool
    {
        return $this->getResponse()->getStatusCode() === 404;
    }

    /**
     * Is this response informational?
     *
     * Note: This method is not part of the PSR-7 standard.
     *
     * @return bool
     */
    public function isInformational(): bool
    {
        $response = $this->getResponse();

        return $response->getStatusCode() >= 100 && $response->getStatusCode() < 200;
    }

    /**
     * Is this response successful?
     *
     * Note: This method is not part of the PSR-7 standard.
     *
     * @return bool
     */
    public function isSuccessful(): bool
    {
        $response = $this->getResponse();

        return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
    }

    /**
     * Is this response a redirection?
     *
     * Note: This method is not part of the PSR-7 standard.
     *
     * @return bool
     */
    public function isRedirection(): bool
    {
        $response = $this->getResponse();

        return $response->getStatusCode() >= 300 && $response->getStatusCode() < 400;
    }

    /**
     * Is this response a client error?
     *
     * Note: This method is not part of the PSR-7 standard.
     *
     * @return bool
     */
    public function isClientError(): bool
    {
        $response = $this->getResponse();

        return $response->getStatusCode() >= 400 && $response->getStatusCode() < 500;
    }

    /**
     * Is this response a server error?
     *
     * Note: This method is not part of the PSR-7 standard.
     *
     * @return bool
     */
    public function isServerError(): bool
    {
        $response = $this->getResponse();

        return $response->getStatusCode() >= 500 && $response->getStatusCode() < 600;
    }

    /**
     * Convert response to string.
     *
     * Note: This method is not part of the PSR-7 standard.
     *
     * @return string
     */
    public function __toString(): string
    {
        $response = $this->getResponse();
        $output = sprintf(
            'HTTP/%s %s %s%s',
            $response->getProtocolVersion(),
            $response->getStatusCode(),
            $response->getReasonPhrase(),
            self::EOL
        );

        foreach ($response->getHeaders() as $name => $values) {
            $output .= sprintf('%s: %s', $name, $response->getHeaderLine($name)) . self::EOL;
        }

        $output .= self::EOL;
        $output .= (string) $response->getBody();

        return $output;
    }
}
