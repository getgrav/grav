<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Psr7
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Psr7;

use Grav\Framework\Psr7\Traits\ResponseDecoratorTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Class Response
 * @package Slim\Http
 */
class Response implements ResponseInterface
{
    use ResponseDecoratorTrait;

    /**
     * @var string EOL characters used for HTTP response.
     */
    private const EOL = "\r\n";

    /**
     * @var ResponseInterface;
     */
    private $response;

    /**
     * @param ResponseInterface $response
     * @return static
     */
    protected static function createFrom(ResponseInterface $response)
    {
        if ($response instanceof self) {
            return $response;
        }

        return new static($response);
    }

    /**
     * @param int|ResponseInterface                $status  Status code
     * @param array                                $headers Response headers
     * @param string|null|resource|StreamInterface $body    Response body
     * @param string                               $version Protocol version
     * @param string|null                          $reason  Reason phrase (optional)
     */
    public function __construct($status = 200, array $headers = [], $body = null, string $version = '1.1', string $reason = null)
    {
        if ($status instanceof ResponseInterface) {
            $this->message = $status;
        } else {
            $this->message = new \Nyholm\Psr7\Response($status, $headers, $body, $version, $reason);
        }
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
     * @param  int    $status The HTTP status code.
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

        $response = $this->message
            ->withHeader('Content-Type', 'application/json;charset=utf-8')
            ->withBody(new Stream($json));

        if ($status !== null) {
            $response = $response->withStatus($status);
        }

        return static::createFrom($response);
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
        $response = $this->message->withHeader('Location', $url);

        if ($status === null) {
            $status = 302;
        }
        $response = $response->withStatus($status);

        return static::createFrom($response);
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
        return \in_array($this->message->getStatusCode(), [204, 205, 304], true);
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
        return $this->message->getStatusCode() === 200;
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
        return \in_array($this->message->getStatusCode(), [301, 302, 303, 307, 308], true);
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
        return $this->message->getStatusCode() === 403;
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
        return $this->message->getStatusCode() === 404;
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
        return $this->message->getStatusCode() >= 100 && $this->message->getStatusCode() < 200;
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
        return $this->message->getStatusCode() >= 200 && $this->message->getStatusCode() < 300;
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
        return $this->message->getStatusCode() >= 300 && $this->message->getStatusCode() < 400;
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
        return $this->message->getStatusCode() >= 400 && $this->message->getStatusCode() < 500;
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
        return $this->message->getStatusCode() >= 500 && $this->message->getStatusCode() < 600;
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
        $output = sprintf(
            'HTTP/%s %s %s%s',
            $this->message->getProtocolVersion(),
            $this->message->getStatusCode(),
            $this->message->getReasonPhrase(),
            self::EOL
        );

        foreach ($this->message->getHeaders() as $name => $values) {
            $output .= sprintf('%s: %s', $name, $this->message->getHeaderLine($name)) . self::EOL;
        }

        $output .= self::EOL;
        $output .= (string) $this->message->getBody();

        return $output;
    }
}
