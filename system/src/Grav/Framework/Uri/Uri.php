<?php

/**
 * @package    Grav\Framework\Uri
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Uri;

use Grav\Framework\Psr7\AbstractUri;
use GuzzleHttp\Psr7\Uri as GuzzleUri;
use InvalidArgumentException;
use Psr\Http\Message\UriInterface;

/**
 * Implements PSR-7 UriInterface.
 *
 * @package Grav\Framework\Uri
 */
class Uri extends AbstractUri
{
    /** @var array Array of Uri query. */
    private $queryParams;

    /**
     * You can use `UriFactory` functions to create new `Uri` objects.
     *
     * @param array $parts
     * @return void
     * @throws InvalidArgumentException
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

        return $queryParams[$key] ?? null;
    }

    /**
     * @param string $key
     * @return UriInterface
     */
    public function withoutQueryParam($key)
    {
        return GuzzleUri::withoutQueryValue($this, $key);
    }

    /**
     * @param string $key
     * @param string|null $value
     * @return UriInterface
     */
    public function withQueryParam($key, $value)
    {
        return GuzzleUri::withQueryValue($this, $key, $value);
    }

    /**
     * @return array
     */
    public function getQueryParams()
    {
        if ($this->queryParams === null) {
            $this->queryParams = UriFactory::parseQuery($this->getQuery());
        }

        return $this->queryParams;
    }

    /**
     * @param array $params
     * @return UriInterface
     */
    public function withQueryParams(array $params)
    {
        $query = UriFactory::buildQuery($params);

        return $this->withQuery($query);
    }

    /**
     * Whether the URI has the default port of the current scheme.
     *
     * `$uri->getPort()` may return the standard port. This method can be used for some non-http/https Uri.
     *
     * @return bool
     */
    public function isDefaultPort()
    {
        return $this->getPort() === null || GuzzleUri::isDefaultPort($this);
    }

    /**
     * Whether the URI is absolute, i.e. it has a scheme.
     *
     * An instance of UriInterface can either be an absolute URI or a relative reference. This method returns true
     * if it is the former. An absolute URI has a scheme. A relative reference is used to express a URI relative
     * to another URI, the base URI. Relative references can be divided into several forms:
     * - network-path references, e.g. '//example.com/path'
     * - absolute-path references, e.g. '/path'
     * - relative-path references, e.g. 'subpath'
     *
     * @return bool
     * @link https://tools.ietf.org/html/rfc3986#section-4
     */
    public function isAbsolute()
    {
        return GuzzleUri::isAbsolute($this);
    }

    /**
     * Whether the URI is a network-path reference.
     *
     * A relative reference that begins with two slash characters is termed an network-path reference.
     *
     * @return bool
     * @link https://tools.ietf.org/html/rfc3986#section-4.2
     */
    public function isNetworkPathReference()
    {
        return GuzzleUri::isNetworkPathReference($this);
    }

    /**
     * Whether the URI is a absolute-path reference.
     *
     * A relative reference that begins with a single slash character is termed an absolute-path reference.
     *
     * @return bool
     * @link https://tools.ietf.org/html/rfc3986#section-4.2
     */
    public function isAbsolutePathReference()
    {
        return GuzzleUri::isAbsolutePathReference($this);
    }

    /**
     * Whether the URI is a relative-path reference.
     *
     * A relative reference that does not begin with a slash character is termed a relative-path reference.
     *
     * @return bool
     * @link https://tools.ietf.org/html/rfc3986#section-4.2
     */
    public function isRelativePathReference()
    {
        return GuzzleUri::isRelativePathReference($this);
    }

    /**
     * Whether the URI is a same-document reference.
     *
     * A same-document reference refers to a URI that is, aside from its fragment
     * component, identical to the base URI. When no base URI is given, only an empty
     * URI reference (apart from its fragment) is considered a same-document reference.
     *
     * @param UriInterface|null $base An optional base URI to compare against
     * @return bool
     * @link https://tools.ietf.org/html/rfc3986#section-4.4
     */
    public function isSameDocumentReference(UriInterface $base = null)
    {
        return GuzzleUri::isSameDocumentReference($this, $base);
    }
}
