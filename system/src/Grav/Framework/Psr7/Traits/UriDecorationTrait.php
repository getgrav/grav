<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Psr7
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Psr7\Traits;

use Psr\Http\Message\UriInterface;

trait UriDecorationTrait
{
    /** @var UriInterface */
    protected $uri;

    public function __toString(): string
    {
        return $this->uri->__toString();
    }

    public function getScheme(): string
    {
        return $this->uri->getScheme();
    }

    public function getAuthority(): string
    {
        return $this->uri->getAuthority();
    }

    public function getUserInfo(): string
    {
        return $this->uri->getUserInfo();
    }

    public function getHost(): string
    {
        return $this->uri->getHost();
    }

    public function getPort(): ?int
    {
        return $this->uri->getPort();
    }

    public function getPath(): string
    {
        return $this->uri->getPath();
    }

    public function getQuery(): string
    {
        return $this->uri->getQuery();
    }

    public function getFragment(): string
    {
        return $this->uri->getFragment();
    }

    public function withScheme($scheme): UriInterface
    {
        return $this->uri->withScheme($scheme);
    }

    public function withUserInfo($user, $password = null): UriInterface
    {
        return $this->uri->withUserInfo($user, $password);
    }

    public function withHost($host): UriInterface
    {
        return $this->uri->withHost($host);
    }

    public function withPort($port): UriInterface
    {
        return $this->uri->withPort($port);
    }

    public function withPath($path): UriInterface
    {
        return $this->uri->withPath($path);
    }

    public function withQuery($query): UriInterface
    {
        return $this->uri->withQuery($query);
    }

    public function withFragment($fragment): UriInterface
    {
        return $this->uri->withFragment($fragment);
    }
}
