<?php

declare(strict_types=1);

/**
 * @package    Grav\Framework\Psr7
 *
 * @copyright  Copyright (C) 2015 - 2019 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Psr7;

use Grav\Framework\Psr7\Traits\UriDecorationTrait;
use Grav\Framework\Uri\UriFactory;
use GuzzleHttp\Psr7\Uri as GuzzleUri;
use Psr\Http\Message\UriInterface;

class Uri implements UriInterface
{
    use UriDecorationTrait;

    /** @var array Array of Uri query. */
    private $queryParams;

    public function __construct(string $uri = '')
    {
        $this->uri = new \Nyholm\Psr7\Uri($uri);
    }

    /**
     * @return array
     */
    public function getQueryParams(): array
    {
        return UriFactory::parseQuery($this->getQuery());
    }

    /**
     * @param array $params
     * @return UriInterface
     */
    public function withQueryParams(array $params): UriInterface
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
    public function isDefaultPort(): bool
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
    public function isAbsolute(): bool
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
    public function isNetworkPathReference(): bool
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
    public function isAbsolutePathReference(): bool
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
    public function isRelativePathReference(): bool
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
    public function isSameDocumentReference(UriInterface $base = null): bool
    {
        return GuzzleUri::isSameDocumentReference($this, $base);
    }
}
