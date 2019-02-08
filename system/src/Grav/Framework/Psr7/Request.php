<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Psr7
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Psr7;

use Grav\Framework\Psr7\Traits\RequestDecoratorTrait;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class Request implements RequestInterface
{
    use RequestDecoratorTrait;

    /**
     * @param string                               $method  HTTP method
     * @param string|UriInterface                  $uri     URI
     * @param array                                $headers Request headers
     * @param string|null|resource|StreamInterface $body    Request body
     * @param string                               $version Protocol version
     */
    public function __construct(string $method, $uri, array $headers = [], $body = null, string $version = '1.1')
    {
        $this->message = new \Nyholm\Psr7\Request($method, $uri, $headers, $body, $version);
    }
}
