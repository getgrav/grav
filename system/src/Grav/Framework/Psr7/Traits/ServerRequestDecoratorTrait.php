<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Psr7
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Psr7\Traits;

use Psr\Http\Message\ServerRequestInterface;

trait ServerRequestDecoratorTrait
{
    use RequestDecoratorTrait;

    /**
     * Returns the decorated request.
     *
     * Since the underlying Request is immutable as well
     * exposing it is not an issue, because it's state cannot be altered
     *
     * @return ServerRequestInterface
     */
    public function getRequest(): ServerRequestInterface
    {
        /** @var ServerRequestInterface $message */
        $message = $this->getMessage();

        return $message;
    }

    /**
     * @inheritdoc
     */
    public function getAttribute($name, $default = null)
    {
        return $this->getRequest()->getAttribute($name, $default);
    }

    /**
     * @inheritdoc
     */
    public function getAttributes()
    {
        return $this->getRequest()->getAttributes();
    }


    /**
     * @inheritdoc
     */
    public function getCookieParams()
    {
        return $this->getRequest()->getCookieParams();
    }

    /**
     * @inheritdoc
     */
    public function getParsedBody()
    {
        return $this->getRequest()->getParsedBody();
    }

    /**
     * @inheritdoc
     */
    public function getQueryParams()
    {
        return $this->getRequest()->getQueryParams();
    }

    /**
     * @inheritdoc
     */
    public function getServerParams()
    {
        return $this->getRequest()->getServerParams();
    }

    /**
     * @inheritdoc
     */
    public function getUploadedFiles()
    {
        return $this->getRequest()->getUploadedFiles();
    }

    /**
     * @inheritdoc
     */
    public function withAttribute($name, $value)
    {
        $new = clone $this;
        $new->message = $this->getRequest()->withAttribute($name, $value);

        return $new;
    }

    public function withAttributes(array $attributes)
    {
        $new = clone $this;
        foreach ($attributes as $attribute => $value) {
            $new->message = $new->withAttribute($attribute, $value);
        }

        return $new;
    }

    /**
     * @inheritdoc
     */
    public function withoutAttribute($name)
    {
        $new = clone $this;
        $new->message = $this->getRequest()->withoutAttribute($name);

        return $new;
    }

    /**
     * @inheritdoc
     */
    public function withCookieParams(array $cookies)
    {
        $new = clone $this;
        $new->message = $this->getRequest()->withCookieParams($cookies);

        return $new;
    }

    /**
     * @inheritdoc
     */
    public function withParsedBody($data)
    {
        $new = clone $this;
        $new->message = $this->getRequest()->withParsedBody($data);

        return $new;
    }

    /**
     * @inheritdoc
     */
    public function withQueryParams(array $query)
    {
        $new = clone $this;
        $new->message = $this->getRequest()->withQueryParams($query);

        return $new;
    }

    /**
     * @inheritdoc
     */
    public function withUploadedFiles(array $uploadedFiles)
    {
        $new = clone $this;
        $new->message = $this->getRequest()->withUploadedFiles($uploadedFiles);

        return $new;
    }
}
