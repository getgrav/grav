<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Psr7
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Psr7\Traits;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * @author Márk Sági-Kazár <mark.sagikazar@gmail.com>
 */
trait RequestDecoratorTrait
{
    use MessageDecoratorTrait {
        getMessage as private;
    }

    /**
     * Returns the decorated request.
     *
     * Since the underlying Request is immutable as well
     * exposing it is not an issue, because it's state cannot be altered
     *
     * @return RequestInterface
     */
    public function getRequest(): RequestInterface
    {
        /** @var RequestInterface $message */
        $message = $this->getMessage();

        return $message;
    }

    /**
     * Exchanges the underlying request with another.
     *
     * @param RequestInterface $request
     * @return self
     */
    public function withRequest(RequestInterface $request): self
    {
        $new = clone $this;
        $new->message = $request;

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestTarget(): string
    {
        return $this->getRequest()->getRequestTarget();
    }

    /**
     * {@inheritdoc}
     */
    public function withRequestTarget($requestTarget): self
    {
        $new = clone $this;
        $new->message = $this->getRequest()->withRequestTarget($requestTarget);

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function getMethod(): string
    {
        return $this->getRequest()->getMethod();
    }

    /**
     * {@inheritdoc}
     */
    public function withMethod($method): self
    {
        $new = clone $this;
        $new->message = $this->getRequest()->withMethod($method);

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function getUri(): UriInterface
    {
        return $this->getRequest()->getUri();
    }

    /**
     * {@inheritdoc}
     */
    public function withUri(UriInterface $uri, $preserveHost = false): self
    {
        $new = clone $this;
        $new->message = $this->getRequest()->withUri($uri, $preserveHost);

        return $new;
    }
}
