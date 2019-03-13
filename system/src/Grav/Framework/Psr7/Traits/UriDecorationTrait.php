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
        /** @var UriInterface|UriDecorationTrait $new */
        $new = clone $this;
        $new->uri = $this->uri->withScheme($scheme);

        return $new;
    }

    public function withUserInfo($user, $password = null): UriInterface
    {
        /** @var UriInterface|UriDecorationTrait $new */
        $new = clone $this;
        $new->uri = $this->uri->withUserInfo($user, $password);

        return $new;
    }

    public function withHost($host): UriInterface
    {
        /** @var UriInterface|UriDecorationTrait $new */
        $new = clone $this;
        $new->uri = $this->uri->withHost($host);

        return $new;
    }

    public function withPort($port): UriInterface
    {
        /** @var UriInterface|UriDecorationTrait $new */
        $new = clone $this;
        $new->uri = $this->uri->withPort($port);

        return $new;
    }

    public function withPath($path): UriInterface
    {
        /** @var UriInterface|UriDecorationTrait $new */
        $new = clone $this;
        $new->uri = $this->uri->withPath($path);

        return $new;
    }

    public function withQuery($query): UriInterface
    {
        /** @var UriInterface|UriDecorationTrait $new */
        $new = clone $this;
        $new->uri = $this->uri->withQuery($query);

        return $new;
    }

    public function withFragment($fragment): UriInterface
    {
        /** @var UriInterface|UriDecorationTrait $new */
        $new = clone $this;
        $new->uri = $this->uri->withFragment($fragment);

        return $new;
    }
}
