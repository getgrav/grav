<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Psr7
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Psr7\Traits;

use Psr\Http\Message\UriInterface;

/**
 * Trait UriDecorationTrait
 * @package Grav\Framework\Psr7\Traits
 */
trait UriDecorationTrait
{
    /** @var UriInterface */
    protected $uri;

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->uri->__toString();
    }

    /**
     * @return string
     */
    public function getScheme(): string
    {
        return $this->uri->getScheme();
    }

    /**
     * @return string
     */
    public function getAuthority(): string
    {
        return $this->uri->getAuthority();
    }

    /**
     * @return string
     */
    public function getUserInfo(): string
    {
        return $this->uri->getUserInfo();
    }

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->uri->getHost();
    }

    /**
     * @return int|null
     */
    public function getPort(): ?int
    {
        return $this->uri->getPort();
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->uri->getPath();
    }

    /**
     * @return string
     */
    public function getQuery(): string
    {
        return $this->uri->getQuery();
    }

    /**
     * @return string
     */
    public function getFragment(): string
    {
        return $this->uri->getFragment();
    }

    /**
     * @param string $scheme
     * @return UriInterface
     */
    public function withScheme($scheme): UriInterface
    {
        $new = clone $this;
        $new->uri = $this->uri->withScheme($scheme);

        /** @var UriInterface $new */
        return $new;
    }

    /**
     * @param string $user
     * @param string|null $password
     * @return UriInterface
     */
    public function withUserInfo($user, $password = null): UriInterface
    {
        $new = clone $this;
        $new->uri = $this->uri->withUserInfo($user, $password);

        /** @var UriInterface $new */
        return $new;
    }

    /**
     * @param string $host
     * @return UriInterface
     */
    public function withHost($host): UriInterface
    {
        $new = clone $this;
        $new->uri = $this->uri->withHost($host);

        /** @var UriInterface $new */
        return $new;
    }

    /**
     * @param int|null $port
     * @return UriInterface
     */
    public function withPort($port): UriInterface
    {
        $new = clone $this;
        $new->uri = $this->uri->withPort($port);

        /** @var UriInterface $new */
        return $new;
    }

    /**
     * @param string $path
     * @return UriInterface
     */
    public function withPath($path): UriInterface
    {
        $new = clone $this;
        $new->uri = $this->uri->withPath($path);

        /** @var UriInterface $new */
        return $new;
    }

    /**
     * @param string $query
     * @return UriInterface
     */
    public function withQuery($query): UriInterface
    {
        $new = clone $this;
        $new->uri = $this->uri->withQuery($query);

        /** @var UriInterface $new */
        return $new;
    }

    /**
     * @param string $fragment
     * @return UriInterface
     */
    public function withFragment($fragment): UriInterface
    {
        $new = clone $this;
        $new->uri = $this->uri->withFragment($fragment);

        /** @var UriInterface $new */
        return $new;
    }
}
