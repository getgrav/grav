<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Psr7
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Psr7\Traits;

use Psr\Http\Message\ResponseInterface;

/**
 * @author Márk Sági-Kazár <mark.sagikazar@gmail.com>
 */
trait ResponseDecoratorTrait
{
    use MessageDecoratorTrait {
        getMessage as private;
    }

    /**
     * Returns the decorated response.
     *
     * Since the underlying Response is immutable as well
     * exposing it is not an issue, because it's state cannot be altered
     *
     * @return ResponseInterface
     */
    public function getResponse(): ResponseInterface
    {
        /** @var ResponseInterface $message */
        $message = $this->getMessage();

        return $message;
    }

    /**
     * Exchanges the underlying response with another.
     *
     * @param ResponseInterface $response
     *
     * @return self
     */
    public function withResponse(ResponseInterface $response): self
    {
        $new = clone $this;
        $new->message = $response;

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function getStatusCode(): int
    {
        return $this->getResponse()->getStatusCode();
    }

    /**
     * {@inheritdoc}
     */
    public function withStatus($code, $reasonPhrase = ''): self
    {
        $new = clone $this;
        $new->message = $this->getResponse()->withStatus($code, $reasonPhrase);

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function getReasonPhrase(): string
    {
        return $this->getResponse()->getReasonPhrase();
    }
}
