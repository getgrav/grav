<?php
/**
 * @package    Grav\Framework\Uri
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Uri;

use Grav\Framework\Psr7\AbstractUri;
use Psr\Http\Message\UriInterface;

/**
 * Implements PSR-7 UriInterface.
 *
 * @package Grav\Framework\Uri
 */
class Uri extends AbstractUri
{
    protected $queryParams;

    /**
     * Uri constructor.
     *
     * @param array $parts
     * @throws \InvalidArgumentException
     */
    public function __construct(array $parts = [])
    {
        $this->initParts($parts);
    }

    /**
     * @return string
     */
    public function getUser()
    {
        return parent::getUser();
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return parent::getPassword();
    }

    /**
     * @return array
     */
    public function getParts()
    {
        return parent::getParts();
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return parent::getUrl();
    }

    /**
     * @return string
     */
    public function getBaseUrl()
    {
        return parent::getBaseUrl();
    }

    /**
     * @param string $key
     * @return string|null
     */
    public function getQueryParam($key)
    {
        $queryParams = $this->getQueryParams();

        return isset($queryParams[$key]) ? $queryParams[$key] : null;
    }

    /**
     * @param string $key
     * @return UriInterface
     */
    public function withoutQueryParam($key)
    {
        return UriHelper::withoutQueryParam($this, $key);
    }

    /**
     * @param string $key
     * @param string|null $value
     * @return UriInterface
     */
    public function withQueryParam($key, $value)
    {
        return UriHelper::withQueryParam($this, $key, $value);
    }

    /**
     * @return array
     */
    public function getQueryParams()
    {
        if ($this->queryParams === null) {
            parse_str($this->getQuery(), $this->queryParams);
            array_map(function($str) { return rawurldecode($str); }, $this->queryParams);
        }

        return $this->queryParams;
    }

    /**
     * @param array $params
     * @return UriInterface
     */
    public function withQueryParams(array $params)
    {
        return empty($params) ? $this->withQuery('') : UriHelper::withQueryParams($this, $params);
    }
}
