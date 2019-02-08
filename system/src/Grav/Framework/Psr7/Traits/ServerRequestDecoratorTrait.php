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
        $new = clone $this;
        $new->message = $this->message->withAttribute($name, $value);

        return $new;
    }

    /**
     * @inheritdoc
     */
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
        $new->message = $this->message->withoutAttribute($name);

        return $new;
    }

    /**
     * @inheritdoc
     */
    public function withCookieParams(array $cookies)
    {
        $new = clone $this;
        $new->message = $this->message->withCookieParams($cookies);

        return $new;
    }

    /**
     * @inheritdoc
     */
    public function withParsedBody($data)
    {
        $new = clone $this;
        $new->message = $this->message->withParsedBody($data);

        return $new;
    }

    /**
     * @inheritdoc
     */
    public function withQueryParams(array $query)
    {
        $new = clone $this;
        $new->message = $this->message->withQueryParams($query);

        return $new;
    }

    /**
     * @inheritdoc
     */
    public function withUploadedFiles(array $uploadedFiles)
    {
        $new = clone $this;
        $new->message = $this->message->withUploadedFiles($uploadedFiles);

        return $new;
    }
}
