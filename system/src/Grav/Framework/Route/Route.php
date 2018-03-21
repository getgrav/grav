<?php
/**
 * @package    Grav\Framework\Route
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Framework\Route;

use Grav\Framework\Uri\UriFactory;

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

    /** @var array */
    private $gravParams = [];

    /** @var array */
    private $queryParams = [];

    /**
     * You can use `RouteFactory` functions to create new `Route` objects.
     *
     * @param array $parts
     * @throws \InvalidArgumentException
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
            'path' => $this->getUriPath(),
            'query' => $this->getUriQuery(),
            'grav' => [
                'root' => $this->root,
                'language' => $this->language,
                'route' => $this->route,
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
    public function getLanguagePrefix()
    {
        return $this->language !== '' ? '/' . $this->language : '';
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
     * @return string|null
     */
    public function getParam($param)
    {
        $value = $this->getGravParam($param);
        if ($value === null) {
            $value = $this->getQueryParam($param);
        }

        return $value;
    }

    /**
     * @param string $param
     * @return string|null
     */
    public function getGravParam($param)
    {
        return isset($this->gravParams[$param]) ? $this->gravParams[$param] : null;
    }

    /**
     * @param string $param
     * @return string|null
     */
    public function getQueryParam($param)
    {
        return isset($this->queryParams[$param]) ? $this->queryParams[$param] : null;
    }

    /**
     * @param string $param
     * @param mixed $value
     * @return Route
     */
    public function withGravParam($param, $value)
    {
        return $this->withParam('gravParams', $param, $value);
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
     * @return \Grav\Framework\Uri\Uri
     */
    public function getUri()
    {
        return UriFactory::createFromParts($this->getParts());
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $url = $this->getUriPath();

        if ($this->queryParams) {
            $url .= '?' . $this->getUriQuery();
        }

        return $url;
    }

    /**
     * @param string $type
     * @param string $param
     * @param mixed $value
     * @return static
     */
    protected function withParam($type, $param, $value)
    {
        $oldValue = isset($this->{$type}[$param]) ? $this->{$type}[$param] : null;
        $newValue = null !== $value ? (string)$value : null;

        if ($oldValue === $newValue) {
            return $this;
        }

        $new = clone $this;
        if ($newValue === null) {
            unset($new->{$type}[$param]);
        } else {
            $new->{$type}[$param] = $newValue;
        }

        return $new;
    }

    /**
     * @return string
     */
    protected function getUriPath()
    {
        $parts = [$this->root];

        if ($this->language !== '') {
            $parts[] = $this->language;
        }

        if ($this->route !== '') {
            $parts[] = $this->route;
        }

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
     */
    protected function initParts(array $parts)
    {
        if (isset($parts['grav'])) {
            $gravParts = $parts['grav'];
            $this->root = $gravParts['root'];
            $this->language = $gravParts['language'];
            $this->route = $gravParts['route'];
            $this->gravParams = $gravParts['params'];
            $this->queryParams = $parts['query_params'];

        } else {
            $this->root = RouteFactory::getRoot();
            $this->language = RouteFactory::getLanguage();

            $path = isset($parts['path']) ? $parts['path'] : '/';
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
