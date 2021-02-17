<?php

/**
 * @package    Grav\Framework\Route
 *
 * @copyright  Copyright (c) 2015 - 2021 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Route;

use Grav\Framework\Uri\UriFactory;
use InvalidArgumentException;
use function array_slice;

/**
 * Implements Grav Route.
 *
 * @package Grav\Framework\Route
 */
class Route
{
    /** @var string */
    private $root = '';
    /** @var string */
    private $language = '';
    /** @var string */
    private $route = '';
    /** @var string */
    private $extension = '';
    /** @var array */
    private $gravParams = [];
    /** @var array */
    private $queryParams = [];

    /**
     * You can use `RouteFactory` functions to create new `Route` objects.
     *
     * @param array $parts
     * @throws InvalidArgumentException
     */
    public function __construct(array $parts = [])
    {
        $this->initParts($parts);
    }

    /**
     * @return array
     */
    public function getParts()
    {
        return [
            'path' => $this->getUriPath(true),
            'query' => $this->getUriQuery(),
            'grav' => [
                'root' => $this->root,
                'language' => $this->language,
                'route' => $this->route,
                'extension' => $this->extension,
                'grav_params' => $this->gravParams,
                'query_params' => $this->queryParams,
            ],
        ];
    }

    /**
     * @return string
     */
    public function getRootPrefix()
    {
        return $this->root;
    }

    /**
     * @return string
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * @return string
     */
    public function getLanguagePrefix()
    {
        return $this->language !== '' ? '/' . $this->language : '';
    }

    /**
     * @param string|null $language
     * @return string
     */
    public function getBase(string $language = null): string
    {
        $parts = [$this->root];

        if (null === $language) {
            $language = $this->language;
        }

        if ($language !== '') {
            $parts[] = $language;
        }

        return implode('/', $parts);
    }

    /**
     * @param int $offset
     * @param int|null $length
     * @return string
     */
    public function getRoute($offset = 0, $length = null)
    {
        if ($offset !== 0 || $length !== null) {
            return ($offset === 0 ? '/' : '') . implode('/', $this->getRouteParts($offset, $length));
        }

        return '/' . $this->route;
    }

    /**
     * @return string
     */
    public function getExtension()
    {
        return $this->extension;
    }

    /**
     * @param int $offset
     * @param int|null $length
     * @return array
     */
    public function getRouteParts($offset = 0, $length = null)
    {
        $parts = explode('/', $this->route);

        if ($offset !== 0 || $length !== null) {
            $parts = array_slice($parts, $offset, $length);
        }

        return $parts;
    }

    /**
     * Return array of both query and Grav parameters.
     *
     * If a parameter exists in both, prefer Grav parameter.
     *
     * @return array
     */
    public function getParams()
    {
        return $this->gravParams + $this->queryParams;
    }

    /**
     * @return array
     */
    public function getGravParams()
    {
        return $this->gravParams;
    }

    /**
     * @return array
     */
    public function getQueryParams()
    {
        return $this->queryParams;
    }

    /**
     * Return value of the parameter, looking into both Grav parameters and query parameters.
     *
     * If the parameter exists in both, return Grav parameter.
     *
     * @param string $param
     * @return string|array|null
     */
    public function getParam($param)
    {
        return $this->getGravParam($param) ?? $this->getQueryParam($param);
    }

    /**
     * @param string $param
     * @return string|null
     */
    public function getGravParam($param)
    {
        return $this->gravParams[$param] ?? null;
    }

    /**
     * @param string $param
     * @return string|array|null
     */
    public function getQueryParam($param)
    {
        return $this->queryParams[$param] ?? null;
    }

    /**
     * Allow the ability to set the route to something else
     *
     * @param string $route
     * @return Route
     */
    public function withRoute($route)
    {
        $new = $this->copy();
        $new->route = $route;

        return $new;
    }

    /**
     * Allow the ability to set the root to something else
     *
     * @param string $root
     * @return Route
     */
    public function withRoot($root)
    {
        $new = $this->copy();
        $new->root = $root;

        return $new;
    }

    /**
     * @param string|null $language
     * @return Route
     */
    public function withLanguage($language)
    {
        $new = $this->copy();
        $new->language = $language ?? '';

        return $new;
    }

    /**
     * @param string $path
     * @return Route
     */
    public function withAddedPath($path)
    {
        $new = $this->copy();
        $new->route .= '/' . ltrim($path, '/');

        return $new;
    }

    /**
     * @param string $extension
     * @return Route
     */
    public function withExtension($extension)
    {
        $new = $this->copy();
        $new->extension = $extension;

        return $new;
    }

    /**
     * @param string $param
     * @param mixed $value
     * @return Route
     */
    public function withGravParam($param, $value)
    {
        return $this->withParam('gravParams', $param, null !== $value ? (string)$value : null);
    }

    /**
     * @param string $param
     * @param mixed $value
     * @return Route
     */
    public function withQueryParam($param, $value)
    {
        return $this->withParam('queryParams', $param, $value);
    }

    /**
     * @return Route
     */
    public function withoutParams()
    {
        return $this->withoutGravParams()->withoutQueryParams();
    }

    /**
     * @return Route
     */
    public function withoutGravParams()
    {
        $new = $this->copy();
        $new->gravParams = [];

        return $new;
    }

    /**
     * @return Route
     */
    public function withoutQueryParams()
    {
        $new = $this->copy();
        $new->queryParams = [];

        return $new;
    }

    /**
     * @return \Grav\Framework\Uri\Uri
     */
    public function getUri()
    {
        return UriFactory::createFromParts($this->getParts());
    }

    /**
     * @param bool $includeRoot
     * @return string
     */
    public function toString(bool $includeRoot = false)
    {
        $url = $this->getUriPath($includeRoot);

        if ($this->queryParams) {
            $url .= '?' . $this->getUriQuery();
        }

        return rtrim($url,'/');
    }

    /**
     * @return string
     * @deprecated 1.6 Use ->toString(true) or ->getUri() instead.
     */
    public function __toString()
    {
        user_error(__CLASS__ . '::' . __FUNCTION__ . '() will change in the future to return route, not relative url: use ->toString(true) or ->getUri() instead.', E_USER_DEPRECATED);

        return $this->toString(true);
    }

    /**
     * @param string $type
     * @param string $param
     * @param mixed $value
     * @return Route
     */
    protected function withParam($type, $param, $value)
    {
        $values = $this->{$type} ?? [];
        $oldValue = $values[$param] ?? null;

        if ($oldValue === $value) {
            return $this;
        }

        $new = $this->copy();
        if ($value === null) {
            unset($values[$param]);
        } else {
            $values[$param] = $value;
        }

        $new->{$type} = $values;

        return $new;
    }

    /**
     * @return Route
     */
    protected function copy()
    {
        return clone $this;
    }

    /**
     * @param bool $includeRoot
     * @return string
     */
    protected function getUriPath($includeRoot = false)
    {
        $parts = $includeRoot ? [$this->root] : [''];

        if ($this->language !== '') {
            $parts[] = $this->language;
        }

        $parts[] = $this->extension ? $this->route . '.' . $this->extension : $this->route;


        if ($this->gravParams) {
            $parts[] = RouteFactory::buildParams($this->gravParams);
        }

        return implode('/', $parts);
    }

    /**
     * @return string
     */
    protected function getUriQuery()
    {
        return UriFactory::buildQuery($this->queryParams);
    }

    /**
     * @param array $parts
     * @return void
     */
    protected function initParts(array $parts)
    {
        if (isset($parts['grav'])) {
            $gravParts = $parts['grav'];
            $this->root = $gravParts['root'];
            $this->language = $gravParts['language'];
            $this->route = $gravParts['route'];
            $this->extension = $gravParts['extension'] ?? '';
            $this->gravParams = $gravParts['params'] ?? [];
            $this->queryParams = $parts['query_params'] ?? [];
        } else {
            $this->root = RouteFactory::getRoot();
            $this->language = RouteFactory::getLanguage();

            $path = $parts['path'] ?? '/';
            if (isset($parts['params'])) {
                $this->route = trim(rawurldecode($path), '/');
                $this->gravParams = $parts['params'];
            } else {
                $this->route = trim(RouteFactory::stripParams($path, true), '/');
                $this->gravParams = RouteFactory::getParams($path);
            }
            if (isset($parts['query'])) {
                $this->queryParams = UriFactory::parseQuery($parts['query']);
            }
        }
    }
}
