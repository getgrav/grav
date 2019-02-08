<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Psr7
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Psr7\Traits;

trait ServerRequestDecoratorTrait
{
    use RequestDecoratorTrait;

    /**
     * @inheritdoc
     */
    public function getAttribute($name, $default = null)
    {
        return $this->message->getAttribute($name, $default);
    }

    /**
     * @inheritdoc
     */
    public function getAttributes()
    {
        return $this->message->getAttributes();
    }


    /**
     * @inheritdoc
     */
    public function getCookieParams()
    {
        return $this->message->getCookieParams();
    }

    /**
     * @inheritdoc
     */
    public function getParsedBody()
    {
        return $this->message->getParsedBody();
    }

    /**
     * @inheritdoc
     */
    public function getQueryParams()
    {
        return $this->message->getQueryParams();
    }

    /**
     * @inheritdoc
     */
    public function getServerParams()
    {
        return $this->message->getServerParams();
    }

    /**
     * @inheritdoc
     */
    public function getUploadedFiles()
    {
        return $this->message->getUploadedFiles();
    }

    /**
     * @inheritdoc
     */
    public function withAttribute($name, $value)
    {
        $serverRequest = $this->message->withAttribute($name, $value);

        return static::createFrom($serverRequest);
    }

    /**
     * @inheritdoc
     */
    public function withAttributes(array $attributes)
    {
        $serverRequest = $this->message;

        foreach ($attributes as $attribute => $value) {
            $serverRequest = $serverRequest->withAttribute($attribute, $value);
        }

        return static::createFrom($serverRequest);
    }

    /**
     * @inheritdoc
     */
    public function withoutAttribute($name)
    {
        $serverRequest = $this->message->withoutAttribute($name);

        return static::createFrom($serverRequest);
    }

    /**
     * @inheritdoc
     */
    public function withCookieParams(array $cookies)
    {
        $serverRequest = $this->message->withCookieParams($cookies);

        return static::createFrom($serverRequest);
    }

    /**
     * @inheritdoc
     */
    public function withParsedBody($data)
    {
        $serverRequest = $this->message->withParsedBody($data);

        return static::createFrom($serverRequest);
    }

    /**
     * @inheritdoc
     */
    public function withQueryParams(array $query)
    {
        $serverRequest = $this->message->withQueryParams($query);

        return static::createFrom($serverRequest);
    }

    /**
     * @inheritdoc
     */
    public function withUploadedFiles(array $uploadedFiles)
    {
        $serverRequest = $this->message->withUploadedFiles($uploadedFiles);

        return static::createFrom($serverRequest);
    }
}
